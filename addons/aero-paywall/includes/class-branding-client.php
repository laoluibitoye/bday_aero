<?php
/**
 * Reads logo/accent-color branding from subscription-service's `GET
 * /public/branding` — admin-web is the single source of truth for this,
 * the theme's own accent-color option is a fallback only. Cache-wrapped
 * via Bday_Query_Cache, fails open (cosmetic data is never worth risking
 * a broken page render over).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Branding_Client {

	private const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

	/** @return array{logoUrl: string, accentColor: string} */
	public static function get(): array {
		return Bday_Query_Cache::remember(
			'aero_paywall',
			'branding',
			array( self::class, 'fetch' ),
			self::CACHE_TTL
		);
	}

	/** @return array{logoUrl: string, accentColor: string} */
	public static function fetch(): array {
		$fallback = array(
			'logoUrl'     => '',
			'accentColor' => Bday_Aero_Settings::accent_color(),
		);

		$base_url = Bday_Aero_Settings::api_base_url();
		if ( '' === $base_url ) {
			return $fallback;
		}

		$response = wp_remote_get( $base_url . '/public/branding', array( 'timeout' => 3 ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $fallback;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$data = $body['data'] ?? null;
		if ( ! is_array( $data ) || ! isset( $data['accentColor'] ) ) {
			return $fallback;
		}

		return array(
			'logoUrl'     => (string) ( $data['logoUrl'] ?? '' ),
			'accentColor' => (string) $data['accentColor'],
		);
	}
}
