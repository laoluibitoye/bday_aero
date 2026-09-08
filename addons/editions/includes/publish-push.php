<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'transition_post_status', 'bday_editions_on_publish', 10, 3 );

/**
 * Fires on a draft/pending/future → publish transition (same reasoning as
 * addons/follow-notify's identically-shaped hook) — and also on a plain
 * save of an already-published edition, which matters now that the PDF
 * metabox supports re-uploading a replacement file (Phase: wp-admin PDF
 * upload): without this second case, correcting a bad PDF on a live
 * edition would silently never reach subscription-service, and readers
 * would keep getting signed URLs for the old object key.
 *
 * NOTE: this alone is not sufficient for a brand-new edition that's
 * uploaded-and-published in one click — wp_insert_post() fires
 * transition_post_status (this hook) *before* save_post_{post_type}
 * (metabox.php's save_post_bday_edition, which is what actually uploads
 * the file and sets _bday_edition_object_key), so on that very first
 * publish the object key isn't set yet and this function returns early
 * having synced nothing. metabox.php's save handler calls
 * bday_edition_sync_to_subscription_service() directly, right after
 * setting the object key, to cover exactly that case — this hook remains
 * for the republish-without-touching-the-metabox path (e.g. re-assigning
 * the publication term alone).
 */
function bday_editions_on_publish( string $new_status, string $old_status, WP_Post $post ): void {
	$is_publish_transition = 'publish' !== $old_status && 'publish' === $new_status;
	$is_republish_of_live  = 'publish' === $old_status && 'publish' === $new_status;
	if ( ! $is_publish_transition && ! $is_republish_of_live ) {
		return;
	}
	bday_edition_sync_to_subscription_service( $post );
}

/**
 * The actual /connector/edition-sync push — pulled out of
 * bday_editions_on_publish() so metabox.php's save handler can call it
 * directly right after setting the object key (see that function's
 * docblock for why the transition_post_status hook alone misses a
 * brand-new edition's first publish). Safe to call unconditionally on any
 * save; no-ops quietly if the post isn't a published bday_edition or is
 * still missing an object key/publication term.
 */
function bday_edition_sync_to_subscription_service( WP_Post $post ): void {
	if ( 'bday_edition' !== $post->post_type || 'publish' !== $post->post_status ) {
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
		// Unlike the missing-object-key/missing-terms returns above (both
		// legitimate "still assembling this edition" states, not errors),
		// a missing connector connection is a real setup problem — surface
		// it, since otherwise a reader hits "No edition exists for that
		// date" on subscription-service with nothing in wp-admin
		// explaining why the sync that would have prevented it never ran.
		bday_edition_queue_upload_notice( 'This edition was saved, but could not be synced to the archive service — the Aero Paywall API connection is not configured. Readers will see "No edition exists for that date" until this is fixed and the post is saved again.' );
		return;
	}

	$response = wp_remote_post(
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
					// Plain 'Y-m-d', not 'c' (full ISO8601 with a
					// timezone offset) — a reader's own lookup constructs
					// its date the same plain way (single-bday_edition.php's
					// data-bd-edition-date, "Y-m-d") and subscription-
					// service parses it as `new Date("2026-09-08")`, which
					// JS treats as exact UTC midnight. Sending a full
					// timestamp+offset here instead gets converted to a
					// different UTC calendar day whenever the post's local
					// save time falls within the first N hours after
					// midnight (N = the site's UTC offset) — Edition.date
					// is a DATE-only column, so that one-day drift means
					// the sync silently writes a different date than
					// readers ever look up, surfacing as "No edition
					// exists for that date" despite the sync having
					// "succeeded."
					'date'        => get_the_date( 'Y-m-d', $post ),
					'objectKey'   => $object_key,
				)
			),
		)
	);

	// Previously unchecked entirely — a failed sync here (wrong API
	// key/URL, subscription-service down, a rejected payload) looked
	// identical to a successful one from wp-admin's point of view: the
	// edition post saves fine either way, and only a reader clicking
	// "Read Edition" later would discover anything was wrong, via
	// subscription-service's "No edition exists for that date" — by
	// which point there's nothing in wp-admin pointing back at the cause.
	if ( is_wp_error( $response ) ) {
		bday_edition_queue_upload_notice( 'This edition was saved, but syncing it to the archive service failed: ' . $response->get_error_message() . '. Readers will see "No edition exists for that date" until this is fixed and the post is saved again.' );
		return;
	}
	$status = wp_remote_retrieve_response_code( $response );
	if ( $status < 200 || $status >= 300 ) {
		$body    = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );
		$detail  = is_array( $decoded ) && isset( $decoded['message'] ) ? $decoded['message'] : ( '' !== $body ? $body : "HTTP {$status}" );
		bday_edition_queue_upload_notice( 'This edition was saved, but syncing it to the archive service failed (' . ( is_array( $detail ) ? wp_json_encode( $detail ) : $detail ) . '). Readers will see "No edition exists for that date" until this is fixed and the post is saved again.' );
	}
}
