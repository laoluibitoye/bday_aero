<?php
/**
 * Interactive past-editions grid — thumbnail cards sorted by date
 * descending, each linking to that day's single-cartoons.php view. Two
 * modes via $args: an embedded short strip ("More editions"), or a full
 * paginated browser (the archive). Bounded posts_per_page (never -1),
 * fields=>ids, no_found_rows disabled only when pagination totals are
 * actually needed, and the id list is cached so a traffic burst doesn't
 * repeat the query.
 *
 * @var array $args { limit:int, paginate:bool, exclude_id:int, heading:string }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$limit      = isset( $args['limit'] ) ? max( 1, (int) $args['limit'] ) : 12;
$paginate   = ! empty( $args['paginate'] );
$exclude_id = isset( $args['exclude_id'] ) ? (int) $args['exclude_id'] : 0;
$heading    = $args['heading'] ?? 'Past Editions';

$paged = 1;
if ( $paginate ) {
	$paged = get_query_var( 'paged' ) ? (int) get_query_var( 'paged' ) : ( get_query_var( 'page' ) ? (int) get_query_var( 'page' ) : 1 );
}

$query_args = array(
	'post_type'      => 'cartoons',
	'posts_per_page' => $limit,
	'paged'          => $paged,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'fields'         => 'ids',
	'no_found_rows'  => ! $paginate,
);
if ( $exclude_id ) {
	$query_args['post__not_in'] = array( $exclude_id );
}

$cartoon_query = Bday_Query_Cache::query( 'cartoons', md5( wp_json_encode( $query_args ) ), $query_args, 5 * MINUTE_IN_SECONDS );

if ( empty( $cartoon_query->posts ) ) {
	return;
}

_prime_post_caches( $cartoon_query->posts, false, true );
?>
<section class="bday-cartoon-grid">
	<h2 class="bday-section-heading"><?php echo esc_html( $heading ); ?></h2>
	<div class="bday-cartoon-grid__grid">
		<?php foreach ( $cartoon_query->posts as $cartoon_id ) : ?>
			<a class="bday-cartoon-grid__card" href="<?php echo esc_url( get_permalink( $cartoon_id ) ); ?>">
				<figure><?php echo bday_get_thumbnail( $cartoon_id, 'medium_rectangle' ); ?></figure>
				<span class="bday-cartoon-grid__date"><?php echo esc_html( get_the_date( '', $cartoon_id ) ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
	<?php if ( $paginate ) : ?>
		<div class="bday-pagination">
			<?php echo paginate_links( array( 'mid_size' => 2, 'total' => $cartoon_query->max_num_pages, 'next_text' => '»', 'prev_text' => '«' ) ); ?>
		</div>
	<?php endif; ?>
</section>
