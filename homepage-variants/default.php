<?php
/**
 * Variant Name: Default
 * Variant Slug: default
 * Description: The standard weekday front page — top news, lead story, recent, then a full news grid.
 *
 * Module flow adopts wsj.com's homepage layout (reader-requested), mapped
 * onto BusinessDay's real content rather than cloned pixel-for-pixel —
 * see design.md's WSJ-layout-adoption phase for the full per-module
 * reasoning. WSJ modules with no sensible BusinessDay equivalent (a
 * shopping-deals vertical, a prediction-market widget, reader-poll and
 * "people to know" profile boxes) are not built here at all, rather than
 * filled with placeholder content for brands that don't exist on this
 * site.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data = bday_get_homepage_data();

// 1. Top News | Lead | Opinion — already a 3-column hero grid; only the
// third column's content (now Opinion, was Recent) changed for this.
get_template_part( 'template-parts/homepage/hero', null, array( 'data' => $data, 'layout' => 'split' ) );

// 2. The Briefing — Semafor-style headline strip, WSJ's "Newswire
// Global" brief-strip equivalent; stays directly under the hero.
get_template_part( 'template-parts/homepage/briefing', null, array( 'data' => $data ) );

// 3. Podcast carousel.
get_template_part( 'template-parts/homepage/podcast-carousel', null, array( 'data' => $data ) );

get_template_part( 'template-parts/homepage/carousel-zone' );

// 4. "In Other News" 3-6 card grid — WSJ's "Best of the Week" row.
if ( ! empty( $data['other_news'] ) ) {
	echo '<section class="bday-other-news"><div class="bday-container">';
	echo '<h2 class="bday-section-heading"><a href="' . esc_url( bday_section_url( 'news' ) ) . '">' . esc_html( bday_section_label( 'news' ) ) . '</a></h2>';
	echo '<div class="bday-card-grid">';
	foreach ( $data['other_news'] as $post ) {
		echo bday_card_html( $post, array( 'show_byline' => true ) );
	}
	echo '</div></div></section>';
}

// 5. Subscribe banner.
get_template_part( 'template-parts/homepage/subscribe-banner' );

// 6. Feature spotlight ("Big Read").
get_template_part( 'template-parts/homepage/feature-spotlight', null, array( 'data' => $data ) );

// 7. Topic-list sections + "Most Popular" sidebar, two-up.
echo '<div class="bday-container bday-two-col bday-two-col--topics">';
echo '<div class="bday-topic-sections">';
// Layout mix is deliberate, not decorative — reader-requested "mixed
// content display types," matching how nytimes.com's front page never
// repeats the same module shape twice in a row: 'grid' gives a section
// real visual weight (bigger cards, excerpts), 'text' is a dense
// thumbnail-free list (this section's headlines carry it, not an image),
// 'list' (the original shape) sits in between.
foreach ( array(
	array( 'heading' => 'Economy', 'category_slug' => 'economy', 'posts' => $data['topic_economy'], 'layout' => 'list' ),
	array( 'heading' => 'Politics', 'category_slug' => 'politics', 'posts' => $data['topic_politics'], 'layout' => 'text' ),
	array( 'heading' => 'Life & Arts', 'category_slug' => 'life-arts', 'posts' => $data['topic_life_arts'], 'layout' => 'list' ),
	array( 'heading' => 'World', 'category_slug' => 'world', 'posts' => $data['topic_world'], 'layout' => 'grid' ),
	array( 'heading' => 'Companies', 'category_slug' => 'companies', 'posts' => $data['topic_companies'], 'layout' => 'grid' ),
	array( 'heading' => 'Law', 'category_slug' => 'law', 'posts' => $data['topic_law'], 'layout' => 'list' ),
) as $topic ) {
	get_template_part( 'template-parts/homepage/topic-list', null, $topic );
}
echo '</div>';

echo '<aside class="bday-sidebar">';
if ( ! empty( $data['most_popular'] ) ) {
	echo '<div class="bday-sidebar__most-popular">';
	echo '<h2 class="bday-eyebrow">Most Popular</h2>';
	echo '<ol class="bday-list bday-list--numbered">';
	foreach ( $data['most_popular'] as $post ) {
		echo '<li><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></li>';
	}
	echo '</ol></div>';
}
if ( ! empty( $data['recent'] ) ) {
	echo '<div class="bday-sidebar__recent">';
	echo '<h2 class="bday-eyebrow">Just In</h2>';
	echo '<ul class="bday-list">';
	foreach ( $data['recent'] as $post ) {
		echo '<li><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a><time>' . esc_html( bday_time_ago( $post->post_date ) ) . '</time></li>';
	}
	echo '</ul></div>';
}
bday_ad_zone( 'sidebar' );
echo '</aside>';
echo '</div>';

// 8. Video row (BD TV).
get_template_part( 'template-parts/homepage/video-row' );

// 9. Real Estate | Sports two-up.
echo '<div class="bday-container bday-two-col bday-two-col--even">';
get_template_part( 'template-parts/homepage/topic-list', null, array( 'heading' => 'Real Estate', 'category_slug' => 'real-estate', 'posts' => $data['topic_real_estate'], 'layout' => 'list' ) );
get_template_part( 'template-parts/homepage/topic-list', null, array( 'heading' => 'Sports', 'category_slug' => 'sports', 'posts' => $data['topic_sports'], 'layout' => 'text' ) );
echo '</div>';

// 10. Columnists + e-paper/premium sidebar.
echo '<div class="bday-container bday-two-col bday-two-col--rail">';
get_template_part( 'template-parts/homepage/rail', null, array( 'data' => $data ) );
get_template_part( 'template-parts/homepage/sidebar', null, array( 'data' => $data ) );
echo '</div>';

// 11-12. Today's Paper + Toon of the Day, e-editions, events.
get_template_part( 'template-parts/homepage/bottom-widgets', null, array( 'data' => $data ) );
