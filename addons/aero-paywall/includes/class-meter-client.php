<?php
/**
 * Server-to-server client for subscription-service's `POST /meter/check`.
 * Personalized (per device+post), so it is never cached — short 5s
 * timeout, fails closed (null = "couldn't reach the service") at the call
 * site, not here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Meter_Client {

	/** @return array{stage: string, remaining: int|null}|null */
	public static function check( string $device_id, int $post_id ): ?array {
		$base_url = Bday_Aero_Settings::api_base_url();
		if ( '' === $base_url ) {
			return null;
		}

		$response = wp_remote_post(
			$base_url . '/meter/check',
			array(
				'body'    => wp_json_encode(
					array(
						'deviceId' => $device_id,
						'postId'   => (string) $post_id,
					)
				),
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => 5,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$data = $body['data'] ?? null;
		if ( ! is_array( $data ) || ! isset( $data['stage'] ) ) {
			return null;
		}

		return array(
			'stage'     => (string) $data['stage'],
			'remaining' => $data['remaining'] ?? null,
		);
	}
}
