<?php
/**
 * Addon Name: Ads & Sharing Matrix
 * Addon Slug: ads-sharing-matrix
 * Cache Namespace: ads_matrix
 * Settings Tab: Ads & Sharing Matrix
 * Default: on
 *
 * Decides WHETHER a placement zone is active on a given post-type/category
 * — a full matrix, not just a global on/off. Decoupled from vendor logic:
 * whichever vendor driver is enabled decides WHAT renders into an active
 * zone (see addons/vendors/). Every zone defaults to "on, all post types"
 * so an admin only interacts with this to narrow behavior, not to
 * configure every zone from a blank slate.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/data.php';

add_filter(
	'bday_settings_schema',
	static function ( array $schema ): array {
		$schema['ads-sharing-matrix'] = array(
			'tab_label' => 'Ads & Sharing Matrix',
			'option'    => 'bday_ads_matrix',
			'render'    => 'bday_render_ads_matrix_tab',
		);
		return $schema;
	}
);

add_action(
	'admin_init',
	static function (): void {
		register_setting(
			'bday_ads_matrix',
			'bday_ads_matrix',
			array( 'sanitize_callback' => 'bday_sanitize_ads_matrix' )
		);
	}
);
