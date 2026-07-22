<?php
/**
 * Staff-vs-reader front-end behavior: hides the wp-admin toolbar from
 * regular readers, and a nonce-signed direct-logout URL that bypasses
 * WordPress's built-in logout confirmation page.
 */

/**
 * ADDED (2026-07-17): Hide the WordPress admin toolbar on the front end for
 * subscriber/customer accounts — they should get the normal reader
 * experience, not the wp-admin toolbar. Implemented as a hardcoded staff
 * allowlist rather than a subscriber/customer blocklist, so any role not
 * explicitly listed as staff is treated as a reader and has the toolbar
 * hidden by default.
 */
add_filter( 'show_admin_bar', 'bd_hide_admin_bar_for_non_staff' );
function bd_hide_admin_bar_for_non_staff( $show ) {
	if ( ! is_user_logged_in() ) {
		return $show;
	}

	$user        = wp_get_current_user();
	$staff_roles = [ 'administrator', 'editor', 'author', 'wpseo_manager', 'bddraft', 'bdeditor', 'wpseo_editor' ];

	if ( array_intersect( $staff_roles, (array) $user->roles ) ) {
		return $show; // Staff — leave the toolbar behavior as WordPress normally decides
	}

	return false; // Everyone else (subscriber, customer, etc.) — no toolbar
}

/**
 * Direct logout handler — bypasses WordPress's built-in logout confirmation page.
 * Triggered via a nonce-signed custom URL generated in header.php.
 */
function bd_direct_logout_handler() {
	if ( isset( $_GET['bd_action'] ) && $_GET['bd_action'] === 'logout' ) {

		if (
			isset( $_GET['_wpnonce'] ) &&
			wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ),
				'bd-direct-logout'
			)
		) {
			wp_logout();
			wp_safe_redirect( home_url() );
			exit;
		}
	}
}
add_action( 'init', 'bd_direct_logout_handler', 1 );
