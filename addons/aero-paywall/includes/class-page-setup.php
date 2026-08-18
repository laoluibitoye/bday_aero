<?php
/**
 * Reader-requested: auto-created pages for every page the theme depends
 * on to work at all, plus a "Create pages" button in the React admin app
 * (Dashboard tab) to (re)run the same creation on demand — not just a
 * one-shot on theme activation. Runs on `after_switch_theme` (the theme
 * equivalent of a plugin's activation hook) AND is exposed as an AJAX
 * action, since a theme can be activated once long before AeroPaywall
 * itself is ever configured/enabled, and an admin needs a way to create
 * (or re-create, if one was deleted) these pages afterward without a
 * wp-cli/database detour.
 *
 * Each page is tracked by a post-meta marker (not by title/slug), same
 * posture as the retired connector-plugin's class-activator.php — an
 * admin is free to rename/move a page afterward without this losing
 * track of it or creating a duplicate on the next run.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Page_Setup {

	private const PAGE_MARKER_META_KEY = '_bday_aero_auto_page';

	public function __construct() {
		add_action( 'after_switch_theme', array( self::class, 'create_missing_pages' ) );
		add_action( 'wp_ajax_bday_aero_create_pages', array( $this, 'handle_create_pages' ) );
	}

	/** @return array<string, array{label: string, content: string, template: string}> */
	private static function definitions(): array {
		return array(
			'account'       => array(
				'label'    => 'My Account',
				'content'  => '[aeropaywall_account]',
				'template' => '',
			),
			'subscribe'     => array(
				'label'    => 'Subscribe',
				'content'  => '[aeropaywall_account tab="subscribe"]',
				'template' => 'templates/template-subscribe.php',
			),
			'todays_paper'  => array(
				'label'    => "Today's Paper",
				'content'  => '',
				'template' => 'templates/template-todays-paper.php',
			),
			'todays_epaper' => array(
				'label'    => 'Todays Epaper',
				'content'  => '',
				'template' => 'templates/todays-epaper.php',
			),
			'newsletter'    => array(
				'label'    => 'Newsletter Opt-In',
				'content'  => '',
				'template' => 'templates/template-newsletter-opt-in.php',
			),
		);
	}

	/** @return array<int, array{key: string, label: string, exists: bool, url: string|null}> */
	public static function status(): array {
		$rows = array();
		foreach ( self::definitions() as $key => $def ) {
			$page = self::find_marked_page( $key );
			$rows[] = array(
				'key'    => $key,
				'label'  => $def['label'],
				'exists' => null !== $page,
				'url'    => $page ? get_permalink( $page ) : null,
			);
		}
		return $rows;
	}

	public static function create_missing_pages(): void {
		foreach ( array_keys( self::definitions() ) as $key ) {
			self::ensure_page( $key );
		}
	}

	/** Idempotent: a page already marked (or an explicit admin choice already on record for 'account') is never re-created or overridden. */
	private static function ensure_page( string $key ): ?WP_Post {
		$existing = self::find_marked_page( $key );
		if ( $existing ) {
			return $existing;
		}

		$def = self::definitions()[ $key ] ?? null;
		if ( null === $def ) {
			return null;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => $def['label'],
				'post_content' => $def['content'],
				'post_status'  => 'publish',
				'post_type'    => 'page',
			),
			true
		);
		if ( is_wp_error( $page_id ) || ! $page_id ) {
			return null;
		}

		update_post_meta( $page_id, self::PAGE_MARKER_META_KEY, $key );
		if ( '' !== $def['template'] ) {
			update_post_meta( $page_id, '_wp_page_template', $def['template'] );
		}

		if ( 'account' === $key && '' === Bday_Aero_Settings::account_page_url() ) {
			update_option( Bday_Aero_Settings::ACCOUNT_PAGE_URL, get_permalink( $page_id ) );
		}
		if ( 'subscribe' === $key && '' === Bday_Aero_Settings::subscribe_page_url() ) {
			update_option( Bday_Aero_Settings::SUBSCRIBE_PAGE_URL, get_permalink( $page_id ) );
		}

		return get_post( $page_id );
	}

	private static function find_marked_page( string $key ): ?WP_Post {
		$pages = get_posts(
			array(
				'post_type'   => 'page',
				'post_status' => 'any',
				'numberposts' => 1,
				'meta_key'    => self::PAGE_MARKER_META_KEY,
				'meta_value'  => $key,
			)
		);
		return $pages[0] ?? null;
	}

	public function handle_create_pages(): void {
		check_ajax_referer( 'bday_aero_create_pages', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'bday-aero' ) ), 403 );
			return;
		}

		self::create_missing_pages();

		wp_send_json_success(
			array(
				'pages'    => self::status(),
				'settings' => array(
					Bday_Aero_Settings::ACCOUNT_PAGE_URL   => Bday_Aero_Settings::account_page_url(),
					Bday_Aero_Settings::SUBSCRIBE_PAGE_URL => Bday_Aero_Settings::subscribe_page_url(),
				),
			)
		);
	}
}
