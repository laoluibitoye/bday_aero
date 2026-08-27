<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BDAY_EDITION_BULK_IMPORT_MAX_ROWS = 500;

add_action(
	'admin_menu',
	static function (): void {
		add_submenu_page(
			'edit.php?post_type=bday_edition',
			'Bulk Import Editions',
			'Bulk Import',
			'edit_others_posts',
			'bday-edition-bulk-import',
			'bday_edition_render_bulk_import_page'
		);
	}
);

/**
 * For past editions whose PDFs already live in the target bucket — skips
 * uploading a file per edition entirely, taking a CSV of
 * title,date,publication,object_key and creating a real bday_edition post
 * per row so they show up on the normal taxonomy-edition_publication.php
 * archive exactly like a hand-created edition.
 *
 * If a past edition's PDF is instead sitting somewhere other than the
 * target bucket (a different host/bucket, Drive, Dropbox), it must be
 * copied into the bucket first — this screen only records object keys,
 * it never fetches or moves files (same "record the mapping, don't
 * touch the file" posture as edition-sync itself).
 */
function bday_edition_render_bulk_import_page(): void {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.' ) );
	}

	$results = null;
	if ( isset( $_POST['bday_edition_bulk_import_nonce'] ) && wp_verify_nonce( $_POST['bday_edition_bulk_import_nonce'], 'bday_edition_bulk_import' ) ) {
		$results = bday_edition_process_bulk_import();
	}
	?>
	<div class="wrap">
		<h1>Bulk Import Editions</h1>
		<p>Upload a CSV with columns (no header row): <code>title,date,publication,object_key</code> — date as <code>YYYY-MM-DD</code>, publication as the taxonomy slug (created automatically if it doesn't exist yet), object_key as the path already sitting in the archive storage bucket.</p>

		<?php if ( null !== $results ) : ?>
			<div class="notice notice-<?php echo empty( $results['skipped'] ) ? 'success' : 'warning'; ?>">
				<p><strong><?php echo (int) $results['created']; ?></strong> edition(s) created.</p>
				<?php if ( ! empty( $results['skipped'] ) ) : ?>
					<p><?php echo count( $results['skipped'] ); ?> row(s) skipped:</p>
					<ul style="list-style:disc;margin-left:2em;">
						<?php foreach ( $results['skipped'] as $skip ) : ?>
							<li>Row <?php echo (int) $skip['row']; ?>: <?php echo esc_html( $skip['reason'] ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'bday_edition_bulk_import', 'bday_edition_bulk_import_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="bday-edition-bulk-csv">CSV file</label></th>
					<td><input type="file" id="bday-edition-bulk-csv" name="bulk_csv" accept=".csv,text/csv" required /></td>
				</tr>
			</table>
			<?php submit_button( 'Import Editions' ); ?>
		</form>
	</div>
	<?php
}

/**
 * @return array{created:int, skipped:array<int, array{row:int, reason:string}>}
 */
function bday_edition_process_bulk_import(): array {
	$created = 0;
	$skipped = array();

	if ( empty( $_FILES['bulk_csv']['tmp_name'] ) || UPLOAD_ERR_OK !== ( $_FILES['bulk_csv']['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
		return array(
			'created' => 0,
			'skipped' => array( array( 'row' => 0, 'reason' => 'No CSV file was uploaded.' ) ),
		);
	}

	$handle = fopen( $_FILES['bulk_csv']['tmp_name'], 'r' );
	if ( false === $handle ) {
		return array(
			'created' => 0,
			'skipped' => array( array( 'row' => 0, 'reason' => 'Could not read the uploaded file.' ) ),
		);
	}

	$row_number = 0;
	while ( false !== ( $row = fgetcsv( $handle ) ) ) {
		++$row_number;
		if ( $row_number > BDAY_EDITION_BULK_IMPORT_MAX_ROWS ) {
			$skipped[] = array(
				'row'    => $row_number,
				'reason' => 'Batch limit of ' . BDAY_EDITION_BULK_IMPORT_MAX_ROWS . ' rows reached — split the remainder into a second import.',
			);
			break;
		}
		if ( 1 === count( $row ) && '' === trim( (string) $row[0] ) ) {
			continue; // Blank line.
		}

		list( $title, $date, $publication, $object_key ) = array_pad( array_map( 'trim', $row ), 4, '' );

		if ( '' === $title || '' === $date || '' === $publication || '' === $object_key ) {
			$skipped[] = array( 'row' => $row_number, 'reason' => 'Missing one or more required columns (title, date, publication, object_key).' );
			continue;
		}

		$timestamp = strtotime( $date );
		if ( false === $timestamp ) {
			$skipped[] = array( 'row' => $row_number, 'reason' => "Unparseable date \"{$date}\" (expected YYYY-MM-DD)." );
			continue;
		}

		$publication_slug = sanitize_title( $publication );
		if ( ! term_exists( $publication_slug, 'edition_publication' ) ) {
			wp_insert_term( $publication, 'edition_publication', array( 'slug' => $publication_slug ) );
		}

		// Insert as a draft first, then set meta/terms, then transition to
		// publish — wp_insert_post() with post_status => 'publish' directly
		// would fire transition_post_status (and therefore
		// bday_editions_on_publish()) before this function ever gets a
		// chance to set the object key or taxonomy term, so the push to
		// /connector/edition-sync would fire with nothing to send and
		// silently no-op.
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'bday_edition',
				'post_title'  => sanitize_text_field( $title ),
				'post_date'   => gmdate( 'Y-m-d H:i:s', $timestamp ),
				'post_status' => 'draft',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			$skipped[] = array( 'row' => $row_number, 'reason' => 'Could not create the post: ' . $post_id->get_error_message() );
			continue;
		}

		update_post_meta( $post_id, '_bday_edition_object_key', sanitize_text_field( $object_key ) );
		wp_set_post_terms( $post_id, array( $publication_slug ), 'edition_publication' );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		++$created;
	}
	fclose( $handle );

	return array(
		'created' => $created,
		'skipped' => $skipped,
	);
}
