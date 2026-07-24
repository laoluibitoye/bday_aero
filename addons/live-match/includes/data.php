<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @return WP_Post[] */
function bday_live_match_get_current(): array {
	$settings = get_option( 'bday_addon_live_match', array() );
	if ( empty( $settings['enabled'] ) ) {
		return array(); // this add-on's own toggle — checked before anything else
	}

	$ttl   = max( 30, (int) ( $settings['cache_ttl'] ?? 60 ) );
	$limit = max( 1, (int) ( $settings['max_matches'] ?? 5 ) );

	return Bday_Query_Cache::posts(
		'live_match',
		'current',
		array(
			'post_type'      => 'live_match',
			'posts_per_page' => $limit,
			'no_found_rows'  => true, // a ticker never needs pagination totals
		),
		$ttl
	);
}
