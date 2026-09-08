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

	/**
	 * Zero-outbound-call geography signal — reads the CF-IPCountry edge
	 * header directly, costs nothing. Drives the SDK's NGN-vs-USD /
	 * Paystack-vs-Stripe selection (sdk/src/my-account.ts's
	 * resolveCurrencyAndGateway()) entirely client-side; the server never
	 * trusts this value for the actual charge (checkout.service.ts derives
	 * currency from the reader's own persisted billingCurrency instead).
	 *
	 * Field-tested (Gating System Field Test follow-up, 2026-09-08): this
	 * add-on's sibling implementation (connector-plugin/includes/
	 * class-sdk-loader.php) already had a `?aero_country=` override for
	 * exactly this reason — this copy didn't, making the non-Nigeria/USD
	 * branch untestable on a local stack with no real Cloudflare in front
	 * of it. Same dev-mode gate as that sibling implementation: only
	 * honored when AERO_PAYWALL_DEV_MODE is set in wp-config.php, so it
	 * can't be used to spoof geography on a real site.
	 *
	 * Separately worth flagging (not something this override fixes): CF-
	 * IPCountry being absent is treated identically to Nigeria by design
	 * (returns null when no header is present, and the SDK's own fallback
	 * for `null` is NGN/Paystack — see my-account.ts). That's the right
	 * choice for "Cloudflare is in front of this site but a request
	 * somehow skipped it" — but if this site's production origin isn't
	 * actually behind Cloudflare at all, every reader worldwide gets NGN
	 * pricing silently, with nothing here to signal that. Worth confirming
	 * against the real DNS/hosting setup, not something verifiable from
	 * code alone.
	 */
	private static function country_code(): ?string {
		if ( Bday_Aero_License_Client::is_dev_mode_bypass_active() && isset( $_GET['aero_country'] ) ) {
			return strtoupper( sanitize_text_field( wp_unslash( $_GET['aero_country'] ) ) );
		}
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
