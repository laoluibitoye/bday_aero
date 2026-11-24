<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bday_Vendor_Playstream extends Bday_Vendor_Driver {

	// Falls back to this exact default when the stored option predates this
	// field (the old tag_id/publisher_id-based integration) — otherwise the
	// script would silently stop printing on every existing site the
	// moment this ships, until someone happens to open and re-save the
	// Vendors settings tab. This is also the one and only id the new
	// Playstream snippet (play-stream.tech) was actually issued with.
	private const DEFAULT_SCRIPT_ID = '69146ebc9c5690287580f4d3';

	public function slug(): string {
		return 'playstream';
	}

	public function is_configured(): bool {
		$opt = $this->option();
		return ! empty( $opt['enabled'] ) && '' !== $this->script_id();
	}

	public function settings_fields(): array {
		return array(
			array( 'key' => 'enabled', 'type' => 'checkbox', 'label' => 'Enable Playstream', 'default' => true, 'description' => 'A video-ad network — auto-inserts video ad units into the page without further template changes.' ),
			array( 'key' => 'script_id', 'type' => 'text', 'label' => 'Script ID', 'default' => self::DEFAULT_SCRIPT_ID, 'description' => 'Provided by Playstream (play-stream.tech) when the player was set up.' ),
		);
	}

	public function print_footer(): void {
		$script_id = $this->script_id();
		printf(
			'<script async id="PS_%1$s" type="text/javascript" src="https://player.play-stream.tech/scripts/%1$s.min.js"></script>',
			esc_attr( $script_id )
		);
	}

	private function script_id(): string {
		$opt = $this->option();
		return '' !== ( $opt['script_id'] ?? '' ) ? (string) $opt['script_id'] : self::DEFAULT_SCRIPT_ID;
	}
}
