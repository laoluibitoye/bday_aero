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

	/**
	 * @param bool $exempt Registration-exempt content (Bday_Aero_Registration_Exemptions) —
	 *   forwarded as-is; subscription-service is the one place that also knows whether
	 *   this post is premium, so it (not this call site) makes the final "does exempt
	 *   actually apply here" call — see MeterController.check()'s own `isExempt` guard.
	 * @param array<string, string>|null $traffic Referrer/UTM for this view, from
	 *   traffic_context_from_request() — the page the reader is on, as reported by the SDK. null
	 *   (an app request, or an older SDK that sends none) falls back to this request's own
	 *   Referer/query string.
	 * @return array{stage: string, remaining: int|null, remainingToRegister: int|null, eventId: string|null}|null
	 */
	public static function check( string $device_id, int $post_id, bool $exempt = false, ?array $traffic = null ): ?array {
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
					array_merge(
						array(
							'deviceId' => $device_id,
							'postId'   => (string) $post_id,
							'exempt'   => $exempt,
						),
						$traffic ?? self::current_traffic_context()
					)
				),
				'headers' => self::forwarded_headers(),
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
			'stage'               => (string) $data['stage'],
			'remaining'           => $data['remaining'] ?? null,
			// Floating funnel-countdown widget (funnel-counter.ts): the
			// pre-registration counterpart to `remaining` above.
			'remainingToRegister' => $data['remainingToRegister'] ?? null,
			// The analytics row subscription-service recorded for this view (absent when it
			// recorded none, or on an older subscription-service) — see record_shown_async().
			'eventId'             => isset( $data['eventId'] ) && is_string( $data['eventId'] ) ? $data['eventId'] : null,
		);
	}

	/**
	 * Tells subscription-service which stage the reader was actually shown for a view it
	 * recorded, when that differs from the stage check() returned. resolve_entitlement()
	 * (class-mobile-api.php) adjusts the meter's answer per reader — signed-in readers skip the
	 * register prompt, and so on — and without this the admin console's funnel counted the
	 * meter's answer, not what the reader saw. Analytics only (the meter count is untouched), so
	 * fire-and-forget like record_async(): never worth a PHP-FPM worker's time, and a lost
	 * report just leaves the meter's stage on that one row.
	 */
	public static function record_shown_async( string $event_id, string $stage ): void {
		$base_url = Bday_Aero_Settings::api_base_url();
		if ( '' === $base_url || '' === $event_id ) {
			return;
		}

		wp_remote_post(
			$base_url . '/meter/shown',
			array(
				'body'     => wp_json_encode(
					array(
						'eventId' => $event_id,
						'stage'   => $stage,
					)
				),
				'headers'  => self::forwarded_headers(),
				'timeout'  => 0.01,
				'blocking' => false,
			)
		);
	}

	/**
	 * Where the reader came from, as the SDK reports it on the entitlement request
	 * (sdk/src/entitlement.ts's trafficHeaders()). That request's own Referer is the article page
	 * itself and its URL has none of the page's query string, so reading them here (what
	 * current_traffic_context() does) filed every premium view and every paywall block as
	 * Internal traffic with no campaign. X-Aero-Referrer is always sent by an SDK that knows about
	 * this ("-" for no referrer), so its absence means an older SDK or the app: null, and check()
	 * falls back to current_traffic_context().
	 *
	 * @return array{referrerHost?: string, utmSource?: string, utmMedium?: string, utmCampaign?: string}|null
	 */
	public static function traffic_context_from_request( WP_REST_Request $request ): ?array {
		$referrer = $request->get_header( 'x_aero_referrer' );
		if ( null === $referrer ) {
			return null;
		}

		$context = array();
		$host    = strtolower( trim( (string) $referrer ) );
		// A bare host name only; anything else ("-" for direct, or junk) is sent as no referrer.
		if ( '' !== $host && '-' !== $host && 1 === preg_match( '/^[a-z0-9.-]{1,253}$/', $host ) ) {
			$context['referrerHost'] = $host;
		}

		$utm = array(
			'utmSource'   => 'x_aero_utm_source',
			'utmMedium'   => 'x_aero_utm_medium',
			'utmCampaign' => 'x_aero_utm_campaign',
		);
		foreach ( $utm as $field => $header ) {
			$value = $request->get_header( $header );
			if ( null === $value ) {
				continue;
			}
			$value = substr( sanitize_text_field( (string) $value ), 0, 200 );
			if ( '' !== $value ) {
				$context[ $field ] = $value;
			}
		}

		return $context;
	}

	/**
	 * A true sliding window: stores each failure's own timestamp and prunes
	 * anything older than CB_FAILURE_WINDOW before counting, rather than a
	 * plain counter whose transient TTL got pushed back to a fresh
	 * CB_FAILURE_WINDOW on every new failure — that let a slow trickle of
	 * failures spaced less than the window apart accumulate indefinitely
	 * (never expiring on its own the way the constant's own comment says),
	 * instead of tripping only on a genuine burst within the window.
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
	public static function record_async( string $device_id, int $post_id, bool $exempt = false ): void {
		$base_url = Bday_Aero_Settings::api_base_url();
		if ( '' === $base_url ) {
			return;
		}

		wp_remote_post(
			$base_url . '/meter/check',
			array(
				'body'     => wp_json_encode(
					array_merge(
						array(
							'deviceId' => $device_id,
							'postId'   => (string) $post_id,
							'exempt'   => $exempt,
						),
						self::current_traffic_context()
					)
				),
				// Counts toward the reader's meter but records no analytics row: the SDK's
				// entitlement call (class-mobile-api.php) records this same page view moments
				// later, with the page's real referrer and the stage the reader was shown.
				// Recording both counted every free article view twice. A reader whose browser
				// never runs the SDK (no JavaScript, link-preview fetchers such as WhatsApp's)
				// is still metered, just not reported as a view.
				'headers'  => array_merge( self::forwarded_headers(), array( 'X-Aero-Analytics' => 'skip' ) ),
				'timeout'  => 0.01,
				'blocking' => false,
			)
		);
	}

	/**
	 * Traffic-source instrumentation (admin analytics breakdown) — derived
	 * from the current server-side request, since both call sites above run
	 * synchronously within the reader's own page request. referrerHost is a
	 * bare hostname only (never the full referrer URL, which can carry
	 * query strings/paths from the referring site), or omitted for direct
	 * traffic/an unparseable referrer. UTM params are read from the
	 * article's own query string, exactly as the reader's URL had them.
	 *
	 * @return array{referrerHost?: string, utmSource?: string, utmMedium?: string, utmCampaign?: string}
	 */
	private static function current_traffic_context(): array {
		$context = array();

		$referrer = isset( $_SERVER['HTTP_REFERER'] ) ? (string) $_SERVER['HTTP_REFERER'] : '';
		if ( '' !== $referrer ) {
			$host = wp_parse_url( $referrer, PHP_URL_HOST );
			if ( is_string( $host ) && '' !== $host ) {
				$context['referrerHost'] = $host;
			}
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only, not a state-changing request.
		if ( ! empty( $_GET['utm_source'] ) ) {
			$context['utmSource'] = sanitize_text_field( wp_unslash( $_GET['utm_source'] ) );
		}
		if ( ! empty( $_GET['utm_medium'] ) ) {
			$context['utmMedium'] = sanitize_text_field( wp_unslash( $_GET['utm_medium'] ) );
		}
		if ( ! empty( $_GET['utm_campaign'] ) ) {
			$context['utmCampaign'] = sanitize_text_field( wp_unslash( $_GET['utm_campaign'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $context;
	}

	/**
	 * Forwards the reader's real User-Agent so subscription-service's
	 * device-type classification (admin analytics) reflects the actual
	 * reader, not WordPress's own outbound HTTP client string. Deliberately
	 * does NOT forward X-Forwarded-For / the reader's IP here — that feeds
	 * subscription-service's crawler-verification and org-IP-bypass
	 * *security* decisions (see TRUSTED_PROXY_HOPS in subscription-service's
	 * main.ts), and adding a new proxy hop here would silently change what
	 * hop count operators need configured; changing that is a deliberate,
	 * separate decision, not a side effect of an analytics feature.
	 *
	 * @return array<string, string>
	 */
	private static function forwarded_headers(): array {
		$headers = array( 'Content-Type' => 'application/json' );

		// subscription-service now requires the connector API key on /meter/check (it used to be
		// open, so anyone who found the URL could send fake page views). Same key, same header the
		// other connector calls (publish-push.php, class-premium-map.php) already use. If it isn't
		// configured the call is simply refused server-side — which the circuit breaker treats as
		// any other failure — rather than silently sending an unauthenticated request.
		$api_key = Bday_Aero_Settings::api_key();
		if ( '' !== $api_key ) {
			$headers['X-Api-Key'] = $api_key;
		}

		if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$headers['User-Agent'] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		}

		// The BusinessDay app marks its entitlement requests with X-App-Channel: mobile (see
		// resolve_entitlement() in class-mobile-api.php). Passed on so subscription-service
		// records the view as an app view: the app's User-Agent ("okhttp/…" on Android) names
		// no device and looks like a script, so without this Android app views were dropped
		// from analytics as automated traffic and iPhone app views were filed as desktop.
		if ( isset( $_SERVER['HTTP_X_APP_CHANNEL'] ) && 'mobile' === $_SERVER['HTTP_X_APP_CHANNEL'] ) {
			$headers['X-App-Channel'] = 'mobile';
		}

		return $headers;
	}
}
