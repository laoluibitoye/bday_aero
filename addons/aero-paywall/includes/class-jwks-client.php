<?php
/**
 * Shared JWT verification against a service's published JWKS. Used for
 * both subscription-service reader tokens and licensing-platform license
 * tokens (each with its own cache key/origin, both passed in by the
 * caller). Returns null uniformly on any failure — expired, malformed, or
 * an unreachable JWKS endpoint are all "not valid", never a fatal error.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;

final class Bday_Aero_Jwks_Client {

	private const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * A small clock-skew allowance between this WP host and
	 * subscription-service — without it, minor drift between the two
	 * clocks can reject a token as prematurely expired (or "not yet
	 * valid") even though it's genuinely still good. firebase/php-jwt's
	 * JWT::$leeway is a static property consulted by JWT::decode()'s own
	 * nbf/iat/exp checks, so it must be set before every decode call
	 * (decode_claims() below does this).
	 */
	private const LEEWAY_SECONDS = 60;

	/** @return array<string, mixed>|null decoded token claims, or null if invalid/unreachable */
	public static function verify( string $token, string $api_base_url, string $cache_key ): ?array {
		$jwks = self::fetch_jwks( $api_base_url, $cache_key );
		if ( null === $jwks ) {
			return null;
		}

		$result = self::decode_claims( $token, $jwks );
		if ( null !== $result['claims'] ) {
			return $result['claims'];
		}

		if ( ! $result['unknown_kid'] ) {
			// Genuinely expired, malformed, or otherwise invalid — no
			// amount of re-fetching the JWKS would change that outcome,
			// so fail immediately rather than retrying.
			return null;
		}

		/**
		 * The token's `kid` isn't in our cached key set — most likely
		 * subscription-service rotated its signing key since our last
		 * CACHE_TTL-bounded fetch (up to 12h stale). Previously this was
		 * only ever refetched when the transient fully expired, never in
		 * response to a verification failure, so a reader could be
		 * spuriously rejected for up to 12 hours after a legitimate key
		 * rotation. Bust the cache and retry exactly once with a fresh
		 * JWKS pull before giving up — deliberately not a retry loop: if
		 * the fresh key set still doesn't recognize this kid, the token
		 * really is invalid.
		 */
		Bday_Query_Cache::forget( 'aero_paywall', $cache_key );
		$jwks = self::fetch_jwks( $api_base_url, $cache_key );
		if ( null === $jwks ) {
			return null;
		}
		return self::decode_claims( $token, $jwks )['claims'];
	}

	/** @param array<string, mixed> $jwks @return array{claims: array<string, mixed>|null, unknown_kid: bool} */
	private static function decode_claims( string $token, array $jwks ): array {
		try {
			$key_set       = JWK::parseKeySet( $jwks );
			JWT::$leeway   = self::LEEWAY_SECONDS;
			$decoded       = JWT::decode( $token, $key_set );
			return array( 'claims' => (array) $decoded, 'unknown_kid' => false );
		} catch ( \UnexpectedValueException $e ) {
			// firebase/php-jwt's exact, stable message for this case
			// (JWT::getKey()) — distinct from other UnexpectedValueException
			// subclasses like ExpiredException ("Expired token") or
			// SignatureInvalidException ("Signature verification failed"),
			// which must NOT trigger a retry.
			$unknown_kid = false !== strpos( $e->getMessage(), '"kid" invalid' );
			return array( 'claims' => null, 'unknown_kid' => $unknown_kid );
		} catch ( \Throwable $e ) {
			return array( 'claims' => null, 'unknown_kid' => false );
		}
	}

	/** @return array<string, mixed>|null */
	private static function fetch_jwks( string $api_base_url, string $cache_key ): ?array {
		return Bday_Query_Cache::remember(
			'aero_paywall',
			$cache_key,
			static function () use ( $api_base_url ) {
				$origin = self::origin_of( $api_base_url );
				if ( '' === $origin ) {
					return null;
				}
				$response = wp_remote_get( $origin . '/.well-known/jwks.json', array( 'timeout' => 5 ) );
				if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
					return null;
				}
				$body = json_decode( wp_remote_retrieve_body( $response ), true );
				return is_array( $body ) ? $body : null;
			},
			self::CACHE_TTL
		);
	}

	private static function origin_of( string $url ): string {
		$parts  = wp_parse_url( $url );
		$scheme = $parts['scheme'] ?? 'https';
		$host   = $parts['host'] ?? '';
		if ( '' === $host ) {
			return '';
		}
		$port = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
		return $scheme . '://' . $host . $port;
	}
}
