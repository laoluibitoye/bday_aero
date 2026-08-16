<?php
/**
 * Reads GET /connector/dashboard-stats — reader counts, active
 * subscriber count, and the most-followed categories/tags — for the
 * AeroPaywall wp-admin "Readers" tab (reader-requested: "personalizations,
 * tag, categories and how many people are following it... on the
 * dashboard"). Same connector/X-Api-Key auth and {success,data} envelope
 * class-premium-map.php's sync calls already use, wp_remote_get side
 * rather than wp_remote_post; cached briefly via Bday_Query_Cache same as
 * class-branding-client.php, fails open to zeros rather than a broken
 * admin screen if the backend is briefly unreachable.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Dashboard_Stats_Client {

	private const CACHE_TTL = MINUTE_IN_SECONDS;

	/** @return array{totalReaders: int, activeSubscribers: int, totalFollows: int, topFollowedTerms: array<int, array{taxonomy: string, termId: string, termLabel: string, followerCount: int}>} */
	public static function get(): array {
		return Bday_Query_Cache::remember(
			'aero_paywall',
			'dashboard_stats',
			array( self::class, 'fetch' ),
			self::CACHE_TTL
		);
	}

	/** @return array{totalReaders: int, activeSubscribers: int, totalFollows: int, topFollowedTerms: array<int, array{taxonomy: string, termId: string, termLabel: string, followerCount: int}>} */
	public static function fetch(): array {
		$fallback = array(
			'totalReaders'      => 0,
			'activeSubscribers' => 0,
			'totalFollows'      => 0,
			'topFollowedTerms'  => array(),
		);

		$base_url = Bday_Aero_Settings::api_base_url();
		$api_key  = Bday_Aero_Settings::api_key();
		if ( '' === $base_url || '' === $api_key ) {
			return $fallback;
		}

		$response = wp_remote_get(
			$base_url . '/connector/dashboard-stats',
			array(
				'timeout' => 5,
				'headers' => array( 'X-Api-Key' => $api_key ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $fallback;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$data = $body['data'] ?? null;
		if ( ! is_array( $data ) ) {
			return $fallback;
		}

		return array(
			'totalReaders'      => (int) ( $data['totalReaders'] ?? 0 ),
			'activeSubscribers' => (int) ( $data['activeSubscribers'] ?? 0 ),
			'totalFollows'      => (int) ( $data['totalFollows'] ?? 0 ),
			'topFollowedTerms'  => is_array( $data['topFollowedTerms'] ?? null ) ? $data['topFollowedTerms'] : array(),
		);
	}
}
