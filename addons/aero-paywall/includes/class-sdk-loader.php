<?php
/**
 * Enqueues the versioned, CDN-hosted reader SDK and passes it page
 * context. This is a plain wp_enqueue_script() against an external CDN
 * URL, deliberately not routed through the theme's own Vite pipeline —
 * the SDK is an independently-versioned artifact (deployed by a separate
 * repo/CDN release), not a theme-owned asset. Only the public API base
 * URL is exposed to the browser here; the server-to-server API key never
 * leaves this add-on's own wp_remote_post()/wp_remote_get() calls.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Sdk_Loader {

	private Bday_Aero_Premium_Map $premium_map;

	public function __construct( Bday_Aero_Premium_Map $premium_map ) {
		$this->premium_map = $premium_map;
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_sdk' ) );
		add_action( 'wp_footer', array( $this, 'render_mount_points' ) );
	}

	public function enqueue_sdk(): void {
		$version = Bday_Aero_Settings::sdk_version();
		$src     = sprintf( '%s/%s/aeropaywall.global.js', Bday_Aero_Settings::sdk_cdn_base(), $version );

		wp_enqueue_script( 'aero-paywall-sdk', $src, array(), $version, array( 'strategy' => 'defer', 'in_footer' => true ) );
		wp_localize_script( 'aero-paywall-sdk', 'aeroPaywallContext', $this->build_context() );
	}

	/** @return array<string, mixed> */
	private function build_context(): array {
		$post_id   = is_singular( 'post' ) ? (int) get_the_ID() : null;
		$branding  = Bday_Aero_Branding_Client::get();
		$config    = Bday_Aero_Paywall_Config_Client::get();

		return array(
			'apiBaseUrl'   => Bday_Aero_Settings::api_base_url(),
			'postId'       => $post_id,
			'isPremium'    => $post_id ? $this->premium_map->is_premium( $post_id ) : false,
			'categoryIds'  => $post_id ? wp_get_post_categories( $post_id, array( 'fields' => 'ids' ) ) : array(),
			'accentColor'  => $branding['accentColor'],
			'logoUrl'      => $branding['logoUrl'],
			'adFreeEnabled' => Bday_Aero_Settings::adfree_enabled(),
			'privateModeEnforcement' => Bday_Aero_Settings::private_mode_enforcement(),
			'accountUrl'   => Bday_Aero_Settings::account_page_url() ?: null,
			// The public /subscribe/ page's Corporate tab links here rather
			// than rendering a self-serve B2B plan grid (see
			// renderPublicSubscribeTab() in the SDK) — same page
			// template-subscribe.php's own "Need 20+ seats?" line already
			// points to.
			'corporateSubscriptionUrl' => class_exists( 'Bday_Aero_Page_Setup' ) ? Bday_Aero_Page_Setup::url_for( 'corporate_subscription' ) : null,
			'googleClientId' => Bday_Aero_Settings::google_client_id() ?: null,
			'appleClientId'  => Bday_Aero_Settings::apple_client_id() ?: null,
			'captcha'      => $config['captcha'] ?? null,
			'jwksUrl'      => self::jwks_url(),
			'countryCode'  => self::country_code(),
			'promptCopy'   => Bday_Aero_Settings::prompt_copy(),
		);
	}

	private static function jwks_url(): string {
		$api_base_url = Bday_Aero_Settings::api_base_url();
		if ( '' === $api_base_url ) {
			return '';
		}
		$parts  = wp_parse_url( $api_base_url );
		$scheme = $parts['scheme'] ?? 'https';
		$host   = $parts['host'] ?? '';
		$port   = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
		return $scheme . '://' . $host . $port . '/.well-known/jwks.json';
	}

	/** Zero-outbound-call geography signal — reads the CF-IPCountry edge header directly, costs nothing. */
	private static function country_code(): ?string {
		$header = isset( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) : '';
		return '' !== $header ? strtoupper( $header ) : null;
	}

	public function render_mount_points(): void {
		echo '<div id="aero-paywall-mounts" hidden>'
			. '<div class="aero-paywall-mount aero-paywall-mount-slide-in-alert"></div>'
			. '</div>'
			. '<div id="aero-paywall-gift-mount"></div>';
	}
}
