<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'transition_post_status', 'bday_editions_on_publish', 10, 3 );

/**
 * Fires only on an actual draft/pending/future → publish transition
 * (never on every save — same reasoning as addons/follow-notify's
 * identically-shaped hook), and only pushes when both a publication term
 * and the PDF object key are actually set — an edition an editor is still
 * assembling shouldn't sync a broken/empty mapping.
 */
function bday_editions_on_publish( string $new_status, string $old_status, WP_Post $post ): void {
	if ( 'publish' === $old_status || 'publish' !== $new_status ) {
		return;
	}
	if ( 'bday_edition' !== $post->post_type ) {
		return;
	}

	$object_key = get_post_meta( $post->ID, '_bday_edition_object_key', true );
	if ( '' === $object_key ) {
		return;
	}

	$terms = wp_get_post_terms( $post->ID, 'edition_publication', array( 'fields' => 'slugs' ) );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return;
	}
	$publication = $terms[0];

	// Same directly-read-options pattern as addons/follow-notify (Phase 7)
	// — reads the connector config regardless of whether the separate,
	// Default:-off aero-paywall add-on happens to be enabled.
	$base_url = (string) get_option( 'aero_paywall_api_base_url', '' );
	$api_key  = (string) get_option( 'aero_paywall_api_key', '' );
	if ( '' === $base_url || '' === $api_key ) {
		return;
	}

	wp_remote_post(
		rtrim( $base_url, '/' ) . '/connector/edition-sync',
		array(
			'timeout' => 5,
			'headers' => array(
				'Content-Type' => 'application/json',
				'X-Api-Key'    => $api_key,
			),
			'body'    => wp_json_encode(
				array(
					'publication' => $publication,
					'date'        => get_the_date( 'c', $post ),
					'objectKey'   => $object_key,
				)
			),
		)
	);
}
