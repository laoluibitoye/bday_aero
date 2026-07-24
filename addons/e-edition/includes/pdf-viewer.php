<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Shared PDF embed — previously duplicated between single-edition and the todays-epaper page template. */
function bday_render_pdf_viewer( string $preview_url ): void {
	if ( '' === $preview_url ) {
		return;
	}
	if ( function_exists( 'amp_is_request' ) && amp_is_request() ) {
		printf( '<amp-google-document-embed src="%s" width="600" height="800" layout="responsive" type="application/pdf"></amp-google-document-embed>', esc_url( $preview_url ) );
		return;
	}
	?>
	<object data="<?php echo esc_url( $preview_url ); ?>" type="application/pdf" width="100%" height="800px">
		<iframe src="<?php echo esc_url( $preview_url ); ?>" width="100%" height="800px"><p>This browser does not support PDF viewing.</p></iframe>
	</object>
	<?php
}

/** Cached, paginated grid of e-edition PDF thumbnails — the previous version had an undefined $paged (page 2 re-rendered page 1) and no caching at all. */
function bday_render_e_edition_grid(): void {
	$paged = max( 1, (int) ( get_query_var( 'paged' ) ?: get_query_var( 'page' ) ?: 1 ) );
	$args  = array(
		'category_name'  => 'e-paper',
		'post_type'      => 'post',
		'posts_per_page' => 15,
		'paged'          => $paged,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);

	$results = Bday_Query_Cache::query( 'e_edition', md5( wp_json_encode( $args ) ), $args, 300 );
	?>
	<div class="bday-container">
		<div class="bday-card-grid">
			<?php foreach ( $results->posts as $post ) : ?>
				<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="bday-card bday-card--pdf">
					<?php echo bday_get_thumbnail( $post->ID, 'pdf_thumbnail' ); ?>
				</a>
			<?php endforeach; ?>
		</div>
		<div class="bday-pagination">
			<?php echo paginate_links( array( 'mid_size' => 2, 'total' => $results->max_num_pages, 'next_text' => '»', 'prev_text' => '«' ) ); ?>
		</div>
	</div>
	<?php
}
