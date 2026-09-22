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
	 * How long a "someone's already computing this" lock is held, and how
	 * many times/how long a request that missed the lock waits before
	 * giving up and computing anyway. Added 2026-09-22 after an RDS
	 * resource-spike incident: without this, every concurrent request that
	 * hit a namespace:key right as it expired independently re-ran the
	 * same expensive query — with several `bday_get_posts()` call sites
	 * across the theme all expiring on short TTLs under real reader
	 * traffic, that repeated dozens of times a minute and was a top
	 * contributor to sustained DB load. Short and bounded on purpose: a
	 * slow producer should never make other requests wait long, just long
	 * enough that most concurrent misses resolve to one shared computation.
	 */
	private const LOCK_TTL           = 10;
	private const LOCK_POLL_ATTEMPTS = 3;
	private const LOCK_POLL_INTERVAL_US = 50000;

	/**
	 * @param string   $namespace Addon/module slug, e.g. 'live_match', 'homepage'.
	 * @param string   $key       Cache key local to the namespace.
	 * @param callable $producer  Zero-arg closure; only invoked on a cache miss.
	 * @param int      $ttl       Seconds. No default on purpose — pick deliberately.
	 * @return mixed
	 */
	public static function remember( string $namespace, string $key, callable $producer, int $ttl ) {
		// The generation suffix lets bump_generation() invalidate every
		// entry in a namespace at once (e.g. on publish/update) without
		// knowing individual hashed-args keys in advance — see that
		// method's docblock. Namespaces that never call it just stay on
		// generation 1 forever, so this is a no-op for them.
		$full_key = $namespace . ':' . self::generation( $namespace ) . ':' . $key;
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

		// Stampede guard: wp_cache_add() only succeeds for the first
		// request to reach a given miss, so it doubles as an atomic lock.
		// Everyone else polls briefly for the value that request is about
		// to write, instead of every concurrent request independently
		// re-running $producer().
		$lock_key = 'lock:' . $full_key;
		$got_lock = wp_cache_add( $lock_key, 1, $group, self::LOCK_TTL );

		if ( ! $got_lock ) {
			for ( $i = 0; $i < self::LOCK_POLL_ATTEMPTS; $i++ ) {
				usleep( self::LOCK_POLL_INTERVAL_US );
				$value = wp_cache_get( $full_key, $group );
				if ( false !== $value ) {
					return $value;
				}
			}
			// Lock holder still hasn't written a value — compute directly
			// rather than block this request any further.
		}

		$value = $producer();

		wp_cache_set( $full_key, $value, $group, $ttl );
		if ( ! wp_using_ext_object_cache() ) {
			set_transient( $full_key, $value, $ttl );
		}
		if ( $got_lock ) {
			wp_cache_delete( $lock_key, $group );
		}

		return $value;
	}

	/**
	 * Current generation number for a namespace — folded into every
	 * remember() key in it. Backed by a (non-autoloaded) option rather
	 * than the object cache alone so it survives even without a
	 * persistent object cache, and so a Redis restart can't silently
	 * reset every namespace's freshness guarantee at once.
	 */
	public static function generation( string $namespace ): int {
		return (int) get_option( 'bday_cache_gen_' . $namespace, 1 );
	}

	/**
	 * Invalidates every remember() entry in $namespace in one call, by
	 * moving all its keys onto a new generation — the old ones are simply
	 * never read again (they expire out of the object cache/transients on
	 * their own TTL). Use this for hashed-args caches (bday_get_posts()
	 * listings, etc.) where forget() can't target a specific key because
	 * the key is an md5 of args not known in advance; see save_post hook
	 * below for the actual invalidation trigger.
	 */
	public static function bump_generation( string $namespace ): void {
		$option = 'bday_cache_gen_' . $namespace;
		update_option( $option, self::generation( $namespace ) + 1, false );
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
	 * Never a blanket cache flush. Must build the same generation-suffixed
	 * key remember() does, or this silently deletes a key that was never
	 * actually written and the real entry lives on untouched.
	 */
	public static function forget( string $namespace, string $key ): void {
		$full_key = $namespace . ':' . self::generation( $namespace ) . ':' . $key;
		wp_cache_delete( $full_key, 'bday_' . $namespace );
		if ( ! wp_using_ext_object_cache() ) {
			delete_transient( $full_key );
		}
	}
}

/**
 * Bumps the generation for every cache namespace a given post type's
 * listings can appear in, on publish/update — keeps bday_get_posts()
 * listing caches (homepage rails, "Read Also"/"You Might Also Like", the
 * breaking ticker, Today's Paper) fresh without relying on the short TTLs
 * that, under real reader traffic, were recomputing dozens of times a
 * minute and became a top contributor to an RDS resource-spike incident
 * (2026-09-22). Deliberately broad (a podcast save also bumps 'homepage',
 * which doesn't show podcasts) rather than an exact per-namespace map —
 * editorial publish/update frequency is far lower than reader-traffic
 * frequency, so a little over-invalidation here is cheap; getting the
 * mapping subtly wrong and leaving something stale is not.
 *
 * Audited 2026-09-23 against every cache_namespace value actually used
 * with bday_get_posts()/Bday_Query_Cache theme-wide (grep
 * cache_namespace across the repo to re-verify if a new one is added) —
 * the original 2026-09-22 mapping only covered post/bday_video/podcast/
 * events and missed 'sections', 'news_carousel', 'e_edition' (all
 * populated by post_type => 'post', same as 'article'/'homepage') and
 * the bday_edition/cartoons post types entirely (which populate
 * 'homepage', 'todays_paper', 'editions', and 'cartoons' but weren't
 * wired to bump anything). 'podcast' vs 'podcasts' (plural) are two
 * separate existing namespaces for the same post type — a pre-existing
 * naming inconsistency between single-podcast.php and
 * taxonomy-podcast_series.php, not something introduced here; both are
 * bumped together since fixing the inconsistency itself is a separate,
 * lower-priority cleanup.
 */
add_action(
	'save_post',
	static function ( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$namespaces_by_type = array(
			'post'         => array( 'core', 'homepage', 'article', 'breaking_ticker', 'todays_paper', 'sections', 'news_carousel', 'e_edition' ),
			'bday_video'   => array( 'videos', 'homepage' ),
			'podcast'      => array( 'podcast', 'podcasts', 'homepage' ),
			'events'       => array( 'homepage', 'events' ),
			'cartoons'     => array( 'homepage', 'cartoons' ),
			'bday_edition' => array( 'homepage', 'todays_paper', 'editions' ),
		);
		foreach ( $namespaces_by_type[ $post->post_type ] ?? array() as $namespace ) {
			Bday_Query_Cache::bump_generation( $namespace );
		}
	},
	10,
	2
);
