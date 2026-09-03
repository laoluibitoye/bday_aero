<?php
/**
 * Per-tab role visibility for the theme's settings framework. Every tab
 * (core, add-on, and the standalone AeroPaywall admin screen) is gated by a
 * synthesized capability `bday_view_settings_tab_{slug}`, resolved here via
 * `map_meta_cap` against a stored `[slug => role[]]` map rather than a role
 * ever being granted the capability directly — administrators always pass
 * regardless of the map, and the `access-control` slug (this feature's own
 * settings tab) always requires `manage_options`, ignoring the map
 * entirely, so no role can grant itself broader access.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Settings_Visibility {

	public const OPTION            = 'bday_settings_tab_roles';
	private const CAP_PREFIX       = 'bday_view_settings_tab_';
	public const ADMIN_ONLY_SLUG   = 'access-control';

	public static function init(): void {
		add_filter( 'map_meta_cap', array( self::class, 'map_meta_cap' ), 10, 4 );
	}

	public static function capability_for( string $slug ): string {
		return self::CAP_PREFIX . $slug;
	}

	/** @return array<string, string[]> slug => role slugs */
	public static function map(): array {
		$map = get_option( self::OPTION, array() );
		return is_array( $map ) ? $map : array();
	}

	/** @return string[] */
	public static function roles_for( string $slug ): array {
		$map = self::map();
		return is_array( $map[ $slug ] ?? null ) ? $map[ $slug ] : array();
	}

	/**
	 * @param mixed $input
	 * @return array<string, string[]>
	 */
	public static function sanitize( $input ): array {
		$input       = is_array( $input ) ? $input : array();
		$valid_roles = array_keys( wp_roles()->get_names() );
		$output      = array();

		foreach ( $input as $slug => $roles ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' === $slug || self::ADMIN_ONLY_SLUG === $slug ) {
				continue;
			}
			$roles = is_array( $roles ) ? array_map( 'sanitize_key', $roles ) : array();
			$roles = array_values( array_intersect( $valid_roles, $roles ) );
			if ( ! empty( $roles ) ) {
				$output[ $slug ] = $roles;
			}
		}

		return $output;
	}

	/**
	 * @param string[] $caps
	 * @param string   $cap
	 * @param int      $user_id
	 * @return string[]
	 */
	public static function map_meta_cap( array $caps, string $cap, int $user_id, array $args ): array {
		if ( 0 !== strpos( $cap, self::CAP_PREFIX ) ) {
			return $caps;
		}

		if ( ! $user_id ) {
			return array( 'do_not_allow' );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array( 'do_not_allow' );
		}

		if ( in_array( 'administrator', (array) $user->roles, true ) || user_can( $user_id, 'manage_options' ) ) {
			return array( 'exist' );
		}

		$slug = substr( $cap, strlen( self::CAP_PREFIX ) );
		if ( self::ADMIN_ONLY_SLUG === $slug ) {
			return array( 'do_not_allow' );
		}

		$allowed = self::roles_for( $slug );
		return array_intersect( $allowed, (array) $user->roles ) ? array( 'exist' ) : array( 'do_not_allow' );
	}
}

Bday_Settings_Visibility::init();
