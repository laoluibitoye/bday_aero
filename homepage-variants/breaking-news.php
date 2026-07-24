<?php
/**
 * Variant Name: Breaking News
 * Variant Slug: breaking-news
 * Description: Full-width single-story takeover for major breaking events — admin-forced only, never auto-selected.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data = bday_get_homepage_data();

get_template_part( 'template-parts/homepage/hero', null, array( 'data' => $data, 'layout' => 'takeover' ) );
get_template_part( 'template-parts/homepage/ticker-zone' );

echo '<div class="bday-container bday-two-col bday-two-col--rail">';
get_template_part( 'template-parts/homepage/rail', null, array( 'data' => $data ) );
get_template_part( 'template-parts/homepage/sidebar', null, array( 'data' => $data ) );
echo '</div>';
