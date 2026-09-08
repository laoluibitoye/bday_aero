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

// header.php already fires the bday_header_ticker_zone action
// unconditionally on every page (including this one) — calling
// ticker-zone here too double-rendered the ticker/Live Match widget
// whenever this variant is admin-forced on.
get_template_part( 'template-parts/homepage/hero', null, array( 'data' => $data, 'layout' => 'takeover' ) );

echo '<div class="bday-container bday-two-col bday-two-col--rail">';
get_template_part( 'template-parts/homepage/rail', null, array( 'data' => $data ) );
get_template_part( 'template-parts/homepage/sidebar', null, array( 'data' => $data ) );
echo '</div>';
