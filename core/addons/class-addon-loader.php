<?php
/**
 * Discovers add-ons under addons/{slug}/addon.php and only require()s the
 * ones that are enabled. This is the actual enforcement mechanism behind "no
 * query runs unconditionally": a disabled add-on's code is never loaded,
 * so it structurally cannot run a query, register a shortcode, or enqueue
 * anything — not a runtime check that could be forgotten, a file that
 * simply isn't there.
 *
 * addon.php's header comment is parsed with WordPress core's own
 * get_file_data() — the same mechanism core uses for theme/plugin/page-
 * template headers — so no custom parser is needed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Addon_Loader {

	private const STATE_OPTION = 'bday_addon_states';

	/** @var array<string, array<string, string>>|null */
	private static ?array $registry = null;

	/** @return array<string, array<string, string>> slug => header fields */
	public static function discover(): array {
		if ( null !== self::$registry ) {
			return self::$registry;
		}

		self::$registry = array();
		$files          = glob( get_template_directory() . '/addons/*/addon.php' );

		foreach ( (array) $files as $file ) {
			$headers = get_file_data(
				$file,
				array(
					'name'        => 'Addon Name',
					'slug'        => 'Addon Slug',
					'namespace'   => 'Cache Namespace',
					'tab'         => 'Settings Tab',
					'default'     => 'Default',
					'description' => 'Description',
				)
			);

			if ( '' === $headers['slug'] ) {
				continue;
			}

			$headers['file'] = $file;
			self::$registry[ $headers['slug'] ] = $headers;
		}

		return self::$registry;
	}

	/** @return array<string, bool> slug => enabled */
	public static function states(): array {
		$saved = get_option( self::STATE_OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();

		$states = array();
		foreach ( self::discover() as $slug => $meta ) {
			$states[ $slug ] = array_key_exists( $slug, $saved )
				? (bool) $saved[ $slug ]
				: ( 'on' === $meta['default'] );
		}
		return $states;
	}

	public static function is_enabled( string $slug ): bool {
		$states = self::states();
		return $states[ $slug ] ?? false;
	}

	/** Boots every enabled add-on by requiring its addon.php exactly once. */
	public static function boot(): void {
		$states = self::states();
		foreach ( self::discover() as $slug => $meta ) {
			if ( empty( $states[ $slug ] ) ) {
				continue;
			}
			require_once $meta['file'];
			do_action( 'bday_addon_loaded', $slug );
		}
	}

	/** @param array<string, bool> $states */
	public static function save_states( array $states ): void {
		update_option( self::STATE_OPTION, $states );
	}
}
