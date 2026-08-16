<?php
/**
 * WP-admin-facing proxy for System B's ApiKeyGuard-protected
 * `GET`/`PATCH /connector/settings` — the backend-enforced metering
 * knobs (free-article count, reset cycle, funnel thresholds, scope/
 * hard-wall mode, IP-fallback flag) that WP does NOT keep a second,
 * independently-editable copy of.
 *
 * The browser never talks to the Subscription Service directly for this
 * — the connector API key is a server-to-server secret that must never
 * reach client-side JS, so the admin screen calls these two nonce-checked
 * admin-ajax.php actions instead, and this class does the actual
 * wp_remote_get/post call with the key attached server-side.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Connector_Settings_Client {

	public function __construct() {
		add_action( 'wp_ajax_aero_paywall_get_connector_settings', array( $this, 'handle_get' ) );
		add_action( 'wp_ajax_aero_paywall_update_connector_settings', array( $this, 'handle_update' ) );
	}

	public function handle_get(): void {
		check_ajax_referer( 'aero_paywall_connector_settings', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'bday-aero' ) ), 403 );
			return;
		}

		$base_url = Bday_Aero_Settings::api_base_url();
		$api_key  = Bday_Aero_Settings::api_key();
		if ( '' === $base_url || '' === $api_key ) {
			wp_send_json_error( array( 'message' => __( 'Configure the Subscription Service connection first.', 'bday-aero' ) ) );
			return;
		}

		$response = wp_remote_get(
			$base_url . '/connector/settings',
			array(
				'timeout' => 8,
				'headers' => array( 'X-Api-Key' => $api_key ),
			)
		);
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
			return;
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not load metering settings from the Subscription Service.', 'bday-aero' ) ) );
			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		wp_send_json_success( $body['data'] ?? array() );
	}

	public function handle_update(): void {
		check_ajax_referer( 'aero_paywall_connector_settings', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'bday-aero' ) ), 403 );
			return;
		}

		$key = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
		if ( '' === $key || ! isset( $_POST['value'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing key or value.', 'bday-aero' ) ), 400 );
			return;
		}
		// The value can be a bool/number/object, not just a string — the
		// browser sends it JSON-encoded in a single hidden field rather
		// than trying to reconstruct arbitrary types from raw $_POST.
		$value = json_decode( wp_unslash( $_POST['value'] ), true );

		$base_url = Bday_Aero_Settings::api_base_url();
		$api_key  = Bday_Aero_Settings::api_key();
		if ( '' === $base_url || '' === $api_key ) {
			wp_send_json_error( array( 'message' => __( 'Configure the Subscription Service connection first.', 'bday-aero' ) ) );
			return;
		}

		// The backend's own allowlist is the real enforcement boundary —
		// this proxy doesn't duplicate that list, it just forwards and
		// surfaces whatever the backend decides.
		$response = wp_remote_request(
			$base_url . '/connector/settings',
			array(
				'method'  => 'PATCH',
				'timeout' => 8,
				'headers' => array(
					'Content-Type' => 'application/json',
					'X-Api-Key'    => $api_key,
				),
				'body'    => wp_json_encode( array( 'key' => $key, 'value' => $value ) ),
			)
		);
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
			return;
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			wp_send_json_error( array( 'message' => __( 'The Subscription Service rejected that setting.', 'bday-aero' ) ) );
			return;
		}

		wp_send_json_success( array( 'key' => $key, 'value' => $value ) );
	}
}
