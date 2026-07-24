<?php
/**
 * Vite-manifest-based asset enqueueing. No jQuery dependency (the previous
 * theme's script.js required jQuery, then a separate hook deregistered
 * jQuery after enqueueing it — a real, reproducible bug). This rebuild's
 * script.js is dependency-free vanilla JS, so there's nothing to
 * deregister and nothing that can go stale-load-order wrong.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bday_vite_asset( string $entry ): ?string {
	static $manifest = null;

	if ( null === $manifest ) {
		$manifest_path = BDAY_THEME_DIR . 'assets/build/.vite/manifest.json';
		$manifest      = file_exists( $manifest_path )
			? json_decode( (string) file_get_contents( $manifest_path ), true )
			: array();
	}

	return isset( $manifest[ $entry ]['file'] ) ? BDAY_THEME_URI . 'assets/build/' . $manifest[ $entry ]['file'] : null;
}

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$css = bday_vite_asset( 'assets/src/scss/main.scss' );
		if ( $css ) {
			wp_enqueue_style( 'bday-main', $css, array(), null );
		}

		$js = bday_vite_asset( 'assets/src/js/script.js' );
		if ( $js ) {
			wp_enqueue_script( 'bday-script', $js, array(), null, true );
		}
	}
);

add_filter(
	'script_loader_tag',
	static function ( string $tag, string $handle ): string {
		if ( 'bday-script' !== $handle ) {
			return $tag;
		}
		return str_replace( ' src=', ' type="module" src=', $tag );
	},
	10,
	2
);
