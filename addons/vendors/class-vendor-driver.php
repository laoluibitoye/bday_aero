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

	/**
	 * Phase 5 of the Bday_Aero roadmap: the missing listener side of
	 * ads-sharing-matrix's bday_ad_zone()/bday_render_ad_zone dispatch
	 * (addons/ads-sharing-matrix/includes/data.php) — a driver that cares
	 * about in-content ad placement (currently only GAM) overrides this;
	 * every other driver's no-op default means the action safely does
	 * nothing for them, matching this class's existing "unconfigured
	 * prints nothing" contract.
	 */
	public function render_zone( string $zone, ?WP_Post $post ): void {}

	protected function option(): array {
		$all = get_option( 'bday_addon_vendors', array() );
		return is_array( $all[ $this->slug() ] ?? null ) ? $all[ $this->slug() ] : array();
	}
}
