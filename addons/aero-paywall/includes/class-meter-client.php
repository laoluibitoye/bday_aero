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

	/**
	 * Fire-and-forget variant of check() — same endpoint, same effect on
	 * subscription-service's meter (it's the single call that both counts
	 * *and* answers; this just discards the answer), used specifically for
	 * counting a free/non-premium view under Hybrid scope mode, where
	 * nothing in the response is needed and nothing about the page's
	 * rendering depends on it.
	 *
	 * Deliberately non-blocking (`blocking => false`): this fires on every
	 * free-article view under Hybrid mode — the common case, not the rare
	 * one — and this theme has a documented incident (Market Pulse's live
	 * feed, this session) where a synchronous outbound HTTP call sitting
	 * in a real visitor's request path caused intermittent 502/504s under
	 * load. `blocking => false` returns as soon as the request is written
	 * to the socket, without waiting for or parsing a response — exactly
	 * right here, since the caller was never going to use the response
	 * anyway.
	 */
	public static function record_async( string $device_id, int $post_id ): void {
		$base_url = Bday_Aero_Settings::api_base_url();
		if ( '' === $base_url ) {
			return;
		}

		wp_remote_post(
			$base_url . '/meter/check',
			array(
				'body'     => wp_json_encode(
					array(
						'deviceId' => $device_id,
						'postId'   => (string) $post_id,
					)
				),
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'timeout'  => 0.01,
				'blocking' => false,
			)
		);
	}
}
