<?php
/**
 * Homepage section auto-discovery — the same file-header-scanning idea as
 * class-variant-registry.php (glob a directory, read a header comment),
 * applied one level down: a *variant* picks which whole-page layout runs;
 * a *section* is one reorderable, enable/disable-able block inside the
 * "redesign" variant (homepage-variants/redesign.php). Adding a new
 * section is "drop a file into homepage-sections/" — no code elsewhere
 * has to change for it to become orderable/toggleable from wp-admin.
 *
 * A section file is rendered with get_template_part() (not load_template()
 * the way the variant registry renders variants) specifically so it goes
 * through the same $args-passing convention every existing homepage
 * template-part already relies on — see template-parts/homepage/hero.php's
 * docblock: this codebase's WP core does not extract get_template_part()'s
 * $args into local variables, so every section file must read
 * `$args['data'] ?? array()` itself, exactly like hero.php does.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Section_Registry {

	private const OPTION = 'bday_homepage_sections';

	/** @var array<string, array<string, mixed>>|null */
	private static ?array $sections = null;

	/** @return array<string, array{label: string, description: string, file: string, default_enabled: bool, part: string}> */
	public static function discover(): array {
		if ( null !== self::$sections ) {
			return self::$sections;
		}

		self::$sections = array();
		$files          = glob( get_template_directory() . '/homepage-sections/*.php' );

		foreach ( (array) $files as $file ) {
			$headers = get_file_data(
				$file,
				array(
					'name'        => 'Section Name',
					'slug'        => 'Section Slug',
					'description' => 'Description',
					'default'     => 'Default Enabled',
				)
			);

			if ( '' === $headers['slug'] ) {
				continue;
			}

			self::$sections[ $headers['slug'] ] = array(
				'label'           => '' !== $headers['name'] ? $headers['name'] : $headers['slug'],
				'description'     => $headers['description'],
				'file'            => $file,
				// Anything other than an explicit "no" defaults a newly
				// dropped-in section file to visible — matches the addon
				// loader's own "no config yet = show it" first-run posture.
				'default_enabled' => 'no' !== strtolower( trim( $headers['default'] ) ),
				'part'            => 'homepage-sections/' . basename( $file, '.php' ),
			);
		}

		return self::$sections;
	}

	/**
	 * Saved order + enabled state, reconciled against what's actually on
	 * disk: a slug in the saved option that no longer has a file is
	 * dropped silently (the file was removed in a later release), and a
	 * section file that exists but was never saved (a newly shipped
	 * section) is appended at the end using its own declared default —
	 * same "no admin action needed for new stuff to show up" posture as
	 * every other auto-discovery mechanism in this theme.
	 *
	 * @return array<int, string> ordered, enabled-only slugs
	 */
	public static function ordered_active(): array {
		$discovered = self::discover();
		$saved      = get_option( self::OPTION, array() );
		$saved      = is_array( $saved ) ? $saved : array();

		$ordered = array();
		$seen    = array();

		foreach ( $saved as $row ) {
			$slug = is_array( $row ) ? (string) ( $row['slug'] ?? '' ) : '';
			if ( '' === $slug || ! isset( $discovered[ $slug ] ) || isset( $seen[ $slug ] ) ) {
				continue;
			}
			$seen[ $slug ] = true;
			if ( ! empty( $row['enabled'] ) ) {
				$ordered[] = $slug;
			}
		}

		foreach ( $discovered as $slug => $meta ) {
			if ( ! isset( $seen[ $slug ] ) && $meta['default_enabled'] ) {
				$ordered[] = $slug;
			}
		}

		return $ordered;
	}

	/**
	 * Same reconciliation as ordered_active(), but returns every discovered
	 * section (enabled or not) in saved order, for the admin screen's row
	 * list — it needs to show disabled sections too, so an admin can turn
	 * them back on.
	 *
	 * @return array<int, array{slug: string, enabled: bool}>
	 */
	public static function ordered_all(): array {
		$discovered = self::discover();
		$saved      = get_option( self::OPTION, array() );
		$saved      = is_array( $saved ) ? $saved : array();

		$rows = array();
		$seen = array();

		foreach ( $saved as $row ) {
			$slug = is_array( $row ) ? (string) ( $row['slug'] ?? '' ) : '';
			if ( '' === $slug || ! isset( $discovered[ $slug ] ) || isset( $seen[ $slug ] ) ) {
				continue;
			}
			$seen[ $slug ] = true;
			$rows[]        = array( 'slug' => $slug, 'enabled' => ! empty( $row['enabled'] ) );
		}

		foreach ( $discovered as $slug => $meta ) {
			if ( ! isset( $seen[ $slug ] ) ) {
				$rows[] = array( 'slug' => $slug, 'enabled' => $meta['default_enabled'] );
			}
		}

		return $rows;
	}

	/** Renders every active section, in the admin-configured order, passing the same $data array to each. */
	public static function render_active( array $data ): void {
		$discovered = self::discover();

		foreach ( self::ordered_active() as $slug ) {
			if ( ! isset( $discovered[ $slug ] ) ) {
				continue;
			}
			get_template_part( $discovered[ $slug ]['part'], null, array( 'data' => $data ) );
		}
	}
}
