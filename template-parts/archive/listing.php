<?php
/**
 * Shared listing partial for archive.php/tag.php/author.php/search.php and
 * every category-*.php template — one cached, paginated post grid instead
 * of each template hand-rolling its own query.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_query;
$paged = max( 1, get_query_var( 'paged' ) ?: get_query_var( 'page' ) ?: 1 );

$query_args = array(
	'post_type'      => 'post',
	'posts_per_page' => 12,
	'paged'          => $paged,
);
if ( is_category() || is_tag() || is_tax() ) {
	$term = get_queried_object();
	if ( $term instanceof WP_Term ) {
		$query_args['tax_query'] = array( array( 'taxonomy' => $term->taxonomy, 'field' => 'term_id', 'terms' => $term->term_id ) );
	}
} elseif ( is_author() ) {
	$query_args['author'] = get_queried_object_id();
} elseif ( is_search() ) {
	$query_args['s'] = get_search_query();
} elseif ( is_post_type_archive() ) {
	/**
	 * Phase 9: this partial is also WordPress's fallback for any custom
	 * post type archive with no dedicated archive-{post_type}.php of its
	 * own (e.g. the new addons/podcasts/ CPT) — found while adding that
	 * CPT that the post_type was hardcoded to 'post' above, so a CPT
	 * archive page would have silently listed regular blog posts instead
	 * of its own content. Category/tag/author/search behavior is
	 * untouched — this only takes effect for a genuine post-type archive.
	 */
	$query_args['post_type'] = get_query_var( 'post_type' );
}

$results = Bday_Query_Cache::query( 'listing', md5( wp_json_encode( $query_args ) ), $query_args, 300 );
?>
<div class="bday-container">
	<?php if ( $results->have_posts() ) : ?>
		<div class="bday-card-grid">
			<?php while ( $results->have_posts() ) : $results->the_post(); ?>
				<?php echo bday_card_html( get_post(), array( 'show_byline' => true, 'show_excerpt' => true ) ); ?>
				<?php if ( 0 === ( $results->current_post + 1 ) % 6 ) : ?>
					<?php bday_ad_zone( 'below_article_recirculation' ); ?>
				<?php endif; ?>
			<?php endwhile; ?>
		</div>
		<div class="bday-pagination">
			<?php bday_render_load_more_button( '.bday-card-grid', $paged, $results->max_num_pages ); ?>
		</div>
		<?php wp_reset_postdata(); ?>
	<?php else : ?>
		<p>No posts found.</p>
	<?php endif; ?>
</div>
