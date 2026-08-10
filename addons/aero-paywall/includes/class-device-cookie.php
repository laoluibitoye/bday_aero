<?php
/**
 * Anonymous-reader device identity — same cookie name/TTL/generation as
 * the retired connector-plugin, so an existing reader's meter progress
 * isn't reset by the cutover from plugin to native add-on.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Device_Cookie {

	private const COOKIE_NAME = 'aero_paywall_device_id';
	private const TTL         = 2 * YEAR_IN_SECONDS;

	public function __construct() {
		add_action( 'init', array( $this, 'maybe_set' ), 5 );
	}

	public function maybe_set(): void {
		if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) && '' !== $_COOKIE[ self::COOKIE_NAME ] ) {
			return;
		}
		if ( headers_sent() ) {
			return;
		}
		$id = wp_generate_uuid4();
		setcookie( self::COOKIE_NAME, $id, time() + self::TTL, '/' );
		$_COOKIE[ self::COOKIE_NAME ] = $id;
	}

	public static function get(): ?string {
		$id = $_COOKIE[ self::COOKIE_NAME ] ?? null;
		return is_string( $id ) && '' !== $id ? $id : null;
	}
}
