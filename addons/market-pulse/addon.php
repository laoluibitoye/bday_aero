<?php
/**
 * Addon Name: Market Pulse
 * Addon Slug: market-pulse
 * Description: The homepage's six-figure market strip (NGX All-Share, Naira/USD, Brent Crude, inflation, MPR, FX reserves). Naira/USD is a live feed; the rest are manually entered.
 * Cache Namespace: market_pulse
 * Settings Tab: Market Pulse
 * Default: on
 *
 * Naira/USD is the one figure with a realistic free, keyless data source
 * (includes/live-feed.php, open.er-api.com) and is fetched live,
 * refreshed twice daily by WP-Cron. The other five stay manual: NGX
 * All-Share and Brent Crude would need a paid market-data vendor no one's
 * chosen yet (see the homepage-rebuild-plan review doc's §03), and
 * inflation/MPR/FX-reserves are periodic CBN releases — there is no live
 * feed for those to poll in the first place, live or otherwise.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/live-feed.php';

add_option(
	'bday_market_pulse',
	array(
		'ngx_value'       => '',
		'ngx_change'      => '',
		'naira_value'     => '',
		'naira_change'    => '',
		'brent_value'     => '',
		'brent_change'    => '',
		'inflation_value' => '',
		'inflation_note'  => '',
		'mpr_value'       => '',
		'mpr_note'        => '',
		'reserves_value'  => '',
		'reserves_change' => '',
	)
);
