<?php
/**
 * Resolves a subscription-service reader identity to a local WP user,
 * creating one if none is linked yet. Links via user meta
 * `_aero_paywall_user_id`; refuses to link onto an existing account that
 * already has publishing capabilities (an email collision with a staff
 * account should never silently grant a reader session on it).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Wp_User_Resolver {

	public static function find_or_create( string $aero_user_id, string $email, string $first_name, string $last_name ): ?WP_User {
		$existing = get_users(
			array(
				'meta_key'   => '_aero_paywall_user_id',
				'meta_value' => $aero_user_id,
				'number'     => 1,
			)
		);
		if ( ! empty( $existing ) ) {
			return $existing[0];
		}

		$by_email = get_user_by( 'email', $email );
		if ( $by_email && $by_email->has_cap( 'edit_posts' ) ) {
			return null; // refuse to silently attach a reader identity to a staff account
		}
		if ( $by_email ) {
			update_user_meta( $by_email->ID, '_aero_paywall_user_id', $aero_user_id );
			return $by_email;
		}

		$username = self::unique_username( $email );
		$user_id  = wp_insert_user(
			array(
				'user_login' => $username,
				'user_email' => $email,
				'user_pass'  => wp_generate_password( 32, true, true ),
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'role'       => 'subscriber',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return null;
		}

		update_user_meta( $user_id, '_aero_paywall_user_id', $aero_user_id );
		return get_user_by( 'id', $user_id );
	}

	private static function unique_username( string $email ): string {
		$base = sanitize_user( current( explode( '@', $email ) ), true );
		$base = '' !== $base ? $base : 'reader';
		$username = $base;
		$suffix   = 1;
		while ( username_exists( $username ) ) {
			$username = $base . $suffix;
			++$suffix;
		}
		return $username;
	}
}
