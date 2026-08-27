<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	static function (): void {
		register_post_type(
			'bday_video',
			array(
				'labels'       => array(
					'name'          => 'Videos',
					'singular_name' => 'Video',
					'add_new_item'  => 'Add New Video',
					'all_items'     => 'All Videos',
				),
				'public'       => true,
				'query_var'    => true,
				'rewrite'      => array( 'slug' => 'video' ),
				'has_archive'  => true,
				'hierarchical' => false,
				'menu_icon'    => 'dashicons-video-alt3',
				// 'editor' holds the optional description/body shown below
				// the embed — same role as a standard post's body, so
				// bday_aero_gate_content() previews/locks it identically.
				'supports'     => array( 'title', 'editor', 'thumbnail' ),
			)
		);

		// Own dedicated taxonomy rather than the article category/tag
		// system — videos group into playlists ("2026 Election Explainers",
		// "Weekender Highlights"), not article sections. Non-hierarchical
		// (flat, tag-entry UI in the editor sidebar), same shape as
		// addons/editions' edition_publication and addons/podcasts'
		// podcast_series.
		register_taxonomy(
			'video_playlist',
			'bday_video',
			array(
				'labels'            => array(
					'name'          => 'Playlists',
					'singular_name' => 'Playlist',
				),
				'public'            => true,
				'hierarchical'      => false,
				'show_admin_column' => true,
				// Deliberately NOT 'video' (the CPT's own rewrite slug) —
				// see edition_publication's identical note in
				// addons/editions/includes/cpt.php.
				'rewrite'           => array( 'slug' => 'video-playlists' ),
			)
		);
	}
);
