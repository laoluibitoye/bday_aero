<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	static function (): void {
		register_post_type(
			'cartoons',
			array(
				'labels'      => array(
					'name'          => 'Cartoons',
					'singular_name' => 'Cartoon',
					'add_new_item'  => 'Add New Cartoon',
					'all_items'     => 'All Cartoons',
				),
				'public'      => true,
				'query_var'   => true,
				'rewrite'     => array( 'slug' => 'cartoons' ),
				'has_archive' => true,
				'hierarchical' => false,
				'supports'    => array( 'title', 'thumbnail' ),
			)
		);
	}
);
