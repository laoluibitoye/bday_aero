<?php
/**
 * Shared interface every vendor driver implements. An unconfigured or
 * disabled driver prints nothing — no exceptions — which is what
 * structurally prevents the "fires on staging and production alike with
 * no way to turn it off" problem the audit found in the previous theme.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Bday_Vendor_Driver {

	abstract public function slug(): string;
	abstract public function is_configured(): bool;

	/** @return array<int, array<string, mixed>> field defs for the Integrations tab */
	public function settings_fields(): array {
		return array();
	}

	public function print_head(): void {}
	public function print_body_open(): void {}
	public function print_footer(): void {}

	protected function option(): array {
		$all = get_option( 'bday_addon_vendors', array() );
		return is_array( $all[ $this->slug() ] ?? null ) ? $all[ $this->slug() ] : array();
	}
}
