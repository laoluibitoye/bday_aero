<?php
/**
 * Default category archive template.
 *
 * Thin wrapper: build the query args + heading for this page type, then
 * hand off to the shared listing partial. See
 * template-parts/archive/archive-listing.php for the actual markup and
 * the bug fixes that came with consolidating it.
 */
get_header();

$term = get_queried_object();

$archive_query_args = array(
	'category_name'   => $term->slug,
	'post_type'       => 'post',
	'posts_per_page'  => 20,
	'orderby'         => 'date',
	'order'           => 'DESC',
);
$archive_heading       = 'Browsing Category';
$archive_title         = get_the_archive_title();
$archive_show_featured = true;

require get_template_directory() . '/template-parts/archive/archive-listing.php';

get_footer();
