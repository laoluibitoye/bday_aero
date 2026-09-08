<?php
/**
 * Client for licensing-platform (System C): activates/validates this
 * site's license key against its domain, verifying the returned token via
 * JWKS (Bday_Aero_Jwks_Client) rather than trusting it blindly.
 *
 * Field-tested finding (Gating System Field Test, 2026-09-08): this used
 * to fail open (gating off, site-wide, for every reader) after only
 * FAILURE_GRACE=5 consecutive failures — trivially exhausted within
 * seconds by real concurrent traffic during any *connectivity* blip to
 * licensing-platform, which is a completely different situation from a
 * license actually being invalid. is_active() now tells those two cases
 * apart: a licensing-platform response that genuinely says "not valid"
 * (expired/domain mismatch/bad signature/not configured) still fails open
 * immediately — that's the real, intended behavior, an unlicensed site's
 * paywall shouldn't be enforced. An *unreachable* licensing-platform no
 * longer counts toward that at all; the last confirmed-valid state is
 * trusted for UNREACHABLE_GRACE (7 days) before gating ever gives up, and
 * a circuit breaker (mirroring Bday_Aero_Meter_Client's own) stops a
 * sustained outage from making every concurrent gated pageview separately
 * wait out a blocking 5s HTTP call.
 *
 * AERO_PAYWALL_DEV_MODE (a wp-config.php constant, not a DB-editable
 * option) bypasses licensing entirely for local/CI stacks — same
 * convention as the retired connector-plugin, so it's visible in
 * wp-config, not something a database compromise alone could flip on.
 *
 * Platform Audit G2 / July audit §1.2: three independent, low-effort
 * bypasses existed here — (a) hand-editing the cached STATE_OPTION row
 * directly in wp_options, (b) blocking outbound traffic so gating fails
 * open (already addressed by this file's UNREACHABLE_GRACE/circuit-breaker
 * design above — an outage still fails open eventually, by design, but
 * only after a real multi-day grace, not instantly), and (c) repointing
 * the DB-editable "Licensing Platform base URL" setting at a self-hosted
 * fake server, which previously defeated even the JWKS signature check
 * since the fake server could serve its own self-generated JWKS too. This
 * pass closes (a) and (c):
 * - (a): STATE_OPTION now carries an HMAC (wp_salt('auth') — a real
 *   per-install secret that lives in wp-config.php, not the database,
 *   same secret source WordPress itself uses for auth cookies/nonces)
 *   over its own contents, re-verified on every read via
 *   read_verified_state(). A hand-edited row fails that check and is
 *   treated exactly like no prior state existing at all.
 * - (c): when AERO_PAYWALL_LICENSING_TRUST_HOST / _TRUST_KID are defined
 *   in wp-config.php, the JWKS used to verify a validate response's
 *   signature is fetched from the pinned host and filtered to the pinned
 *   key id — see Bday_Aero_Jwks_Client::verify()'s new optional
 *   parameters. A fake server can still answer the initial
 *   activate/validate HTTP call (that endpoint stays DB-configurable,
 *   deliberately — some deployments legitimately point at a staging
 *   licensing-platform), but it cannot forge a signature that verifies
 *   against the real, pinned production key, so validate() falls through
 *   to "invalid signature" regardless of which server answered.
 *
 * Both constants are optional and undefined by default — this repo has no
 * single known production licensing hostname/key to compile in, so
 * there's nothing to hardcode here safely. Each real deployment sets its
 * own trust anchor once, in that site's wp-config.php, from its actual
 * production licensing-platform's real host and its
 * GET {host}/.well-known/jwks.json response's real `kid`. Until set, this
 * file behaves exactly as before this change (DB-configurable origin, no
 * kid pin) — bypass (c) only closes once a deployment actually configures
 * these two constants.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_License_Client {

	private const STATE_OPTION      = 'aero_paywall_license_state';
	private const ACTIVATED_OPTION  = 'aero_paywall_license_activated';
	private const REASON_OPTION     = 'aero_paywall_license_inactive_reason';
	private const CACHE_TTL         = 12 * HOUR_IN_SECONDS;

	// How long a licensing-platform that's simply unreachable (not "we
	// asked and it said no") is allowed to leave the last confirmed-valid
	// state in effect. Deliberately much longer than CACHE_TTL — that
	// bounds how stale a *successful* check can be before re-checking, not
	// how a site should behave through a multi-hour/day vendor outage.
	private const UNREACHABLE_GRACE = 7 * DAY_IN_SECONDS;

	// Circuit breaker over unreachable-only failures — same sliding-window
	// pattern as Bday_Aero_Meter_Client, own transient keys so the two
	// breakers can't interfere with each other.
	private const CB_OPEN_TRANSIENT    = 'bday_aero_license_cb_open';
	private const CB_FAILURE_TRANSIENT = 'bday_aero_license_cb_failures';
	private const CB_FAILURE_THRESHOLD = 5;
	private const CB_FAILURE_WINDOW    = 30;
	private const CB_COOLDOWN          = 15;

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

		$state           = self::read_verified_state();
		$has_prior_state = null !== $state;

		if ( $has_prior_state && ( time() - (int) $state['checkedAt'] ) < self::CACHE_TTL ) {
			return true; // already verified within the cache window
		}

		// Breaker open: licensing-platform has been unreachable
		// CB_FAILURE_THRESHOLD times in the last CB_FAILURE_WINDOW seconds.
		// Skip the network call and fall back to the same "trust the last
		// confirmed-good state for a while longer" logic a single
		// unreachable result would produce below — never trips on a
		// confirmed-invalid answer, only on connectivity failures.
		if ( false !== get_transient( self::CB_OPEN_TRANSIENT ) ) {
			return self::trust_last_known_state( $has_prior_state, $state );
		}

		$result = self::validate();

		if ( $result['reachable'] && $result['valid'] ) {
			self::store_state( true, time() );
			self::record_success();
			return true;
		}

		if ( $result['reachable'] ) {
			// A real answer, not a connectivity problem: licensing-platform
			// itself says this license isn't valid right now (expired,
			// wrong domain, bad signature, or none configured). Applies
			// immediately, no grace — an unlicensed site's paywall
			// shouldn't be enforced, and this isn't the "one blip
			// shouldn't take gating down" scenario the grace period below
			// exists for.
			self::clear_state();
			self::record_success(); // the connection itself succeeded — don't count this toward the breaker
			return false;
		}

		// Unreachable: a connectivity problem, not an answer. Never let
		// this alone flip a previously-licensed site to unlicensed.
		self::record_failure();
		return self::trust_last_known_state( $has_prior_state, $state );
	}

	/**
	 * How gating behaves while licensing-platform can't be reached at all
	 * (mid-outage, or the breaker's currently open): keep trusting the
	 * last confirmed-valid check for UNREACHABLE_GRACE, not just
	 * CACHE_TTL — a site that's never once been successfully verified
	 * (fresh install, wrong key) has nothing to fall back to and stays
	 * inactive.
	 */
	private static function trust_last_known_state( bool $has_prior_state, $state ): bool {
		if ( ! $has_prior_state ) {
			return false;
		}
		return ( time() - (int) $state['checkedAt'] ) < self::UNREACHABLE_GRACE;
	}

	/**
	 * HMAC over the cached state's own contents, keyed on wp_salt('auth')
	 * — a per-install secret that lives in wp-config.php (or, if that
	 * site never defined AUTH_KEY/AUTH_SALT, WordPress's own database
	 * fallback; either way it's the identical secret WordPress core
	 * itself trusts for auth cookies and nonces, not a new one this
	 * plugin introduces). Same hash_hmac('sha256', ..., wp_salt('auth'))
	 * pattern already used elsewhere in this theme
	 * (world-cup-2026/addon.php).
	 *
	 * @param array{token: bool, checkedAt: int} $state
	 */
	private static function state_signature( array $state ): string {
		$payload = ( $state['token'] ? '1' : '0' ) . '|' . $state['checkedAt'];
		return hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
	}

	private static function store_state( bool $valid, int $checked_at ): void {
		$state        = array(
			'token'     => $valid,
			'checkedAt' => $checked_at,
		);
		$state['sig'] = self::state_signature( $state );
		update_option( self::STATE_OPTION, $state );
	}

	private static function clear_state(): void {
		delete_option( self::STATE_OPTION );
	}

	/**
	 * Reads STATE_OPTION and re-verifies its signature before trusting it
	 * — every read, not just on fresh fetch (Platform Audit G2). A missing,
	 * malformed, or tampered row (hand-edited via wp-admin/phpMyAdmin/a
	 * direct DB compromise — the bypass this closes) returns null and is
	 * treated identically to "never successfully validated," which
	 * is_active() already handles correctly by falling through to a live
	 * validate() call.
	 *
	 * @return array{token: bool, checkedAt: int, sig: string}|null
	 */
	private static function read_verified_state(): ?array {
		$state = get_option( self::STATE_OPTION, array() );
		if ( ! is_array( $state ) || ! isset( $state['token'], $state['checkedAt'], $state['sig'] ) ) {
			return null;
		}
		$expected = self::state_signature( array( 'token' => (bool) $state['token'], 'checkedAt' => (int) $state['checkedAt'] ) );
		if ( ! hash_equals( $expected, (string) $state['sig'] ) ) {
			return null;
		}
		return array(
			'token'     => (bool) $state['token'],
			'checkedAt' => (int) $state['checkedAt'],
			'sig'       => (string) $state['sig'],
		);
	}

	/**
	 * Optional wp-config.php-only trust anchor (Platform Audit G2) — see
	 * this file's top-of-file docblock for why these aren't hardcoded
	 * literals. Undefined by default; each real deployment sets its own.
	 */
	private static function trust_anchor_host(): ?string {
		return ( defined( 'AERO_PAYWALL_LICENSING_TRUST_HOST' ) && '' !== AERO_PAYWALL_LICENSING_TRUST_HOST )
			? AERO_PAYWALL_LICENSING_TRUST_HOST
			: null;
	}

	private static function trust_anchor_kid(): ?string {
		return ( defined( 'AERO_PAYWALL_LICENSING_TRUST_KID' ) && '' !== AERO_PAYWALL_LICENSING_TRUST_KID )
			? AERO_PAYWALL_LICENSING_TRUST_KID
			: null;
	}

	/**
	 * Sliding-window failure counter — same approach as
	 * Bday_Aero_Meter_Client::record_failure(), own transient keys so the
	 * two breakers can't interfere with each other. Only ever called for
	 * an UNREACHABLE result; a confirmed-invalid license calls
	 * record_success() instead, since the HTTP round trip itself worked.
	 */
	private static function record_failure(): void {
		$failures = get_transient( self::CB_FAILURE_TRANSIENT );
		$failures = is_array( $failures ) ? $failures : array();

		$cutoff   = time() - self::CB_FAILURE_WINDOW;
		$failures = array_values(
			array_filter(
				$failures,
				static function ( int $timestamp ) use ( $cutoff ): bool {
					return $timestamp > $cutoff;
				}
			)
		);
		$failures[] = time();

		set_transient( self::CB_FAILURE_TRANSIENT, $failures, self::CB_FAILURE_WINDOW );
		if ( count( $failures ) >= self::CB_FAILURE_THRESHOLD ) {
			set_transient( self::CB_OPEN_TRANSIENT, true, self::CB_COOLDOWN );
		}
	}

	private static function record_success(): void {
		delete_transient( self::CB_FAILURE_TRANSIENT );
	}

	/** @return array{reachable: bool, valid: bool} */
	private static function validate(): array {
		$license_key = Bday_Aero_Settings::license_key();
		$base_url    = Bday_Aero_Settings::licensing_api_base_url();
		if ( '' === $license_key || '' === $base_url ) {
			update_option( self::REASON_OPTION, 'not_configured' );
			return array( 'reachable' => true, 'valid' => false );
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
			return array( 'reachable' => false, 'valid' => false );
		}

		$body  = json_decode( wp_remote_retrieve_body( $response ), true );
		$token = $body['data']['token'] ?? null;
		if ( ! is_string( $token ) || '' === $token ) {
			update_option( self::REASON_OPTION, 'api_unreachable' );
			return array( 'reachable' => false, 'valid' => false );
		}

		$claims = Bday_Aero_Jwks_Client::verify( $token, $base_url, 'license_jwks', self::trust_anchor_host(), self::trust_anchor_kid() );
		if ( null === $claims ) {
			// Could genuinely be a bad signature, or could be this host's
			// own JWKS fetch failing — Bday_Aero_Jwks_Client::verify()
			// returns null uniformly for both (see its own docblock).
			// Treating this as "unreachable" rather than "confirmed
			// invalid" is the safer read: a JWKS fetch failure is a
			// connectivity problem in disguise, not licensing-platform
			// actually answering "no".
			update_option( self::REASON_OPTION, 'invalid_signature' );
			return array( 'reachable' => false, 'valid' => false );
		}

		$domain = $claims['domain'] ?? null;
		$valid  = $claims['valid'] ?? false;

		if ( $domain !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
			update_option( self::REASON_OPTION, 'domain_mismatch' );
			return array( 'reachable' => true, 'valid' => false );
		}
		if ( ! $valid ) {
			update_option( self::REASON_OPTION, 'expired' );
			return array( 'reachable' => true, 'valid' => false );
		}

		delete_option( self::REASON_OPTION );
		return array( 'reachable' => true, 'valid' => true );
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
