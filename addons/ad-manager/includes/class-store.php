<?php
/**
 * Ad Manager data layer — three options, each independently saved by its
 * own admin tab:
 *
 *   bday_ad_settings    global switches, GAM/AdSense identity, refresh and
 *                       lazy-load tuning, ad categories, and the default
 *                       reader policy for each reader state.
 *   bday_ad_units       the registry of ad units ("partners"): GAM slots,
 *                       AdSense, pasted third-party tags, local banners.
 *   bday_ad_placements  which units fill each catalog placement, plus the
 *                       per-placement rules (post types, excluded
 *                       categories, in-article paragraph numbers).
 *
 * Until an option has been saved at least once, its effective value is the
 * seeded default below — real inventory carried over from the previous ad
 * system (the GAM paths in the old class-gam.php and the legacy theme's
 * ads.js) plus any local banners from the retired Premium Leaderboard,
 * Sidebar Promo and Homepage Rail Banner add-ons. That is what lets this
 * ship without a manual re-entry step and without a gap in ad delivery.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Ads_Store {

	public const SETTINGS   = 'bday_ad_settings';
	public const UNITS      = 'bday_ad_units';
	public const PLACEMENTS = 'bday_ad_placements';

	/** @var array<string, mixed>|null */
	private static ?array $settings = null;
	/** @var array<string, array<string, mixed>>|null */
	private static ?array $units = null;
	/** @var array<string, array<string, mixed>>|null */
	private static ?array $placements = null;

	public static function flush(): void {
		self::$settings   = null;
		self::$units      = null;
		self::$placements = null;
	}

	/* ── Settings ─────────────────────────────────────────────────── */

	/** @return array<string, mixed> */
	public static function settings_defaults(): array {
		$open = array( 'mode' => 'all', 'hide' => array(), 'max' => 0 );
		// Honour the old Integrations-tab ads switch until this tab is saved once.
		$legacy = get_option( 'bday_addon_vendors', array() );
		$master = ! ( is_array( $legacy ) && isset( $legacy['ads_master_switch'] ) && ! $legacy['ads_master_switch'] );
		return array(
			'master'           => $master,
			'adsense_client'   => '',
			'breakpoint'       => 768,
			'refresh_seconds'  => 60,
			'refresh_max'      => 3,
			'lazy_fetch_pct'   => 200,
			'lazy_render_pct'  => 100,
			'collapse_empty'   => true,
			'targeting'        => true,
			'debug'            => false,
			'categories'       => bday_ads_default_categories(),
			// What each reader state sees when no plan-specific policy
			// overrides it. A subscriber whose plan carries no ad policy of
			// its own gets 'subscriber' — ad-free by default, since an
			// ad-free experience is what a subscription promises.
			'policy_anonymous' => $open,
			'policy_registered' => $open,
			'policy_subscriber' => array( 'mode' => 'none', 'hide' => array(), 'max' => 0 ),
		);
	}

	/** @return array<string, mixed> */
	public static function settings(): array {
		if ( null === self::$settings ) {
			$saved = get_option( self::SETTINGS, array() );
			$saved = is_array( $saved ) ? $saved : array();
			$merged = array_merge( self::settings_defaults(), $saved );
			if ( empty( $merged['categories'] ) || ! is_array( $merged['categories'] ) ) {
				$merged['categories'] = bday_ads_default_categories();
			}
			self::$settings = $merged;
		}
		return self::$settings;
	}

	/** @return array<string, string> */
	public static function categories(): array {
		return (array) self::settings()['categories'];
	}

	/* ── Units ────────────────────────────────────────────────────── */

	/** @return array<string, mixed> */
	public static function unit_defaults(): array {
		return array(
			'id'             => '',
			'label'          => '',
			'partner'        => 'gam', // gam | adsense | html | banner
			'status'         => 'active', // active | paused
			'device'         => 'all', // all | desktop | mobile
			'category'       => 'display',
			'sizes_desktop'  => '',
			'sizes_mobile'   => '',
			'fallback'       => '',
			// gam
			'path'           => '',
			'format'         => 'standard', // standard | interstitial | top_anchor | bottom_anchor | left_rail | right_rail
			'refresh'        => true,
			// adsense
			'adsense_slot'   => '',
			// html (third-party tag)
			'head_code'      => '',
			'body_code'      => '',
			// banner (local, editor-managed)
			'creatives'      => array(),
			'sponsor_label'  => '',
			'rotate_seconds' => 8,
			'start'          => '',
			'end'            => '',
			'new_tab'        => true,
		);
	}

	/** @return array<string, array<string, mixed>> */
	public static function units(): array {
		if ( null === self::$units ) {
			$saved = get_option( self::UNITS, null );
			$list  = is_array( $saved ) ? $saved : self::seed_units();
			$out   = array();
			foreach ( $list as $unit ) {
				if ( ! is_array( $unit ) || empty( $unit['id'] ) ) {
					continue;
				}
				$unit               = array_merge( self::unit_defaults(), $unit );
				$out[ $unit['id'] ] = $unit;
			}
			self::$units = $out;
		}
		return self::$units;
	}

	/** @return array<string, mixed>|null */
	public static function unit( string $id ): ?array {
		return self::units()[ $id ] ?? null;
	}

	/* ── Placements ───────────────────────────────────────────────── */

	/** @return array<string, mixed> */
	public static function placement_defaults(): array {
		return array(
			'enabled'            => true,
			'units'              => array(),
			'post_types'         => array(), // empty = every post type
			'exclude_categories' => array(), // category slugs
			'paragraph'          => 0,       // content kind only; 0 = catalog default
			'min_paragraphs'     => 0,       // content kind only; 0 = paragraph + 2
		);
	}

	/** @return array<string, array<string, mixed>> */
	public static function placements(): array {
		if ( null === self::$placements ) {
			$saved = get_option( self::PLACEMENTS, null );
			$base  = is_array( $saved ) ? $saved : self::seed_placements();
			$out   = array();
			foreach ( bday_ads_placement_catalog() as $id => $def ) {
				$conf = array_merge( self::placement_defaults(), is_array( $base[ $id ] ?? null ) ? $base[ $id ] : array() );
				if ( 'content' === $def['kind'] ) {
					if ( empty( $conf['paragraph'] ) ) {
						$conf['paragraph'] = (int) ( $def['paragraph'] ?? 2 );
					}
					if ( empty( $conf['min_paragraphs'] ) ) {
						$conf['min_paragraphs'] = (int) $conf['paragraph'] + 2;
					}
				}
				$out[ $id ] = $conf;
			}
			self::$placements = $out;
		}
		return self::$placements;
	}

	/** @return array<string, mixed> */
	public static function placement( string $id ): array {
		return self::placements()[ $id ] ?? self::placement_defaults();
	}

	/* ── Seeds ────────────────────────────────────────────────────── */

	/**
	 * Real GAM inventory. Two networks' worth of paths: the
	 * "/23043164651,21781351181/…" ones are exactly what the previous
	 * theme code already served in production; the "/21781351181/…" ones
	 * are the legacy theme's units (assets/src/js/ads.js). Sizes are
	 * copied from there — never invented.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function seed_units(): array {
		$gam = static function ( string $id, string $label, string $path, string $desktop, string $mobile, array $extra = array() ): array {
			return array_merge(
				array(
					'id'            => $id,
					'label'         => $label,
					'partner'       => 'gam',
					'path'          => $path,
					'sizes_desktop' => $desktop,
					'sizes_mobile'  => $mobile,
				),
				$extra
			);
		};

		$units = array(
			// ---- Responsive units already live on the current theme ----
			$gam( 'bd-top', 'Top (leaderboard)', '/23043164651,21781351181/businessday_top', '970x90,728x90,fluid', '320x100,320x50,300x250,fluid' ),
			$gam( 'bd-body-1', 'Body 1 (in-article)', '/23043164651,21781351181/businessday_body1', '336x280,300x250,250x250,320x100,320x50,fluid', '300x250,336x280,250x250,320x100,320x50,fluid' ),
			$gam( 'bd-body-2', 'Body 2', '/23043164651,21781351181/businessday_body2', '728x90,336x280,300x250,300x100,fluid', '300x250,336x280,320x100,320x50,fluid' ),
			$gam( 'bd-body-3', 'Body 3', '/23043164651,21781351181/businessday_body3', '728x90,336x280,300x250,fluid', '300x250,336x280,320x100,320x50,fluid' ),

			// ---- Legacy theme inventory (separate desktop / mobile units) ----
			$gam( 'bd-desktop-1', 'Desktop 1', '/21781351181/bd_desktop_1', '728x90,300x250,fluid', '', array( 'device' => 'desktop' ) ),
			$gam( 'bd-desktop-2', 'Desktop 2', '/21781351181/bd_desktop_2', '728x90,300x250,fluid', '', array( 'device' => 'desktop' ) ),
			$gam( 'bd-desktop-3', 'Desktop 3', '/21781351181/bd_desktop_3', '728x90,300x50,300x100,fluid', '', array( 'device' => 'desktop' ) ),
			$gam( 'bd-desktop-4', 'Desktop 4', '/21781351181/bd_desktop_4', '728x90,300x250,300x100,fluid', '', array( 'device' => 'desktop' ) ),
			$gam( 'bd-desktop-tenancy-1', 'Homepage tenancy 1 (desktop)', '/21781351181/bd_desktop_homepage_tenancy_1', '970x250,728x90,300x250,300x100,300x50,fluid', '', array( 'device' => 'desktop' ) ),
			$gam( 'bd-desktop-tenancy-2', 'Homepage tenancy 2 (desktop)', '/21781351181/bd_desktop_homepage_tenancy_2', '970x250,970x90,970x66,728x90,300x250,300x100,300x50,fluid', '', array( 'device' => 'desktop', 'refresh' => false ) ),
			$gam( 'bd-sidebar-1', 'Sidebar 1', '/21781351181/bd_sidebar_1', '300x600,300x250', '', array( 'device' => 'desktop' ) ),
			$gam( 'bd-sidebar-2', 'Sidebar 2', '/21781351181/bd_sidebar_2', '300x250,300x600', '', array( 'device' => 'desktop' ) ),
			$gam( 'bd-mobile-1', 'Mobile 1', '/21781351181/bd_mobile_1', '', '300x250,300x50', array( 'device' => 'mobile' ) ),
			$gam( 'bd-mobile-2', 'Mobile 2', '/21781351181/bd_mobile_2', '', '300x100,300x250', array( 'device' => 'mobile' ) ),
			$gam( 'bd-mobile-3', 'Mobile 3', '/21781351181/bd_mobile_3', '', '300x250,336x280,320x100,300x100,300x50', array( 'device' => 'mobile' ) ),
			$gam( 'bd-mobile-4', 'Mobile 4', '/21781351181/bd_mobile_4', '', '300x250,336x280,320x100,300x100,300x50', array( 'device' => 'mobile' ) ),
			$gam( 'bd-mobile-tenancy-1', 'Homepage tenancy 1 (mobile)', '/21781351181/bd_mobile_tenancy_1', '', '300x250,336x280,320x100,320x50,300x100,300x50', array( 'device' => 'mobile' ) ),
			$gam( 'bd-mobile-tenancy-2', 'Homepage tenancy 2 (mobile)', '/21781351181/bd_mobile_tenancy_2', '', '300x250,336x280,320x100,320x50,300x100,300x50', array( 'device' => 'mobile' ) ),

			// ---- Out-of-page formats ----
			$gam( 'bd-anchor', 'Anchor (bottom)', '/23043164651,21781351181/businessday/businessday_anchor', '', '', array( 'format' => 'bottom_anchor', 'category' => 'sticky', 'refresh' => false ) ),
			$gam( 'bd-interstitial', 'Interstitial', '/23043164651,21781351181/businessday/businessday_interstitial', '', '', array( 'format' => 'interstitial', 'category' => 'sticky', 'refresh' => false ) ),
			$gam( 'bd-left-rail', 'Left side rail', '/21781351181/bd_left_rail', '', '', array( 'format' => 'left_rail', 'category' => 'sticky', 'refresh' => false ) ),
			$gam( 'bd-right-rail', 'Right side rail', '/21781351181/bd_right_rail', '', '', array( 'format' => 'right_rail', 'category' => 'sticky', 'refresh' => false ) ),
		);

		return array_merge( $units, self::legacy_banner_units() );
	}

	/**
	 * Local banners from the three retired add-ons, converted to banner
	 * units so nothing an editor already uploaded goes dark. Only the ones
	 * that were actually live (enabled + image) are carried over.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function legacy_banner_units(): array {
		$units = array();

		$lb     = get_option( 'bday_addon_premium_leaderboard', array() );
		$slides = array();
		foreach ( (array) ( $lb['slides'] ?? array() ) as $slide ) {
			if ( ! empty( $slide['image'] ) ) {
				$slides[] = array( 'image' => 0, 'image_url' => (string) $slide['image'], 'image_mobile' => 0, 'url' => (string) ( $slide['url'] ?? '' ), 'alt' => 'Sponsor' );
			}
		}
		if ( $slides ) {
			$units[] = array(
				'id'             => 'legacy-leaderboard',
				'label'          => 'Leaderboard banners (carried over)',
				'partner'        => 'banner',
				'category'       => 'sponsored',
				'creatives'      => $slides,
				'rotate_seconds' => max( 3, (int) round( ( (int) ( $lb['slider_speed'] ?? 20000 ) ) / 1000 ) ),
				'sizes_desktop'  => '970x90',
				'sizes_mobile'   => '320x100',
			);
		}

		$sp = get_option( 'bday_addon_sidebar_promo', array() );
		foreach ( array( 'slot_1', 'slot_2' ) as $n => $key ) {
			if ( ! empty( $sp[ $key . '_enabled' ] ) && ! empty( $sp[ $key . '_image' ] ) ) {
				$units[] = array(
					'id'            => 'legacy-sidebar-promo-' . ( $n + 1 ),
					'label'         => 'Sidebar promo ' . ( $n + 1 ) . ' (carried over)',
					'partner'       => 'banner',
					'category'      => 'sponsored',
					'device'        => 'desktop',
					'creatives'     => array( array( 'image' => (int) $sp[ $key . '_image' ], 'image_mobile' => 0, 'url' => (string) ( $sp[ $key . '_url' ] ?? '' ), 'alt' => (string) ( $sp[ $key . '_label' ] ?? '' ) ) ),
					'sponsor_label' => (string) ( $sp[ $key . '_label' ] ?? '' ),
					'sizes_desktop' => '300x250',
				);
			}
		}

		$rb = get_option( 'bday_addon_homepage_rail_banner', array() );
		if ( ! empty( $rb['enabled'] ) && ! empty( $rb['image'] ) ) {
			$units[] = array(
				'id'            => 'legacy-home-rail',
				'label'         => 'Homepage rail banner (carried over)',
				'partner'       => 'banner',
				'category'      => 'sponsored',
				'creatives'     => array( array( 'image' => (int) $rb['image'], 'image_mobile' => 0, 'url' => (string) ( $rb['url'] ?? '' ), 'alt' => (string) ( $rb['label'] ?? '' ) ) ),
				'sponsor_label' => (string) ( $rb['label'] ?? '' ),
				'sizes_desktop' => '300x250',
				'sizes_mobile'  => '300x250',
			);
		}

		return $units;
	}

	/**
	 * First-run assignments. Reuses what was already serving, adds the
	 * previously-unwired legacy units to the new positions, and applies the
	 * old Ads & Sharing Matrix's on/off switches if one was ever saved.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function seed_placements(): array {
		$p = array(
			'homepage_leaderboard'        => array( 'units' => array( 'bd-top' ) ),
			'home_mid_1'                  => array( 'units' => array( 'bd-desktop-tenancy-1', 'bd-mobile-tenancy-1' ) ),
			'home_mid_2'                  => array( 'units' => array( 'bd-desktop-tenancy-2', 'bd-mobile-tenancy-2' ) ),
			'home_sidebar'                => array( 'units' => array( 'bd-sidebar-2' ) ),
			'home_rail'                   => array( 'units' => array( 'bd-sidebar-1' ) ),
			'home_other_news_rail'        => array( 'units' => array( 'legacy-home-rail', 'bd-sidebar-1' ) ),
			'sidebar'                     => array( 'units' => array( 'bd-sidebar-1' ) ),
			'in_article_after_p2'         => array( 'units' => array( 'bd-body-1' ) ),
			'in_article_mid'              => array( 'units' => array( 'bd-desktop-3', 'bd-mobile-3' ) ),
			'in_article_late'             => array( 'units' => array( 'bd-desktop-4', 'bd-mobile-4' ) ),
			'below_share_buttons'         => array( 'units' => array( 'bd-body-2' ) ),
			'below_article_recirculation' => array( 'units' => array( 'bd-body-3' ) ),
			'listing_inline'              => array( 'units' => array( 'bd-desktop-2', 'bd-mobile-2' ) ),
			'global_oop'                  => array( 'units' => array( 'bd-anchor', 'bd-interstitial', 'bd-left-rail', 'bd-right-rail' ) ),
		);

		// The old leaderboard / sidebar promo add-ons rendered unconditionally, so carry them over as-is.
		$legacy = array_column( self::legacy_banner_units(), 'id' );
		if ( in_array( 'legacy-leaderboard', $legacy, true ) ) {
			$p['top_leaderboard'] = array( 'units' => array( 'legacy-leaderboard' ) );
		}
		$promo = array_values( array_filter( $legacy, static fn( $id ) => 0 === strpos( $id, 'legacy-sidebar-promo' ) ) );
		if ( $promo ) {
			$p['sidebar_top'] = array( 'units' => $promo );
		}

		// Old Ads & Sharing Matrix: an explicitly-saved "off" stays off.
		$matrix = get_option( 'bday_ads_matrix', array() );
		if ( is_array( $matrix ) ) {
			foreach ( $matrix as $zone => $conf ) {
				if ( isset( $p[ $zone ] ) && is_array( $conf ) && empty( $conf['enabled_globally'] ) ) {
					$p[ $zone ]['enabled'] = false;
				}
			}
		}

		return $p;
	}
}
