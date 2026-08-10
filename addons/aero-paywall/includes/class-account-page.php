<?php
/**
 * Registers [aeropaywall_account] — a bare mount point only. All account/
 * login/register/subscribe UI is SDK-rendered client-side; this shortcode
 * exists purely so an editor can place the mount on any Page (typically
 * one using the Funnel/Landing Page template, so it isn't boxed into the
 * site's standard article chrome).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Account_Page {

	public function __construct() {
		add_shortcode( 'aeropaywall_account', array( $this, 'render' ) );
	}

	public function render( $atts ): string {
		$atts = shortcode_atts( array( 'tab' => '' ), $atts );
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : sanitize_key( $atts['tab'] );

		return sprintf( '<div id="aero-paywall-account-mount" data-aero-default-tab="%s"></div>', esc_attr( $tab ) );
	}
}
