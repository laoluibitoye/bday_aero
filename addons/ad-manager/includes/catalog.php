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
			'where'    => 'Directly under the site header, on every page that allows ads.',
			'kind'     => 'slot',
			'desktop'  => '970×90 or 728×90',
			'mobile'   => '320×100 or 320×50',
			'category' => 'display',
		),
		'homepage_leaderboard'        => array(
			'label'    => 'Homepage — below the lead stories',
			'where'    => 'Full-width, at the bottom of the homepage hero block.',
			'kind'     => 'slot',
			'desktop'  => '970×250, 970×90 or 728×90',
			'mobile'   => '300×250 or 320×100',
			'category' => 'display',
		),
		'home_mid_1'                  => array(
			'label'    => 'Homepage — mid-page 1',
			'where'    => 'Full-width, between "In Other News" and the subscribe banner.',
			'kind'     => 'slot',
			'desktop'  => '970×250 or 728×90',
			'mobile'   => '300×250 or 320×100',
			'category' => 'display',
		),
		'home_mid_2'                  => array(
			'label'    => 'Homepage — mid-page 2',
			'where'    => 'Full-width, between the video row and the Real Estate / Sports block.',
			'kind'     => 'slot',
			'desktop'  => '970×250 or 728×90',
			'mobile'   => '300×250 or 320×100',
			'category' => 'display',
		),
		'home_sidebar'                => array(
			'label'    => 'Homepage — topics sidebar',
			'where'    => 'Right column beside the topic lists (under Most Popular / Just In).',
			'kind'     => 'slot',
			'desktop'  => '300×600 or 300×250',
			'mobile'   => '300×250',
			'category' => 'display',
		),
		'home_rail'                   => array(
			'label'    => 'Homepage — columnists rail',
			'where'    => 'Right column beside the columnists block.',
			'kind'     => 'slot',
			'desktop'  => '300×600 or 300×250',
			'mobile'   => '300×250',
			'category' => 'display',
		),
		'home_other_news_rail'        => array(
			'label'    => 'Homepage — "In Other News" rail',
			'where'    => 'Right column of the "In Other News" section, under Today\'s E-paper.',
			'kind'     => 'slot',
			'desktop'  => '300×250 or 300×600',
			'mobile'   => '300×250',
			'category' => 'display',
		),
		'sidebar_top'                 => array(
			'label'    => 'Article sidebar — top',
			'where'    => 'Top of the article sidebar, under the widget area. Best for house ads and direct-sold banners.',
			'kind'     => 'slot',
			'desktop'  => '300×250',
			'mobile'   => 'hidden on mobile',
			'category' => 'sponsored',
		),
		'sidebar'                     => array(
			'label'    => 'Article sidebar — main',
			'where'    => 'Bottom of the article sidebar (desktop and tablet only; the sidebar is hidden on phones).',
			'kind'     => 'slot',
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
