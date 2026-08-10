<?php
/**
 * Pull-based reader identity sync: if a logged-out visitor carries a
 * valid subscription-service reader JWT (cookie `ap_access_token`), log
 * them into WordPress as the linked/created local user. Staff tokens
 * (type === 'staff') are explicitly rejected — this path only ever
 * recognizes readers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_User_Sync {

	private const TOKEN_COOKIE = 'ap_access_token';

	public function __construct() {
		add_action( 'init', array( $this, 'maybe_sync' ), 20 );
		add_action( 'wp_logout', array( $this, 'clear_cookie' ) );
	}

	public function maybe_sync(): void {
		if ( is_user_logged_in() ) {
			return;
		}
		$token = $_COOKIE[ self::TOKEN_COOKIE ] ?? null;
		if ( ! is_string( $token ) || '' === $token ) {
			return;
		}

		$base_url = Bday_Aero_Settings::api_base_url();
		if ( '' === $base_url ) {
			return;
		}

		$claims = Bday_Aero_Jwks_Client::verify( $token, $base_url, 'reader_jwks' );
		if ( null === $claims || ( $claims['type'] ?? '' ) === 'staff' ) {
			return;
		}

		$user = Bday_Aero_Wp_User_Resolver::find_or_create(
			(string) ( $claims['sub'] ?? '' ),
			(string) ( $claims['email'] ?? '' ),
			(string) ( $claims['firstName'] ?? '' ),
			(string) ( $claims['lastName'] ?? '' )
		);
		if ( $user ) {
			wp_set_current_user( $user->ID );
			wp_set_auth_cookie( $user->ID );
		}
	}

	public function clear_cookie(): void {
		if ( headers_sent() ) {
			return;
		}
		setcookie( self::TOKEN_COOKIE, '', time() - HOUR_IN_SECONDS, '/' );
	}
}
