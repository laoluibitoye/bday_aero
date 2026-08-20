<?php
/**
 * Addon Name: Market Pulse
 * Addon Slug: market-pulse
 * Description: The homepage's auto-scrolling market ticker — an admin-editable, freely add/remove list of figures (NGX All-Share, NGN/USD, Brent Crude, inflation, MPR, FX reserves by default, plus whatever else the desk adds).
 * Cache Namespace: market_pulse
 * Settings Tab: Market Pulse
 * Default: on
 *
 * Storage moved from a fixed set of named fields (ngx_value/ngx_change/
 * naira_value/...) to a single repeatable `items` list — reader/editor
 * requested full CRUD (add new figures, not just edit the original six).
 * The one figure with a realistic free, keyless live source (NGN/USD,
 * includes/live-feed.php, open.er-api.com, refreshed twice daily by
 * WP-Cron) stays live by convention: whichever item has id 'ngn_usd' gets
 * the live value merged in at render time, same as before, but it's now
 * just a regular (deletable, reorderable) row like any other rather than
 * a hardcoded slot.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/live-feed.php';

add_option(
	'bday_market_pulse',
	array(
		'items'          => array(
			array( 'id' => 'ngx', 'label' => 'NGX All-Share', 'value' => '', 'note' => '', 'note_type' => 'percent' ),
			array( 'id' => 'ngn_usd', 'label' => 'NGN / USD', 'value' => '', 'note' => '', 'note_type' => 'percent' ),
			array( 'id' => 'brent', 'label' => 'Brent Crude', 'value' => '', 'note' => '', 'note_type' => 'percent' ),
			array( 'id' => 'inflation', 'label' => 'Inflation', 'value' => '', 'note' => '', 'note_type' => 'text' ),
			array( 'id' => 'mpr', 'label' => 'MPR', 'value' => '', 'note' => '', 'note_type' => 'text' ),
			array( 'id' => 'reserves', 'label' => 'FX Reserves', 'value' => '', 'note' => '', 'note_type' => 'percent' ),
		),
		'scroll_seconds' => 30,
	)
);
