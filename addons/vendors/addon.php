<?php
/**
 * Addon Name: Vendors & Integrations
 * Addon Slug: vendors
 * Description: Third-party integrations: GA4, Chartbeat, Matomo, Taboola, TradingView, and more.
 * Cache Namespace: vendors
 * Settings Tab: Integrations
 * Default: on
 *
 * One driver per third-party script (GA4, Chartbeat, Matomo, Taboola,
 * Lytics, GetSiteControl, Terrific, TradingView, Playstream), each
 * independently configurable and independently kill-switchable — where the
 * previous theme had exactly one kill switch (ads-only, staging-only) for
 * everything. FlashTalking is not ported: it was already broken (a hardcoded
 * iframe with unfilled macro placeholders) and is closed out here rather
 * than replicated.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-vendor-driver.php';
require_once __DIR__ . '/drivers/class-ga4.php';
require_once __DIR__ . '/drivers/class-chartbeat.php';
require_once __DIR__ . '/drivers/class-matomo.php';
require_once __DIR__ . '/drivers/class-taboola.php';
require_once __DIR__ . '/drivers/class-lytics.php';
require_once __DIR__ . '/drivers/class-getsitecontrol.php';
require_once __DIR__ . '/drivers/class-terrific.php';
require_once __DIR__ . '/drivers/class-tradingview.php';
require_once __DIR__ . '/drivers/class-playstream.php';

function bday_current_page_section(): string {
	if ( is_front_page() ) {
		return 'homepage';
	}
	if ( is_single() ) {
		$cats = get_the_category();
		return ! empty( $cats ) ? $cats[0]->name : '';
	}
	if ( is_archive() ) {
		return (string) get_the_archive_title();
	}
	return '';
}

function bday_current_page_author(): string {
	if ( ! is_single() ) {
		return '';
	}
	return get_the_author_meta( 'display_name', get_post_field( 'post_author', get_the_ID() ) );
}

/** @return Bday_Vendor_Driver[] */
function bday_vendor_drivers(): array {
	static $drivers = null;
	if ( null === $drivers ) {
		$drivers = array(
			new Bday_Vendor_Ga4(),
			new Bday_Vendor_Chartbeat(),
			new Bday_Vendor_Matomo(),
			new Bday_Vendor_Taboola(),
			new Bday_Vendor_Lytics(),
			new Bday_Vendor_Getsitecontrol(),
			new Bday_Vendor_Terrific(),
			new Bday_Vendor_Tradingview(),
			new Bday_Vendor_Playstream(),
		);
	}
	return $drivers;
}

function bday_vendor( string $slug ): ?Bday_Vendor_Driver {
	foreach ( bday_vendor_drivers() as $driver ) {
		if ( $driver->slug() === $slug ) {
			return $driver;
		}
	}
	return null;
}

add_action(
	'wp_head',
	static function (): void {
		foreach ( bday_vendor_drivers() as $driver ) {
			if ( $driver->is_configured() ) {
				$driver->print_head();
			}
		}
	}
);

add_action(
	'wp_footer',
	static function (): void {
		foreach ( bday_vendor_drivers() as $driver ) {
			if ( $driver->is_configured() ) {
				$driver->print_footer();
			}
		}
	}
);

// Settings tab: one collapsible section per driver, plus the ads master switch.
// The actual field list is built by bday_render_vendors_tab() itself
// (below) per driver — this schema entry uses 'render', not 'fields', so
// nothing here needs to pre-compute one.
add_filter(
	'bday_settings_schema',
	static function ( array $schema ): array {
		$schema['vendors'] = array(
			'tab_label' => 'Integrations',
				'group'     => 'technical', // third-party API keys/tracking IDs
			'option'    => 'bday_addon_vendors_flat',
			'render'    => 'bday_render_vendors_tab',
			'intro'     => 'Every third-party script this site loads — analytics, ad serving, content recommendations, on-site widgets — in one place, each independently on/off with its own account credentials. Each collapsible section below is a separate vendor; a vendor left disabled never loads its script at all, so it can\'t affect page speed or fire any tracking for that vendor.',
			'about'     => '<p>These are the actual third-party integrations running on the live site — real account IDs, not placeholders. Changing an ID here redirects that vendor\'s data collection to a different account immediately; double-check before saving.</p>',
		);
		return $schema;
	}
);

function bday_render_vendors_tab(): void {
	$saved = get_option( 'bday_addon_vendors', array() );
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row">Advertising</th><td><p class="description">Ad serving (Google Ad Manager, AdSense, ad tags and banners) now lives under <strong>Ads &amp; Monetization → Ad Manager</strong>.</p></td></tr>';
	foreach ( bday_vendor_drivers() as $driver ) {
		$slug   = $driver->slug();
		$values = $saved[ $slug ] ?? array();
		foreach ( $driver->settings_fields() as $field ) {
			$field['_option'] = "bday_addon_vendors[{$slug}]";
			echo '<tr><th scope="row">' . esc_html( $field['label'] ) . '</th><td>';
			bday_render_field( $field, $values );
			echo '</td></tr>';
		}
	}
	echo '</tbody></table>';
}

add_action(
	'admin_init',
	static function (): void {
		register_setting(
			'bday_addon_vendors_flat',
			'bday_addon_vendors',
			array(
				'sanitize_callback' => static function ( $input ) {
					$input  = is_array( $input ) ? $input : array();
					$output = array();
					foreach ( bday_vendor_drivers() as $driver ) {
						$slug           = $driver->slug();
						$sub            = is_array( $input[ $slug ] ?? null ) ? $input[ $slug ] : array();
						$output[ $slug ] = bday_sanitize_fields( $driver->settings_fields(), $sub );
					}
					return $output;
				},
			)
		);
	}
);
