<?php
/**
 * "Registration exceptions" (Bday_Aero_Settings::REGISTRATION_EXEMPT_TERMS)
 * — the admin-configurable category/tag list content is never asked to
 * register for, under Hybrid scope mode, regardless of the reader's own
 * free-article count. Sponsored/Partnered Content is the motivating case
 * (Editor-requested, 2026-09-28).
 *
 * Purely a term-membership check — same shape as
 * Bday_Aero_Premium_Map::terms_match(), kept as its own small class rather
 * than folded into Premium_Map since it answers a different question
 * ("does this post ever need a reader to register") from what that class
 * answers ("is this post premium"). No caching of its own: called at most
 * once per request (resolve_entitlement()/maybe_count_ungated_view()), so
 * the per-post memoization Premium_Map needs (called once per row on
 * wp-admin list screens) doesn't apply here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Registration_Exemptions {

	public static function matches( int $post_id ): bool {
		$taxonomy_term_map = Bday_Aero_Settings::registration_exempt_terms();
		if ( empty( $taxonomy_term_map ) ) {
			return false;
		}

		$post_type              = get_post_type( $post_id );
		$applicable_taxonomies  = $post_type ? get_object_taxonomies( $post_type ) : array();

		foreach ( $taxonomy_term_map as $taxonomy => $term_ids ) {
			if ( empty( $term_ids ) || ! in_array( $taxonomy, $applicable_taxonomies, true ) ) {
				continue;
			}
			$post_term_ids = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $post_term_ids ) ) {
				continue;
			}
			if ( count( array_intersect( $term_ids, $post_term_ids ) ) > 0 ) {
				return true;
			}
		}

		return false;
	}
}
