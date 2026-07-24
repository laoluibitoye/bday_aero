<?php
/**
 * The mandatory cache-wrapper every query in this theme goes through — no
 * WP_Query/get_posts/get_pages call may bypass this without a documented,
 * reviewed reason. Reads the object cache first, falls back to a transient
 * only where there's no persistent object cache (dev/staging; production
 * is required to have Redis/Memcached per the infra checklist), and always
 * writes through both on a miss.
 *
 * This exists because the previous theme's single worst finding was an
 * uncached WP_Query for a homepage-only feature (Live Match) running on
 * every single pageview site-wide. Wrapping every query the same way here
 * makes that class of bug structurally harder to reintroduce.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Query_Cache {

	/**
	 * @param string   $namespace Addon/module slug, e.g. 'live_match', 'homepage'.
	 * @param string   $key       Cache key local to the namespace.
	 * @param callable $producer  Zero-arg closure; only invoked on a cache miss.
	 * @param int      $ttl       Seconds. No default on purpose — pick deliberately.
	 * @return mixed
	 */
	public static function remember( string $namespace, string $key, callable $producer, int $ttl ) {
		$full_key = $namespace . ':' . $key;
		$group    = 'bday_' . $namespace;

		$value = wp_cache_get( $full_key, $group );
		if ( false !== $value ) {
			return $value;
		}

		if ( ! wp_using_ext_object_cache() ) {
			$value = get_transient( $full_key );
			if ( false !== $value ) {
				wp_cache_set( $full_key, $value, $group, $ttl );
				return $value;
			}
		}

		$value = $producer();

		wp_cache_set( $full_key, $value, $group, $ttl );
		if ( ! wp_using_ext_object_cache() ) {
			set_transient( $full_key, $value, $ttl );
		}

		return $value;
	}

	/** Thin wrapper for the common WP_Query case. */
	public static function query( string $namespace, string $key, array $args, int $ttl ): WP_Query {
		return self::remember(
			$namespace,
			$key,
			static function () use ( $args ) {
				return new WP_Query( $args );
			},
			$ttl
		);
	}

	/** Thin wrapper for the common get_posts() case. @return WP_Post[] */
	public static function posts( string $namespace, string $key, array $args, int $ttl ): array {
		return self::remember(
			$namespace,
			$key,
			static function () use ( $args ) {
				return get_posts( $args );
			},
			$ttl
		);
	}

	/**
	 * Invalidates one namespace:key entry — addons call this on save_post/
	 * option-update hooks that should freshen a specific cached value.
	 * Never a blanket cache flush.
	 */
	public static function forget( string $namespace, string $key ): void {
		wp_cache_delete( $namespace . ':' . $key, 'bday_' . $namespace );
		if ( ! wp_using_ext_object_cache() ) {
			delete_transient( $namespace . ':' . $key );
		}
	}
}
