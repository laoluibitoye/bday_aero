<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Generous but bounded — full newspaper-edition PDFs run much larger than
// a typical image upload. Must not exceed the raw-body limit configured on
// the receiving end (subscription-service's main.ts, edition-pdf route).
const BDAY_EDITION_PDF_MAX_BYTES = 75 * 1024 * 1024;

add_action(
	'add_meta_boxes',
	static function (): void {
		add_meta_box( 'bday-edition-pdf', 'Edition PDF', 'bday_edition_pdf_metabox', 'bday_edition', 'side' );
	}
);

function bday_edition_pdf_metabox( WP_Post $post ): void {
	wp_nonce_field( 'bday_edition_pdf', 'bday_edition_pdf_nonce' );
	printf(
		'<p><label for="bday-edition-pdf-file">Upload a new PDF:</label><br><input type="file" id="bday-edition-pdf-file" name="edition_pdf_file" accept="application/pdf" class="widefat" /></p>' .
		'<p class="description">Uploads straight to the archive storage platform — WordPress never keeps a copy. Leave empty to keep the current file.</p>'
	);
	printf(
		'<p><label for="bday-edition-object-key">...or paste an existing object key/path:</label><br><input type="text" id="bday-edition-object-key" name="object_key" value="%s" class="widefat" placeholder="editions/e-paper/2026-08-14.pdf" /></p>' .
		'<p class="description">Only used if no file is uploaded above. This is only ever read server-side, signed into a short-lived download link when an entitled reader requests it.</p>',
		esc_attr( get_post_meta( $post->ID, '_bday_edition_object_key', true ) )
	);
}

add_action(
	'save_post_bday_edition',
	static function ( int $post_id ): void {
		if ( ! isset( $_POST['bday_edition_pdf_nonce'] ) || ! wp_verify_nonce( $_POST['bday_edition_pdf_nonce'], 'bday_edition_pdf' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$uploaded_key = bday_edition_upload_pdf_if_present( $post_id );
		if ( null !== $uploaded_key ) {
			update_post_meta( $post_id, '_bday_edition_object_key', $uploaded_key );
			return;
		}

		if ( isset( $_POST['object_key'] ) ) {
			update_post_meta( $post_id, '_bday_edition_object_key', sanitize_text_field( wp_unslash( $_POST['object_key'] ) ) );
		}
	}
);

/**
 * Streams $_FILES['edition_pdf_file'] straight through to
 * subscription-service's /connector/edition-pdf (which writes it to the
 * archive bucket and mints the object key), so the PDF is never written
 * anywhere in WordPress itself — same "don't self-host the file" posture
 * as the paste-a-link flow, just with WP doing the upload instead of the
 * editor doing it by hand first.
 *
 * Returns the new object key on a successful upload, null if no file was
 * submitted (caller falls back to the pasted object-key field) or the
 * upload failed (an admin notice is queued; the existing object key,
 * if any, is left untouched rather than being overwritten with nothing).
 */
function bday_edition_upload_pdf_if_present( int $post_id ): ?string {
	if ( empty( $_FILES['edition_pdf_file']['tmp_name'] ) || UPLOAD_ERR_NO_FILE === ( $_FILES['edition_pdf_file']['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
		return null;
	}
	if ( UPLOAD_ERR_OK !== $_FILES['edition_pdf_file']['error'] ) {
		bday_edition_queue_upload_notice( 'The PDF upload failed (upload error). The previous file, if any, was kept.' );
		return null;
	}
	if ( $_FILES['edition_pdf_file']['size'] > BDAY_EDITION_PDF_MAX_BYTES ) {
		bday_edition_queue_upload_notice( 'The PDF is larger than the ' . size_format( BDAY_EDITION_PDF_MAX_BYTES ) . ' upload limit. The previous file, if any, was kept.' );
		return null;
	}

	$base_url = (string) get_option( 'aero_paywall_api_base_url', '' );
	$api_key  = (string) get_option( 'aero_paywall_api_key', '' );
	if ( '' === $base_url || '' === $api_key ) {
		bday_edition_queue_upload_notice( 'The PDF upload could not be sent — the Aero Paywall API connection is not configured.' );
		return null;
	}

	$file_body = file_get_contents( $_FILES['edition_pdf_file']['tmp_name'] );
	if ( false === $file_body ) {
		bday_edition_queue_upload_notice( 'The PDF upload failed (could not read the uploaded file). The previous file, if any, was kept.' );
		return null;
	}

	return bday_edition_upload_bytes( $post_id, $file_body );
}

/**
 * Shared upload path: streams raw PDF bytes to subscription-service's
 * /connector/edition-pdf (which writes them to the archive bucket and mints
 * the object key). Used by the metabox upload above and by the legacy-post
 * migration script, so there is exactly one code path that talks to the
 * connector endpoint.
 *
 * Returns the new object key on success, null on any failure (an admin
 * notice is queued via bday_edition_queue_upload_notice()).
 */
function bday_edition_upload_bytes( int $post_id, string $bytes ): ?string {
	$base_url = (string) get_option( 'aero_paywall_api_base_url', '' );
	$api_key  = (string) get_option( 'aero_paywall_api_key', '' );
	if ( '' === $base_url || '' === $api_key ) {
		bday_edition_queue_upload_notice( 'The PDF upload could not be sent — the Aero Paywall API connection is not configured.' );
		return null;
	}

	$response = wp_remote_post(
		rtrim( $base_url, '/' ) . '/connector/edition-pdf?postId=' . $post_id,
		array(
			// Much longer than the 5-8s used elsewhere for small JSON
			// payloads — this is a synchronous, potentially multi-ten-MB
			// upload happening inline with the admin's "Publish"/"Update" click.
			'timeout' => 120,
			'headers' => array(
				'Content-Type' => 'application/pdf',
				'X-Api-Key'    => $api_key,
			),
			'body'    => $bytes,
		)
	);

	if ( is_wp_error( $response ) ) {
		bday_edition_queue_upload_notice( 'The PDF upload failed: ' . $response->get_error_message() . '. The previous file, if any, was kept.' );
		return null;
	}

	$status = wp_remote_retrieve_response_code( $response );
	$data   = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( 200 !== $status && 201 !== $status || empty( $data['objectKey'] ) ) {
		bday_edition_queue_upload_notice( 'The PDF upload failed (storage service returned an error). The previous file, if any, was kept.' );
		return null;
	}

	return sanitize_text_field( $data['objectKey'] );
}

function bday_edition_queue_upload_notice( string $message ): void {
	set_transient( 'bday_edition_upload_notice_' . get_current_user_id(), $message, MINUTE_IN_SECONDS );
}

add_action(
	'admin_notices',
	static function (): void {
		$screen = get_current_screen();
		if ( ! $screen || 'bday_edition' !== $screen->post_type ) {
			return;
		}
		$key     = 'bday_edition_upload_notice_' . get_current_user_id();
		$message = get_transient( $key );
		if ( ! $message ) {
			return;
		}
		delete_transient( $key );
		printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $message ) );
	}
);
