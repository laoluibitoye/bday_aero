<?php
/**
 * Registers [aero_reader_settings] — Phase 12 (Deep Dive §12/§17): a bare
 * mount point only, same convention as [aeropaywall_account]
 * (class-account-page.php). All actual UI (followed topics, notification
 * digest toggle, archive-access status) is SDK-rendered client-side
 * (sdk/src/reader-settings.ts) against endpoints already built in Phases
 * 7/10/11 — nothing here does more than place the container.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Reader_Settings_Page {

	public function __construct() {
		add_shortcode( 'aero_reader_settings', array( $this, 'render' ) );
	}

	public function render(): string {
		return '<div id="aero-reader-settings-mount"></div>';
	}
}
