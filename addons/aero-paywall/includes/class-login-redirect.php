<?php
/**
 * Readers should never see WordPress's own wp-login.php/wp-admin screens —
 * the Account page (SDK-rendered) already has a full login/register/reset
 * experience. This redirects every core-generated login/register/lost-
 * password URL there, and redirects a direct, unauthenticated visit to
 * wp-login.php too.
 *
 * Staff/admin logins are untouched by design:
 * - Only unauthenticated GET requests to wp-login.php are ever redirected
 *   — an actual credential POST always passes through untouched.
 * - Any request/URL carrying a `redirect_to` parameter is left alone.
 *   That's the exact signal WordPress core's own auth_redirect() adds
 *   whenever an unauthenticated visit to /wp-admin bounces through
 *   wp-login.php, so staff following that normal path always see the
 *   real form. (An earlier version of this class skipped this check on
 *   the login_url filter specifically — the one auth_redirect() actually
 *   calls — which hijacked every /wp-admin login attempt straight to the
 *   reader account page. That's the bug this rewrite fixes.)
 * - If no account page URL is configured at all, every method here
 *   leaves WordPress's own computed URL/behavior untouched rather than
 *   inventing a fallback destination.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Login_Redirect {

	public function __construct() {
		add_filter( 'login_url', array( self::class, 'login_url' ) );
		add_filter( 'register_url', array( self::class, 'register_url' ) );
		add_filter( 'lostpassword_url', array( self::class, 'lostpassword_url' ) );
		add_action( 'login_init', array( self::class, 'maybe_redirect_direct_visit' ) );
	}

	public static function login_url( string $login_url ): string {
		if ( self::has_redirect_to( $login_url ) ) {
			return $login_url;
		}
		return self::tabbed_url( 'login', $login_url );
	}

	public static function register_url( string $register_url ): string {
		return self::tabbed_url( 'register', $register_url );
	}

	public static function lostpassword_url( string $lostpassword_url ): string {
		return self::tabbed_url( 'reset', $lostpassword_url );
	}

	private static function has_redirect_to( string $url ): bool {
		$query = wp_parse_url( $url, PHP_URL_QUERY );
		if ( ! $query ) {
			return false;
		}
		parse_str( $query, $params );
		return ! empty( $params['redirect_to'] );
	}

	private static function tabbed_url( string $tab, string $fallback ): string {
		$account_url = Bday_Aero_Settings::account_page_url();
		if ( '' === $account_url ) {
			return $fallback;
		}
		return add_query_arg( 'tab', $tab, $account_url );
	}

	/**
	 * Only ever fires for a direct, unauthenticated GET to wp-login.php
	 * with no `redirect_to` — someone typed the URL, bookmarked it, or
	 * found it via search. POST (an actual login attempt), redirect_to
	 * (the normal /wp-admin bounce-through), and already-authenticated
	 * requests all pass through untouched.
	 */
	public static function maybe_redirect_direct_visit(): void {
		if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'GET' ) {
			return;
		}
		if ( ! empty( $_GET['redirect_to'] ) ) {
			return;
		}
		if ( is_user_logged_in() ) {
			return;
		}

		$account_url = Bday_Aero_Settings::account_page_url();
		if ( '' === $account_url ) {
			return;
		}

		$action = is_string( $_GET['action'] ?? null ) ? $_GET['action'] : 'login';
		$tab    = 'register' === $action ? 'register' : ( 'lostpassword' === $action ? 'reset' : 'login' );

		wp_safe_redirect( add_query_arg( 'tab', $tab, $account_url ) );
		exit;
	}
}
