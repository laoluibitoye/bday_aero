<?php
/**
 * Staff-vs-reader front-end behavior: hides the wp-admin toolbar from
 * regular readers, and a nonce-signed direct-logout URL that bypasses
 * WordPress's built-in logout confirmation page. Unchanged from the
 * previous theme.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'show_admin_bar',
	static function ( $show ) {
		if ( ! is_user_logged_in() ) {
			return $show;
		}
		$staff_roles = array( 'administrator', 'editor', 'author', 'wpseo_manager', 'bddraft', 'bdeditor', 'wpseo_editor' );
		$user        = wp_get_current_user();
		return array_intersect( $staff_roles, (array) $user->roles ) ? $show : false;
	}
);

add_action(
	'init',
	static function (): void {
		if ( isset( $_GET['bd_action'] ) && 'logout' === $_GET['bd_action']
			&& isset( $_GET['_wpnonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bd-direct-logout' )
		) {
			wp_logout();
			wp_safe_redirect( home_url() );
			exit;
		}
	},
	1
);

function bday_direct_logout_url(): string {
	return add_query_arg(
		array(
			'bd_action' => 'logout',
			'_wpnonce'  => wp_create_nonce( 'bd-direct-logout' ),
		),
		home_url( '/' )
	);
}
