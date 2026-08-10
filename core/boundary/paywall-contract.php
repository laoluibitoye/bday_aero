<?php
/**
 * The theme-side half of the "content-only platform" boundary: every
 * subscription/paywall/account decision stays inside the AeroPaywall
 * connector plugin (or any future replacement) — this file only defines
 * hooks/filters for it to answer, and reads its own option for the one
 * value (the account/login URL) the plugin already publicly exposes.
 *
 * Enforced rule: nothing here may write a new option/meta key for gating
 * state, or make any database write related to entitlement. This file
 * only ever reads.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the account-page URL option directly — not a second copy of it,
 * since both the retired connector-plugin AND the native
 * addons/aero-paywall add-on read/write this exact same option key
 * (aero_paywall_account_page_url). Works identically regardless of which
 * of the two (plugin or native add-on) is actually active, since it never
 * touches either one's code, only the option they share.
 */
function bday_paywall_login_url(): string {
	$configured = get_option( 'aero_paywall_account_page_url', '' );
	$fallback   = home_url( '/my-account/' );
	return (string) apply_filters( 'bday_paywall_login_url', $configured ?: $fallback );
}

/**
 * 'public' | 'premium' | 'locked' — the plugin answers this via the filter
 * below if it's active; the theme only ever renders whatever comes back as
 * a body/post class, it never decides the value itself.
 */
function bday_paywall_post_state( ?WP_Post $post = null ): string {
	$post = $post ?: get_post();
	return (string) apply_filters( 'bday_paywall_post_state', 'public', $post );
}

add_filter(
	'body_class',
	static function ( array $classes ): array {
		if ( is_singular() ) {
			$classes[] = 'is-' . bday_paywall_post_state() . '-content';
		}
		return $classes;
	}
);
