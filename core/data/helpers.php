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

	// Editor-reported: 300s (5 min) meant a newly published/edited post
	// could sit invisible on cached listings (homepage sections, archives)
	// for up to 5 minutes with no way to force a refresh — none of these
	// hashed-args cache keys can be selectively invalidated on save_post
	// (see Bday_Query_Cache::forget()'s own docblock), so freshness here
	// is purely a function of this TTL. 60s still meaningfully absorbs
	// repeated hits within any given traffic burst, just without the
	// multi-minute editorial lag.
	$ttl = isset( $args['cache_ttl'] ) ? (int) $args['cache_ttl'] : MINUTE_IN_SECONDS;
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
