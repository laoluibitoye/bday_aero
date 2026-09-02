<?php
/**
 * Reads the backend-authoritative paywall config (free-article threshold,
 * reset cycle, funnel thresholds, captcha site key) from subscription-
 * service's unauthenticated `GET /public/paywall-config`. Cache-wrapped via
 * Bday_Query_Cache — same primitive as every DB query in this theme, now
 * also the one caching path for this add-on's HTTP reads. Fails open with
 * defaults mirroring subscription-service's own fallback, since this runs
 * on every page load (class-sdk-loader.php's build_context()) and a
 * slow/unreachable backend must never block a page render.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Paywall_Config_Client {

	// Editor-reported: a scope-mode/funnel-threshold change made in
	// admin-web took up to 5 minutes to show up here — subscription-
	// service has no way to push an invalidation to WP when a setting
	// changes, so freshness is purely this TTL. This is a lightweight
	// unauthenticated GET specifically meant to be cache-friendly, so
	// shortening it to 1 minute costs little (still absorbs every
	// concurrent pageview within that window into one shared cache entry)
	// while cutting the lag 5x.
	private const CACHE_TTL = MINUTE_IN_SECONDS;

	/** @return array<string, mixed> */
	private static function defaults(): array {
		return array(
			'meter_scope_mode'          => 'hybrid',
			'meter_limit'                => 3,
			'meter_cycle_days'          => 30,
			'funnel_thresholds'         => array(
				'stage2' => 2,
				'stage3' => 3,
				'stage4' => 4,
			),
			'meter_ip_fallback_enabled' => false,
			'captcha'                   => null,
		);
	}

	/** @return array<string, mixed> */
	public static function get(): array {
		return Bday_Query_Cache::remember(
			'aero_paywall',
			'paywall_config',
			array( self::class, 'fetch' ),
			self::CACHE_TTL
		);
	}

	/** @return array<string, mixed> */
	public static function fetch(): array {
		$defaults = self::defaults();

		$base_url = Bday_Aero_Settings::api_base_url();
		if ( '' === $base_url ) {
			return $defaults;
		}

		$response = wp_remote_get( $base_url . '/public/paywall-config', array( 'timeout' => 3 ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $defaults;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$data = $body['data'] ?? null;
		if ( ! is_array( $data ) ) {
			return $defaults;
		}

		return array_merge( $defaults, $data );
	}
}
