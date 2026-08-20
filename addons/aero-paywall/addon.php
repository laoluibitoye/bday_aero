<?php
/**
 * Addon Name: AeroPaywall
 * Addon Slug: aero-paywall
 * Description: The reader paywall and subscription system — sign-in, content gating, entitlements, and payments.
 * Cache Namespace: aero_paywall
 * Settings Tab: AeroPaywall
 * Default: off
 *
 * Native, in-theme replacement for the standalone AeroPaywall connector
 * plugin — content gating, the reader SDK, entitlement REST routes, and
 * reader identity sync, folded directly into the theme instead of a
 * separate plugin to install/activate. Gets its OWN top-level wp-admin
 * menu (class-admin-ui.php) rather than a tab under the shared
 * BusinessDay Theme settings screen — an intentional, documented
 * exception, same as the retired plugin's own dedicated admin screen.
 *
 * Reader-requested, emphatically: the wp-admin screen is the same React
 * admin app the retired connector-plugin already built
 * (assets/src/js/admin-app/), ported here rather than the plain
 * native-forms screen this add-on used to have — see class-admin-ui.php's
 * own docblock.
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
require_once __DIR__ . '/includes/class-dashboard-stats-client.php';
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
require_once __DIR__ . '/includes/class-reader-settings-page.php';
require_once __DIR__ . '/includes/class-nav-button.php';
require_once __DIR__ . '/includes/class-post-list-badge.php';
require_once __DIR__ . '/includes/class-admin-search-optimizer.php';
require_once __DIR__ . '/includes/class-vite-assets.php';
require_once __DIR__ . '/includes/class-shared-assets.php';
require_once __DIR__ . '/includes/class-restrictions-picker.php';
require_once __DIR__ . '/includes/class-connector-settings-client.php';
require_once __DIR__ . '/includes/class-page-setup.php';
require_once __DIR__ . '/includes/class-admin-ui.php';

new Bday_Aero_Admin_Ui();
new Bday_Aero_Restriction_Rules();
new Bday_Aero_Connector_Settings_Client();

// Always constructed, independent of the enabled+license gate below — a
// site needs its required pages (My Account, Subscribe, etc.) to exist
// from the moment the theme is activated, not only once AeroPaywall
// itself is enabled and licensed.
new Bday_Aero_Page_Setup();

// Premium-map sync is inert with respect to what a reader sees, so it
// (and its metabox/post-list badge) run regardless of the enabled+license
// gate below — same reasoning as the retired plugin: a category mapping
// should stay accurate even while gating itself is switched off.
$bday_aero_premium_map = new Bday_Aero_Premium_Map();
new Bday_Aero_Post_List_Badge( $bday_aero_premium_map );

// Reader-reported: editors searching the wp-admin post list caused a
// server/RDS resource spike — same "always run regardless of the reader-
// facing gate" reasoning as the premium map above, this is a wp-admin-only
// query optimization with no reader-facing effect either way.
new Bday_Aero_Admin_Search_Optimizer();

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
	new Bday_Aero_Reader_Settings_Page();
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
