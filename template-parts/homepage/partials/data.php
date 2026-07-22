<?php
/**
 * Homepage data-fetch — the same query pool templates/masterpage.php always
 * built inline. Pulled into its own function so any variant partial can
 * call it without duplicating the queries, and so a future variant that
 * wants different source data has one clear function to override rather
 * than a copy-pasted block.
 *
 * Every call here goes through custom_get_posts(), which is itself cached
 * (functions.php) — no variant should call get_posts()/WP_Query directly
 * for homepage sections; add new sections through this function so they
 * inherit the same caching.
 */
function bd_get_homepage_default_data(): array {
	$news = custom_get_posts( [ 'tag' => 'bdothernews', 'numberposts' => 9 ] );

	return [
		'main'     => custom_get_posts( [ 'tag' => 'bdlead', 'numberposts' => 1 ] ),
		'top_post' => custom_get_posts( [ 'tag' => 'bdlead', 'numberposts' => 4, 'offset' => 1 ] ),
		'news1'    => array_splice( $news, 0, 6 ),
		'news2'    => array_splice( $news, 0, 3 ),
		'column'   => custom_get_posts( [ 'category_name' => 'Columnist', 'numberposts' => 6 ] ),
		'opinion'  => custom_get_posts( [ 'category_name' => 'opinion', 'numberposts' => 3 ] ),
		'e_paper'  => custom_get_posts( [ 'category_name' => 'e-paper', 'numberposts' => 1 ] ),
		'premium'  => custom_get_posts( [ 'tag' => 'premium', 'numberposts' => 4 ] ),
		'running'  => custom_get_posts( [ 'tag' => '2026-fifa-world-cup', 'numberposts' => 4 ] ),
	];
}
