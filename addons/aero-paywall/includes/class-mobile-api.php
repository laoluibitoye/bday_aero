<?php
/**
 * GET /wp-json/aeropaywall/v1/articles/{id} — the single source of real
 * article content for the reader SDK and native mobile apps alike. Route
 * path is unchanged from the retired connector-plugin: the CDN-hosted SDK
 * and mobile app builds call this path directly and aren't part of this
 * theme's deploy, so it can't move.
 *
 * Same "reveal the real body only after genuine entitlement" invariant as
 * the render-time content gate: an unauthenticated/unentitled request
 * gets the preview only, never the full content. Unlike the cosmetic
 * clients (branding/paywall-config), this fails CLOSED — an unreachable
 * subscription-service or missing device id/token means "not entitled",
 * never "assume open".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Mobile_Api {

	private Bday_Aero_Premium_Map $premium_map;

	public function __construct( Bday_Aero_Premium_Map $premium_map ) {
		$this->premium_map = $premium_map;
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	public function register_route(): void {
		register_rest_route(
			'aeropaywall/v1',
			'/articles/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'resolve_article' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function resolve_article( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return new WP_REST_Response( array( 'error' => 'Not found' ), 404 );
		}

		$is_premium = $this->premium_map->is_premium( $post_id );
		$is_gated   = Bday_Aero_Settings::enabled() && Bday_Aero_License_Client::is_active()
			&& ( 'hard' === Bday_Aero_Settings::paywall_mode() || $is_premium );

		$content = apply_filters( 'the_content', $post->post_content );
		$preview = wp_trim_words( wp_strip_all_tags( $post->post_content ), Bday_Aero_Settings::preview_word_count() );

		if ( ! $is_gated ) {
			return $this->response( $post, $is_premium, 'open', null, true, $preview, $content );
		}

		$gift_token = $request->get_header( 'x-gift-token' );
		if ( $gift_token ) {
			$redeemed = $this->redeem_gift( (string) $gift_token, $post_id );
			if ( $redeemed ) {
				return $this->response( $post, $is_premium, 'open', null, true, $preview, $content );
			}
		}

		$entitlement = $this->resolve_entitlement( $request, $post_id );
		if ( $entitlement['open'] ) {
			return $this->response( $post, $is_premium, $entitlement['stage'], $entitlement['remaining'], $entitlement['isSubscriber'], $preview, $content );
		}

		return $this->response( $post, $is_premium, $entitlement['stage'], $entitlement['remaining'], false, $preview, null );
	}

	/**
	 * Reader-reported live: an already-logged-in reader still saw the
	 * register/login gate. Traced to a well-known Apache/CGI/FastCGI
	 * hosting quirk (common on shared/cPanel hosts, unrelated to this
	 * theme's own code): the Authorization header is silently stripped
	 * before it ever reaches PHP unless the server explicitly passes it
	 * through, so $request->get_header('authorization') alone can come
	 * back empty even though the reader's browser genuinely sent it —
	 * every request then falls through to the anonymous/device-based
	 * path below, regardless of how validly signed-in the reader actually
	 * is. This checks every place a host might have put that header
	 * instead of trusting just one, so the fix doesn't also depend on the
	 * server config being correct.
	 */
	private static function get_authorization_header( WP_REST_Request $request ): string {
		$from_request = $request->get_header( 'authorization' );
		if ( $from_request ) {
			return $from_request;
		}
		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
		}
		// Apache's own name for the header once an internal redirect (e.g.
		// a rewrite rule already present for something unrelated) has
		// touched the request — the single most common place a stripped
		// Authorization header actually turns up.
		if ( ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
		}
		if ( function_exists( 'getallheaders' ) ) {
			foreach ( getallheaders() as $name => $value ) {
				if ( 0 === strcasecmp( $name, 'Authorization' ) ) {
					return sanitize_text_field( wp_unslash( $value ) );
				}
			}
		}
		return '';
	}

	/** @return array{open: bool, stage: string, remaining: int|null, isSubscriber: bool} */
	private function resolve_entitlement( WP_REST_Request $request, int $post_id ): array {
		$is_authenticated_reader = false;

		$auth = self::get_authorization_header( $request );
		if ( str_starts_with( $auth, 'Bearer ' ) ) {
			$token    = substr( $auth, 7 );
			$base_url = Bday_Aero_Settings::api_base_url();
			$claims   = '' !== $base_url ? Bday_Aero_Jwks_Client::verify( $token, $base_url, 'reader_jwks' ) : null;
			if ( null !== $claims && ( $claims['type'] ?? '' ) !== 'staff' ) {
				/**
				 * A verified, non-staff token only means "this is a real
				 * signed-in reader" — it does NOT mean "this reader has
				 * paid." Bug found live while seeding test content: this
				 * used to return open+isSubscriber unconditionally for any
				 * valid reader token, so every registered-but-free account
				 * read unlimited premium content through the exact route
				 * the SDK uses to unlock articles — the paywall was a
				 * no-op for anyone who'd merely signed up. subscription-
				 * service bakes the real answer into every token it issues
				 * (EntitlementClaimsService, checked on login/register/
				 * refresh/checkout-verify) as `subscriptionStatus`, the
				 * same claim the SDK's own nav-sync.ts already trusts
				 * client-side for UI state — reusing it here, not
				 * inventing a new signal.
				 */
				if ( 'active' === ( $claims['subscriptionStatus'] ?? '' ) ) {
					return array( 'open' => true, 'stage' => 'open', 'remaining' => null, 'isSubscriber' => true );
				}
				// Signed in but not subscribed: same free-article meter an
				// anonymous reader gets, not an automatic pass — falls
				// through to the device-id/meter check below. Remembered
				// so the *stage* we hand back once that meter is
				// exhausted skips register/profile prompts (below).
				$is_authenticated_reader = true;
			}
		}

		$device_id = $request->get_header( 'x-device-id' ) ?? '';
		if ( '' === $device_id ) {
			return array( 'open' => false, 'stage' => 'paid_lock', 'remaining' => 0, 'isSubscriber' => false );
		}

		$meter = Bday_Aero_Meter_Client::check( (string) $device_id, $post_id );
		if ( null === $meter ) {
			// Unreachable subscription-service: fail closed, unlike the cosmetic clients.
			return array( 'open' => false, 'stage' => 'paid_lock', 'remaining' => 0, 'isSubscriber' => false );
		}

		$stage = $meter['stage'];
		/**
		 * Bug found live: a signed-in-but-unsubscribed reader who'd used up
		 * their free views still got whichever stage the device meter
		 * naturally computes — register_prompt or profile_prompt, the same
		 * ones an anonymous, never-registered visitor sees. Those steps
		 * are already done for this reader; showing "Create a free
		 * account" to someone already signed in is confusing and, per
		 * reader feedback, actively hurts conversion (it reads as broken,
		 * not as a nudge to subscribe). The only gate that ever makes
		 * sense for an authenticated non-subscriber is "Subscribe."
		 */
		if ( $is_authenticated_reader && in_array( $stage, array( 'register_prompt', 'profile_prompt' ), true ) ) {
			$stage = 'paid_lock';
		}

		$open = ! in_array( $stage, array( 'paid_lock', 'register_prompt', 'profile_prompt' ), true );
		return array( 'open' => $open, 'stage' => $stage, 'remaining' => $meter['remaining'], 'isSubscriber' => false );
	}

	private function redeem_gift( string $token, int $post_id ): bool {
		$base_url = Bday_Aero_Settings::api_base_url();
		if ( '' === $base_url ) {
			return false;
		}
		$response = wp_remote_post(
			$base_url . '/gift/redeem',
			array(
				'timeout' => 5,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'token' => $token ) ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$data = $body['data'] ?? null;
		return is_array( $data ) && ! empty( $data['valid'] ) && (string) ( $data['postId'] ?? '' ) === (string) $post_id;
	}

	private function response( WP_Post $post, bool $is_premium, string $stage, ?int $remaining, bool $is_subscriber, string $preview, ?string $content ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'id'           => $post->ID,
				'title'        => get_the_title( $post ),
				'excerpt'      => get_the_excerpt( $post ),
				'isPremium'    => $is_premium,
				'stage'        => $stage,
				'remaining'    => $remaining,
				'isSubscriber' => $is_subscriber,
				'preview'      => $preview,
				'content'      => $content,
			),
			200
		);
	}
}
