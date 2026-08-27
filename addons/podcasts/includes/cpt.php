<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	static function (): void {
		register_post_type(
			'podcast',
			array(
				'labels'       => array(
					'name'          => 'Podcast Episodes',
					'singular_name' => 'Episode',
					'add_new_item'  => 'Add New Episode',
					'all_items'     => 'All Episodes',
				),
				'public'       => true,
				'query_var'    => true,
				'rewrite'      => array( 'slug' => 'podcast' ),
				'has_archive'  => true,
				'hierarchical' => false,
				'menu_icon'    => 'dashicons-microphone',
				// 'editor' holds the episode's show notes — the same
				// content bday_aero_gate_content() previews/locks, exactly
				// like a standard post's body.
				'supports'     => array( 'title', 'editor', 'thumbnail' ),
			)
		);

		// Own dedicated taxonomy rather than the article category/tag
		// system — episodes group into shows/series ("The Money Show",
		// "Weekend Wrap-Up"), not article sections, so mixing them into
		// 'category' would pollute the article taxonomy and vice versa.
		// Non-hierarchical (flat, tag-entry UI in the editor sidebar),
		// same shape as addons/editions' edition_publication.
		register_taxonomy(
			'podcast_series',
			'podcast',
			array(
				'labels'            => array(
					'name'          => 'Series',
					'singular_name' => 'Series',
				),
				'public'            => true,
				'hierarchical'      => false,
				'show_admin_column' => true,
				// Deliberately NOT 'podcast' (the CPT's own rewrite slug) —
				// see edition_publication's identical note in
				// addons/editions/includes/cpt.php: sharing a slug between
				// a CPT and its taxonomy causes a rewrite-rule collision.
				'rewrite'           => array( 'slug' => 'podcast-series' ),
			)
		);
	}
);
