<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves a local secure-epapers/ PDF once subscription-service has already
 * checked the reader's entitlement and minted a signed link
 * (WordpressEditionLinkService::buildUrl()) — this endpoint's own job is
 * only to verify that signature, never to re-derive entitlement itself.
 * permission_callback is intentionally '__return_true': auth here is the
 * HMAC signature in the query string, not a WordPress capability — same
 * posture as a presigned S3 URL, just self-hosted (and the same posture
 * class-mobile-api.php already uses for its own reader-facing REST route).
 */
add_action(
	'rest_api_init',
	static function (): void {
		register_rest_route(
			'aeropaywall/v1',
			'/edition-download/(?P<post_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => 'bday_edition_stream_signed_download',
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_id' => array(
						'validate_callback' => static fn( $value ) => ctype_digit( (string) $value ),
					),
				),
			)
		);
	}
);

function bday_edition_stream_signed_download( WP_REST_Request $request ) {
	$post_id = (int) $request->get_param( 'post_id' );
	$exp     = (int) $request->get_param( 'exp' );
	$sig     = (string) $request->get_param( 'sig' );

	if ( $post_id <= 0 || $exp <= 0 || '' === $sig ) {
		return new WP_Error( 'bday_edition_bad_request', 'Invalid request.', array( 'status' => 400 ) );
	}
	if ( time() > $exp ) {
		return new WP_Error( 'bday_edition_link_expired', 'This link has expired.', array( 'status' => 403 ) );
	}

	$secret   = bday_edition_signing_secret();
	$expected = hash_hmac( 'sha256', $post_id . '.' . $exp, $secret );
	if ( ! hash_equals( $expected, $sig ) ) {
		return new WP_Error( 'bday_edition_bad_signature', 'Invalid signature.', array( 'status' => 403 ) );
	}

	$post = get_post( $post_id );
	if ( ! $post || 'bday_edition' !== $post->post_type || 'publish' !== $post->post_status ) {
		return new WP_Error( 'bday_edition_not_found', 'Edition not found.', array( 'status' => 404 ) );
	}

	$object_key = (string) get_post_meta( $post_id, '_bday_edition_object_key', true );
	$local      = bday_edition_parse_local_object_key( $object_key );
	// The signature already proves the request was authorized for this
	// exact post_id, but if the stored object key were ever for a
	// *different* post (shouldn't happen — signing always uses the
	// requesting post's own ID) this catches the mismatch rather than
	// serving the wrong file.
	if ( null === $local || $local['post_id'] !== $post_id ) {
		return new WP_Error( 'bday_edition_not_local', 'No local file for this edition.', array( 'status' => 404 ) );
	}

	$real_path = bday_edition_resolve_secure_file( $local['filename'] );
	if ( null === $real_path ) {
		return new WP_Error( 'bday_edition_file_missing', 'File not found.', array( 'status' => 404 ) );
	}

	$force_download = '1' === (string) $request->get_param( 'dl' );

	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Length: ' . filesize( $real_path ) );
	header(
		'Content-Disposition: ' . ( $force_download ? 'attachment' : 'inline' )
		. '; filename="' . rawurlencode( basename( $real_path ) ) . '"'
	);
	// Signed and short-lived: safe for the browser's own single-request use,
	// never for a shared/CDN cache — a cached copy would keep working past
	// `exp` and bypass per-reader entitlement entirely.
	header( 'Cache-Control: private, no-store, max-age=0' );
	header( 'X-Content-Type-Options: nosniff' );

	readfile( $real_path );
	exit;
}
