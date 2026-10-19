<?php
/**
 * Pre-paint ad-free check.
 *
 * ad-manager.js is deferred, so for a fully ad-free subscriber the reserved
 * ad containers would otherwise be on screen for a moment and then vanish —
 * a visible layout jump. This tiny inline script reads the same access-token
 * cookie and flags <html> before first paint, and ad-manager.css hides every
 * .bday-ad under that flag. It only handles the unambiguous case (an active
 * subscriber whose policy is "ad-free"); partial policies are applied by the
 * loader.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_head',
	static function (): void {
		if ( ! bday_page_allows_ads() ) {
			return;
		}
		$default_none = 'none' === ( bday_ads_clean_policy( Bday_Ads_Store::settings()['policy_subscriber'] ?? array() )['mode'] );
		?>
		<script>
		(function () {
			// Same width rule as ad-manager.js: the smaller of layout width and device width.
			try {
				var w = window.innerWidth, s = window.screen && window.screen.width;
				if (s && s < w) w = s;
				document.documentElement.setAttribute('data-bday-ad-device', w < <?php echo (int) Bday_Ads_Store::settings()['breakpoint']; ?> ? 'mobile' : 'desktop');
			} catch (e) {}
			try {
				var m = document.cookie.match(/(?:^|; )ap_access_token=([^;]*)/);
				if (!m) return;
				var p = m[1].split('.')[1].replace(/-/g, '+').replace(/_/g, '/');
				while (p.length % 4) p += '=';
				var c = JSON.parse(decodeURIComponent(escape(atob(p))));
				if (c.exp && c.exp * 1000 < Date.now()) return;
				if (c.subscriptionStatus !== 'active') return;
				var none = c.adPolicy ? c.adPolicy.mode === 'none' : <?php echo $default_none ? 'true' : 'false'; ?>;
				if (none) document.documentElement.classList.add('bday-ads-off');
			} catch (e) {}
		})();
		</script>
		<?php
	},
	2
);
