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
 * full CRUD (add new figures, not just edit a fixed set). NGN/USD is
 * auto-refreshed hourly via WP-Cron (includes/live-feed.php) — a
 * previous attempt at this was removed after it caused real 502/504s
 * from a stale-cache window that fell through to a synchronous,
 * unlocked external HTTP call in real visitors' own page-render
 * requests. The reintroduced version never calls the external API
 * outside the WP-Cron request itself; every page render only ever
 * reads the already-cached option value, same as every other figure
 * here. Every other row (Brent Crude, inflation, MPR, FX reserves) is
 * still a plain manually-entered value — no free, keyless market-data
 * API reliably covers those.
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
