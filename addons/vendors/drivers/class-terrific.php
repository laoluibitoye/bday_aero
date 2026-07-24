<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bday_Vendor_Terrific extends Bday_Vendor_Driver {

	public function slug(): string {
		return 'terrific';
	}

	public function is_configured(): bool {
		$opt = $this->option();
		return ! empty( $opt['enabled'] ) && ! empty( $opt['store_id'] );
	}

	public function settings_fields(): array {
		return array(
			array( 'key' => 'enabled', 'type' => 'checkbox', 'label' => 'Enable Terrific', 'default' => true ),
			array( 'key' => 'store_id', 'type' => 'text', 'label' => 'Store ID', 'default' => 'hcIgBSw8yP8qpUmQrosv' ),
		);
	}

	public function print_footer(): void {
		printf(
			'<script defer src="https://terrific.live/terrific-sdk.js" storeId="%s"></script>',
			esc_attr( $this->option()['store_id'] )
		);
	}
}
