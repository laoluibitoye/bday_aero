<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Local-filesystem storage for e-edition PDFs — replaces S3 as the default
 * going forward (S3/PdfStorageService on the subscription-service side is
 * left fully intact for already-migrated content, just no longer the path
 * new uploads take). Files live in wp-content/uploads/secure-epapers/,
 * uploaded either via this addon's metabox or dropped in directly (FileZilla)
 * and referenced by filename. The folder must NEVER be reachable by a
 * direct request — that block is an Nginx `location` rule the operator adds
 * to the server config (this repo has no access to it), NOT anything PHP
 * can enforce on its own under Nginx (.htaccess is an Apache-only
 * mechanism and does nothing here). Everything in this file is defense in
 * depth on top of that server-level block, never a substitute for it.
 *
 * Storage-type convention, shared with subscription-service's
 * parseLocalObjectKey() (src/editions/local-object-key.util.ts) — keep the
 * two in lockstep: an _bday_edition_object_key value shaped
 * "local:{postId}:{filename}" is a local file; anything else is treated as
 * an S3 object key exactly as before. No existing data needs backfilling —
 * a real S3 key has never had this shape (see bulk-import.php/uploadPdf()'s
 * key-minting pattern, always "editions/uploads/...").
 */

const BDAY_EDITION_SECURE_SUBDIR = 'secure-epapers';

/**
 * Resolves (and lazily creates) the secure folder. Safe to call on every
 * request that needs it — wp_mkdir_p() and the index.php write are both
 * cheap no-ops once the folder already exists.
 */
function bday_edition_secure_dir(): string {
	$dir = trailingslashit( wp_upload_dir()['basedir'] ) . BDAY_EDITION_SECURE_SUBDIR;
	wp_mkdir_p( $dir );

	// Belt-and-suspenders only — stops directory listing if some
	// misconfigured server ever allows it. The real access block is the
	// Nginx location rule the operator applies server-side; this does
	// nothing to prevent a direct fetch of a known filename.
	$index = $dir . '/index.php';
	if ( ! file_exists( $index ) ) {
		file_put_contents( $index, "<?php\n// Silence is golden.\n" );
	}

	return $dir;
}

function bday_edition_make_local_object_key( int $post_id, string $filename ): string {
	return 'local:' . $post_id . ':' . $filename;
}

/**
 * @return array{post_id:int, filename:string}|null
 */
function bday_edition_parse_local_object_key( string $object_key ): ?array {
	if ( ! str_starts_with( $object_key, 'local:' ) ) {
		return null;
	}
	$rest  = substr( $object_key, strlen( 'local:' ) );
	$parts = explode( ':', $rest, 2 );
	if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) || '' === $parts[1] ) {
		return null;
	}
	return array(
		'post_id'  => (int) $parts[0],
		'filename' => $parts[1],
	);
}

/**
 * Resolves a filename to a real, existing path strictly inside the secure
 * folder — never trusts a stored/submitted filename blindly. basename()
 * strips any path component first; the realpath()+containment check below
 * is the actual guard against traversal (a filename that basename() left
 * unchanged but which still resolves outside the folder, e.g. via a
 * symlink, is rejected too).
 */
function bday_edition_resolve_secure_file( string $filename ): ?string {
	$safe_name = basename( $filename );
	if ( '' === $safe_name || $safe_name !== $filename ) {
		return null;
	}

	$dir  = bday_edition_secure_dir();
	$real_dir = realpath( $dir );
	$real_path = realpath( $dir . '/' . $safe_name );
	if ( false === $real_dir || false === $real_path ) {
		return null;
	}
	if ( 0 !== strpos( $real_path, $real_dir . DIRECTORY_SEPARATOR ) ) {
		return null;
	}

	return $real_path;
}

/**
 * The shared secret subscription-service's WordpressEditionLinkService
 * signs with — auto-generated on first use (same "operator copies a value
 * between the two systems once" posture as aero_paywall_api_key/
 * CONNECTOR_API_KEY, just generated here instead of typed in). Not
 * currently exposed in the aero-paywall settings React app (no build
 * pipeline available to extend it as part of this change) — retrieve it
 * via `wp option get aero_paywall_edition_signing_secret` and paste it
 * into subscription-service's EDITION_SIGNING_SECRET env var.
 */
function bday_edition_signing_secret(): string {
	$secret = (string) get_option( 'aero_paywall_edition_signing_secret', '' );
	if ( '' === $secret ) {
		$secret = wp_generate_password( 64, false, false );
		update_option( 'aero_paywall_edition_signing_secret', $secret, false );
	}
	return $secret;
}
