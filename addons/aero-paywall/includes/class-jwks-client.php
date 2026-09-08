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

	/**
	 * @param string      $token         The JWT to verify.
	 * @param string      $api_base_url  DB-configurable base URL — still used to derive the
	 *                                   JWKS origin when $trusted_host is null (reader-token
	 *                                   verification always passes null here: subscription-service
	 *                                   has no equivalent trust-anchor requirement, only licensing
	 *                                   does — Platform Audit G2).
	 * @param string      $cache_key     Cache-bucket key (kept separate per caller, e.g.
	 *                                   'reader_jwks' vs 'license_jwks').
	 * @param string|null $trusted_host  When set (license verification only), the JWKS is
	 *                                   fetched from this host instead of $api_base_url's —
	 *                                   closes July-audit bypass (c): a customer repointing the
	 *                                   DB-editable "Licensing Platform base URL" setting at a
	 *                                   self-hosted fake server no longer changes where the JWKS
	 *                                   itself comes from.
	 * @param string|null $pinned_kid    When set (license verification only), any fetched key
	 *                                   whose `kid` doesn't match is discarded before signature
	 *                                   verification — belt-and-suspenders alongside
	 *                                   $trusted_host: even if a fake server's response were
	 *                                   somehow reached, a self-generated key can't share the
	 *                                   real production key's kid.
	 * @return array<string, mixed>|null decoded token claims, or null if invalid/unreachable
	 */
	public static function verify(
		string $token,
		string $api_base_url,
		string $cache_key,
		?string $trusted_host = null,
		?string $pinned_kid = null
	): ?array {
		$jwks = self::fetch_jwks( $api_base_url, $cache_key, $trusted_host );
		if ( null === $jwks ) {
			return null;
		}
		$jwks = self::apply_kid_pin( $jwks, $pinned_kid );

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
		$jwks = self::fetch_jwks( $api_base_url, $cache_key, $trusted_host );
		if ( null === $jwks ) {
			return null;
		}
		$jwks = self::apply_kid_pin( $jwks, $pinned_kid );
		return self::decode_claims( $token, $jwks )['claims'];
	}

	/**
	 * Trust-anchor key pinning (Platform Audit G2). No-op (returns $jwks
	 * unchanged) whenever the caller passes no pin — the reader-token path
	 * always does, so this function is a pure pass-through for that case.
	 *
	 * @param array<string, mixed> $jwks
	 * @return array<string, mixed>
	 */
	private static function apply_kid_pin( array $jwks, ?string $pinned_kid ): array {
		if ( null === $pinned_kid || '' === $pinned_kid ) {
			return $jwks;
		}
		if ( ! isset( $jwks['keys'] ) || ! is_array( $jwks['keys'] ) ) {
			return array( 'keys' => array() );
		}
		return array(
			'keys' => array_values(
				array_filter(
					$jwks['keys'],
					static function ( $key ) use ( $pinned_kid ): bool {
						return is_array( $key ) && isset( $key['kid'] ) && hash_equals( $pinned_kid, (string) $key['kid'] );
					}
				)
			),
		);
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

	/**
	 * Field-tested finding (Gating System Field Test, 2026-09-08): this
	 * used to go through Bday_Query_Cache::remember(), which caches
	 * whatever its producer callback returns as long as it's not literally
	 * `false` — including `null`. A single failed fetch (one timeout, one
	 * 5xx from the origin) got written through with the full 12-hour TTL,
	 * so every JWT verification for up to 12 real hours read back that
	 * cached `null` and failed, with no retry — turning one transient blip
	 * into up to half a day of every reader (subscribers included) being
	 * treated as unverifiable/anonymous. Manages its own cache here
	 * instead, using the identical key/group Bday_Query_Cache::remember()
	 * would have used (so the "unknown kid" retry's
	 * Bday_Query_Cache::forget() call above still busts the right entry),
	 * but only ever writes through a genuinely successful fetch.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function fetch_jwks( string $api_base_url, string $cache_key, ?string $trusted_host = null ): ?array {
		$group    = 'bday_aero_paywall';
		$full_key = 'aero_paywall:' . $cache_key;

		$cached = wp_cache_get( $full_key, $group );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		if ( ! wp_using_ext_object_cache() ) {
			$cached = get_transient( $full_key );
			if ( is_array( $cached ) ) {
				wp_cache_set( $full_key, $cached, $group, self::CACHE_TTL );
				return $cached;
			}
		}

		// A configured trust anchor always wins over the DB-editable base
		// URL — the entire point of Platform Audit G2's fix is that this
		// choice must not be something wp_options write access can steer.
		$origin = ( null !== $trusted_host && '' !== $trusted_host )
			? 'https://' . $trusted_host
			: self::origin_of( $api_base_url );
		if ( '' === $origin ) {
			return null;
		}
		$response = wp_remote_get( $origin . '/.well-known/jwks.json', array( 'timeout' => 5 ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null; // deliberately not cached — retried fresh on the very next call
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return null;
		}

		wp_cache_set( $full_key, $body, $group, self::CACHE_TTL );
		if ( ! wp_using_ext_object_cache() ) {
			set_transient( $full_key, $body, self::CACHE_TTL );
		}
		return $body;
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
