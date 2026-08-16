<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	static function (): void {
		register_post_type(
			'bday_edition',
			array(
				'labels'       => array(
					'name'          => 'E-Editions',
					'singular_name' => 'Edition',
					'add_new_item'  => 'Add New Edition',
					'all_items'     => 'All Editions',
				),
				'public'       => true,
				'query_var'    => true,
				'rewrite'      => array( 'slug' => 'e-edition' ),
				'has_archive'  => true,
				'hierarchical' => false,
				'menu_icon'    => 'dashicons-media-document',
				'supports'     => array( 'title', 'thumbnail' ),
			)
		);

		register_taxonomy(
			'edition_publication',
			'bday_edition',
			array(
				'labels'            => array(
					'name'          => 'Publications',
					'singular_name' => 'Publication',
				),
				'public'            => true,
				'hierarchical'      => false,
				'show_admin_column' => true,
				// Deliberately NOT 'e-edition' (the CPT's own rewrite slug,
				// used by single edition permalinks) — found live that
				// sharing one slug between a CPT and its taxonomy causes a
				// rewrite-rule collision: /e-edition/e-paper/ matched the
				// single-post rule first (no post with that slug exists) and
				// 404'd instead of falling through to the term archive rule.
				'rewrite'           => array( 'slug' => 'e-editions' ),
			)
		);
	},
	5 // before the term-seeding hook below, which needs the taxonomy to already be registered
);

/**
 * Idempotent default-term seeding — term_exists() makes re-running this
 * on every 'init' (rather than a one-off activation hook, which themes
 * don't get the same way plugins do) safe and cheap. An editor is free to
 * rename/remove these or add further publications from wp-admin's normal
 * taxonomy screen afterward; this only ever creates what's missing.
 */
add_action(
	'init',
	static function (): void {
		$defaults = array(
			'e-paper'             => 'E-Paper',
			'she-means-business'  => 'She Means Business',
			'real-estate-digest'  => 'Real Estate Digest',
			'weekender'           => 'Weekender',
		);
		foreach ( $defaults as $slug => $label ) {
			if ( ! term_exists( $slug, 'edition_publication' ) ) {
				wp_insert_term( $label, 'edition_publication', array( 'slug' => $slug ) );
			}
		}
	},
	10
);
