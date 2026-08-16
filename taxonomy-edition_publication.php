<?php
/** Past editions for one publication — WordPress's native taxonomy-{taxonomy}.php convention for edition_publication (addons/editions/). */
get_header();

$term = get_queried_object();
?>
<section id="edition-archive" class="bday-container">
	<header class="bday-gallery-header">
		<span class="bday-eyebrow">E-Editions</span>
		<h1><?php echo esc_html( $term instanceof WP_Term ? $term->name : 'Past Editions' ); ?></h1>
		<p class="bday-gallery-header__dek">Browse past issues<?php echo $term instanceof WP_Term && '' !== $term->description ? esc_html( ' — ' . $term->description ) : ''; ?>.</p>
	</header>
	<?php
	if ( $term instanceof WP_Term ) {
		get_template_part(
			'template-parts/components/gallery-grid',
			null,
			array(
				'post_type'       => 'bday_edition',
				'tax_query'       => array(
					array( 'taxonomy' => 'edition_publication', 'field' => 'term_id', 'terms' => $term->term_id ),
				),
				'limit'           => 12,
				'paginate'        => true,
				'heading'         => 'All Editions',
				'cache_namespace' => 'editions',
			)
		);
	}
	?>
</section>
<?php
get_footer();
