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
		'<p class="description">Saved into this site\'s secure e-editions folder (not publicly reachable). Leave empty to keep the current file.</p>'
	);
	printf(
		'<p><label for="bday-edition-local-filename">...or use a filename already dropped into <code>%s</code>:</label><br><input type="text" id="bday-edition-local-filename" name="local_filename" value="" class="widefat" placeholder="BD_20230729.pdf" /></p>' .
		'<p class="description">For a PDF already uploaded to the server directly (FileZilla/SFTP). Only used if no file is uploaded above; the filename must already exist in that folder.</p>',
		esc_html( 'wp-content/uploads/' . BDAY_EDITION_SECURE_SUBDIR . '/' )
	);
	printf(
		'<p><label for="bday-edition-object-key">...or paste a raw storage object key (legacy/S3 only):</label><br><input type="text" id="bday-edition-object-key" name="object_key" value="%s" class="widefat" placeholder="editions/e-paper/2026-08-14.pdf" /></p>' .
		'<p class="description">Only for a file already sitting in the old S3 archive bucket. Only used if neither field above is filled in.</p>',
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
		} elseif ( isset( $_POST['local_filename'] ) && '' !== trim( wp_unslash( $_POST['local_filename'] ) ) ) {
			$local_key = bday_edition_use_local_filename_if_present( $post_id );
			if ( null !== $local_key ) {
				update_post_meta( $post_id, '_bday_edition_object_key', $local_key );
			}
		} elseif ( isset( $_POST['object_key'] ) && '' !== trim( wp_unslash( $_POST['object_key'] ) ) ) {
			update_post_meta( $post_id, '_bday_edition_object_key', sanitize_text_field( wp_unslash( $_POST['object_key'] ) ) );
		}

		// Publishing and uploading the PDF in the same click fires this
		// handler AFTER transition_post_status (publish-push.php's own
		// hook) already ran with the object key still empty — see that
		// file's docblock. Calling the sync directly here, now that the
		// object key above is guaranteed current, is what actually makes a
		// brand-new edition reach subscription-service on its first
		// publish instead of requiring a second, separate save.
		if ( function_exists( 'bday_edition_sync_to_subscription_service' ) ) {
			bday_edition_sync_to_subscription_service( get_post( $post_id ) );
		}
	}
);

/**
 * Moves $_FILES['edition_pdf_file'] into the local secure-epapers/ folder
 * (bday_edition_secure_dir(), secure-storage.php) and returns a
 * "local:{postId}:{filename}" object key — the default upload path since
 * S3 was replaced with local storage. Returns null if no file was
 * submitted (caller falls through to the other two fields) or the upload
 * failed (an admin notice is queued; the existing object key, if any, is
 * left untouched rather than being overwritten with nothing).
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

	$tmp_name = $_FILES['edition_pdf_file']['tmp_name'];
	$head     = file_get_contents( $tmp_name, false, null, 0, 5 );
	if ( "%PDF-" !== $head ) {
		bday_edition_queue_upload_notice( 'The uploaded file does not look like a PDF. The previous file, if any, was kept.' );
		return null;
	}

	$dir       = bday_edition_secure_dir();
	$orig_name = sanitize_file_name( $_FILES['edition_pdf_file']['name'] );
	$base      = pathinfo( $orig_name, PATHINFO_FILENAME );
	$base      = '' !== $base ? $base : 'edition';
	// Collision-safe: {postId}-{sanitized original name}-{random suffix}.pdf.
	// An old file from a previous upload on the same post is deliberately
	// left orphaned on disk rather than deleted — same "never destroy data
	// on a save" posture the old S3 path had (it never deleted replaced
	// objects either).
	$filename = $post_id . '-' . $base . '-' . wp_generate_password( 8, false, false ) . '.pdf';
	$dest     = $dir . '/' . $filename;

	if ( ! move_uploaded_file( $tmp_name, $dest ) ) {
		bday_edition_queue_upload_notice( 'Could not save the uploaded PDF to the secure folder. The previous file, if any, was kept.' );
		return null;
	}
	chmod( $dest, 0640 );

	return bday_edition_make_local_object_key( $post_id, $filename );
}

/**
 * The "...or use a filename already dropped into secure-epapers/" field —
 * the FileZilla/SFTP workflow. Only ever accepts a filename that already
 * exists inside the secure folder (bday_edition_resolve_secure_file()
 * does the real validation); a typo or a not-yet-uploaded filename queues
 * a notice and leaves the existing object key untouched, rather than
 * saving a reference to a file that doesn't exist.
 */
function bday_edition_use_local_filename_if_present( int $post_id ): ?string {
	$filename = basename( sanitize_text_field( wp_unslash( $_POST['local_filename'] ?? '' ) ) );
	if ( '' === $filename ) {
		return null;
	}
	if ( null === bday_edition_resolve_secure_file( $filename ) ) {
		bday_edition_queue_upload_notice( 'No file named "' . $filename . '" was found in the secure e-editions folder. The previous file, if any, was kept.' );
		return null;
	}
	return bday_edition_make_local_object_key( $post_id, $filename );
}

/**
 * LEGACY / DORMANT since local storage replaced S3 as the default: nothing
 * in this addon's own upload flow calls this anymore (kept only for
 * rollback safety — flip bday_edition_upload_pdf_if_present() back to
 * calling this if local storage ever needs reverting). Streams raw PDF
 * bytes to subscription-service's /connector/edition-pdf, which writes
 * them to the S3 archive bucket and mints the object key. Existing S3-
 * backed editions from before this change keep resolving through this same
 * bucket/key shape at read time (subscription-service's getDownloadUrl()
 * branch is unchanged for any non-"local:" object key) — this function is
 * unused for *new* saves, not something that needs undoing for old data.
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
