<?php
/**
 * Addon Name: News Carousel
 * Addon Slug: news-carousel
 * Description: Configures which categories/tags feed the "Your News" section on the Redesign 2026 homepage; also still renders its own scrolling carousel widget on the classic homepage variants.
 * Cache Namespace: news_carousel
 * Settings Tab: News Carousel
 * Default: on
 *
 * Reader-reported: the Redesign 2026 homepage variant had this addon's own
 * multi-column "Bloomberg-style" carousel widget (bday_news_carousel_render(),
 * hooked to bday_homepage_carousel_zone) AND its own separate "Your News"
 * desk-rail section (homepage-sections/your-news.php) — same content idea
 * rendered two different ways on the same page. The carousel widget's own
 * placement on that homepage (homepage-sections/carousel.php) was removed;
 * this addon's settings (still under Settings → News Carousel — same
 * column title/type/slug UI as before) now drive Your News' desks instead
 * (bday_get_redesign_your_news_desks(), core/homepage/redesign-data.php),
 * falling back to auto-picking the busiest categories whenever no columns
 * are configured. The classic homepage variants (default.php/weekend.php)
 * have no Your News-equivalent of their own, so bday_news_carousel_render()
 * below still mounts its original carousel widget there, unchanged — one
 * config, two renderers depending on which homepage variant is active.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/render.php';

add_action( 'bday_homepage_carousel_zone', 'bday_news_carousel_render' );

add_filter(
	'bday_settings_schema',
	static function ( array $schema ): array {
		$schema['news-carousel'] = array(
			'tab_label' => 'News Carousel',
			'option'    => 'bday_addon_news_carousel',
			'render'    => 'bday_news_carousel_settings_tab',
			'intro'     => 'On the Redesign 2026 homepage, these columns are what feed the "Your News" section\'s desk cards — each column is a small headline list pulled live from a category or tag you choose here, not manually curated content, and its title here becomes that desk\'s display name. On the classic homepage variants, the same columns still render as this addon\'s own scrolling carousel widget near the top of the page. Add, remove, or retitle columns any time; the five most recent posts in each column\'s source update automatically.',
			'about'     => '<p>Redesign 2026 homepage: each configured column becomes one "Your News" desk card. Classic homepage variants: a horizontally-scrolling row of cards, one per configured column, each showing that source\'s five most recent posts — readers can drag, use the arrow buttons, or (if enabled) let it auto-advance.</p>',
			'use_cases' => array(
				'Choose which categories/tags "Your News" surfaces on the Redesign 2026 homepage, and what each desk is labeled.',
				'Give a tag-based collection (e.g. a running news event) its own column temporarily.',
			),
		);
		return $schema;
	}
);

add_action(
	'admin_init',
	static function (): void {
		register_setting( 'bday_addon_news_carousel', 'bday_addon_news_carousel', array( 'sanitize_callback' => 'bday_news_carousel_sanitize' ) );
	}
);
