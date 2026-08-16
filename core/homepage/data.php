<?php
/**
 * Homepage data-fetch, shared by every variant so none of them duplicates
 * these queries. Every call goes through bday_get_posts() (cached) — no
 * variant should call get_posts()/WP_Query directly. Tag/category slugs
 * (bdlead, bdothernews, Columnist, opinion, e-paper, premium,
 * 2026-fifa-world-cup) are preserved exactly as editors already use them
 * day to day — renaming them here would silently break live tagging
 * workflows that have nothing to do with this rebuild.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bday_get_homepage_data(): array {
	$other_news = bday_get_posts( array( 'tag' => 'bdothernews', 'numberposts' => 9, 'cache_namespace' => 'homepage' ) );

	return array(
		// Semafor-style short-form briefing strip — Africa/World/Politics
		// headlines only, no images, deliberately visually distinct from
		// every other (card-based) section on the page.
		'briefing'    => bday_get_posts(
			array(
				'tax_query'       => array(
					array(
						'taxonomy' => 'category',
						'field'    => 'slug',
						'terms'    => array( 'africa', 'world', 'politics' ),
					),
				),
				'numberposts'     => 10,
				'cache_namespace' => 'homepage',
			)
		),
		'lead'        => bday_get_posts( array( 'tag' => 'bdlead', 'numberposts' => 1, 'cache_namespace' => 'homepage' ) ),
		'top_stories' => bday_get_posts( array( 'tag' => 'bdlead', 'numberposts' => 4, 'offset' => 1, 'cache_namespace' => 'homepage' ) ),
		'recent'      => bday_get_posts( array( 'tag' => 'bdrecent', 'numberposts' => 4, 'cache_namespace' => 'homepage' ) ),
		'other_news'  => array_slice( $other_news, 0, 6 ),
		'columnists'  => bday_get_posts( array( 'category_name' => 'Columnist', 'numberposts' => 6, 'cache_namespace' => 'homepage' ) ),
		// Bumped from 3 to 5 — now feeds the hero's right-hand Opinion
		// column (WSJ-layout adoption) instead of a small box further down
		// the page, so it needs to actually fill that column.
		'opinion'     => bday_get_posts( array( 'category_name' => 'opinion', 'numberposts' => 5, 'cache_namespace' => 'homepage' ) ),
		'e_paper'     => bday_get_posts( array( 'category_name' => 'e-paper', 'numberposts' => 1, 'cache_namespace' => 'homepage' ) ),
		// "Most Popular" sidebar (WSJ-layout adoption) — comment_count is
		// the only real engagement signal this codebase tracks; no
		// page-view counter exists, so this is the honest proxy rather
		// than a fabricated "trending" metric.
		'most_popular' => bday_get_posts( array( 'numberposts' => 5, 'orderby' => 'comment_count', 'cache_namespace' => 'homepage' ) ),
		// One large feature + a short list from the same category — the
		// "Big Read" module (WSJ-layout adoption's Documentaries-module
		// equivalent).
		'feature_spotlight' => bday_get_posts( array( 'category_name' => 'editorial', 'numberposts' => 4, 'cache_namespace' => 'homepage' ) ),
		// Repeatable headline-with-thumbnail sections (WSJ-layout
		// adoption) — one BusinessDay category per WSJ vertical, per
		// design.md's Phase 17 mapping table.
		'topic_economy'    => bday_get_posts( array( 'category_name' => 'economy', 'numberposts' => 5, 'cache_namespace' => 'homepage' ) ),
		'topic_politics'   => bday_get_posts( array( 'category_name' => 'politics', 'numberposts' => 5, 'cache_namespace' => 'homepage' ) ),
		'topic_life_arts'  => bday_get_posts( array( 'category_name' => 'life-arts', 'numberposts' => 5, 'cache_namespace' => 'homepage' ) ),
		'topic_world'      => bday_get_posts( array( 'category_name' => 'world', 'numberposts' => 5, 'cache_namespace' => 'homepage' ) ),
		'topic_companies'  => bday_get_posts( array( 'category_name' => 'companies', 'numberposts' => 5, 'cache_namespace' => 'homepage' ) ),
		'topic_law'        => bday_get_posts( array( 'category_name' => 'law', 'numberposts' => 5, 'cache_namespace' => 'homepage' ) ),
		'topic_real_estate' => bday_get_posts( array( 'category_name' => 'real-estate', 'numberposts' => 5, 'cache_namespace' => 'homepage' ) ),
		'topic_sports'      => bday_get_posts( array( 'category_name' => 'sports', 'numberposts' => 5, 'cache_namespace' => 'homepage' ) ),
		// Latest podcast episodes (WSJ-layout adoption's podcast carousel)
		// — post_type_exists() guard matches the same pattern
		// bottom-widgets.php already uses for this CPT.
		'podcasts'    => post_type_exists( 'podcast' )
			? bday_get_posts( array( 'post_type' => 'podcast', 'numberposts' => 6, 'cache_namespace' => 'homepage' ) )
			: array(),
		'premium'     => bday_get_posts( array( 'tag' => 'premium', 'numberposts' => 4, 'cache_namespace' => 'homepage' ) ),
		'world_cup'   => bday_get_posts( array( 'tag' => '2026-fifa-world-cup', 'numberposts' => 4, 'cache_namespace' => 'homepage' ) ),
		'weekender'   => bday_get_posts( array( 'category_name' => 'weekender', 'numberposts' => 3, 'cache_namespace' => 'homepage' ) ),
		'womens_hub'  => bday_get_posts( array( 'category_name' => 'womens-hub', 'numberposts' => 2, 'cache_namespace' => 'homepage' ) ),
		'reports'     => bday_get_posts( array( 'category_name' => 'reports', 'numberposts' => 3, 'cache_namespace' => 'homepage' ) ),
	);
}
