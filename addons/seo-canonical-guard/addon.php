<?php
/**
 * Addon Name: SEO Canonical Guard
 * Addon Slug: seo-canonical-guard
 * Description: Stops Yoast from publishing a "?p=123" link as an article's canonical URL, og:url and schema URL. On live, a freshly published article's first render (which Cloudflare then caches for up to an hour, exactly while Google News is crawling it) named https://businessday.ng/?p=ID as its canonical — a URL that only 301s back to the article. This swaps that for the article's real permalink.
 * Default: on
 *
 * What was seen on businessday.ng (2026-11-25): 11 of the 18 newest
 * articles in news-sitemap.xml served
 *
 *   <link rel="canonical" href="https://businessday.ng/?p=1226155" />
 *
 * plus the same ?p= URL in og:url and in every @id/url of the NewsArticle
 * schema graph. A cache-busted render of the same article was already
 * correct, and the cached copies had been stored ~20s after publish. So
 * Yoast's stored data for a post (its "indexable") still holds the
 * draft-era permalink — WordPress's get_permalink() returns ?p=ID for any
 * post that isn't published yet — at the moment of the first public
 * request, and Cloudflare APO then serves that render for max-age=3600.
 * Yoast corrects its own data a little later; the cached page does not.
 *
 * This add-on doesn't try to change when Yoast refreshes its data (that's
 * Yoast internals, and the timing depends on how a post was published).
 * It corrects the output instead: whenever Yoast is about to print a
 * canonical / og:url / schema URL that is the plain-query form of a
 * published post's own address, it's replaced with that post's real
 * permalink. Any other URL, any unpublished post (previews really do live
 * at ?p=ID), and every post whose data is already correct pass through
 * untouched, so this is a no-op on the steady state.
 *
 * Uses the post id Yoast passes alongside each filter (presentation /
 * schema context) rather than the main query, so the same fix also covers
 * Yoast's REST `yoast_head` output, where is_singular() isn't set.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The real permalink of a published post, or '' when there is nothing
 * safe to swap in (unpublished, missing, or a site without pretty links).
 */
function bday_seo_guard_permalink( int $post_id ): string {
	static $cache = array();

	if ( $post_id <= 0 ) {
		return '';
	}
	if ( isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}

	$permalink = '';
	if ( 'publish' === get_post_status( $post_id ) ) {
		$link = get_permalink( $post_id );
		// With plain permalinks the ?p= form *is* the real address — leave it.
		if ( is_string( $link ) && '' !== $link && false === strpos( $link, '?' ) ) {
			$permalink = $link;
		}
	}

	$cache[ $post_id ] = $permalink;
	return $permalink;
}

/**
 * If $url is this site's plain-query address for $post_id (?p=ID,
 * ?page_id=ID or ?post_type=X&p=ID, with an optional #fragment such as
 * Yoast's "#article" / "#primaryimage"), return the real permalink with
 * the same fragment. Otherwise return $url unchanged.
 *
 * @param mixed $url
 * @return mixed
 */
function bday_seo_guard_fix_url( $url, int $post_id ) {
	if ( ! is_string( $url ) || false === strpos( $url, '?' ) ) {
		return $url;
	}

	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || empty( $parts['query'] ) ) {
		return $url;
	}

	$home = wp_parse_url( home_url( '/' ) );
	if ( strtolower( (string) ( $parts['host'] ?? '' ) ) !== strtolower( (string) ( $home['host'] ?? '' ) ) ) {
		return $url;
	}
	if ( trim( (string) ( $parts['path'] ?? '' ), '/' ) !== trim( (string) ( $home['path'] ?? '' ), '/' ) ) {
		return $url;
	}

	parse_str( $parts['query'], $query );
	$url_id = $query['p'] ?? ( $query['page_id'] ?? null );
	if ( ! is_scalar( $url_id ) || (string) (int) $url_id !== (string) $url_id || (int) $url_id !== $post_id ) {
		return $url;
	}

	$permalink = bday_seo_guard_permalink( $post_id );
	if ( '' === $permalink ) {
		return $url;
	}

	return isset( $parts['fragment'] ) ? $permalink . '#' . $parts['fragment'] : $permalink;
}

/**
 * The post id a Yoast filter call is about: from the presentation/context
 * object Yoast passes, else the main query's singular post.
 *
 * @param mixed $source Yoast Indexable_Presentation or Meta_Tags_Context.
 */
function bday_seo_guard_post_id( $source ): int {
	$indexable = null;
	if ( is_object( $source ) ) {
		if ( isset( $source->model ) && is_object( $source->model ) ) {
			$indexable = $source->model; // Indexable_Presentation.
		} elseif ( isset( $source->indexable ) && is_object( $source->indexable ) ) {
			$indexable = $source->indexable; // Meta_Tags_Context.
		}
	}

	if ( null !== $indexable ) {
		return ( 'post' === ( $indexable->object_type ?? '' ) ) ? (int) ( $indexable->object_id ?? 0 ) : 0;
	}

	return is_singular() ? (int) get_queried_object_id() : 0;
}

/** Recursively applies bday_seo_guard_fix_url() to every string in a schema graph. */
function bday_seo_guard_fix_graph( $node, int $post_id ) {
	if ( is_array( $node ) ) {
		foreach ( $node as $key => $value ) {
			$node[ $key ] = bday_seo_guard_fix_graph( $value, $post_id );
		}
		return $node;
	}
	return bday_seo_guard_fix_url( $node, $post_id );
}

$bday_seo_guard_single_url = static function ( $url, $presentation = null ) {
	return bday_seo_guard_fix_url( $url, bday_seo_guard_post_id( $presentation ) );
};
add_filter( 'wpseo_canonical', $bday_seo_guard_single_url, 20, 2 );
add_filter( 'wpseo_opengraph_url', $bday_seo_guard_single_url, 20, 2 );

add_filter(
	'wpseo_schema_graph',
	static function ( $graph, $context = null ) {
		$post_id = bday_seo_guard_post_id( $context );
		if ( $post_id <= 0 || ! is_array( $graph ) || '' === bday_seo_guard_permalink( $post_id ) ) {
			return $graph;
		}
		return bday_seo_guard_fix_graph( $graph, $post_id );
	},
	20,
	2
);
