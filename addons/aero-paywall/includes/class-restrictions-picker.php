<?php
/**
 * Shared post-type/taxonomy/term enumeration for the React admin app's
 * Restrictions tab bootstrap payload (class-admin-ui.php's
 * build_bootstrap_data()) — plain data, not pre-rendered HTML, so the
 * client-side rendering can't drift between two hand-rolled
 * implementations.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Restrictions_Picker {

	/** @return array<int, array{slug: string, label: string}> */
	public static function get_public_post_types(): array {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		$result = array();
		foreach ( $post_types as $post_type ) {
			if ( 'attachment' === $post_type->name ) {
				continue; // media isn't a content type a paywall makes sense on
			}
			$result[] = array( 'slug' => $post_type->name, 'label' => $post_type->label );
		}
		return $result;
	}

	/**
	 * One entry per taxonomy attached to at least one of the given post
	 * types.
	 *
	 * @param string[] $post_types
	 * @return array<int, array{slug: string, label: string, terms: array<int, array{id: int, name: string}>}>
	 */
	public static function get_taxonomies_for_post_types( array $post_types ): array {
		$taxonomy_names = array();
		foreach ( $post_types as $post_type ) {
			$taxonomy_names = array_merge( $taxonomy_names, get_object_taxonomies( $post_type ) );
		}
		$taxonomy_names = array_unique( $taxonomy_names );

		$result = array();
		foreach ( $taxonomy_names as $taxonomy_name ) {
			$taxonomy = get_taxonomy( $taxonomy_name );
			if ( ! $taxonomy || ! $taxonomy->public ) {
				continue;
			}

			$terms = get_terms( array( 'taxonomy' => $taxonomy_name, 'hide_empty' => false ) );
			if ( is_wp_error( $terms ) ) {
				continue;
			}

			$result[] = array(
				'slug'  => $taxonomy_name,
				'label' => $taxonomy->label,
				'terms' => array_map(
					static fn( $term ): array => array( 'id' => (int) $term->term_id, 'name' => (string) $term->name ),
					$terms
				),
			);
		}
		return $result;
	}
}
