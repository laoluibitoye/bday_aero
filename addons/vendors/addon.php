<?php
/**
 * Addon Name: Vendors & Integrations
 * Addon Slug: vendors
 * Cache Namespace: vendors
 * Settings Tab: Integrations
 * Default: on
 *
 * One driver per third-party script (GAM, GA4, Chartbeat, Matomo, Taboola,
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
require_once __DIR__ . '/drivers/class-gam.php';
require_once __DIR__ . '/drivers/class-ga4.php';
require_once __DIR__ . '/drivers/class-chartbeat.php';
require_once __DIR__ . '/drivers/class-matomo.php';
require_once __DIR__ . '/drivers/class-taboola.php';
require_once __DIR__ . '/drivers/class-lytics.php';
require_once __DIR__ . '/drivers/class-getsitecontrol.php';
require_once __DIR__ . '/drivers/class-terrific.php';
require_once __DIR__ . '/drivers/class-tradingview.php';
require_once __DIR__ . '/drivers/class-playstream.php';

/**
 * Master ads kill switch — replaces $ad_is_live. Live by default; disabled
 * automatically on staging hostnames so ads never pollute staging metrics.
 */
function bday_ads_allowed(): bool {
	static $allowed = null;
	if ( null !== $allowed ) {
		return $allowed;
	}

	$opt = get_option( 'bday_addon_vendors', array() );
	if ( isset( $opt['ads_master_switch'] ) && ! $opt['ads_master_switch'] ) {
		$allowed = false;
		return $allowed;
	}

	$host = $_SERVER['HTTP_HOST'] ?? '';
	$is_staging = ( false !== strpos( $host, 'stg' ) || false !== strpos( $host, 'staging' ) )
		|| ( defined( 'WP_HOME' ) && ( false !== strpos( WP_HOME, 'stg' ) || false !== strpos( WP_HOME, 'staging' ) ) );

	$allowed = ! $is_staging;
	return $allowed;
}

/**
 * Ads run on posts, archives/search (post listings), and the front page —
 * never on a WordPress Page, since a Page is as likely to be a sales-funnel
 * or account/transactional screen as an article, and those shouldn't
 * compete with ads for the one task a reader is there to complete. The
 * front page is its own exception even when it's a static Page (it is
 * here — templates/masterpage.php) because it's still the main editorial
 * surface, not a transactional one.
 */
function bday_page_allows_ads(): bool {
	if ( ! bday_ads_allowed() ) {
		return false;
	}
	if ( is_404() ) {
		return false;
	}
	if ( is_page() && ! is_front_page() ) {
		return false;
	}
	return true;
}

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
			new Bday_Vendor_Gam(),
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
add_filter(
	'bday_settings_schema',
	static function ( array $schema ): array {
		$fields   = array(
			array( 'key' => 'ads_master_switch', 'type' => 'checkbox', 'label' => 'Ads enabled site-wide', 'default' => true, 'description' => 'Master switch for all ad rendering (GAM + direct-sold). Auto-disabled on staging hostnames regardless of this setting.' ),
		);
		foreach ( bday_vendor_drivers() as $driver ) {
			foreach ( $driver->settings_fields() as $field ) {
				$field['key']         = $driver->slug() . '__' . $field['key'];
				$fields[] = $field;
			}
		}

		$schema['vendors'] = array(
			'tab_label' => 'Integrations',
			'option'    => 'bday_addon_vendors_flat',
			'render'    => 'bday_render_vendors_tab',
		);
		return $schema;
	}
);

function bday_render_vendors_tab(): void {
	$saved = get_option( 'bday_addon_vendors', array() );
	echo '<table class="form-table" role="presentation"><tbody>';
	printf(
		'<tr><th scope="row">Ads enabled site-wide</th><td><label><input type="checkbox" name="bday_addon_vendors[ads_master_switch]" value="1" %s /> Enabled</label><p class="description">Auto-disabled on staging hostnames regardless of this setting.</p></td></tr>',
		checked( $saved['ads_master_switch'] ?? true, true, false )
	);
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
					$output = array(
						'ads_master_switch' => ! empty( $input['ads_master_switch'] ),
					);
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
