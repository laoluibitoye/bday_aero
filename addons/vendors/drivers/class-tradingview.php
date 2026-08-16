<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** TradingView FX/index ticker tape — rendered inline in the page shell (core/../header), not head/footer, so it has its own render() method beyond the base hooks. */
class Bday_Vendor_Tradingview extends Bday_Vendor_Driver {

	public function slug(): string {
		return 'tradingview';
	}

	public function is_configured(): bool {
		$opt = $this->option();
		return ! empty( $opt['enabled'] ) && ! empty( $opt['symbols'] );
	}

	public function settings_fields(): array {
		return array(
			array( 'key' => 'enabled', 'type' => 'checkbox', 'label' => 'Enable TradingView ticker', 'default' => true, 'description' => 'The scrolling forex/stock-index strip shown between the utility bar and the masthead. Real-time market data from TradingView, not editorially curated.' ),
			array(
				'key'         => 'symbols',
				'type'        => 'text',
				'label'       => 'Symbols (comma-separated)',
				'default'     => 'NSENG:NGXGROUP,FX_IDC:NGNUSD,FX_IDC:NGNGBP,FX_IDC:NGNEUR,ECONOMICS:NGNOE,FX_IDC:NGNJPY',
				'description' => 'TradingView\'s own ticker symbol format (EXCHANGE:SYMBOL), e.g. "NSENG:NGXGROUP" for the Nigerian Exchange All-Share Index. Look up a symbol at tradingview.com/symbols before adding it — an invalid symbol shows as blank in the strip rather than an error.',
			),
		);
	}

	public function render(): void {
		if ( ! $this->is_configured() ) {
			return;
		}
		$symbols = array_map(
			static fn( $s ) => array( 'description' => '', 'proName' => trim( $s ) ),
			explode( ',', $this->option()['symbols'] )
		);
		?>
		<div class="tradingview-widget-container">
			<div class="tradingview-widget-container__widget"></div>
			<script type="text/javascript" src="https://s3.tradingview.com/external-embedding/embed-widget-ticker-tape.js" async>
			<?php echo wp_json_encode( array(
				'symbols'       => $symbols,
				'showSymbolLogo' => true,
				'isTransparent'  => false,
				'displayMode'    => 'adaptive',
				'colorTheme'     => 'light',
				'locale'         => 'en',
			) ); ?>
			</script>
		</div>
		<?php
	}
}
