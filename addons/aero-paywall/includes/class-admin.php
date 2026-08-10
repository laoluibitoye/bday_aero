<?php
/**
 * wp-admin UI: its own top-level "AeroPaywall" menu (per explicit
 * direction — kept separate from the "BusinessDay Theme" menu rather than
 * folded into it), with Connection / Restrictions / Advanced as real
 * submenu pages. Deliberately plain PHP-rendered forms via the native
 * WordPress Settings API, not a ported React/Vite SPA — one fewer build
 * toolchain for this add-on to maintain long-term, and consistent with
 * every other admin screen in this theme.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Admin {

	private const PAGE_SLUG = 'aero-paywall';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_bday_aero_test_connection', array( $this, 'handle_test_connection' ) );
	}

	public function register_menu(): void {
		add_menu_page( 'AeroPaywall', 'AeroPaywall', 'manage_options', self::PAGE_SLUG, array( $this, 'render_connection' ), 'dashicons-lock', 62 );
		add_submenu_page( self::PAGE_SLUG, 'Connection', 'Connection', 'manage_options', self::PAGE_SLUG, array( $this, 'render_connection' ) );
		add_submenu_page( self::PAGE_SLUG, 'Restrictions', 'Restrictions', 'manage_options', self::PAGE_SLUG . '-restrictions', array( $this, 'render_restrictions' ) );
		add_submenu_page( self::PAGE_SLUG, 'Advanced', 'Advanced', 'manage_options', self::PAGE_SLUG . '-advanced', array( $this, 'render_advanced' ) );
	}

	public function register_settings(): void {
		$text_fields = array(
			Bday_Aero_Settings::API_BASE_URL,
			Bday_Aero_Settings::API_KEY,
			Bday_Aero_Settings::LICENSING_API_BASE_URL,
			Bday_Aero_Settings::LICENSE_KEY,
		);
		foreach ( $text_fields as $field ) {
			register_setting( 'bday_aero_connection', $field, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		}

		register_setting( 'bday_aero_restrictions', Bday_Aero_Settings::ENABLED, array( 'sanitize_callback' => 'rest_sanitize_boolean' ) );
		register_setting( 'bday_aero_restrictions', Bday_Aero_Settings::PAYWALL_MODE, array( 'sanitize_callback' => array( $this, 'sanitize_paywall_mode' ) ) );
		register_setting( 'bday_aero_restrictions', Bday_Aero_Settings::PREVIEW_WORD_COUNT, array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'bday_aero_restrictions', Bday_Aero_Settings::RESTRICTION_RULES, array( 'sanitize_callback' => array( 'Bday_Aero_Restriction_Rules', 'sanitize_rules' ) ) );

		$url_fields = array( Bday_Aero_Settings::ACCOUNT_PAGE_URL, Bday_Aero_Settings::LOGIN_PAGE_URL, Bday_Aero_Settings::REGISTER_PAGE_URL, Bday_Aero_Settings::SDK_CDN_BASE );
		foreach ( $url_fields as $field ) {
			register_setting( 'bday_aero_advanced', $field, array( 'sanitize_callback' => 'esc_url_raw' ) );
		}
		register_setting( 'bday_aero_advanced', Bday_Aero_Settings::SDK_VERSION, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'bday_aero_advanced', Bday_Aero_Settings::GOOGLE_CLIENT_ID, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'bday_aero_advanced', Bday_Aero_Settings::APPLE_CLIENT_ID, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'bday_aero_advanced', Bday_Aero_Settings::ADFREE_ENABLED, array( 'sanitize_callback' => 'rest_sanitize_boolean' ) );
		register_setting( 'bday_aero_advanced', Bday_Aero_Settings::JSONLD_ENABLED, array( 'sanitize_callback' => 'rest_sanitize_boolean' ) );
		register_setting( 'bday_aero_advanced', Bday_Aero_Settings::BYPASS_ROLES, array( 'sanitize_callback' => array( $this, 'sanitize_roles' ) ) );
	}

	public function sanitize_paywall_mode( $value ): string {
		return in_array( $value, array( 'soft', 'hard' ), true ) ? $value : 'soft';
	}

	/** @return string[] */
	public function sanitize_roles( $value ): array {
		$parts = array_filter( array_map( 'trim', explode( ',', (string) $value ) ) );
		return array_values( array_map( 'sanitize_key', $parts ) );
	}

	public function render_connection(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1>AeroPaywall — Connection</h1>
			<p>Connects this site to the AeroPaywall subscription-service backend and licensing-platform. Nothing reader-facing activates until both a base URL/key and a valid license are set.</p>
			<form action="options.php" method="post">
				<?php settings_fields( 'bday_aero_connection' ); ?>
				<table class="form-table" role="presentation"><tbody>
					<tr><th scope="row">Subscription Service API base URL</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( Bday_Aero_Settings::API_BASE_URL ); ?>" value="<?php echo esc_attr( Bday_Aero_Settings::api_base_url() ); ?>"></td></tr>
					<tr><th scope="row">API key</th><td><input type="password" class="regular-text" name="<?php echo esc_attr( Bday_Aero_Settings::API_KEY ); ?>" value="<?php echo esc_attr( Bday_Aero_Settings::api_key() ); ?>"></td></tr>
					<tr><th scope="row">Licensing platform API base URL</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( Bday_Aero_Settings::LICENSING_API_BASE_URL ); ?>" value="<?php echo esc_attr( Bday_Aero_Settings::licensing_api_base_url() ); ?>"></td></tr>
					<tr><th scope="row">License key</th><td><input type="password" class="regular-text" name="<?php echo esc_attr( Bday_Aero_Settings::LICENSE_KEY ); ?>" value="<?php echo esc_attr( Bday_Aero_Settings::license_key() ); ?>"></td></tr>
				</tbody></table>
				<?php submit_button(); ?>
			</form>

			<h2>Status</h2>
			<p>
				License: <?php echo Bday_Aero_License_Client::is_active() ? '<strong style="color:#0a7d32">Active</strong>' : '<strong style="color:#b91c1c">Not active</strong>'; ?>
				<?php if ( Bday_Aero_License_Client::is_dev_mode_bypass_active() ) : ?>
					&mdash; <strong style="color:#b45309">Dev-mode bypass active (AERO_PAYWALL_DEV_MODE)</strong>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	public function render_restrictions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$rules = Bday_Aero_Settings::restriction_rules();
		?>
		<div class="wrap">
			<h1>AeroPaywall — Restrictions</h1>
			<form action="options.php" method="post">
				<?php settings_fields( 'bday_aero_restrictions' ); ?>
				<table class="form-table" role="presentation"><tbody>
					<tr><th scope="row">Enabled</th><td><label><input type="checkbox" name="<?php echo esc_attr( Bday_Aero_Settings::ENABLED ); ?>" value="1" <?php checked( Bday_Aero_Settings::enabled() ); ?>> Gate content site-wide</label></td></tr>
					<tr><th scope="row">Paywall mode</th><td>
						<select name="<?php echo esc_attr( Bday_Aero_Settings::PAYWALL_MODE ); ?>">
							<option value="soft" <?php selected( Bday_Aero_Settings::paywall_mode(), 'soft' ); ?>>Soft — only premium posts are gated</option>
							<option value="hard" <?php selected( Bday_Aero_Settings::paywall_mode(), 'hard' ); ?>>Hard — every restricted post type is gated</option>
						</select>
					</td></tr>
					<tr><th scope="row">Preview word count</th><td><input type="number" min="0" class="small-text" name="<?php echo esc_attr( Bday_Aero_Settings::PREVIEW_WORD_COUNT ); ?>" value="<?php echo esc_attr( (string) Bday_Aero_Settings::preview_word_count() ); ?>"></td></tr>
				</tbody></table>

				<h2>Restriction rules</h2>
				<p class="description">Matched top-to-bottom; the first matching rule governs a post. Drag to reorder.</p>
				<table class="widefat" id="bday-aero-rules-table">
					<thead><tr><th style="width:24px"></th><th>Post type</th><th>Taxonomy</th><th>Term IDs (comma-separated)</th><th># free</th><th>Period (days)</th><th>Require registration</th><th></th></tr></thead>
					<tbody id="bday-aero-rules-tbody">
						<?php foreach ( $rules as $i => $rule ) : ?>
							<?php $this->render_rule_row( $i, $rule ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button" id="bday-aero-rules-add">Add Rule</button></p>
				<template id="bday-aero-rule-template"><?php $this->render_rule_row( '__INDEX__', array() ); ?></template>

				<?php submit_button(); ?>
			</form>
		</div>
		<script>
		(function () {
			var tbody = document.getElementById( 'bday-aero-rules-tbody' );
			var addBtn = document.getElementById( 'bday-aero-rules-add' );
			var template = document.getElementById( 'bday-aero-rule-template' );
			var dragged = null;
			var counter = <?php echo (int) count( $rules ); ?>;

			function bindRow( row ) {
				row.setAttribute( 'draggable', 'true' );
				row.addEventListener( 'dragstart', function () { dragged = row; row.classList.add( 'is-dragging' ); } );
				row.addEventListener( 'dragend', function () { row.classList.remove( 'is-dragging' ); } );
				row.addEventListener( 'dragover', function ( e ) { e.preventDefault(); } );
				row.addEventListener( 'drop', function ( e ) {
					e.preventDefault();
					if ( ! dragged || dragged === row ) return;
					var rect = row.getBoundingClientRect();
					var before = ( e.clientY - rect.top ) < rect.height / 2;
					row.parentNode.insertBefore( dragged, before ? row : row.nextSibling );
				} );
				var remove = row.querySelector( '.bday-aero-rule-remove' );
				if ( remove ) remove.addEventListener( 'click', function () { row.remove(); } );
			}
			Array.prototype.forEach.call( tbody.querySelectorAll( 'tr' ), bindRow );
			addBtn.addEventListener( 'click', function () {
				var html = template.innerHTML.replace( /__INDEX__/g, 'new' + ( counter++ ) );
				var wrapper = document.createElement( 'tbody' );
				wrapper.innerHTML = html.trim();
				var row = wrapper.firstElementChild;
				tbody.appendChild( row );
				bindRow( row );
			} );
		})();
		</script>
		<style>#bday-aero-rules-table tr.is-dragging{opacity:.4} #bday-aero-rules-table td{vertical-align:middle}</style>
		<?php
	}

	/** @param int|string $index @param array<string, mixed> $rule */
	private function render_rule_row( $index, array $rule ): void {
		$name = fn( string $field ) => sprintf( '%s[%s][%s]', esc_attr( Bday_Aero_Settings::RESTRICTION_RULES ), esc_attr( (string) $index ), $field );
		?>
		<tr>
			<td><span class="dashicons dashicons-menu" style="cursor:grab;color:#999;"></span></td>
			<td><input type="text" class="regular-text" name="<?php echo $name( 'post_type' ); ?>" value="<?php echo esc_attr( $rule['post_type'] ?? 'post' ); ?>"></td>
			<td><input type="text" class="regular-text" name="<?php echo $name( 'taxonomy' ); ?>" value="<?php echo esc_attr( $rule['taxonomy'] ?? 'category' ); ?>"></td>
			<td><input type="text" class="regular-text" name="<?php echo $name( 'term_ids' ); ?>" value="<?php echo esc_attr( implode( ',', $rule['term_ids'] ?? array() ) ); ?>" placeholder="e.g. 4,9"></td>
			<td><input type="number" min="0" class="small-text" name="<?php echo $name( 'number_allowed' ); ?>" value="<?php echo esc_attr( (string) ( $rule['number_allowed'] ?? '' ) ); ?>"></td>
			<td><input type="number" min="0" class="small-text" name="<?php echo $name( 'period_days' ); ?>" value="<?php echo esc_attr( (string) ( $rule['period_days'] ?? '' ) ); ?>"></td>
			<td><input type="checkbox" name="<?php echo $name( 'require_registration' ); ?>" value="1" <?php checked( ! empty( $rule['require_registration'] ) ); ?>></td>
			<td><button type="button" class="button-link bday-aero-rule-remove" aria-label="Remove rule">&times;</button></td>
		</tr>
		<?php
	}

	public function render_advanced(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1>AeroPaywall — Advanced</h1>
			<form action="options.php" method="post">
				<?php settings_fields( 'bday_aero_advanced' ); ?>
				<table class="form-table" role="presentation"><tbody>
					<tr><th scope="row">Account page URL</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( Bday_Aero_Settings::ACCOUNT_PAGE_URL ); ?>" value="<?php echo esc_attr( Bday_Aero_Settings::account_page_url() ); ?>"></td></tr>
					<tr><th scope="row">Login page URL override</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( Bday_Aero_Settings::LOGIN_PAGE_URL ); ?>" value="<?php echo esc_attr( Bday_Aero_Settings::login_page_url() ); ?>"></td></tr>
					<tr><th scope="row">Register page URL override</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( Bday_Aero_Settings::REGISTER_PAGE_URL ); ?>" value="<?php echo esc_attr( Bday_Aero_Settings::register_page_url() ); ?>"></td></tr>
					<tr><th scope="row">SDK CDN base</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( Bday_Aero_Settings::SDK_CDN_BASE ); ?>" value="<?php echo esc_attr( Bday_Aero_Settings::sdk_cdn_base() ); ?>"></td></tr>
					<tr><th scope="row">SDK version</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( Bday_Aero_Settings::SDK_VERSION ); ?>" value="<?php echo esc_attr( Bday_Aero_Settings::sdk_version() ); ?>"></td></tr>
					<tr><th scope="row">Google client ID</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( Bday_Aero_Settings::GOOGLE_CLIENT_ID ); ?>" value="<?php echo esc_attr( Bday_Aero_Settings::google_client_id() ); ?>"></td></tr>
					<tr><th scope="row">Apple client ID</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( Bday_Aero_Settings::APPLE_CLIENT_ID ); ?>" value="<?php echo esc_attr( Bday_Aero_Settings::apple_client_id() ); ?>"></td></tr>
					<tr><th scope="row">Ad-free for subscribers</th><td><label><input type="checkbox" name="<?php echo esc_attr( Bday_Aero_Settings::ADFREE_ENABLED ); ?>" value="1" <?php checked( Bday_Aero_Settings::adfree_enabled() ); ?>> Enabled</label></td></tr>
					<tr><th scope="row">NewsArticle JSON-LD on gated posts</th><td><label><input type="checkbox" name="<?php echo esc_attr( Bday_Aero_Settings::JSONLD_ENABLED ); ?>" value="1" <?php checked( Bday_Aero_Settings::jsonld_enabled() ); ?>> Enabled</label></td></tr>
					<tr><th scope="row">Bypass roles</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( Bday_Aero_Settings::BYPASS_ROLES ); ?>" value="<?php echo esc_attr( implode( ',', Bday_Aero_Settings::bypass_roles() ) ); ?>" placeholder="e.g. author,editor" /><p class="description">Comma-separated WP role slugs that always see full content, even in hard mode.</p></td></tr>
				</tbody></table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function handle_test_connection(): void {
		check_admin_referer( 'bday_aero_test_connection' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden', 403 );
		}
		$base_url = Bday_Aero_Settings::api_base_url();
		$ok       = false;
		if ( '' !== $base_url ) {
			$response = wp_remote_get( $base_url . '/public/paywall-config', array( 'timeout' => 5 ) );
			$ok       = ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response );
		}
		wp_safe_redirect( add_query_arg( 'bday_aero_test', $ok ? 'ok' : 'fail', admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
		exit;
	}
}
