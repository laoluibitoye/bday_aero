<?php
/**
 * Shared masonry gallery grid (Deep Dive §15's "Shared Gallery
 * component," generalized in the e-editions build-out): thumbnail cards
 * sorted by date descending, each carrying a machine-readable publish
 * date for the archive-window lock overlay (Phase 10/11's entitlement
 * primitive). Originally cartoons-only (addons/cartoons/template-parts/
 * past-editions-grid.php); that file is now a thin wrapper over this one
 * so its two existing call sites (the homepage strip, the cartoon
 * archive) are unaffected. addons/editions/'s past-editions archive uses
 * this directly.
 *
 * @var array $args {
 *   post_type:string, limit:int, paginate:bool, exclude_id:int,
 *   heading:string, tax_query:array, cache_namespace:string,
 *   grid_class:string (CSS class on the outer <section>, default
 *     'bday-gallery-grid' — cartoons passes 'bday-cartoon-grid' to keep
 *     its already-verified styling untouched; components/_gallery.scss
 *     styles both class names identically)
 * }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_type       = $args['post_type'] ?? 'post';
$limit           = isset( $args['limit'] ) ? max( 1, (int) $args['limit'] ) : 12;
$paginate        = ! empty( $args['paginate'] );
$exclude_id      = isset( $args['exclude_id'] ) ? (int) $args['exclude_id'] : 0;
$heading         = $args['heading'] ?? 'Past Editions';
$tax_query       = $args['tax_query'] ?? array();
$cache_namespace = $args['cache_namespace'] ?? 'gallery';
$grid_class      = $args['grid_class'] ?? 'bday-gallery-grid';

$paged = 1;
if ( $paginate ) {
	$paged = get_query_var( 'paged' ) ? (int) get_query_var( 'paged' ) : ( get_query_var( 'page' ) ? (int) get_query_var( 'page' ) : 1 );
}

$query_args = array(
	'post_type'      => $post_type,
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
if ( ! empty( $tax_query ) ) {
	$query_args['tax_query'] = $tax_query;
}

$gallery_query = Bday_Query_Cache::query( $cache_namespace, md5( wp_json_encode( $query_args ) ), $query_args, 5 * MINUTE_IN_SECONDS );

if ( empty( $gallery_query->posts ) ) {
	return;
}

_prime_post_caches( $gallery_query->posts, false, true );
?>
<section class="<?php echo esc_attr( $grid_class ); ?>">
	<h2 class="bday-section-heading"><?php echo esc_html( $heading ); ?></h2>
	<div class="<?php echo esc_attr( $grid_class ); ?>__grid" data-bd-gallery>
		<?php foreach ( $gallery_query->posts as $item_id ) : ?>
			<a class="<?php echo esc_attr( $grid_class ); ?>__card" href="<?php echo esc_url( get_permalink( $item_id ) ); ?>" data-bd-gallery-item data-bd-gallery-date="<?php echo esc_attr( get_the_date( 'c', $item_id ) ); ?>">
				<figure><?php echo bday_get_thumbnail( $item_id, 'medium_rectangle' ); ?></figure>
				<span class="<?php echo esc_attr( $grid_class ); ?>__overlay">
					<span class="<?php echo esc_attr( $grid_class ); ?>__date"><?php echo esc_html( get_the_date( '', $item_id ) ); ?></span>
				</span>
			</a>
		<?php endforeach; ?>
	</div>
	<?php if ( $paginate ) : ?>
		<div class="bday-pagination">
			<?php echo paginate_links( array( 'mid_size' => 2, 'total' => $gallery_query->max_num_pages, 'next_text' => '»', 'prev_text' => '«' ) ); ?>
		</div>
	<?php endif; ?>
</section>
