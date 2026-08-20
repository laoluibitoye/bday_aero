<?php
/**
 * Narrows wp-admin's post-list search (edit.php?s=...) to post_title only.
 *
 * Reader-reported: editors searching for articles in the wp-admin post
 * list caused a visible spike in server/RDS resource usage. WordPress
 * core's default admin search runs a leading-and-trailing-wildcard
 * `LIKE '%term%'` against post_title, post_excerpt, AND post_content on
 * every keystroke-driven search — post_content is unindexed and can be
 * large, so this table-scans the entire posts table on a site with any
 * meaningful amount of content. Editors searching by headline (the
 * overwhelmingly common case) never needed the post_content/post_excerpt
 * match at all.
 *
 * Scoped to is_admin() + the main query's own is_search() only — never
 * touches the front-end reader-facing search, which still searches the
 * full content as before.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Admin_Search_Optimizer {

	public function __construct() {
		add_filter( 'posts_search', array( $this, 'narrow_admin_search' ), 10, 2 );
	}

	public function narrow_admin_search( string $search, WP_Query $query ): string {
		if ( ! is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
			return $search;
		}

		$term = $query->get( 's' );
		if ( '' === $term ) {
			return $search;
		}

		global $wpdb;
		return $wpdb->prepare(
			" AND {$wpdb->posts}.post_title LIKE %s ",
			'%' . $wpdb->esc_like( $term ) . '%'
		);
	}
}
