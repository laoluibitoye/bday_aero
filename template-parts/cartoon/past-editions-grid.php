<?php
/**
 * Interactive past-editions grid — thumbnail cards sorted by date
 * descending, each linking to that day's single-cartoons.php view.
 *
 * Two modes, both driven by the $args this is called with:
 * - Embedded ("More editions" on single-cartoons.php): a short, unpaginated
 *   strip excluding the cartoon currently being viewed.
 * - Full browser (archive-cartoons.php): paginated, reads ?paged= from the
 *   URL like any other archive.
 *
 * Resource-constraint rules applied (300k+ article table, limited server
 * capacity — see the theme-wide hard rail): bounded posts_per_page (never
 * -1), 'fields' => 'ids' since the grid only needs id + thumbnail (not full
 * post objects), no_found_rows disabled only when pagination totals are
 * actually needed, and the whole id list is cached via
 * bday_get_cached_posts() so a traffic burst doesn't repeat this query.
 *
 * @var array $args {
 *     @type int    $limit      Cards per page. Default 12.
 *     @type bool   $paginate   Show pagination + read ?paged=. Default false.
 *     @type int    $exclude_id Post ID to exclude (the one already showing). Default 0.
 *     @type string $heading    Section heading text. Default 'Past Editions'.
 * }
 */

$limit      = isset( $args['limit'] ) ? max( 1, (int) $args['limit'] ) : 12;
$paginate   = ! empty( $args['paginate'] );
$exclude_id = isset( $args['exclude_id'] ) ? (int) $args['exclude_id'] : 0;
$heading    = $args['heading'] ?? 'Past Editions';

$paged = 1;
if ( $paginate ) {
	$paged = get_query_var( 'paged' ) ? (int) get_query_var( 'paged' ) : ( get_query_var( 'page' ) ? (int) get_query_var( 'page' ) : 1 );
}

$query_args = [
	'post_type'      => 'cartoons',
	'posts_per_page' => $limit,
	'paged'          => $paged,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'fields'         => 'ids',
	'no_found_rows'  => ! $paginate,
];
if ( $exclude_id ) {
	$query_args['post__not_in'] = [ $exclude_id ];
}

$cache_key = 'bday_cartoon_grid_' . md5( wp_json_encode( $query_args ) );
$cartoon_query = get_transient( $cache_key );

if ( false === $cartoon_query ) {
	$cartoon_query = new WP_Query( $query_args );
	// Cache the whole WP_Query result object (ids + pagination totals) —
	// short TTL since new cartoons publish daily and editors expect same-day
	// visibility, but still long enough to absorb a traffic burst.
	set_transient( $cache_key, $cartoon_query, 5 * MINUTE_IN_SECONDS );
}

if ( empty( $cartoon_query->posts ) ) {
	return;
}

// Priming the post-object cache in one batched query for this page's ids —
// avoids an N+1 (one query per card) when the loop below calls
// get_the_title()/get_the_date()/thumbnail lookups per id.
_prime_post_caches( $cartoon_query->posts, false, true );
?>
<section class="cartoon-past-editions">
	<div class="section-heading">
		<span><?= esc_html( $heading ) ?></span>
	</div>
	<div class="cartoon-past-editions__grid">
		<?php foreach ( $cartoon_query->posts as $cartoon_id ) : ?>
			<a class="cartoon-past-editions__card" href="<?= esc_url( get_permalink( $cartoon_id ) ) ?>">
				<figure>
					<?= get_thumbnail( [ 'post_id' => $cartoon_id, 'size' => 'medium_rectangle', 'classes' => 'cartoon-past-editions__thumb' ] ) ?>
				</figure>
				<span class="cartoon-past-editions__date"><?= esc_html( get_the_date( '', $cartoon_id ) ) ?></span>
			</a>
		<?php endforeach; ?>
	</div>

	<?php if ( $paginate ) : ?>
		<div class="pagination">
			<?php echo paginate_links( [ 'mid_size' => 2, 'total' => $cartoon_query->max_num_pages, 'next_text' => '»', 'prev_text' => '«' ] ); ?>
		</div>
	<?php endif; ?>
</section>
