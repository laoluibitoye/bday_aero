<?php
/**
 * Reader-reported: a reader's account is a real WP user (role: subscriber
 * — see class-wp-user-resolver.php) with a real WP auth cookie
 * (class-user-sync.php's wp_set_auth_cookie()), which by WordPress's own
 * default behavior is enough to load wp-admin's (stripped-down but real)
 * dashboard and see the admin toolbar on every page. Neither is meant for
 * readers — content editors and site administrators are real WP users
 * with real WP roles, but "AeroPaywall staff" (Sales/Support, who manage
 * gating/subscriptions) are an entirely separate system (subscription-
 * service's StaffUser table, signed into admin-web, never WordPress) with
 * no WP account at all. So the one signal that actually distinguishes
 * "can legitimately use wp-admin" from "reader account" is capability,
 * not role name: `edit_posts` is WordPress's own line between Subscriber
 * and every role that can actually do something in wp-admin
 * (Contributor+), and is never granted to an account this add-on creates.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Reader_Admin_Lockdown {

	public function __construct() {
		add_action( 'admin_init', array( self::class, 'maybe_redirect_from_wp_admin' ) );
		add_filter( 'show_admin_bar', array( self::class, 'hide_admin_bar_for_readers' ) );
		add_action( 'login_init', array( self::class, 'maybe_redirect_from_wp_login' ) );
	}

	/**
	 * class-login-redirect.php already handles an *unauthenticated* direct
	 * visit to wp-login.php — this covers the other half: an already-
	 * signed-in reader who navigates there anyway (WordPress's default
	 * response is "You are already logged in..." with a link straight
	 * into wp-admin, which is exactly what this add-on doesn't want a
	 * reader looking at).
	 */
	public static function maybe_redirect_from_wp_login(): void {
		if ( ! is_user_logged_in() || current_user_can( 'edit_posts' ) ) {
			return;
		}

		$account_url = Bday_Aero_Settings::account_page_url();
		wp_safe_redirect( '' !== $account_url ? $account_url : home_url( '/' ) );
		exit;
	}

	/**
	 * admin_init fires for every wp-admin request, including the AJAX/
	 * cron/REST requests that happen to be routed through admin-ajax.php
	 * or wp-cron.php — none of those are the dashboard a reader could
	 * "see," and blocking them would break legitimate front-end AJAX
	 * calls a logged-in reader's own page can make. Only a real, direct
	 * dashboard page load is redirected.
	 */
	public static function maybe_redirect_from_wp_admin(): void {
		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( current_user_can( 'edit_posts' ) ) {
			return;
		}

		$account_url = Bday_Aero_Settings::account_page_url();
		wp_safe_redirect( '' !== $account_url ? $account_url : home_url( '/' ) );
		exit;
	}

	public static function hide_admin_bar_for_readers( bool $show ): bool {
		if ( is_user_logged_in() && ! current_user_can( 'edit_posts' ) ) {
			return false;
		}
		return $show;
	}
}
