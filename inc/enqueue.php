<?php
/**
 * Real asset enqueueing against Vite's hashed build output — replaces the
 * old pattern of CSS/JS wrapped in .php files and include()'d/require_once()'d
 * directly into every page's <head> (header.php used to do this for
 * style.php, responsive.php, and a hand-vendored jquery.php). That pattern
 * bypassed HTTP caching entirely: every single pageview re-downloaded the
 * full CSS/JS as part of the HTML response, with no browser cache, no CDN
 * cache, and no 304s — ever. wp_enqueue_* gives real external, cacheable,
 * content-hashed files instead.
 *
 * Requires `npm run build` (see package.json / vite.config.js) to have
 * produced assets/build/.vite/manifest.json. Falls back to the theme
 * version string (unhashed) if the manifest is missing, so a missed build
 * step fails loud (broken/stale asset) rather than silently serving old
 * PHP-inlined output.
 */

/**
 * Reads Vite's manifest.json once per request and returns the hashed
 * filename for a given source entry, or null if the manifest / entry is
 * missing.
 */
function bd_vite_asset( string $entry ): ?string {
	static $manifest = null;

	if ( null === $manifest ) {
		$manifest_path = get_template_directory() . '/assets/build/.vite/manifest.json';
		$manifest      = file_exists( $manifest_path )
			? json_decode( file_get_contents( $manifest_path ), true )
			: [];
	}

	return isset( $manifest[ $entry ]['file'] )
		? get_template_directory_uri() . '/assets/build/' . $manifest[ $entry ]['file']
		: null;
}

add_action( 'wp_enqueue_scripts', 'bd_enqueue_theme_assets' );
function bd_enqueue_theme_assets(): void {
	$css = bd_vite_asset( 'assets/src/scss/main.scss' );
	if ( $css ) {
		wp_enqueue_style( 'bday-main', $css, [], null );
	}

	// WordPress's own bundled jQuery, global ($ and jQuery both available)
	// — replaces the hand-vendored assets/jquery.php that was include()'d
	// as inline PHP (jQuery 3.7.0 itself, ~87KB, re-served uncached on
	// every single pageview).
	wp_enqueue_script( 'jquery' );

	$js = bd_vite_asset( 'assets/src/js/script.js' );
	if ( $js ) {
		wp_enqueue_script( 'bday-script', $js, [ 'jquery' ], null, true );
	}
}

/**
 * script.js is built as an ES module (see vite.config.js — Rollup's 'iife'
 * output format doesn't support the multiple entry points this build has),
 * so its <script> tag needs type="module". wp_enqueue_script() itself has
 * no module-type argument prior to WP 6.5's wp_enqueue_script_module(); this
 * filter is the standard, broadly-compatible way plugins/themes add
 * type="module" to a specific enqueued handle's tag.
 */
add_filter( 'script_loader_tag', 'bd_add_module_type_to_script_tag', 10, 2 );
function bd_add_module_type_to_script_tag( string $tag, string $handle ): string {
	if ( 'bday-script' !== $handle ) {
		return $tag;
	}
	return str_replace( ' src=', ' type="module" src=', $tag );
}
