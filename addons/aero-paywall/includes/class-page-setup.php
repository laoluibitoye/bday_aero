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

	// find_marked_page() runs on reader-facing pages through url_for() (the SDK config on every
	// page, plus the subscribe, Today's Paper and e-edition templates), so its postmeta lookup goes
	// through the query cache. Any page save/trash/delete drops the cached lookups, since that page
	// may be one of ours or have a new permalink; pages change rarely, so this costs little.
	private const CACHE_NAMESPACE = 'page_setup';
	private const CACHE_TTL       = 12 * HOUR_IN_SECONDS;

	public function __construct() {
		add_action( 'after_switch_theme', array( self::class, 'create_missing_pages' ) );
		add_action( 'wp_ajax_bday_aero_create_pages', array( $this, 'handle_create_pages' ) );
		add_action( 'save_post_page', array( self::class, 'flush_cache' ) );
		add_action( 'deleted_post', array( self::class, 'flush_cache_on_delete' ), 10, 2 );
	}

	public static function flush_cache(): void {
		Bday_Query_Cache::bump_generation( self::CACHE_NAMESPACE );
	}

	public static function flush_cache_on_delete( int $post_id, $post = null ): void {
		if ( $post instanceof WP_Post && 'page' === $post->post_type ) {
			self::flush_cache();
		}
	}

	/** @return array<string, array{label: string, content: string, template: string, slug?: string}> */
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
			'epaper_articles' => array(
				'label'    => 'E-Paper Articles',
				'content'  => '',
				'template' => 'templates/template-epaper-articles.php',
				// Explicit slug — the auto-generated one from this title
				// would be "e-paper-articles" (sanitize_title() hyphenates
				// every word), not the "epaper-articles" actually wanted.
				'slug'     => 'epaper-articles',
			),
			'newsletter'    => array(
				'label'    => 'Newsletter Opt-In',
				'content'  => '',
				'template' => 'templates/template-newsletter-opt-in.php',
			),
			'corporate_subscription' => array(
				'label'    => 'Corporate Subscription',
				'content'  => '',
				'template' => 'templates/template-corporate-subscription.php',
			),
		);
	}

	/** URL of one of the auto-created pages above, if it exists yet — used by template-subscribe.php's "Corporate Subscription" button. */
	public static function url_for( string $key ): ?string {
		$page = self::find_marked_page( $key );
		return $page ? get_permalink( $page ) : null;
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
		// Start from fresh lookups so a stale cached "not found" can never create a duplicate page,
		// and flush again afterwards: wp_insert_post() fires save_post before ensure_page() adds the
		// marker meta, so a lookup cached in between would otherwise miss the new page.
		self::flush_cache();
		foreach ( array_keys( self::definitions() ) as $key ) {
			self::ensure_page( $key );
		}
		self::flush_cache();
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
			array_filter(
				array(
					'post_title'   => $def['label'],
					'post_content' => $def['content'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_name'    => $def['slug'] ?? '',
				)
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
		$pages = Bday_Query_Cache::posts(
			self::CACHE_NAMESPACE,
			'marked:' . $key,
			array(
				'post_type'   => 'page',
				'post_status' => 'any',
				'numberposts' => 1,
				'meta_key'    => self::PAGE_MARKER_META_KEY,
				'meta_value'  => $key,
			),
			self::CACHE_TTL
		);
		return $pages[0] ?? null;
	}

	public function handle_create_pages(): void {
		check_ajax_referer( 'bday_aero_create_pages', 'nonce' );
		if ( ! current_user_can( Bday_Settings_Visibility::capability_for( 'aero-paywall' ) ) ) {
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
