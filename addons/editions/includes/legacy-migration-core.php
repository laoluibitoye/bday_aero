<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared migration logic for legacy post_type=post entries in the
 * e-paper/e-edition categories (raw, unauthenticated PDF URL in
 * _bday_pdf_preview_link) — moves them onto the secure bday_edition system.
 * Always loaded (not WP_CLI-gated) because both front doors need it:
 * the wp-admin wizard (legacy-migration-wizard.php — for production sites
 * with no WP-CLI/SSH access) and the `wp bday migrate-legacy-editions`
 * command (legacy-migration.php, for staging/dev where CLI access exists).
 * One code path, two front doors — behaviour can't drift between them.
 */

/**
 * @return WP_Post[]
 */
function bday_edition_get_legacy_posts( int $limit = -1, int $offset = 0 ): array {
	// The single-post dispatcher (single.php) and the legacy grid
	// (pdf-viewer.php's bday_render_e_edition_grid()) key off different
	// category slugs — 'e-edition' and 'e-paper' respectively — so both are
	// treated as in-scope here to avoid silently missing content filed
	// under either one.
	return get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'tax_query'      => array(
				array(
					'taxonomy' => 'category',
					'field'    => 'slug',
					'terms'    => array( 'e-paper', 'e-edition' ),
				),
			),
		)
	);
}

/**
 * Migrates a single legacy post. Safe to call repeatedly on the same post —
 * already-migrated posts (tracked via _bday_migrated_to_edition_id) are
 * reported, not re-processed.
 *
 * @return array{status:string, message:string, new_id:?int}
 *   status is one of: already_migrated, no_link, would_migrate (dry run
 *   only), migrated, failed.
 */
function bday_edition_migrate_one_legacy_post( WP_Post $legacy_post, bool $dry_run = false ): array {
	$existing_id = (int) get_post_meta( $legacy_post->ID, '_bday_migrated_to_edition_id', true );
	if ( $existing_id && get_post_status( $existing_id ) ) {
		return array(
			'status'  => 'already_migrated',
			'message' => "Already migrated to edition #{$existing_id}.",
			'new_id'  => $existing_id,
		);
	}

	$pdf_url = trim( (string) get_post_meta( $legacy_post->ID, '_bday_pdf_preview_link', true ) );
	if ( '' === $pdf_url ) {
		return array(
			'status'  => 'no_link',
			'message' => 'No PDF preview link set — nothing to migrate.',
			'new_id'  => null,
		);
	}

	if ( $dry_run ) {
		return array(
			'status'  => 'would_migrate',
			'message' => "Would fetch and migrate {$pdf_url}.",
			'new_id'  => null,
		);
	}

	$response = wp_remote_get( $pdf_url, array( 'timeout' => 60 ) );
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		$reason = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $response );
		return array(
			'status'  => 'failed',
			'message' => "Could not fetch {$pdf_url} ({$reason}).",
			'new_id'  => null,
		);
	}

	$bytes = wp_remote_retrieve_body( $response );
	if ( '%PDF-' !== substr( $bytes, 0, 5 ) ) {
		return array(
			'status'  => 'failed',
			'message' => "Fetched content at {$pdf_url} is not a PDF.",
			'new_id'  => null,
		);
	}
	if ( strlen( $bytes ) > BDAY_EDITION_PDF_MAX_BYTES ) {
		return array(
			'status'  => 'failed',
			'message' => 'PDF exceeds ' . size_format( BDAY_EDITION_PDF_MAX_BYTES ) . '.',
			'new_id'  => null,
		);
	}

	// Draft first, then set meta/terms, then publish — publishing directly
	// would fire transition_post_status (bday_editions_on_publish) before
	// the object key/term exist, same reasoning bulk-import.php documents
	// for its own draft-then-publish sequencing.
	$new_id = wp_insert_post(
		array(
			'post_type'   => 'bday_edition',
			'post_title'  => $legacy_post->post_title,
			'post_date'   => $legacy_post->post_date,
			'post_status' => 'draft',
		),
		true
	);
	if ( is_wp_error( $new_id ) ) {
		return array(
			'status'  => 'failed',
			'message' => 'Could not create edition post: ' . $new_id->get_error_message(),
			'new_id'  => null,
		);
	}

	$object_key = bday_edition_upload_bytes( $new_id, $bytes );
	if ( null === $object_key ) {
		wp_delete_post( $new_id, true ); // still a draft, safe to remove outright
		return array(
			'status'  => 'failed',
			'message' => 'Upload to storage failed.',
			'new_id'  => null,
		);
	}

	update_post_meta( $new_id, '_bday_edition_object_key', $object_key );
	update_post_meta( $new_id, '_bday_legacy_source_post_id', $legacy_post->ID );

	// Default publication term: legacy posts carried no equivalent taxonomy,
	// so there's no signal to place them under she-means-business/
	// real-estate-digest/weekender instead — flag for editorial review if
	// any migrated posts actually belong elsewhere.
	wp_set_post_terms( $new_id, array( 'e-paper' ), 'edition_publication' );

	wp_update_post(
		array(
			'ID'          => $new_id,
			'post_status' => 'publish',
		)
	);

	// Cross-link both directions; do NOT trash/unpublish the legacy post
	// here — the redirect in addons/e-edition/includes/legacy-redirect.php
	// handles routing traffic away from it, keeping this step inspectable
	// and reversible.
	update_post_meta( $legacy_post->ID, '_bday_migrated_to_edition_id', $new_id );

	return array(
		'status'  => 'migrated',
		'message' => "Migrated to edition #{$new_id}.",
		'new_id'  => $new_id,
	);
}
