<?php
/**
 * Navigation menu behavior: the AeroPaywall account-link URL, login/sign-up/
 * subscribe nav-item visibility by auth state, and page-title display.
 */

/**
 * Returns the AeroPaywall "My Account" page URL (set by the site admin under
 * Settings > AeroPaywall), falling back to a conventional /my-account/ page
 * if the option hasn't been configured yet. Used by header.php in place of
 * the old Magnaquest login/sign-up/my-account/change-password links.
 */
function bd_get_aero_account_url() {
	$url = get_option( 'aero_paywall_account_page_url' );
	return $url ? $url : home_url( '/my-account/' );
}

/**
 * Shows/hides "Login" / "Sign Up" / "Subscribe to our Premium" nav items
 * based on authentication state. Kept because it's a generic, title-matching
 * filter with no third-party coupling — it now applies to whatever nav items
 * point at the AeroPaywall account page (see header.php).
 */
function bd_custom_menu_visibility( $items, $args ) {
	foreach ( $items as $key => $item ) {
		/* Logged IN user */
		if ( is_user_logged_in() ) {
			// Hide Sign In
			if ( $item->title == 'Login' ) {
				unset( $items[ $key ] );
			}
			// Hide Sign Up
			if ( $item->title == 'SignUp' ) {
				unset( $items[ $key ] );
			}
		}
		/* Logged OUT user */
		else {
			// Hide Subscribe
			if ( strpos( trim( strip_tags( $item->title ) ), 'Subscribe to our Premium' ) !== false ) {
				unset( $items[ $key ] );
			}
		}
	}
	return $items;
}
add_filter( 'wp_nav_menu_objects', 'bd_custom_menu_visibility', 10, 2 );

/**
 * Hides the page title on WP Page templates (not needed there — pages carry
 * their own heading in content) while leaving article/post titles alone.
 */
add_action( 'wp_head', 'bd_hide_page_titles' );

function bd_hide_page_titles() {
	// FIX (2026-07-17): skip single posts — articles render their headline as
	// h1.post-title too (see template-parts/single-default.php). Without this
	// check, article titles were hidden site-wide.
	if ( is_singular( 'post' ) ) {
		return;
	}
	?>
	<style>
		.page-title,
		.entry-title,
		.single-post-title,
		h1.post-title {
			display: none !important;
		}
	</style>
	<?php
}
