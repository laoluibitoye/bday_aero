<?php
/**
 * Registers [aeropaywall_account] — a bare mount point only. All account/
 * login/register/subscribe UI is SDK-rendered client-side; this shortcode
 * exists purely so an editor can place the mount on any Page.
 *
 * Reader-requested: My Account/Log In/Create Account/Reset/Change Password
 * moved from the minimal Funnel/Landing Page template to the site's
 * default (full masthead/nav/footer) template, for a "natively
 * integrated" feel rather than reading as a detached checkout flow — the
 * `.bday-account-page-wrap` class here centers/caps the mount's width
 * inside that template's full-bleed `.bday-container`, since the account
 * shell/auth forms were designed to sit in a narrow column (the Funnel
 * template's own `<main>` had no width constraint either, but visually
 * read fine full-bleed since nothing beside it emphasized the page's
 * full width — the standard site chrome's wide masthead/nav make an
 * unconstrained narrow form look adrift by contrast).
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

		return sprintf(
			'<div class="bday-account-page-wrap"><div id="aero-paywall-account-mount" data-aero-default-tab="%s"></div></div>',
			esc_attr( $tab )
		);
	}
}
