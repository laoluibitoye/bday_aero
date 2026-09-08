<?php
/**
 * Addon Name: Ads & Sharing Matrix
 * Addon Slug: ads-sharing-matrix
 * Description: Manages ad zone placements (GAM or direct-sold) across the site. Despite the name, it does not configure social-share buttons — those are fixed, unconfigurable markup in core/helpers.php's bday_social_share_html().
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
 *
 * The "Sharing" half of this addon's name is legacy/aspirational — no
 * sharing-button configuration has ever lived here; a zone named
 * 'below_share_buttons' is just an ad zone's positional name (it renders
 * beneath the share buttons, it doesn't control them). Kept as the tab's
 * existing label/slug rather than renamed, since bday_addon_states and
 * bday_settings_tab_roles already store role/toggle data keyed to
 * 'ads-sharing-matrix' — see the Description above for where the real
 * settings live instead.
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
				'group'     => 'technical', // ad-ops placement rules, not day-to-day content
			// A dummy option-group name, deliberately distinct from the
			// real storage option ('bday_ads_matrix', registered below) —
			// same pattern as addons/vendors. If this said 'bday_ads_matrix'
			// here too, the framework's own generic register_settings()
			// loop would register a second, redundant sanitize_option
			// filter on the exact same real option (harmless today only
			// because that generic fallback is a no-op, but fragile).
			'option'    => 'bday_ads_matrix_flat',
			'render'    => 'bday_render_ads_matrix_tab',
			'intro'     => 'Controls where ad units are allowed to render across the site\'s different page types (homepage, article, category archive, etc.) — the "matrix" of page type × zone. This decides placement rules, not the ad creative or targeting itself, which lives with the ad network configured under Integrations.',
			'about'     => '<p>Each zone corresponds to a real slot already built into the theme\'s templates (in-article, sidebar, below-share-buttons, etc.) — toggling one off here removes ads from that specific position sitewide without needing to touch the ad server\'s own configuration.</p>',
		);
		return $schema;
	}
);

add_action(
	'admin_init',
	static function (): void {
		register_setting(
			'bday_ads_matrix_flat', // option group — matches the schema's dummy 'option' above, not the real storage name below
			'bday_ads_matrix',
			array( 'sanitize_callback' => 'bday_sanitize_ads_matrix' )
		);
	}
);
