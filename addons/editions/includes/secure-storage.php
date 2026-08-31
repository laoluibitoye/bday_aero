<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Local-filesystem storage for e-edition PDFs — replaces S3 as the default
 * going forward (S3/PdfStorageService on the subscription-service side is
 * left fully intact for already-migrated content, just no longer the path
 * new uploads take). Files live in wp-content/uploads/secure-epapers/ by
 * default, uploaded either via this addon's metabox or dropped in directly
 * (FileZilla) and referenced by filename. The folder must NEVER be
 * reachable by a direct request. Two ways to get there, see
 * bday_edition_secure_dir() below: an Nginx `location` rule the operator
 * adds to the server config (this repo has no access to it — .htaccess is
 * an Apache-only mechanism and does nothing under Nginx), or — the
 * stronger option, no server config needed at all — pointing
 * BDAY_EDITION_SECURE_DIR at a folder outside the site's public document
 * root entirely, where no URL can ever reach it regardless of Nginx/CDN
 * config. Everything else in this file is defense in depth on top of
 * whichever of those two is in use, never a substitute for either.
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
 *
 * Defaults to wp-content/uploads/secure-epapers/, which needs an Nginx
 * `location` rule blocking it (that server config isn't reachable from
 * here). If BDAY_EDITION_SECURE_DIR is defined in wp-config.php — an
 * absolute path OUTSIDE the site's public document root, e.g.
 * '/var/www/secure-epapers' — that's used instead, and no server rule is
 * needed at all: a path outside the docroot has no URL that maps to it,
 * regardless of Nginx/CDN/load-balancer config. The directory must
 * already exist and be writable by the PHP-FPM user (this only creates
 * it under the default uploads-based location, never at an arbitrary
 * operator-supplied absolute path — see the wp_mkdir_p() call below).
 */
function bday_edition_secure_dir(): string {
	if ( defined( 'BDAY_EDITION_SECURE_DIR' ) && '' !== BDAY_EDITION_SECURE_DIR ) {
		return untrailingslashit( BDAY_EDITION_SECURE_DIR );
	}

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

/**
 * Whether bday_edition is currently one of aero-paywall's "Restricted
 * Post Types" (the same generic per-content-type gating switch every
 * other post type uses, via class-content-gate.php/class-premium-map.php)
 * — reader-requested: removing a content type from that list should make
 * it fully open, no login/entitlement check at all, exactly like any
 * other unrestricted type; adding it back restores full gating. Before
 * this, e-editions ignored that setting entirely and were always gated
 * via subscription-service's archive-window check regardless of it —
 * this function plus bday_edition_build_signed_download_url() below are
 * what closes that gap.
 *
 * Fails closed (treated as restricted) if the aero-paywall add-on isn't
 * active at all — Bday_Aero_Settings won't even be loaded in that case,
 * and "no paywall add-on configured" should never silently mean "every
 * edition is world-readable."
 */
function bday_edition_type_is_restricted(): bool {
	if ( ! class_exists( 'Bday_Aero_Settings' ) ) {
		return true;
	}
	return in_array( 'bday_edition', Bday_Aero_Settings::restricted_post_types(), true );
}

/**
 * Self-issued counterpart to subscription-service's
 * WordpressEditionLinkService::buildUrl() — same HMAC-over-"{postId}.{exp}"
 * scheme, same shared secret, same download-endpoint.php route, just
 * minted directly by WordPress instead of by subscription-service after
 * an entitlement check. Only ever used for an edition bday_edition_type_
 * is_restricted() says is NOT currently gated — deliberately skips
 * calling subscription-service at all, since the whole point is "no
 * entitlement check applies to this content type right now." Only works
 * for a "local:" edition (S3-backed editions have no WP-side signing
 * capability — subscription-service is the only thing that can presign
 * against the bucket); returns null for anything else, and the caller
 * falls back to the normal gated SDK flow in that case.
 */
function bday_edition_build_signed_download_url( int $post_id, int $ttl_seconds = 300 ): ?string {
	$object_key = (string) get_post_meta( $post_id, '_bday_edition_object_key', true );
	$local      = bday_edition_parse_local_object_key( $object_key );
	if ( null === $local ) {
		return null;
	}

	$exp = time() + $ttl_seconds;
	$sig = hash_hmac( 'sha256', $post_id . '.' . $exp, bday_edition_signing_secret() );

	return add_query_arg(
		array(
			'exp' => $exp,
			'sig' => $sig,
		),
		rest_url( 'aeropaywall/v1/edition-download/' . $post_id )
	);
}

/**
 * Wraps a signed PDF URL (bday_edition_build_signed_download_url(), or
 * subscription-service's own equivalent) in flipbook-reader.php's
 * `?bday_reader=1&pdf=` query — the same page-flip viewer the gated
 * click-to-fetch path (sdk/src/edition-download.ts) opens, so an
 * unrestricted edition's server-rendered link opens identically instead
 * of navigating straight to the raw PDF (which a browser just downloads/
 * displays as a bare file, and which is trivially shareable as a plain
 * file link rather than opening through the reader experience).
 */
function bday_edition_reader_url( string $signed_pdf_url ): string {
	return home_url( '/?bday_reader=1&pdf=' . rawurlencode( $signed_pdf_url ) );
}
