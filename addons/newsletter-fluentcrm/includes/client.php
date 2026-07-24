<?php
/** REST bridge to a remote FluentCRM install. Same wire protocol as before (fc-bridge/v1). */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bday_newsletter_setting( string $key, $default = '' ) {
	$options = get_option( 'bday_addon_newsletter', array() );
	return $options[ $key ] ?? $default;
}

/** @return array|WP_Error */
function bday_newsletter_api_request( string $endpoint, string $method = 'GET', ?array $body = null ) {
	$base_url = rtrim( (string) bday_newsletter_setting( 'remote_url' ), '/' );
	$username = (string) bday_newsletter_setting( 'api_username' );
	$password = (string) bday_newsletter_setting( 'api_password' );

	if ( '' === $base_url || '' === $username || '' === $password ) {
		return new WP_Error( 'missing_credentials', 'Remote API settings are incomplete.' );
	}

	$args = array(
		'method'  => $method,
		'timeout' => 15,
		'headers' => array(
			'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ),
			'Content-Type'  => 'application/json; charset=utf-8',
		),
	);
	if ( null !== $body ) {
		$args['body'] = wp_json_encode( $body );
	}

	$response = wp_remote_request( $base_url . '/wp-json/fc-bridge/v1/' . ltrim( $endpoint, '/' ), $args );
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( $code < 200 || $code >= 300 ) {
		return new WP_Error( 'api_error', $data['message'] ?? "HTTP Error {$code}" );
	}
	return $data;
}

/** @return array<int, array<string, mixed>> */
function bday_newsletter_get_lists(): array {
	return Bday_Query_Cache::remember(
		'newsletter',
		'lists',
		static function () {
			$response = bday_newsletter_api_request( 'lists', 'GET' );
			return is_wp_error( $response ) ? array() : (array) ( $response['lists']['data'] ?? array() );
		},
		DAY_IN_SECONDS
	);
}
