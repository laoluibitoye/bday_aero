<?php
/**
 * Addon Name: Market Pulse
 * Addon Slug: market-pulse
 * Description: The homepage's auto-scrolling market ticker — an admin-editable, freely add/remove list of figures (NGX All-Share, NGN/USD, Brent Crude, inflation, MPR, FX reserves by default, plus whatever else the desk adds).
 * Cache Namespace: market_pulse
 * Settings Tab: Market Pulse
 * Default: on
 *
 * Storage: a single repeatable `items` list — reader/editor requested
 * full CRUD (add new figures, not just edit a fixed set). Every row,
 * including NGN/USD, is a plain manually-entered value. A live NGN/USD
 * feed (open.er-api.com via WP-Cron) was tried twice and removed both
 * times: the first attempt caused real 502/504s from a stale-cache
 * window that fell through to a synchronous, unlocked external HTTP
 * call in real visitors' own page-render requests; the second attempt
 * fixed that (WP-Cron only, never a visitor's request) but was still
 * reader-requested removed in favor of full manual control over every
 * figure here. The 'id' = 'ngn_usd' convention on that row is kept only
 * so a reintroduced live source in the future would have an unambiguous
 * row to attach to; it has no special behavior today.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/admin.php';

// One-time cleanup for a site where the second live-feed attempt above
// was already deployed and its cron scheduled — wp_unschedule_event()/
// delete_option() are cheap no-ops once there's nothing left to clean.
add_action(
	'init',
	static function (): void {
		$scheduled = wp_next_scheduled( 'bday_market_pulse_refresh_naira' );
		if ( $scheduled ) {
			wp_unschedule_event( $scheduled, 'bday_market_pulse_refresh_naira' );
		}
		delete_option( 'bday_market_pulse_naira_last_value' );
	}
);

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
