<?php
/**
 * Server-to-server client for subscription-service's `POST /meter/check`.
 * Personalized (per device+post), so it is never cached — short timeout,
 * fails closed (null = "couldn't reach the service") at the call site, not
 * here.
 *
 * RESOURCE_SAFETY_AUDIT.md 1.1: this is the one call on the reader path
 * that fires on every gated-article view, blocks page rendering, and had
 * no cache to fall back to — the exact combination that lets a slow/down
 * subscription-service pile up PHP-FPM workers on the news site itself
 * until it can't serve *any* page, gated or not. Two changes address that
 * without touching the "always fresh, per device+post" behavior the
 * personalization requires: a shorter timeout (below), and a circuit
 * breaker (record_failure/record_success below) that fails fast once
 * subscription-service is clearly down, instead of every concurrent
 * request separately waiting out the full timeout.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Meter_Client {

	// Plain transients (not wp_cache_*): this failure-count/open state must
	// survive across requests even when no persistent object cache is
	// configured — exactly the scenario where a fast-fail fallback matters
	// most (RESOURCE_SAFETY_AUDIT.md 2.1: the object-cache assumption isn't
	// enforced anywhere, so this can't depend on it being true).
	private const CB_FAILURE_TRANSIENT = 'bday_aero_meter_cb_failures';
	private const CB_OPEN_TRANSIENT    = 'bday_aero_meter_cb_open';
	private const CB_FAILURE_THRESHOLD = 5;
	private const CB_FAILURE_WINDOW    = 30; // seconds a failure counts toward the threshold before expiring on its own
	private const CB_COOLDOWN          = 15; // seconds the breaker stays open once tripped, before the next request is allowed to probe again

	/** @return array{stage: string, remaining: int|null}|null */
	public static function check( string $device_id, int $post_id ): ?array {
		$base_url = Bday_Aero_Settings::api_base_url();
		if ( '' === $base_url ) {
			return null;
		}

		// Breaker open: subscription-service has already failed
		// CB_FAILURE_THRESHOLD times in the last CB_FAILURE_WINDOW seconds.
		// Fail closed immediately rather than joining every other
		// concurrent request in waiting out the timeout below — same
		// outcome the caller would get anyway, just without tying up a
		// PHP-FPM worker to get there.
		if ( false !== get_transient( self::CB_OPEN_TRANSIENT ) ) {
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
				// Was 5s — too long for a synchronous call the page can't
				// render without an answer to; shortened per
				// RESOURCE_SAFETY_AUDIT.md 1.1.
				'timeout' => 3,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			self::record_failure();
			return null;
		}

		self::record_success();

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

	private static function record_failure(): void {
		$count = (int) get_transient( self::CB_FAILURE_TRANSIENT );
		++$count;
		set_transient( self::CB_FAILURE_TRANSIENT, $count, self::CB_FAILURE_WINDOW );
		if ( $count >= self::CB_FAILURE_THRESHOLD ) {
			set_transient( self::CB_OPEN_TRANSIENT, true, self::CB_COOLDOWN );
		}
	}

	// A success resets the run — the breaker should only trip on a
	// genuine consecutive-failure streak, not accumulate stray failures
	// separated by healthy requests until they happen to cross the
	// threshold.
	private static function record_success(): void {
		delete_transient( self::CB_FAILURE_TRANSIENT );
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
