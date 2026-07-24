<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bday_Vendor_Playstream extends Bday_Vendor_Driver {

	public function slug(): string {
		return 'playstream';
	}

	public function is_configured(): bool {
		$opt = $this->option();
		return ! empty( $opt['enabled'] ) && ! empty( $opt['tag_id'] ) && ! empty( $opt['publisher_id'] );
	}

	public function settings_fields(): array {
		return array(
			array( 'key' => 'enabled', 'type' => 'checkbox', 'label' => 'Enable Playstream', 'default' => true ),
			array( 'key' => 'tag_id', 'type' => 'text', 'label' => 'Tag ID', 'default' => '6544b23556f61ee5810f11c9' ),
			array( 'key' => 'publisher_id', 'type' => 'text', 'label' => 'Publisher ID', 'default' => '6544ae1ce11e88434700bf13' ),
		);
	}

	public function print_footer(): void {
		$opt = $this->option();
		printf(
			'<script async id="AV%1$s" type="text/javascript" src="https://tg1.playstream.media/api/adserver/spt?AV_TAGID=%1$s&AV_PUBLISHERID=%2$s"></script>',
			esc_attr( $opt['tag_id'] ),
			esc_attr( $opt['publisher_id'] )
		);
	}
}
