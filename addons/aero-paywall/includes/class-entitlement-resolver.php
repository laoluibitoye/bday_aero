<?php
/**
 * Shared "is this reader's own token valid, and are they actively
 * subscribed" check — the exact JWKS-based verification
 * class-mobile-api.php's REST route already used to answer that question
 * for the SDK's client-side entitlement fetch, extracted here so
 * class-content-gate.php's server-rendered output can ask the same
 * question, with the same fail-closed semantics, without a second
 * duplicate implementation drifting out of sync with the first.
 *
 * A verified, non-staff token only ever means "this is a real signed-in
 * reader" — never "this reader has paid." subscription-service bakes the
 * real answer into every token it issues (EntitlementClaimsService) as
 * `subscriptionStatus`, the same claim callers here rely on.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Entitlement_Resolver {

	/**
	 * Same cookie name the reader SDK itself sets/reads
	 * (sdk/src/token.ts's ACCESS_TOKEN_COOKIE) — a short-lived (15 min),
	 * JS-readable (not httpOnly) access token refreshed silently by the
	 * SDK via the httpOnly refresh-token cookie. Reading it here directly
	 * lets a server-rendered response resolve the same entitlement the
	 * SDK would, without waiting on any client-side round trip.
	 */
	private const ACCESS_TOKEN_COOKIE = 'ap_access_token';

	/**
	 * Resolves the current reader's entitlement from either an explicitly
	 * supplied bearer token (the REST route's case — Authorization header,
	 * already extracted by the caller since header-fallback handling is
	 * REST-request-specific) or, when none is given, the SDK's own
	 * access-token cookie (the SSR-render case, where there is no
	 * Authorization header to read at all).
	 *
	 * Returns null for anything short of "verified, non-staff reader
	 * token" — no token, an unreachable/unconfigured subscription-service,
	 * an invalid/expired/malformed token, or a staff token. Never throws;
	 * a missing or malformed cookie is simply treated as unauthenticated.
	 *
	 * @return array{isAuthenticated: bool, isSubscriber: bool, claims: array<string, mixed>}|null
	 */
	public static function resolve_for_current_request( ?string $bearer_token = null ): ?array {
		$token = ( null !== $bearer_token && '' !== $bearer_token ) ? $bearer_token : self::read_cookie_token();
		if ( null === $token ) {
			return null;
		}

		$base_url = Bday_Aero_Settings::api_base_url();
		if ( '' === $base_url ) {
			return null;
		}

		$claims = Bday_Aero_Jwks_Client::verify( $token, $base_url, 'reader_jwks' );
		if ( null === $claims || 'staff' === ( $claims['type'] ?? '' ) ) {
			return null;
		}

		return array(
			'isAuthenticated' => true,
			'isSubscriber'    => 'active' === ( $claims['subscriptionStatus'] ?? '' ),
			'claims'          => $claims,
		);
	}

	private static function read_cookie_token(): ?string {
		$raw = $_COOKIE[ self::ACCESS_TOKEN_COOKIE ] ?? null;
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}
		$token = sanitize_text_field( wp_unslash( $raw ) );
		return '' !== $token ? $token : null;
	}
}
