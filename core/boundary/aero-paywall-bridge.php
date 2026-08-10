<?php
/**
 * Core-side half of the native AeroPaywall integration: two global
 * helpers core templates call unconditionally (template-parts/single-
 * default.php). Must be defined regardless of whether addons/aero-paywall
 * is enabled — that add-on defaults to OFF (see its own addon.php
 * docblock), and a disabled add-on's code is never loaded at all, so
 * these can't live inside it without every core template that calls them
 * fataling with "call to undefined function" on a fresh/default install.
 *
 * `instanceof` against a class that doesn't exist (add-on disabled) is
 * safe in PHP — it evaluates false rather than fataling — which is what
 * makes the no-op fallback here possible without a function_exists()
 * dance. Same "no-op-when-unavailable" convention this theme already
 * uses for bday_paywall_login_url() (paywall-contract.php) and
 * aero_paywall_nav_button() before it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bday_aero_gate_content( int $post_id, string $content ): string {
	global $bday_aero_content_gate;
	if ( ! ( $bday_aero_content_gate instanceof Bday_Aero_Content_Gate ) ) {
		return $content;
	}
	return $bday_aero_content_gate->gate_content( $post_id, $content );
}

function bday_aero_is_post_gated( int $post_id ): bool {
	global $bday_aero_content_gate;
	if ( ! ( $bday_aero_content_gate instanceof Bday_Aero_Content_Gate ) ) {
		return false;
	}
	return $bday_aero_content_gate->is_post_gated( $post_id );
}
