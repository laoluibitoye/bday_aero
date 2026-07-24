<?php
/**
 * Post dispatcher: e-edition-category posts get the PDF-viewer template,
 * everything else gets the standard article template. The previous version
 * had a real bug here — the e-edition branch only wrapped the PDF include,
 * then unconditionally rendered the default article template too, so an
 * e-edition post showed both templates back-to-back. Fixed: one or the
 * other, never both.
 */
get_header();

if ( have_posts() ) :
	the_post();

	$cats = wp_get_post_categories( get_the_ID(), array( 'fields' => 'slugs' ) );
	if ( in_array( 'e-edition', $cats, true ) && Bday_Addon_Loader::is_enabled( 'e-edition' ) ) {
		get_template_part( 'template-parts/single', 'edition' );
	} else {
		get_template_part( 'template-parts/single', 'default' );
	}
endif;

get_footer();
