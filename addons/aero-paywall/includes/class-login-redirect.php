<?php
/**
 * Sends WP's own login/register/lost-password URLs (and any direct
 * wp-login.php visit) to the configured account page instead — readers
 * never see wp-login.php, it's an SDK-rendered flow.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Login_Redirect {

	public function __construct() {
		add_filter( 'login_url', array( self::class, 'login_url' ) );
		add_filter( 'register_url', array( self::class, 'register_url' ) );
		add_filter( 'lostpassword_url', array( self::class, 'lostpassword_url' ) );
		add_action( 'login_init', array( self::class, 'maybe_redirect' ) );
	}

	private static function account_url(): string {
		$url = Bday_Aero_Settings::account_page_url();
		return '' !== $url ? $url : home_url( '/my-account/' );
	}

	public static function login_url(): string {
		return self::account_url() . '?tab=login';
	}

	public static function register_url(): string {
		return self::account_url() . '?tab=register';
	}

	public static function lostpassword_url(): string {
		return self::account_url() . '?tab=reset';
	}

	public static function maybe_redirect(): void {
		if ( is_user_logged_in() ) {
			return;
		}
		if ( isset( $_GET['redirect_to'] ) || isset( $_POST['action'] ) ) {
			return; // let the SDK's own POSTs / explicit redirects through unmolested
		}
		wp_safe_redirect( self::account_url() );
		exit;
	}
}
