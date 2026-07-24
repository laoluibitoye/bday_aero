<?php
/**
 * Header/body-open/footer script injection — the dedicated "Custom Code"
 * theme-options section. Output verbatim (no escaping) by design; the
 * field itself is gated behind the settings page's manage_options
 * requirement, same as every other option here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bday_custom_code( string $slot ): string {
	$code = get_option( 'bday_custom_code', array() );
	return is_array( $code ) && ! empty( $code[ $slot ] ) ? (string) $code[ $slot ] : '';
}

add_action(
	'wp_head',
	static function (): void {
		$code = bday_custom_code( 'header' );
		if ( $code ) {
			echo "\n<!-- bday: custom header code -->\n" . $code . "\n";
		}
	},
	999
);

add_action(
	'wp_body_open',
	static function (): void {
		$code = bday_custom_code( 'body_open' );
		if ( $code ) {
			echo "\n<!-- bday: custom body-open code -->\n" . $code . "\n";
		}
	}
);

add_action(
	'wp_footer',
	static function (): void {
		$code = bday_custom_code( 'footer' );
		if ( $code ) {
			echo "\n<!-- bday: custom footer code -->\n" . $code . "\n";
		}
	},
	999
);
