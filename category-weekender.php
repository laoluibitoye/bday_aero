<?php
/**
 * "Weekender" category archive.
 *
 * FIX (2026-07-22): this file, category-womens-hub.php, and
 * category-reports.php used to be byte-for-byte identical copies of a bare
 * PDF-thumbnail-grid layout (the pattern meant for e-edition/cartoon image
 * galleries) — a copy-paste starting point that was never finished into a
 * real article listing, so these three text-article categories rendered as
 * an unlabeled grid of linked thumbnails with no headline, author, or
 * excerpt. Now uses the same shared article-listing partial as every other
 * category archive. See archive.php's docblock for the pattern.
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
