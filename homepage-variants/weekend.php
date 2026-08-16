<?php
/**
 * Variant Name: Weekend
 * Variant Slug: weekend
 * Description: Saturday/Sunday front page — leads with the magazine/e-edition content instead of the daily news grid.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data = bday_get_homepage_data();

get_template_part( 'template-parts/homepage/leaderboard-zone' );
get_template_part( 'template-parts/homepage/hero', null, array( 'data' => $data, 'layout' => 'stacked' ) );

echo '<section class="bday-weekend-magazine"><div class="bday-container">';
echo '<h2 class="bday-section-heading">This Weekend</h2><div class="bday-card-grid bday-card-grid--large">';
foreach ( array( $data['weekender'], $data['womens_hub'], $data['reports'] ) as $group ) {
	foreach ( $group as $post ) {
		echo bday_card_html( $post, array( 'size' => 'pdf_thumbnail' ) );
	}
}
echo '</div></div></section>';

get_template_part( 'template-parts/homepage/carousel-zone' );

echo '<div class="bday-container bday-two-col bday-two-col--rail">';
get_template_part( 'template-parts/homepage/rail', null, array( 'data' => $data ) );
get_template_part( 'template-parts/homepage/sidebar', null, array( 'data' => $data ) );
echo '</div>';

get_template_part( 'template-parts/homepage/bottom-widgets', null, array( 'data' => $data ) );
