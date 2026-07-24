<?php
/**
 * Addon Name: News Carousel
 * Addon Slug: news-carousel
 * Cache Namespace: news_carousel
 * Settings Tab: News Carousel
 * Default: on
 *
 * The homepage's multi-column "Bloomberg-style" news carousel. Renders
 * into template-parts/homepage/carousel-zone.php's hook, so it only shows
 * up on homepage variants that mount that zone.
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
