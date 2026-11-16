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
			array( 'key' => 'enabled', 'type' => 'checkbox', 'label' => 'Enable Taboola', 'default' => true, 'description' => 'Content-recommendation widget ("You may also like...") — a revenue source separate from display ads, typically shown at the bottom of an article.' ),
			array( 'key' => 'account', 'type' => 'text', 'label' => 'Account name', 'default' => 'businessdaynigeria', 'description' => 'Your Taboola publisher account name, as provided by Taboola.' ),
		);
	}

	public function print_footer(): void {
		// The Ad Manager owns Taboola delivery now: it loads the library only for readers who may see ads,
		// so a paying subscriber never loads Taboola at all. All that stays in the page is the inert
		// page-type call below (a queue entry that does nothing until a loader reads it), which keeps the
		// page identifiable to Taboola's crawler.
		if ( function_exists( 'bday_ads_taboola_account' ) ) {
			if ( '' === bday_ads_taboola_account() || ! function_exists( 'bday_page_allows_ads' ) ) {
				return;
			}
			?>
			<script type="text/javascript">window._taboola = window._taboola || []; _taboola.push({<?php echo esc_js( bday_ads_taboola_page_type() ); ?>: 'auto'});</script>
			<?php
			return;
		}
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
