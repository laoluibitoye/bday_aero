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
		'lead'        => bday_get_posts( array( 'tag' => 'bdlead', 'numberposts' => 1, 'cache_namespace' => 'homepage' ) ),
		'top_stories' => bday_get_posts( array( 'tag' => 'bdlead', 'numberposts' => 4, 'offset' => 1, 'cache_namespace' => 'homepage' ) ),
		'recent'      => bday_get_posts( array( 'tag' => 'bdrecent', 'numberposts' => 4, 'cache_namespace' => 'homepage' ) ),
		'other_news'  => array_slice( $other_news, 0, 6 ),
		'columnists'  => bday_get_posts( array( 'category_name' => 'Columnist', 'numberposts' => 6, 'cache_namespace' => 'homepage' ) ),
		'opinion'     => bday_get_posts( array( 'category_name' => 'opinion', 'numberposts' => 3, 'cache_namespace' => 'homepage' ) ),
		'e_paper'     => bday_get_posts( array( 'category_name' => 'e-paper', 'numberposts' => 1, 'cache_namespace' => 'homepage' ) ),
		'premium'     => bday_get_posts( array( 'tag' => 'premium', 'numberposts' => 4, 'cache_namespace' => 'homepage' ) ),
		'world_cup'   => bday_get_posts( array( 'tag' => '2026-fifa-world-cup', 'numberposts' => 4, 'cache_namespace' => 'homepage' ) ),
		'weekender'   => bday_get_posts( array( 'category_name' => 'weekender', 'numberposts' => 3, 'cache_namespace' => 'homepage' ) ),
		'womens_hub'  => bday_get_posts( array( 'category_name' => 'womens-hub', 'numberposts' => 2, 'cache_namespace' => 'homepage' ) ),
		'reports'     => bday_get_posts( array( 'category_name' => 'reports', 'numberposts' => 3, 'cache_namespace' => 'homepage' ) ),
	);
}
