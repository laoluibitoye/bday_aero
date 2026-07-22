<?php
/**
 * Search results template. See archive.php's docblock for the shared
 * pattern — the one addition here is $archive_before_listing, which
 * injects the search form/input right after the breadcrumb.
 */
get_header();

$search_query = get_search_query();

$archive_query_args = array(
	's'               => $search_query,
	'posts_per_page'  => 15,
	'orderby'         => 'date',
	'order'           => 'DESC',
);
$archive_heading       = '';
$archive_title         = 'Search result for "' . esc_html( $search_query ) . '"';
$archive_show_featured = false;
$archive_before_listing = function () {
	?>
	<div class="search-container">
		<div class="search">
			<form role="search" method="get" action="<?= esc_url( home_url( '/' ) ) ?>">
				<input type="search" class="search-field" placeholder="Search..." value="<?= esc_attr( get_search_query() ) ?>" name="s" title="Search for:" autocomplete="off">
				<input type="submit" class="search-submit" value="Search">
			</form>
		</div>
	</div>
	<?php
};

require get_template_directory() . '/template-parts/archive/archive-listing.php';

get_footer();
