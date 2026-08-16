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
				'taxonomies'   => array( 'category', 'post_tag' ),
			)
		);
	}
);
