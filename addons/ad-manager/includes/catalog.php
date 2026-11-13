<?php
/**
 * The fixed catalog of places in the theme where an ad can appear.
 *
 * A "placement" is a physical location in a template (e.g. "article
 * sidebar", "after paragraph 2") — code-defined because it maps to a real
 * bday_ad_zone() call in a template. What fills a placement (GAM, AdSense,
 * pasted third-party code, or a local image banner) is data an editor
 * manages from wp-admin: see class-store.php. No ad code lives in
 * templates, only placement names.
 *
 * `desktop` / `mobile` are the recommended creative sizes, shown in the
 * admin so whoever uploads a banner or configures a GAM unit knows what the
 * location is designed to hold.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @return array<string, array<string, mixed>> */
function bday_ads_placement_catalog(): array {
	$catalog = array(
		'top_leaderboard'             => array(
			'label'    => 'Top leaderboard',
			'where'    => 'The very top of every page, above the site header (logo, date and menu).',
			'kind'     => 'slot',
			'desktop'  => '970×90 or 728×90',
			'mobile'   => '320×100 or 320×50',
			'category' => 'display',
		),
		'homepage_leaderboard'        => array(
			'label'    => 'Homepage — below the lead stories',
			'where'    => 'Full-width, after the whole homepage hero block (Top News list, lead story and Recent column — on a phone that is below all three).',
			'kind'     => 'slot',
			'homepage' => true, // exists on every homepage layout
			'desktop'  => '970×250, 970×90 or 728×90',
			'mobile'   => '300×250 or 320×100',
			'category' => 'display',
		),
		'home_after_lead'             => array(
			'label'    => 'Homepage — right under the lead story (phones and tablets)',
			'where'    => 'Redesign homepage only: directly beneath the lead story when the page is a single column (below 1024px wide). Hidden on desktop, where the lead story sits between two columns.',
			'kind'     => 'slot',
			'layouts'  => array( 'redesign' ),
			'desktop'  => 'hidden on desktop',
			'mobile'   => '300×250 or 320×100',
			'category' => 'display',
		),
		'home_slot_1'                 => array(
			'label'    => 'Homepage — slot 1 (between sections)',
			'where'    => 'Redesign homepage only: a full-width ad directly after whichever section you pick below.',
			'kind'     => 'section',
			'layouts'  => array( 'redesign' ),
			'desktop'  => '970×250 or 728×90',
			'mobile'   => '300×250 or 320×100',
			'category' => 'display',
		),
		'home_slot_2'                 => array(
			'label'    => 'Homepage — slot 2 (between sections)',
			'where'    => 'Redesign homepage only: a full-width ad directly after whichever section you pick below.',
			'kind'     => 'section',
			'layouts'  => array( 'redesign' ),
			'desktop'  => '970×250 or 728×90',
			'mobile'   => '300×250 or 320×100',
			'category' => 'display',
		),
		'home_slot_3'                 => array(
			'label'    => 'Homepage — slot 3 (between sections)',
			'where'    => 'Redesign homepage only: a full-width ad directly after whichever section you pick below.',
			'kind'     => 'section',
			'layouts'  => array( 'redesign' ),
			'desktop'  => '970×250 or 728×90',
			'mobile'   => '300×250 or 320×100',
			'category' => 'display',
		),
		'home_slot_4'                 => array(
			'label'    => 'Homepage — slot 4 (between sections)',
			'where'    => 'Redesign homepage only: a full-width ad directly after whichever section you pick below.',
			'kind'     => 'section',
			'layouts'  => array( 'redesign' ),
			'desktop'  => '970×250 or 728×90',
			'mobile'   => '300×250 or 320×100',
			'category' => 'display',
		),
		'home_slot_5'                 => array(
			'label'    => 'Homepage — slot 5 (between sections)',
			'where'    => 'Redesign homepage only: a full-width ad directly after whichever section you pick below.',
			'kind'     => 'section',
			'layouts'  => array( 'redesign' ),
			'desktop'  => '970×250 or 728×90',
			'mobile'   => '300×250 or 320×100',
			'category' => 'display',
		),
		'home_slot_6'                 => array(
			'label'    => 'Homepage — slot 6 (between sections)',
			'where'    => 'Redesign homepage only: a full-width ad directly after whichever section you pick below.',
			'kind'     => 'section',
			'layouts'  => array( 'redesign' ),
			'desktop'  => '970×250 or 728×90',
			'mobile'   => '300×250 or 320×100',
			'category' => 'display',
		),
		'home_mid_1'                  => array(
			'label'    => 'Homepage — mid-page 1',
			'where'    => 'Full-width, between "In Other News" and the subscribe banner.',
			'kind'     => 'slot',
			'layouts'  => array( 'default' ),
			'desktop'  => '970×250 or 728×90',
			'mobile'   => '300×250 or 320×100',
			'category' => 'display',
		),
		'home_mid_2'                  => array(
			'label'    => 'Homepage — mid-page 2',
			'where'    => 'Full-width, between the video row and the Real Estate / Sports block.',
			'kind'     => 'slot',
			'layouts'  => array( 'default' ),
			'desktop'  => '970×250 or 728×90',
			'mobile'   => '300×250 or 320×100',
			'category' => 'display',
		),
		'home_sidebar'                => array(
			'label'    => 'Homepage — topics sidebar',
			'where'    => 'Right column beside the topic lists (under Most Popular / Just In).',
			'kind'     => 'slot',
			'layouts'  => array( 'default' ),
			'desktop'  => '300×600 or 300×250',
			'mobile'   => '300×250',
			'category' => 'display',
		),
		'home_rail'                   => array(
			'label'    => 'Homepage — columnists rail',
			'where'    => 'Right column beside the columnists block.',
			'kind'     => 'slot',
			'layouts'  => array( 'default', 'weekend', 'breaking-news' ),
			'desktop'  => '300×600 or 300×250',
			'mobile'   => '300×250',
			'category' => 'display',
		),
		'home_other_news_rail'        => array(
			'label'    => 'Homepage — "In Other News" rail',
			'where'    => 'Right column of the "In Other News" section, under Today\'s E-paper.',
			'kind'     => 'slot',
			'layouts'  => array( 'redesign' ),
			'desktop'  => '300×250 or 300×600',
			'mobile'   => '300×250',
			'category' => 'display',
		),
		'sidebar_top'                 => array(
			'label'    => 'Article sidebar — top',
			'where'    => 'Top of the article sidebar, under the widget area. Best for house ads and direct-sold banners.',
			'kind'     => 'slot',
			'phone'    => false,
			'desktop'  => '300×250',
			'mobile'   => 'hidden on mobile',
			'category' => 'sponsored',
		),
		'sidebar'                     => array(
			'label'    => 'Article sidebar — main',
			'where'    => 'Bottom of the article sidebar (desktop and tablet only; the sidebar is hidden on phones).',
			'kind'     => 'slot',
			'phone'    => false,
			'desktop'  => '300×600 or 300×250',
			'mobile'   => 'hidden on mobile',
			'category' => 'display',
		),
		'in_article_after_p2'         => array(
			'label'      => 'In-article — early',
			'where'      => 'Inside the article text, after the paragraph set below.',
			'kind'       => 'content',
			'paragraph'  => 2,
			'desktop'    => '336×280 or 300×250',
			'mobile'     => '300×250 or 336×280',
			'category'   => 'display',
		),
		'in_article_mid'              => array(
			'label'      => 'In-article — middle',
			'where'      => 'Inside the article text, after the paragraph set below (only on longer articles).',
			'kind'       => 'content',
			'paragraph'  => 6,
			'desktop'    => '336×280 or 300×250',
			'mobile'     => '300×250 or 336×280',
			'category'   => 'display',
		),
		'in_article_late'             => array(
			'label'      => 'In-article — late',
			'where'      => 'Inside the article text, after the paragraph set below (only on long articles).',
			'kind'       => 'content',
			'paragraph'  => 10,
			'desktop'    => '336×280 or 300×250',
			'mobile'     => '300×250 or 336×280',
			'category'   => 'display',
		),
		'below_share_buttons'         => array(
			'label'    => 'Article — below the author block',
			'where'    => 'After the article body and author bio, above the share buttons.',
			'kind'     => 'slot',
			'desktop'  => '728×90 or 300×250',
			'mobile'   => '300×250 or 320×100',
			'category' => 'display',
		),
		'below_article_recirculation' => array(
			'label'    => 'Article — below "You might also like"',
			'where'    => 'End of the article column.',
			'kind'     => 'slot',
			'desktop'  => '728×90 or 300×250',
			'mobile'   => '300×250 or 320×100',
			'category' => 'display',
		),
		'listing_inline'              => array(
			'label'    => 'Archive / search — inline',
			'where'    => 'Between story cards on category, tag, author and search pages (after every 6th card).',
			'kind'     => 'slot',
			'desktop'  => '728×90 or 300×250',
			'mobile'   => '300×250 or 320×100',
			'category' => 'display',
		),
		'global_oop'                  => array(
			'label'    => 'Sticky & full-screen formats',
			'where'    => 'No fixed spot: anchor, interstitial and side-rail formats that float over the page.',
			'kind'     => 'global',
			'desktop'  => 'format-defined',
			'mobile'   => 'format-defined',
			'category' => 'sticky',
		),
	);

	return apply_filters( 'bday_ads_placement_catalog', $catalog );
}

/**
 * Ad categories — the unit of subscription gating. Every ad unit belongs to
 * one; a reader's plan lists which categories it hides (see
 * subscription-service's Plan.adPolicy). Editors can add their own under
 * Ad Manager → Settings; these are only the starting set.
 *
 * @return array<string, string> key => label
 */
function bday_ads_default_categories(): array {
	return array(
		'display'   => 'Display (standard banners)',
		'sticky'    => 'Sticky & full-screen (anchor, interstitial, rails)',
		'video'     => 'Video',
		'sponsored' => 'Sponsored & direct-sold banners',
	);
}

/**
 * Which homepage layout is live. The homepage is built by one of several
 * layouts ("variants" — Redesign, Default, Weekend, Breaking News) and each
 * calls a different set of ad placements, so a placement only appears if the
 * live layout actually contains it.
 */
function bday_ads_active_layout(): string {
	return class_exists( 'Bday_Variant_Registry' ) ? Bday_Variant_Registry::active_slug() : '';
}

/**
 * Whether a placement exists on the live homepage layout. Placements with no
 * 'layouts' entry are not homepage-layout-specific (articles, archives,
 * header) and are always available.
 *
 * @param array<string,mixed> $def Catalog entry.
 */
function bday_ads_placement_on_live_layout( array $def ): bool {
	if ( empty( $def['layouts'] ) ) {
		return true;
	}
	return in_array( bday_ads_active_layout(), (array) $def['layouts'], true );
}

/**
 * Homepage sections an ad can follow, for the "between sections" slots:
 * slug => label, in the order they appear on the live homepage (enabled
 * sections only).
 *
 * @return array<string,string>
 */
function bday_ads_homepage_sections(): array {
	if ( ! class_exists( 'Bday_Section_Registry' ) ) {
		return array();
	}
	$all = Bday_Section_Registry::discover();
	$out = array();
	foreach ( Bday_Section_Registry::ordered_active() as $slug ) {
		if ( isset( $all[ $slug ] ) ) {
			$out[ $slug ] = (string) $all[ $slug ]['label'];
		}
	}
	return $out;
}
