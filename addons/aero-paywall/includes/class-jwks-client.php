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

	/** @return array<string, mixed>|null decoded token claims, or null if invalid/unreachable */
	public static function verify( string $token, string $api_base_url, string $cache_key ): ?array {
		$jwks = self::fetch_jwks( $api_base_url, $cache_key );
		if ( null === $jwks ) {
			return null;
		}

		try {
			$key_set = JWK::parseKeySet( $jwks );
			$decoded = JWT::decode( $token, $key_set );
			return (array) $decoded;
		} catch ( \Throwable $e ) {
			return null;
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
