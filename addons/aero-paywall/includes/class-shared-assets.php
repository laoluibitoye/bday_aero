<?php
/**
 * Enqueues the plain (non-Vite) tokens.css + shared-admin.css stylesheet
 * pair on the two admin surfaces that live outside the React settings app
 * — the post-edit metabox (class-premium-map.php) and the posts-list
 * badge column (class-post-list-badge.php). Neither of those screens
 * loads the settings app's bundle, so without this the design tokens and
 * metabox/badge styling below them would never reach those screens.
 *
 * Enqueued as static files, not through class-vite-assets.php's manifest
 * — they need no build step (plain CSS, no imports/preprocessing).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Shared_Assets {

	private const HANDLE = 'bday-aero-shared-admin';

	public static function enqueue(): void {
		if ( wp_style_is( self::HANDLE, 'enqueued' ) ) {
			return;
		}

		$tokens_path = get_template_directory() . '/assets/src/css/tokens.css';
		$shared_path = get_template_directory() . '/assets/src/css/shared-admin.css';

		wp_enqueue_style(
			self::HANDLE . '-tokens',
			get_template_directory_uri() . '/assets/src/css/tokens.css',
			array(),
			file_exists( $tokens_path ) ? (string) filemtime( $tokens_path ) : null
		);

		wp_enqueue_style(
			self::HANDLE,
			get_template_directory_uri() . '/assets/src/css/shared-admin.css',
			array( self::HANDLE . '-tokens' ),
			file_exists( $shared_path ) ? (string) filemtime( $shared_path ) : null
		);
	}
}
