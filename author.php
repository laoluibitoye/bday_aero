<?php
/**
 * Author archive template. See archive.php's docblock — same pattern.
 */
get_header();

$archive_query_args = array(
	'author'          => get_query_var( 'author' ),
	'post_type'       => 'post',
	'posts_per_page'  => 13,
	'orderby'         => 'date',
	'order'           => 'DESC',
);
$archive_heading       = 'Author';
$archive_title         = get_the_archive_title();
$archive_show_featured = false;

require get_template_directory() . '/template-parts/archive/archive-listing.php';

get_footer();
