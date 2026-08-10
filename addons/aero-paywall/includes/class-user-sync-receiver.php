<?php
/**
 * Push-based sync: subscription-service's wp-user-sync queue processor
 * calls this REST route whenever a reader's subscription status changes,
 * so WP's own copy of "is this user an active subscriber" stays current
 * without WP ever having to poll for it. Route path is unchanged from the
 * retired connector-plugin (`/wp-json/aeropaywall/v1/user-sync`) since
 * subscription-service's queue processor targets that exact path.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_User_Sync_Receiver {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	public function register_route(): void {
		register_rest_route(
			'aeropaywall/v1',
			'/user-sync',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'check_api_key' ),
			)
		);
	}

	public function check_api_key( WP_REST_Request $request ): bool {
		$key = $request->get_header( 'x-api-key' ) ?? '';
		return '' !== Bday_Aero_Settings::api_key() && hash_equals( Bday_Aero_Settings::api_key(), (string) $key );
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$aero_user_id = sanitize_text_field( (string) $request->get_param( 'external_user_id' ) );
		$email        = sanitize_email( (string) $request->get_param( 'email' ) );
		$first_name   = sanitize_text_field( (string) $request->get_param( 'first_name' ) );
		$last_name    = sanitize_text_field( (string) $request->get_param( 'last_name' ) );
		$status       = sanitize_text_field( (string) $request->get_param( 'subscription_status' ) );
		$plan_key     = sanitize_text_field( (string) $request->get_param( 'plan_key' ) );
		$expires_at   = sanitize_text_field( (string) $request->get_param( 'expires_at' ) );

		if ( '' === $aero_user_id || '' === $email ) {
			return new WP_REST_Response( array( 'error' => 'Missing external_user_id or email' ), 400 );
		}

		$user = Bday_Aero_Wp_User_Resolver::find_or_create( $aero_user_id, $email, $first_name, $last_name );
		if ( ! $user ) {
			return new WP_REST_Response( array( 'error' => 'Could not resolve user' ), 422 );
		}

		update_user_meta( $user->ID, '_aero_paywall_subscription_status', $status );
		update_user_meta( $user->ID, '_aero_paywall_plan', $plan_key );
		update_user_meta( $user->ID, '_aero_paywall_expires_at', $expires_at );

		if ( 'active' === $status ) {
			$user->add_role( 'aero_active_subscriber' );
		} else {
			$user->remove_role( 'aero_active_subscriber' );
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}
}
