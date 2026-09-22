<?php
/**
 * Thin, purpose-named query helpers built on Bday_Query_Cache — every
 * add-on's data.php should call these (or Bday_Query_Cache directly for a
 * shape these don't cover) rather than calling get_posts()/WP_Query itself.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The theme's general-purpose cached post fetch — replaces the old
 * custom_get_posts()/bday_get_cached_posts() pair with one function. Pass a
 * 'cache_ttl' key in $args to override the default TTL; it's stripped
 * before reaching get_posts().
 *
 * @return WP_Post[]
 */
function bday_get_posts( array $args = array() ): array {
	$defaults = array(
		'numberposts'      => 5,
		'orderby'          => 'date',
		'order'            => 'DESC',
		'post_type'        => 'post',
		'suppress_filters' => true,
	);

	$args = wp_parse_args( $args, $defaults );

	// Freshness on publish/update is now handled by save_post bumping the
	// namespace's generation (class-query-cache.php), not by a short TTL —
	// a hashed-args key here can be invalidated the moment relevant
	// content changes, generation and all, without needing to know the
	// specific key in advance. That resolved the original 60s-default
	// trade-off (editorial staleness vs. read-traffic recompute cost): a
	// 60s TTL under real reader traffic was a top contributor to an RDS
	// resource-spike incident (2026-09-22), recomputing several of these
	// listings dozens of times a minute. TTL is now a safety-net expiry,
	// not the freshness mechanism, so it can be much longer.
	$ttl = isset( $args['cache_ttl'] ) ? (int) $args['cache_ttl'] : 10 * MINUTE_IN_SECONDS;
	unset( $args['cache_ttl'] );

	$namespace = isset( $args['cache_namespace'] ) ? (string) $args['cache_namespace'] : 'core';
	unset( $args['cache_namespace'] );

	$key = md5( wp_json_encode( $args ) );

	return Bday_Query_Cache::posts( $namespace, $key, $args, $ttl );
}

/** Reads a WP-local option once per request into a static cache. */
function bday_get_option_cached( string $option, $default = false ) {
	static $cache = array();
	if ( ! array_key_exists( $option, $cache ) ) {
		$cache[ $option ] = get_option( $option, $default );
	}
	return $cache[ $option ];
}
