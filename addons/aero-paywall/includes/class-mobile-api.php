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

	/** @return array{open: bool, stage: string, remaining: int|null, isSubscriber: bool} */
	private function resolve_entitlement( WP_REST_Request $request, int $post_id ): array {
		$auth = $request->get_header( 'authorization' ) ?? '';
		if ( str_starts_with( $auth, 'Bearer ' ) ) {
			$token    = substr( $auth, 7 );
			$base_url = Bday_Aero_Settings::api_base_url();
			$claims   = '' !== $base_url ? Bday_Aero_Jwks_Client::verify( $token, $base_url, 'reader_jwks' ) : null;
			if ( null !== $claims && ( $claims['type'] ?? '' ) !== 'staff' ) {
				return array( 'open' => true, 'stage' => 'open', 'remaining' => null, 'isSubscriber' => true );
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

		$open = ! in_array( $meter['stage'], array( 'paid_lock', 'register_prompt', 'profile_prompt' ), true );
		return array( 'open' => $open, 'stage' => $meter['stage'], 'remaining' => $meter['remaining'], 'isSubscriber' => false );
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
