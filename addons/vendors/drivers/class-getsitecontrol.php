<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bday_Vendor_Getsitecontrol extends Bday_Vendor_Driver {

	public function slug(): string {
		return 'getsitecontrol';
	}

	public function is_configured(): bool {
		$opt = $this->option();
		return ! empty( $opt['enabled'] ) && ! empty( $opt['widget_id'] );
	}

	public function settings_fields(): array {
		return array(
			array( 'key' => 'enabled', 'type' => 'checkbox', 'label' => 'Enable GetSiteControl', 'default' => true ),
			array( 'key' => 'widget_id', 'type' => 'text', 'label' => 'Widget ID', 'default' => 'm42y997y' ),
		);
	}

	public function print_head(): void {
		printf(
			'<script type="text/javascript" async src="//l.getsitecontrol.com/%s.js"></script>',
			esc_attr( $this->option()['widget_id'] )
		);
	}
}
