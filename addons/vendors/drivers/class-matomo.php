<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Matomo — previously hardcoded twice, byte-for-byte identical, once for AMP and once not. One implementation, one call site now. */
class Bday_Vendor_Matomo extends Bday_Vendor_Driver {

	public function slug(): string {
		return 'matomo';
	}

	public function is_configured(): bool {
		$opt = $this->option();
		return ! empty( $opt['enabled'] ) && ! empty( $opt['url'] ) && isset( $opt['site_id'] ) && '' !== $opt['site_id'];
	}

	public function settings_fields(): array {
		return array(
			array( 'key' => 'enabled', 'type' => 'checkbox', 'label' => 'Enable Matomo', 'default' => true, 'description' => 'A self-hosted, privacy-focused alternative/supplement to Google Analytics — data stays on servers this organization controls rather than Google\'s.' ),
			array( 'key' => 'url', 'type' => 'text', 'label' => 'Tracker URL', 'default' => '//data.businessday.ng/', 'description' => 'The base URL of the self-hosted Matomo instance (trailing slash included).' ),
			array( 'key' => 'site_id', 'type' => 'text', 'label' => 'Site ID', 'default' => '2', 'description' => 'The numeric site ID assigned to this property inside Matomo.' ),
		);
	}

	public function print_footer(): void {
		$opt = $this->option();
		?>
		<script>
		var _paq = window._paq = window._paq || [];
		_paq.push(['trackPageView']);
		_paq.push(['enableLinkTracking']);
		(function() {
			var u = "<?php echo esc_js( $opt['url'] ); ?>";
			_paq.push(['setTrackerUrl', u + 'matomo.php']);
			_paq.push(['setSiteId', '<?php echo esc_js( $opt['site_id'] ); ?>']);
			var d = document, g = d.createElement('script'), s = d.getElementsByTagName('script')[0];
			g.async = true; g.src = u + 'matomo.js'; s.parentNode.insertBefore(g, s);
		})();
		</script>
		<?php
	}
}
