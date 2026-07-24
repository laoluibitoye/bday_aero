<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bday_Vendor_Taboola extends Bday_Vendor_Driver {

	public function slug(): string {
		return 'taboola';
	}

	public function is_configured(): bool {
		$opt = $this->option();
		return ! empty( $opt['enabled'] ) && ! empty( $opt['account'] );
	}

	public function settings_fields(): array {
		return array(
			array( 'key' => 'enabled', 'type' => 'checkbox', 'label' => 'Enable Taboola', 'default' => true ),
			array( 'key' => 'account', 'type' => 'text', 'label' => 'Account name', 'default' => 'businessdaynigeria' ),
		);
	}

	public function print_footer(): void {
		$account = $this->option()['account'];
		?>
		<script type="text/javascript">
		window._taboola = window._taboola || [];
		_taboola.push({article: 'auto'});
		!function (e, f, u, i) {
			if (!document.getElementById(i)) {
				e.async = 1; e.src = u; e.id = i;
				f.parentNode.insertBefore(e, f);
			}
		}(document.createElement('script'), document.getElementsByTagName('script')[0],
			'//cdn.taboola.com/libtrc/<?php echo esc_js( $account ); ?>/loader.js', 'tb_loader_script');
		if (window.performance && typeof window.performance.mark === 'function') { window.performance.mark('tbl_ic'); }
		</script>
		<?php
	}
}
