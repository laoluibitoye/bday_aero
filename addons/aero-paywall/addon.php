<?php
/**
 * Addon Name: AeroPaywall
 * Addon Slug: aero-paywall
 * Cache Namespace: aero_paywall
 * Settings Tab: AeroPaywall
 * Default: off
 *
 * Native, in-theme replacement for the standalone AeroPaywall connector
 * plugin — content gating, the reader SDK, entitlement REST routes, and
 * reader identity sync, folded directly into the theme instead of a
 * separate plugin to install/activate. Gets its OWN top-level wp-admin
 * menu (class-admin.php) rather than a tab under the shared BusinessDay
 * Theme settings screen — an intentional, documented exception, same as
 * the retired plugin's own dedicated admin screen.
 *
 * Migration note: every option key here (aero_paywall_*) is unchanged
 * from the retired connector-plugin, so a site that had the plugin
 * configured inherits those values automatically the moment this add-on
 * is enabled and the plugin is deactivated — no re-entry, no migration
 * script needed for settings themselves. Do not run the connector plugin
 * and this add-on active at the same time: both would gate content and
 * sync the premium-map independently, racing each other.
 *
 * Default is "off" (unlike every other add-on) precisely because of that
 * plugin/add-on collision risk — an operator must deliberately enable
 * this after deactivating the plugin, not have it silently start gating
 * content the moment the theme is updated.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Theme-root vendor/ (composer.json lives at the theme root, not per-addon —
// firebase/php-jwt is the only composer dependency this whole theme has).
require_once get_template_directory() . '/vendor/autoload.php';

require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-device-cookie.php';
require_once __DIR__ . '/includes/class-restriction-rules.php';
require_once __DIR__ . '/includes/class-premium-map.php';
require_once __DIR__ . '/includes/class-meter-client.php';
require_once __DIR__ . '/includes/class-paywall-config-client.php';
require_once __DIR__ . '/includes/class-branding-client.php';
require_once __DIR__ . '/includes/class-content-gate.php';
require_once __DIR__ . '/includes/class-sdk-loader.php';
require_once __DIR__ . '/includes/class-jwks-client.php';
require_once __DIR__ . '/includes/class-license-client.php';
require_once __DIR__ . '/includes/class-mobile-api.php';
require_once __DIR__ . '/includes/class-wp-user-resolver.php';
require_once __DIR__ . '/includes/class-user-sync.php';
require_once __DIR__ . '/includes/class-user-sync-receiver.php';
require_once __DIR__ . '/includes/class-login-redirect.php';
require_once __DIR__ . '/includes/class-account-page.php';
require_once __DIR__ . '/includes/class-nav-button.php';
require_once __DIR__ . '/includes/class-post-list-badge.php';
require_once __DIR__ . '/includes/class-admin.php';

new Bday_Aero_Admin();
new Bday_Aero_Restriction_Rules(); // no-op constructor today, kept symmetric with the other classes for a future dedicated AJAX endpoint

// Premium-map sync is inert with respect to what a reader sees, so it
// (and its metabox/post-list badge) run regardless of the enabled+license
// gate below — same reasoning as the retired plugin: a category mapping
// should stay accurate even while gating itself is switched off.
$bday_aero_premium_map = new Bday_Aero_Premium_Map();
new Bday_Aero_Post_List_Badge( $bday_aero_premium_map );

// Always constructed: activation-attempt/JWKS-verification hooks should
// run as soon as a license key is configured, independent of the
// separate reader-facing enabled toggle.
new Bday_Aero_License_Client();

// Always constructed too: mobile apps need a stable REST endpoint
// regardless of rollout state; its own logic already honors the
// enabled+license gate internally (see class-mobile-api.php).
new Bday_Aero_Mobile_Api( $bday_aero_premium_map );
new Bday_Aero_User_Sync_Receiver();

if ( Bday_Aero_Settings::enabled() && Bday_Aero_License_Client::is_active() ) {
	new Bday_Aero_Device_Cookie();

	global $bday_aero_content_gate;
	$bday_aero_content_gate = new Bday_Aero_Content_Gate( $bday_aero_premium_map );

	new Bday_Aero_Sdk_Loader( $bday_aero_premium_map );
	new Bday_Aero_Account_Page();
	new Bday_Aero_User_Sync();
	new Bday_Aero_Login_Redirect();
}

if ( Bday_Aero_License_Client::is_dev_mode_bypass_active() ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-warning"><p><strong>AeroPaywall is running in DEVELOPMENT MODE</strong> &mdash; licensing is not enforced. Remove the <code>AERO_PAYWALL_DEV_MODE</code> constant from wp-config.php before this site handles real traffic.</p></div>';
		}
	);
}
