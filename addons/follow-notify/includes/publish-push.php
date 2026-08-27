<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'transition_post_status', 'bday_follow_notify_on_publish', 10, 3 );

/**
 * Fires only on an actual draft/pending/future → publish transition for a
 * standard post — not on every save (edits to an already-published post
 * don't re-notify followers) and not on other content types (cartoons,
 * events, pages) that don't carry category/tag follows the same way.
 */
function bday_follow_notify_on_publish( string $new_status, string $old_status, WP_Post $post ): void {
	if ( 'publish' === $old_status || 'publish' !== $new_status ) {
		return;
	}
	if ( 'post' !== $post->post_type ) {
		return;
	}

	// Reads the same two options Bday_Aero_Settings::api_base_url()/
	// api_key() wrap (aero_paywall_api_base_url/api_key — confirmed by
	// reading that class directly) rather than calling the class itself.
	// Found live: the class only exists when the separate aero-paywall
	// add-on is enabled (it's Default: off, and in this environment
	// enabling it fatals independently on a missing vendor/autoload.php —
	// a pre-existing, unrelated setup gap). A reader-notification feature
	// has no real reason to be hard-coupled to whether the paywall add-on
	// happens to be enabled/loadable, so this reads the connector config
	// directly and degrades to a silent no-op only if it's genuinely
	// unconfigured, same as before.
	$base_url = (string) get_option( 'aero_paywall_api_base_url', '' );
	$api_key  = (string) get_option( 'aero_paywall_api_key', '' );
	if ( '' === $base_url || '' === $api_key ) {
		return;
	}

	// Full term objects, not just ids — the admin console's category
	// breakdown needs a human-readable label (get_the_category() is the
	// full-object form of wp_get_post_categories(), keyed the same way).
	$category_terms = get_the_category( $post->ID );
	$categories     = wp_list_pluck( $category_terms, 'term_id' );
	$category_names = wp_list_pluck( $category_terms, 'name' );
	$tags           = wp_get_post_tags( $post->ID, array( 'fields' => 'ids' ) );
	if ( empty( $categories ) && empty( $tags ) ) {
		return;
	}

	$thumbnail_id = get_post_thumbnail_id( $post->ID );
	$image_url    = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'medium_rectangle' ) : '';

	wp_remote_post(
		rtrim( $base_url, '/' ) . '/connector/post-published',
		array(
			'timeout' => 5,
			'headers' => array(
				'Content-Type' => 'application/json',
				'X-Api-Key'    => $api_key,
			),
			'body'    => wp_json_encode(
				array(
					'postId'           => (string) $post->ID,
					'title'            => html_entity_decode( get_the_title( $post ) ),
					'url'              => get_permalink( $post ),
					'imageUrl'         => $image_url ?: null,
					'categoryTermIds'  => array_map( 'strval', $categories ),
					'categoryLabels'   => array_values( $category_names ),
					'tagTermIds'       => array_map( 'strval', $tags ),
					'authorId'         => (string) $post->post_author,
					'authorName'       => get_the_author_meta( 'display_name', $post->post_author ),
				)
			),
		)
	);
}
