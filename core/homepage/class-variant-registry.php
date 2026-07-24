<?php
/**
 * Homepage variant auto-discovery. A new variant is one new file dropped
 * into homepage-variants/ with a 3-field header comment — nothing else
 * needs to change, and there is no dashboard upload/execution surface: the
 * only way a variant enters the system is a file shipped in a deployed
 * release. Same get_file_data() mechanism as the add-on loader.
 *
 * The override -> weekday/weekend -> default resolution order is carried
 * over unchanged from the previous theme's homepage-variant system — only
 * the registry it resolves against (discovered files vs. a hardcoded
 * array) is new.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Variant_Registry {

	/** @var array<string, array<string, string>>|null */
	private static ?array $variants = null;

	/** @return array<string, array<string, string>> slug => {label, description, file} */
	public static function discover(): array {
		if ( null !== self::$variants ) {
			return self::$variants;
		}

		self::$variants = array();
		$files          = glob( get_template_directory() . '/homepage-variants/*.php' );

		foreach ( (array) $files as $file ) {
			$headers = get_file_data(
				$file,
				array(
					'name'        => 'Variant Name',
					'slug'        => 'Variant Slug',
					'description' => 'Description',
				)
			);

			if ( '' === $headers['slug'] ) {
				continue;
			}

			self::$variants[ $headers['slug'] ] = array(
				'label'       => '' !== $headers['name'] ? $headers['name'] : $headers['slug'],
				'description' => $headers['description'],
				'file'        => $file,
			);
		}

		return self::$variants;
	}

	public static function active_slug(): string {
		$variants = self::discover();
		$settings = get_option( 'bday_homepage_variant_settings', array() );
		$override = is_array( $settings ) ? ( $settings['override'] ?? 'auto' ) : 'auto';

		if ( 'auto' !== $override && isset( $variants[ $override ] ) ) {
			return $override;
		}

		$day_of_week = (int) wp_date( 'N' );
		if ( $day_of_week >= 6 && isset( $variants['weekend'] ) ) {
			return 'weekend';
		}

		return isset( $variants['default'] ) ? 'default' : (string) array_key_first( $variants );
	}

	public static function render_active(): void {
		$variants = self::discover();
		$slug     = self::active_slug();

		if ( ! isset( $variants[ $slug ] ) ) {
			return;
		}

		load_template( $variants[ $slug ]['file'], false );
	}
}
