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
}

$results = Bday_Query_Cache::query( 'listing', md5( wp_json_encode( $query_args ) ), $query_args, 300 );
?>
<div class="bday-container">
	<?php if ( $results->have_posts() ) : ?>
		<div class="bday-card-grid">
			<?php while ( $results->have_posts() ) : $results->the_post(); ?>
				<article class="bday-card">
					<a href="<?php the_permalink(); ?>" class="bday-card__media"><?php echo bday_get_thumbnail( get_the_ID(), 'medium_rectangle' ); ?></a>
					<h3 class="bday-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
					<div class="bday-byline">
						<span><?php the_author(); ?></span>
						<span><?php echo esc_html( bday_time_ago( get_the_date( 'c' ) ) ); ?></span>
					</div>
					<p class="bday-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></p>
				</article>
				<?php if ( 0 === ( $results->current_post + 1 ) % 6 ) : ?>
					<?php bday_ad_zone( 'below_article_recirculation' ); ?>
				<?php endif; ?>
			<?php endwhile; ?>
		</div>
		<div class="bday-pagination">
			<?php echo paginate_links( array( 'total' => $results->max_num_pages, 'current' => $paged, 'mid_size' => 2, 'prev_text' => '«', 'next_text' => '»' ) ); ?>
		</div>
		<?php wp_reset_postdata(); ?>
	<?php else : ?>
		<p>No posts found.</p>
	<?php endif; ?>
</div>
