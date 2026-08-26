<?php
/**
 * wp-admin settings screen — a single React app mounted into one page,
 * covering every section (Dashboard, Setup Wizard, Connection,
 * Restrictions, Appearance, Paywall Copy, Advanced) that used to be four
 * separate server-rendered `<form action="options.php">` tabs.
 *
 * Reader-requested, in the strongest possible terms: this replaces that
 * plain-native-forms screen with the same React admin app the retired
 * connector-plugin already built and proved out (assets/src/js/admin-app/
 * — ported here near-verbatim, since the settings model is byte-identical
 * by design, see class-settings.php's own docblock), rather than
 * maintaining two parallel, drifting admin UIs. This class only does
 * three things: register the top-level menu, enqueue the built React
 * bundle with a bootstrap-data payload, and persist saves via one JSON
 * AJAX endpoint — all the actual field markup lives in
 * assets/src/js/admin-app/.
 *
 * Restriction rules (class-restriction-rules.php), the backend-enforced
 * metering knobs (class-connector-settings-client.php), and required-page
 * creation (class-page-setup.php) have their own dedicated AJAX
 * endpoints, called directly by their own tab/component — this class's
 * handle_save_settings() only covers the WP-local options everything else
 * in this file used to persist via register_setting().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Admin_Ui {

	private const PAGE_SLUG = 'aero-paywall';

	private const BOOL_FIELDS = array(
		Bday_Aero_Settings::ENABLED,
		Bday_Aero_Settings::ADFREE_ENABLED,
		Bday_Aero_Settings::JSONLD_ENABLED,
	);
	private const URL_FIELDS = array(
		Bday_Aero_Settings::API_BASE_URL,
		Bday_Aero_Settings::LICENSING_API_BASE_URL,
		Bday_Aero_Settings::SDK_CDN_BASE,
		Bday_Aero_Settings::ACCOUNT_PAGE_URL,
		Bday_Aero_Settings::SUBSCRIBE_PAGE_URL,
		Bday_Aero_Settings::LOGIN_PAGE_URL,
		Bday_Aero_Settings::REGISTER_PAGE_URL,
	);
	private const TEXT_FIELDS = array(
		Bday_Aero_Settings::API_KEY,
		Bday_Aero_Settings::LICENSE_KEY,
		Bday_Aero_Settings::SDK_VERSION,
		Bday_Aero_Settings::GOOGLE_CLIENT_ID,
		Bday_Aero_Settings::APPLE_CLIENT_ID,
	);

	/** @var array<string, mixed>|null */
	private ?array $connector_settings_snapshot = null;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
		add_action( 'wp_ajax_aero_paywall_test_connection', array( $this, 'handle_test_connection' ) );
		add_action( 'wp_ajax_aero_paywall_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_license_notice' ) );
	}

	/**
	 * Gating being off for ANY reason (not enabled, no license key,
	 * expired/revoked license, domain mismatch, licensing service
	 * unreachable) is otherwise invisible in wp-admin — is_active()'s
	 * fail-open behavior is intentional and unchanged by this notice, it
	 * just makes the resulting "everything is ungated" state visible
	 * instead of silent. Skipped while the dev-mode bypass notice
	 * (addon.php) is already showing — dev mode makes is_active() true
	 * (gating DOES run, just unverified), so it isn't a "gating is off"
	 * state and gets its own, differently-worded notice.
	 */
	public function maybe_render_license_notice(): void {
		if ( Bday_Aero_License_Client::is_dev_mode_bypass_active() ) {
			return;
		}

		$enabled = Bday_Aero_Settings::enabled();
		if ( $enabled && Bday_Aero_License_Client::is_active() ) {
			return;
		}

		Bday_Aero_Shared_Assets::enqueue();
		$connection_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '#connection' );

		if ( ! $enabled ) {
			$headline = esc_html__( 'AeroPaywall is not enabled', 'bday-aero' );
		} else {
			$headline = esc_html__( 'AeroPaywall is enabled but not licensed', 'bday-aero' );
		}

		echo '<div class="notice notice-warning"><p class="aero-admin-notice">'
			. '<span class="aero-admin-notice__icon" aria-hidden="true">&#128274;</span>'
			. '<span>' . $headline . '</span>'
			. '<span>&mdash; ' . self::inactive_reason_detail( $enabled ) . ' '
			. '<a href="' . esc_url( $connection_url ) . '">' . esc_html__( 'Review licensing settings', 'bday-aero' ) . '</a></span>'
			. '</p></div>';
	}

	/** Reason-specific wording for maybe_render_license_notice(); always ends by stating the site-wide reader impact plainly. */
	private static function inactive_reason_detail( bool $enabled ): string {
		$impact = esc_html__( 'content gating and the reader SDK are inactive — all content is rendering fully ungated, site-wide, for every visitor.', 'bday-aero' );

		if ( ! $enabled ) {
			return $impact;
		}

		switch ( Bday_Aero_License_Client::inactive_reason() ) {
			case 'no_key':
			case 'not_configured':
				return esc_html__( 'no license key is configured.', 'bday-aero' ) . ' ' . $impact;
			case 'domain_mismatch':
				return esc_html__( 'the license key is bound to a different domain.', 'bday-aero' ) . ' ' . $impact;
			case 'expired':
				return esc_html__( 'the license has expired or been revoked.', 'bday-aero' ) . ' ' . $impact;
			case 'api_unreachable':
			case 'invalid_signature':
				return esc_html__( 'the licensing service could not be reached or verified.', 'bday-aero' ) . ' ' . $impact;
			default:
				return $impact;
		}
	}

	public function register_menu(): void {
		add_menu_page(
			'AeroPaywall',
			'AeroPaywall',
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' ),
			self::menu_icon_data_uri(),
			62
		);
	}

	/** Same shield mark as the in-app logo (Icon.tsx's ShieldIcon) — one consistent brand identity, not a dashicon here and an unrelated mark there. */
	private static function menu_icon_data_uri(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none">'
			. '<path d="M12 2 4 5v6c0 5.25 3.4 9.5 8 11 4.6-1.5 8-5.75 8-11V5l-8-3z" fill="#f0f0f1"/>'
			. '<path d="M9 12.3l2 2 4-4.6" stroke="#1d2327" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" fill="none"/>'
			. '</svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	public function maybe_enqueue_assets( string $hook ): void {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		Bday_Aero_Vite_Assets::enqueue( 'admin-app', 'bday-aero-admin-app', $this->build_bootstrap_data() );
	}

	public function render(): void {
		echo '<div class="wrap">';
		$this->maybe_render_backend_unreachable_notice();
		echo '<div id="aero-paywall-admin-app"></div></div>';
	}

	private function maybe_render_backend_unreachable_notice(): void {
		$base_url = Bday_Aero_Settings::api_base_url();
		$api_key  = Bday_Aero_Settings::api_key();
		if ( '' === $base_url || '' === $api_key ) {
			return; // not configured yet — nothing to be unreachable
		}
		if ( ! empty( $this->get_connector_settings_snapshot() ) ) {
			return;
		}

		Bday_Aero_Shared_Assets::enqueue();
		echo '<div class="notice notice-warning"><p class="aero-admin-notice">'
			. '<span class="aero-admin-notice__icon" aria-hidden="true">&#9888;&#65039;</span>'
			. '<span>' . esc_html__( "Couldn't reach the Subscription Service just now", 'bday-aero' ) . '</span>'
			. '<span>&mdash; ' . esc_html__( 'metering settings below may be stale. Double-check the base URL and API key on the Connection tab.', 'bday-aero' ) . '</span>'
			. '</p></div>';
	}

	/** @return array<string, mixed> */
	private function build_bootstrap_data(): array {
		$restricted_post_types = Bday_Aero_Settings::restricted_post_types();

		return array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonces'  => array(
				'testConnection'    => wp_create_nonce( 'aero_paywall_test_connection' ),
				'saveSettings'      => wp_create_nonce( 'aero_paywall_save_settings' ),
				'restrictionRules'  => wp_create_nonce( 'aero_paywall_restriction_rules' ),
				'connectorSettings' => wp_create_nonce( 'aero_paywall_connector_settings' ),
				'createPages'       => wp_create_nonce( 'bday_aero_create_pages' ),
			),
			'setupComplete'  => Bday_Aero_Settings::enabled(),
			'settings'       => array(
				Bday_Aero_Settings::ENABLED                  => Bday_Aero_Settings::enabled(),
				Bday_Aero_Settings::API_BASE_URL              => Bday_Aero_Settings::api_base_url(),
				Bday_Aero_Settings::API_KEY                   => Bday_Aero_Settings::api_key(),
				Bday_Aero_Settings::LICENSING_API_BASE_URL    => Bday_Aero_Settings::licensing_api_base_url(),
				Bday_Aero_Settings::LICENSE_KEY                => Bday_Aero_Settings::license_key(),
				Bday_Aero_Settings::SDK_CDN_BASE               => Bday_Aero_Settings::sdk_cdn_base(),
				Bday_Aero_Settings::SDK_VERSION                => Bday_Aero_Settings::sdk_version(),
				Bday_Aero_Settings::ACCOUNT_PAGE_URL           => Bday_Aero_Settings::account_page_url(),
				Bday_Aero_Settings::SUBSCRIBE_PAGE_URL         => Bday_Aero_Settings::subscribe_page_url(),
				Bday_Aero_Settings::LOGIN_PAGE_URL             => Bday_Aero_Settings::login_page_url(),
				Bday_Aero_Settings::REGISTER_PAGE_URL          => Bday_Aero_Settings::register_page_url(),
				Bday_Aero_Settings::GOOGLE_CLIENT_ID           => Bday_Aero_Settings::google_client_id(),
				Bday_Aero_Settings::APPLE_CLIENT_ID            => Bday_Aero_Settings::apple_client_id(),
				Bday_Aero_Settings::ACCENT_COLOR               => Bday_Aero_Settings::accent_color(),
				Bday_Aero_Settings::ADFREE_ENABLED             => Bday_Aero_Settings::adfree_enabled(),
				Bday_Aero_Settings::PRIVATE_MODE_ENFORCEMENT   => Bday_Aero_Settings::private_mode_enforcement(),
				Bday_Aero_Settings::RESTRICTED_POST_TYPES      => $restricted_post_types,
				Bday_Aero_Settings::PREVIEW_WORD_COUNT         => Bday_Aero_Settings::preview_word_count(),
				Bday_Aero_Settings::BYPASS_ROLES               => Bday_Aero_Settings::bypass_roles(),
				Bday_Aero_Settings::JSONLD_ENABLED             => Bday_Aero_Settings::jsonld_enabled(),
				Bday_Aero_Settings::RESTRICTION_EXCEPTIONS     => Bday_Aero_Settings::restriction_exceptions(),
				Bday_Aero_Settings::PROMPT_COPY                => Bday_Aero_Settings::prompt_copy(),
				Bday_Aero_Settings::PREMIUM_TERMS              => self::normalized_premium_terms(),
			),
			'restrictionRules' => Bday_Aero_Settings::restriction_rules(),
			'postTypes'        => Bday_Aero_Restrictions_Picker::get_public_post_types(),
			'taxonomies'       => Bday_Aero_Restrictions_Picker::get_taxonomies_for_post_types( $restricted_post_types ),
			'roles'            => $this->available_roles(),
			'connectorSettings' => $this->get_connector_settings_snapshot(),
			'dashboardStats'   => Bday_Aero_Dashboard_Stats_Client::get(),
			'requiredPages'    => Bday_Aero_Page_Setup::status(),
			'licenseActive'    => Bday_Aero_License_Client::is_active(),
			'devModeBypass'    => Bday_Aero_License_Client::is_dev_mode_bypass_active(),
		);
	}

	/** @return array{category: int[]} */
	private static function normalized_premium_terms(): array {
		$terms      = Bday_Aero_Settings::premium_terms();
		$categories = is_array( $terms['category'] ?? null ) ? array_map( 'intval', $terms['category'] ) : array();
		return array( 'category' => array_values( $categories ) );
	}

	/** @return array<string, string> slug => display name */
	private function available_roles(): array {
		$roles = function_exists( 'wp_roles' ) ? wp_roles()->get_names() : array();
		return array_map( 'translate_user_role', $roles );
	}

	/** @return array<string, mixed> */
	private function get_connector_settings_snapshot(): array {
		if ( null === $this->connector_settings_snapshot ) {
			$this->connector_settings_snapshot = $this->fetch_connector_settings_snapshot();
		}
		return $this->connector_settings_snapshot;
	}

	/** @return array<string, mixed> */
	private function fetch_connector_settings_snapshot(): array {
		$base_url = Bday_Aero_Settings::api_base_url();
		$api_key  = Bday_Aero_Settings::api_key();
		if ( '' === $base_url || '' === $api_key ) {
			return array();
		}

		$response = wp_remote_get(
			$base_url . '/connector/settings',
			array(
				'timeout' => 5,
				'headers' => array( 'X-Api-Key' => $api_key ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body['data'] ?? null ) ? $body['data'] : array();
	}

	public function handle_test_connection(): void {
		check_ajax_referer( 'aero_paywall_test_connection', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'bday-aero' ) ), 403 );
			return;
		}

		$base_url = isset( $_POST['base_url'] ) ? esc_url_raw( wp_unslash( $_POST['base_url'] ) ) : '';
		if ( '' === $base_url ) {
			wp_send_json_error( array( 'message' => __( 'Enter a Subscription Service base URL first.', 'bday-aero' ) ) );
			return;
		}

		$response = wp_remote_get( rtrim( $base_url, '/' ) . '/plans', array( 'timeout' => 8 ) );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
			return;
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status >= 200 && $status < 500 ) {
			wp_send_json_success( array( 'message' => __( 'Connected successfully.', 'bday-aero' ), 'status' => $status ) );
			return;
		}

		wp_send_json_error( array( 'message' => sprintf( __( 'Unexpected response (HTTP %d).', 'bday-aero' ), $status ) ) );
	}

	public function handle_save_settings(): void {
		check_ajax_referer( 'aero_paywall_save_settings', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'bday-aero' ) ), 403 );
			return;
		}

		$raw     = isset( $_POST['settings'] ) ? wp_unslash( (string) $_POST['settings'] ) : '';
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid payload.', 'bday-aero' ) ), 400 );
			return;
		}

		$saved = $this->sanitize_and_save( $decoded );

		wp_send_json_success( array( 'settings' => $saved ) );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed> the sanitized values actually written
	 */
	private function sanitize_and_save( array $input ): array {
		$saved = array();

		foreach ( self::BOOL_FIELDS as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$saved[ $field ] = (bool) $input[ $field ];
				update_option( $field, $saved[ $field ] );
			}
		}

		foreach ( self::URL_FIELDS as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$saved[ $field ] = esc_url_raw( (string) $input[ $field ] );
				update_option( $field, $saved[ $field ] );
			}
		}

		foreach ( self::TEXT_FIELDS as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$saved[ $field ] = sanitize_text_field( (string) $input[ $field ] );
				update_option( $field, $saved[ $field ] );
			}
		}

		if ( array_key_exists( Bday_Aero_Settings::ACCENT_COLOR, $input ) ) {
			$color = sanitize_hex_color( (string) $input[ Bday_Aero_Settings::ACCENT_COLOR ] );
			if ( null !== $color && '' !== $color ) {
				$saved[ Bday_Aero_Settings::ACCENT_COLOR ] = $color;
				update_option( Bday_Aero_Settings::ACCENT_COLOR, $color );
			}
		}

		if ( array_key_exists( Bday_Aero_Settings::PRIVATE_MODE_ENFORCEMENT, $input ) ) {
			$value = in_array( $input[ Bday_Aero_Settings::PRIVATE_MODE_ENFORCEMENT ], array( 'off', 'soft', 'hard' ), true )
				? $input[ Bday_Aero_Settings::PRIVATE_MODE_ENFORCEMENT ]
				: 'soft';
			$saved[ Bday_Aero_Settings::PRIVATE_MODE_ENFORCEMENT ] = $value;
			update_option( Bday_Aero_Settings::PRIVATE_MODE_ENFORCEMENT, $value );
		}

		if ( array_key_exists( Bday_Aero_Settings::PREVIEW_WORD_COUNT, $input ) ) {
			$value = max( 1, (int) $input[ Bday_Aero_Settings::PREVIEW_WORD_COUNT ] );
			$saved[ Bday_Aero_Settings::PREVIEW_WORD_COUNT ] = $value;
			update_option( Bday_Aero_Settings::PREVIEW_WORD_COUNT, $value );
		}

		if ( array_key_exists( Bday_Aero_Settings::RESTRICTED_POST_TYPES, $input ) ) {
			$valid = array_keys( get_post_types( array( 'public' => true ) ) );
			$types = is_array( $input[ Bday_Aero_Settings::RESTRICTED_POST_TYPES ] )
				? array_values( array_intersect( $valid, array_map( 'sanitize_key', $input[ Bday_Aero_Settings::RESTRICTED_POST_TYPES ] ) ) )
				: array();
			$saved[ Bday_Aero_Settings::RESTRICTED_POST_TYPES ] = empty( $types ) ? array( 'post' ) : $types;
			update_option( Bday_Aero_Settings::RESTRICTED_POST_TYPES, $saved[ Bday_Aero_Settings::RESTRICTED_POST_TYPES ] );
		}

		if ( array_key_exists( Bday_Aero_Settings::BYPASS_ROLES, $input ) ) {
			$roles = is_array( $input[ Bday_Aero_Settings::BYPASS_ROLES ] )
				? array_values( array_map( 'sanitize_key', $input[ Bday_Aero_Settings::BYPASS_ROLES ] ) )
				: array();
			$saved[ Bday_Aero_Settings::BYPASS_ROLES ] = $roles;
			update_option( Bday_Aero_Settings::BYPASS_ROLES, $roles );
		}

		if ( array_key_exists( Bday_Aero_Settings::RESTRICTION_EXCEPTIONS, $input ) ) {
			$saved[ Bday_Aero_Settings::RESTRICTION_EXCEPTIONS ] = self::sanitize_taxonomy_term_map( $input[ Bday_Aero_Settings::RESTRICTION_EXCEPTIONS ] );
			update_option( Bday_Aero_Settings::RESTRICTION_EXCEPTIONS, $saved[ Bday_Aero_Settings::RESTRICTION_EXCEPTIONS ] );
		}

		if ( array_key_exists( Bday_Aero_Settings::PREMIUM_TERMS, $input ) ) {
			$saved[ Bday_Aero_Settings::PREMIUM_TERMS ] = self::sanitize_premium_terms( $input[ Bday_Aero_Settings::PREMIUM_TERMS ] );
			update_option( Bday_Aero_Settings::PREMIUM_TERMS, $saved[ Bday_Aero_Settings::PREMIUM_TERMS ] );
		}

		if ( array_key_exists( Bday_Aero_Settings::PROMPT_COPY, $input ) ) {
			$saved[ Bday_Aero_Settings::PROMPT_COPY ] = self::sanitize_prompt_copy( $input[ Bday_Aero_Settings::PROMPT_COPY ] );
			update_option( Bday_Aero_Settings::PROMPT_COPY, $saved[ Bday_Aero_Settings::PROMPT_COPY ] );
		}

		return $saved;
	}

	/**
	 * @param mixed $value
	 * @return array{category: int[]}
	 */
	private static function sanitize_premium_terms( $value ): array {
		$value      = is_array( $value ) ? $value : array();
		$categories = is_array( $value['category'] ?? null ) ? array_map( 'absint', $value['category'] ) : array();
		return array( 'category' => array_values( array_filter( $categories ) ) );
	}

	/**
	 * @param mixed $value
	 * @return array<string, array{headline: string, subcopy: string, cta: string, offerBadge: string}>
	 */
	private static function sanitize_prompt_copy( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$stages = array( 'register_prompt', 'profile_prompt', 'paid_lock' );
		$fields = array( 'headline', 'subcopy', 'cta', 'offerBadge' );

		$result = array();
		foreach ( $stages as $stage ) {
			if ( ! is_array( $value[ $stage ] ?? null ) ) {
				continue;
			}
			$stage_copy = array();
			foreach ( $fields as $field ) {
				if ( array_key_exists( $field, $value[ $stage ] ) ) {
					$stage_copy[ $field ] = sanitize_text_field( (string) $value[ $stage ][ $field ] );
				}
			}
			if ( ! empty( $stage_copy ) ) {
				$result[ $stage ] = $stage_copy;
			}
		}
		return $result;
	}

	/**
	 * @param mixed $value
	 * @return array<string, int[]>
	 */
	private static function sanitize_taxonomy_term_map( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$result = array();
		foreach ( $value as $taxonomy => $ids ) {
			if ( ! is_array( $ids ) ) {
				continue;
			}
			$result[ sanitize_key( (string) $taxonomy ) ] = array_map( 'intval', $ids );
		}
		return $result;
	}
}
