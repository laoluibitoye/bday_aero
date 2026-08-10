<?php
/**
 * Read-side helpers for the Sections registry. Every call is cheap: the
 * option itself is read once per request (bday_get_option_cached()), and
 * term/post lookups go through the same caching primitives as the rest of
 * the theme (get_category_by_slug() is core-cached by WP itself; post
 * lookups go through Bday_Query_Cache via bday_get_posts()).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @return array<int, array{key: string, label: string, taxonomy: string, term_slug: string}> */
function bday_sections(): array {
	$sections = bday_get_option_cached( 'bday_sections', array() );
	return is_array( $sections ) ? $sections : array();
}

/** @return array{key: string, label: string, taxonomy: string, term_slug: string}|null */
function bday_section( string $key ): ?array {
	foreach ( bday_sections() as $section ) {
		if ( ( $section['key'] ?? '' ) === $key ) {
			return $section;
		}
	}
	return null;
}

function bday_section_label( string $key ): string {
	$section = bday_section( $key );
	return $section['label'] ?? $key;
}

/** '#' when the key is unknown or its mapped term doesn't exist (never fatal, matches bday_category_url()'s existing fallback). */
function bday_section_url( string $key ): string {
	$section = bday_section( $key );
	if ( null === $section || '' === ( $section['term_slug'] ?? '' ) ) {
		return '#';
	}

	$term = get_term_by( 'slug', $section['term_slug'], $section['taxonomy'] ?? 'category' );
	if ( ! $term || is_wp_error( $term ) ) {
		return '#';
	}

	$link = get_term_link( $term );
	return is_wp_error( $link ) ? '#' : (string) $link;
}

/** @return WP_Post[] */
function bday_section_posts( string $key, int $count = 6 ): array {
	$section = bday_section( $key );
	if ( null === $section || '' === ( $section['term_slug'] ?? '' ) ) {
		return array();
	}

	if ( 'category' === ( $section['taxonomy'] ?? 'category' ) ) {
		return bday_get_posts(
			array(
				'category_name'   => $section['term_slug'],
				'numberposts'     => $count,
				'cache_namespace' => 'sections',
			)
		);
	}

	return bday_get_posts(
		array(
			'tax_query'       => array(
				array(
					'taxonomy' => $section['taxonomy'],
					'field'    => 'slug',
					'terms'    => $section['term_slug'],
				),
			),
			'numberposts'     => $count,
			'cache_namespace' => 'sections',
		)
	);
}
