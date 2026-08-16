<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bday_Vendor_Chartbeat extends Bday_Vendor_Driver {

	public function slug(): string {
		return 'chartbeat';
	}

	public function is_configured(): bool {
		$opt = $this->option();
		return ! empty( $opt['enabled'] ) && ! empty( $opt['uid'] ) && ! empty( $opt['domain'] );
	}

	public function settings_fields(): array {
		return array(
			array( 'key' => 'enabled', 'type' => 'checkbox', 'label' => 'Enable Chartbeat', 'default' => true, 'description' => 'Real-time traffic/engagement analytics — the "how many people are on this article right now" dashboard newsrooms use during breaking news.' ),
			array( 'key' => 'uid', 'type' => 'text', 'label' => 'Account UID', 'default' => '67124', 'description' => 'Your numeric Chartbeat account ID, found in Chartbeat\'s own dashboard settings.' ),
			array( 'key' => 'domain', 'type' => 'text', 'label' => 'Domain', 'default' => 'businessday.ng', 'description' => 'The domain this traffic should be attributed to in Chartbeat.' ),
		);
	}

	public function print_head(): void {
		$opt     = $this->option();
		$uid     = $opt['uid'];
		$domain  = $opt['domain'];
		$section = bday_current_page_section();
		$author  = bday_current_page_author();
		?>
		<script type="text/javascript">
		(function() {
			var _sf_async_config = window._sf_async_config = (window._sf_async_config || {});
			_sf_async_config.uid = <?php echo (int) $uid; ?>;
			_sf_async_config.domain = '<?php echo esc_js( $domain ); ?>';
			_sf_async_config.useCanonical = true;
			_sf_async_config.useCanonicalDomain = true;
			_sf_async_config.sections = '<?php echo esc_js( $section ); ?>';
			_sf_async_config.authors = '<?php echo esc_js( $author ); ?>';
			function loadChartbeat() {
				var e = document.createElement('script');
				var n = document.getElementsByTagName('script')[0];
				e.type = 'text/javascript'; e.async = true; e.src = '//static.chartbeat.com/js/chartbeat.js';
				n.parentNode.insertBefore(e, n);
			}
			loadChartbeat();
		})();
		</script>
		<script async src="//static.chartbeat.com/js/chartbeat_mab.js"></script>
		<?php
	}
}
