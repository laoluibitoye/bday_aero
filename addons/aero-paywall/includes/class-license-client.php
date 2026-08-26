<?php
/**
 * Client for licensing-platform (System C): activates/validates this
 * site's license key against its domain, verifying the returned token via
 * JWKS (Bday_Aero_Jwks_Client) rather than trusting it blindly. Fails
 * open (gating off) only after a run of consecutive failures, so a single
 * transient network blip never takes reader-facing gating down.
 *
 * AERO_PAYWALL_DEV_MODE (a wp-config.php constant, not a DB-editable
 * option) bypasses licensing entirely for local/CI stacks — same
 * convention as the retired connector-plugin, so it's visible in
 * wp-config, not something a database compromise alone could flip on.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_License_Client {

	private const STATE_OPTION      = 'aero_paywall_license_state';
	private const FAILURES_OPTION   = 'aero_paywall_license_failures';
	private const ACTIVATED_OPTION  = 'aero_paywall_license_activated';
	private const REASON_OPTION     = 'aero_paywall_license_inactive_reason';
	private const CACHE_TTL         = 12 * HOUR_IN_SECONDS;
	private const FAILURE_GRACE     = 5;

	public function __construct() {
		add_action( 'admin_init', array( self::class, 'maybe_activate' ) );
	}

	public static function is_dev_mode_bypass_active(): bool {
		return defined( 'AERO_PAYWALL_DEV_MODE' ) && true === AERO_PAYWALL_DEV_MODE;
	}

	public static function maybe_activate(): void {
		if ( self::is_dev_mode_bypass_active() ) {
			return;
		}
		if ( get_option( self::ACTIVATED_OPTION ) ) {
			return;
		}
		$license_key = Bday_Aero_Settings::license_key();
		$base_url    = Bday_Aero_Settings::licensing_api_base_url();
		if ( '' === $license_key || '' === $base_url ) {
			return;
		}

		$response = wp_remote_post(
			$base_url . '/licenses/activate',
			array(
				'timeout' => 5,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'licenseKey'    => $license_key,
						'domain'        => wp_parse_url( home_url(), PHP_URL_HOST ),
						'clientVersion' => '1.0',
					)
				),
			)
		);

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			update_option( self::ACTIVATED_OPTION, true );
		}
	}

	public static function is_active(): bool {
		if ( self::is_dev_mode_bypass_active() ) {
			return true;
		}

		$state = get_option( self::STATE_OPTION, array() );
		if ( is_array( $state ) && ! empty( $state['token'] ) && ! empty( $state['checkedAt'] )
			&& ( time() - (int) $state['checkedAt'] ) < self::CACHE_TTL
		) {
			return true; // already verified within the cache window
		}

		$verified = self::validate();
		if ( $verified ) {
			update_option( self::STATE_OPTION, array( 'token' => true, 'checkedAt' => time() ) );
			update_option( self::FAILURES_OPTION, 0 );
			return true;
		}

		$failures = (int) get_option( self::FAILURES_OPTION, 0 ) + 1;
		update_option( self::FAILURES_OPTION, $failures );

		// Grace period: a handful of consecutive failures (network blips,
		// a licensing-platform deploy) doesn't immediately take gating
		// down for readers — only a sustained outage does.
		return $failures <= self::FAILURE_GRACE && is_array( $state ) && ! empty( $state['token'] );
	}

	private static function validate(): bool {
		$license_key = Bday_Aero_Settings::license_key();
		$base_url    = Bday_Aero_Settings::licensing_api_base_url();
		if ( '' === $license_key || '' === $base_url ) {
			update_option( self::REASON_OPTION, 'not_configured' );
			return false;
		}

		$response = wp_remote_post(
			$base_url . '/licenses/validate',
			array(
				'timeout' => 5,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'licenseKey' => $license_key,
						'domain'     => wp_parse_url( home_url(), PHP_URL_HOST ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			update_option( self::REASON_OPTION, 'api_unreachable' );
			return false;
		}

		$body  = json_decode( wp_remote_retrieve_body( $response ), true );
		$token = $body['data']['token'] ?? null;
		if ( ! is_string( $token ) || '' === $token ) {
			update_option( self::REASON_OPTION, 'api_unreachable' );
			return false;
		}

		$claims = Bday_Aero_Jwks_Client::verify( $token, $base_url, 'license_jwks' );
		if ( null === $claims ) {
			update_option( self::REASON_OPTION, 'invalid_signature' );
			return false;
		}

		$domain = $claims['domain'] ?? null;
		$valid  = $claims['valid'] ?? false;

		if ( $domain !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
			update_option( self::REASON_OPTION, 'domain_mismatch' );
			return false;
		}
		if ( ! $valid ) {
			update_option( self::REASON_OPTION, 'expired' );
			return false;
		}

		delete_option( self::REASON_OPTION );
		return true;
	}

	/**
	 * Purely informational, for the wp-admin visibility notice
	 * (class-admin-ui.php's maybe_render_license_notice) — never consulted
	 * by is_active() itself, so it cannot change the fail-open behavior
	 * documented at the top of this file. Returns null when gating is
	 * actually active (nothing to report); otherwise the specific reason
	 * recorded the last time validate() ran, or 'no_key' when no license
	 * key is configured at all.
	 */
	public static function inactive_reason(): ?string {
		if ( self::is_active() ) {
			return null;
		}
		if ( '' === Bday_Aero_Settings::license_key() ) {
			return 'no_key';
		}
		$reason = get_option( self::REASON_OPTION, '' );
		return '' !== $reason ? $reason : 'unknown';
	}
}
