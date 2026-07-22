<?php
/**
 * "Women's Hub" category archive. See category-weekender.php's docblock —
 * same fix, same shared pattern.
 */
get_header();

$term = get_queried_object();

$archive_query_args = array(
	'category_name'   => $term->slug,
	'post_type'       => 'post',
	'posts_per_page'  => 15,
	'orderby'         => 'date',
	'order'           => 'DESC',
);
$archive_heading       = 'Browsing Category';
$archive_title         = get_the_archive_title();
$archive_show_featured = false;

require get_template_directory() . '/template-parts/archive/archive-listing.php';

get_footer();
