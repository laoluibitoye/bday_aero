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

// header.php already fires the bday_homepage_leaderboard_zone action
// unconditionally on every page (including this one) — calling
// leaderboard-zone here too double-rendered the leaderboard widget on
// every Saturday/Sunday.
get_template_part( 'template-parts/homepage/hero', null, array( 'data' => $data, 'layout' => 'stacked' ) );

$weekend_magazine_posts = array_merge( $data['weekender'] ?? array(), $data['womens_hub'] ?? array(), $data['reports'] ?? array() );
if ( ! empty( $weekend_magazine_posts ) ) {
	echo '<section class="bday-weekend-magazine"><div class="bday-container">';
	echo '<h2 class="bday-section-heading">This Weekend</h2><div class="bday-card-grid bday-card-grid--large">';
	foreach ( $weekend_magazine_posts as $post ) {
		echo bday_card_html( $post, array( 'size' => 'pdf_thumbnail' ) );
	}
	echo '</div></div></section>';
}

get_template_part( 'template-parts/homepage/carousel-zone' );

echo '<div class="bday-container bday-two-col bday-two-col--rail">';
get_template_part( 'template-parts/homepage/rail', null, array( 'data' => $data ) );
get_template_part( 'template-parts/homepage/sidebar', null, array( 'data' => $data ) );
echo '</div>';

get_template_part( 'template-parts/homepage/bottom-widgets', null, array( 'data' => $data ) );
