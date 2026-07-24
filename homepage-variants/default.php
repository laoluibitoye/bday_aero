<?php
/**
 * Variant Name: Default
 * Variant Slug: default
 * Description: The standard weekday front page — top news, lead story, recent, then a full news grid.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data = bday_get_homepage_data();
get_template_part( 'template-parts/homepage/hero', null, array( 'data' => $data, 'layout' => 'split' ) );
get_template_part( 'template-parts/homepage/carousel-zone' );

echo '<div class="bday-container bday-two-col bday-two-col--rail">';
get_template_part( 'template-parts/homepage/rail', null, array( 'data' => $data ) );
get_template_part( 'template-parts/homepage/sidebar', null, array( 'data' => $data ) );
echo '</div>';

get_template_part( 'template-parts/homepage/bottom-widgets', null, array( 'data' => $data ) );
