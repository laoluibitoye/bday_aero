<?php
/** All episodes in one series — WordPress's native taxonomy-{taxonomy}.php convention for podcast_series (addons/podcasts/). */
get_header();

$term = get_queried_object();
?>
<section id="podcast-series-archive" class="bday-container">
	<header class="bday-gallery-header">
		<span class="bday-eyebrow">Podcasts</span>
		<h1><?php echo esc_html( $term instanceof WP_Term ? $term->name : 'Series' ); ?></h1>
		<p class="bday-gallery-header__dek">Browse this series<?php echo $term instanceof WP_Term && '' !== $term->description ? esc_html( ' — ' . $term->description ) : ''; ?>.</p>
	</header>
	<?php
	if ( $term instanceof WP_Term ) {
		get_template_part(
			'template-parts/components/gallery-grid',
			null,
			array(
				'post_type'       => 'podcast',
				'tax_query'       => array(
					array( 'taxonomy' => 'podcast_series', 'field' => 'term_id', 'terms' => $term->term_id ),
				),
				'limit'           => 12,
				'paginate'        => true,
				'heading'         => 'All Episodes',
				'cache_namespace' => 'podcasts',
			)
		);
	}
	?>
</section>
<?php
get_footer();
