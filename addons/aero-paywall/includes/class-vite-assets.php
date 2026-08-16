<?php
/**
 * Real asset enqueueing against Vite's hashed build output — mirrors
 * core/assets.php's own manifest-read-once, graceful-miss convention for
 * the main site bundle, applied here to the admin-app entry specifically.
 * Requires `npm run build` (vite.config.js) to have produced
 * assets/build/.vite/manifest.json — falls back to enqueueing nothing (a
 * visibly broken/empty admin screen) rather than a stale or guessed
 * filename if the manifest is missing, so a missed build step fails
 * loud, not silently-wrong.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Vite_Assets {

	/** @return array{file: string, css: string[]}|null */
	private static function manifest_entry( string $entry ): ?array {
		static $manifest = null;

		if ( null === $manifest ) {
			$manifest_path = get_template_directory() . '/assets/build/.vite/manifest.json';
			$manifest      = file_exists( $manifest_path )
				? json_decode( (string) file_get_contents( $manifest_path ), true )
				: array();
		}

		return isset( $manifest[ $entry ]['file'] ) ? $manifest[ $entry ] : null;
	}

	/**
	 * Enqueues both the JS entry (as an ES module — Vite's default output
	 * format) and any CSS Vite extracted for it, then localizes
	 * `$localized_object_name` with `$bootstrap_data` onto the script
	 * handle. `$entry` is the source path exactly as it appears as a
	 * vite.config.js rollupOptions.input key.
	 *
	 * @param array<string, mixed> $bootstrap_data
	 */
	public static function enqueue( string $entry, string $handle, array $bootstrap_data = array(), string $localized_object_name = 'aeroPaywallAdmin' ): void {
		$manifest_entry = self::manifest_entry( 'assets/src/js/' . $entry . '/main.tsx' );
		if ( null === $manifest_entry ) {
			return;
		}

		foreach ( $manifest_entry['css'] ?? array() as $css_file ) {
			wp_enqueue_style(
				$handle . '-' . basename( $css_file, '.css' ),
				get_template_directory_uri() . '/assets/build/' . $css_file,
				array(),
				null
			);
		}

		wp_enqueue_script(
			$handle,
			get_template_directory_uri() . '/assets/build/' . $manifest_entry['file'],
			array(),
			null,
			true
		);

		if ( ! empty( $bootstrap_data ) ) {
			wp_localize_script( $handle, $localized_object_name, $bootstrap_data );
		}

		add_filter(
			'script_loader_tag',
			static function ( string $tag, string $tag_handle ) use ( $handle ): string {
				if ( $tag_handle !== $handle ) {
					return $tag;
				}
				return str_replace( ' src=', ' type="module" src=', $tag );
			},
			10,
			2
		);
	}
}
