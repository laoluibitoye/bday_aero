<?php
/** All videos in one playlist — WordPress's native taxonomy-{taxonomy}.php convention for video_playlist (addons/videos/). */
get_header();

$term = get_queried_object();
?>
<section id="video-playlist-archive" class="bday-container">
	<header class="bday-gallery-header">
		<span class="bday-eyebrow">Videos</span>
		<h1><?php echo esc_html( $term instanceof WP_Term ? $term->name : 'Playlist' ); ?></h1>
		<p class="bday-gallery-header__dek">Browse this playlist<?php echo $term instanceof WP_Term && '' !== $term->description ? esc_html( ' — ' . $term->description ) : ''; ?>.</p>
	</header>
	<?php
	if ( $term instanceof WP_Term ) {
		get_template_part(
			'template-parts/components/gallery-grid',
			null,
			array(
				'post_type'       => 'bday_video',
				'tax_query'       => array(
					array( 'taxonomy' => 'video_playlist', 'field' => 'term_id', 'terms' => $term->term_id ),
				),
				'limit'           => 12,
				'paginate'        => true,
				'heading'         => 'All Videos',
				'cache_namespace' => 'videos',
			)
		);
	}
	?>
</section>
<?php
get_footer();
