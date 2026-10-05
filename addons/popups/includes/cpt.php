<?php
/**
 * The 'bday_popup' post type — one post per popup. Deliberately
 * `public => false` with `show_ui`/`show_in_rest` forced on: a popup has
 * no reader-facing single page or archive of its own (it only ever
 * renders as an overlay on *other* pages, per its own display rules), but
 * it still needs a normal wp-admin list screen and the block editor for
 * its body (see this addon's docblock on why the post content editor
 * doubles as the "content builder" rather than a bespoke one).
 *
 * post_status IS the activate/deactivate switch — Published shows (subject
 * to its display rules/trigger/device target), Draft never renders,
 * reusing WordPress's own Publish/Draft UI (list table status column,
 * Quick Edit, bulk actions) instead of inventing a parallel toggle.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	static function (): void {
		register_post_type(
			'bday_popup',
			array(
				'labels'              => array(
					'name'          => 'Popups',
					'singular_name' => 'Popup',
					'add_new_item'  => 'Add New Popup',
					'edit_item'     => 'Edit Popup',
					'all_items'     => 'All Popups',
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => true,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'menu_icon'           => 'dashicons-megaphone',
				'menu_position'       => 28,
				'supports'            => array( 'title', 'editor', 'thumbnail' ),
			)
		);
	}
);
