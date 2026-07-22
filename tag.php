<?php
/**
 * Tag archive template. See archive.php's docblock — same pattern.
 */
get_header();

$archive_query_args = array(
	'tag'             => get_query_var( 'tag' ),
	'post_type'       => 'post',
	'posts_per_page'  => 10,
	'orderby'         => 'date',
	'order'           => 'DESC',
);
$archive_heading       = 'Browsing Tag';
$archive_title         = get_the_archive_title();
$archive_show_featured = false;

require get_template_directory() . '/template-parts/archive/archive-listing.php';

get_footer();
