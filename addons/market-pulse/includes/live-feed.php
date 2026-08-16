<?php
/**
 * Naira/USD live feed — the one Market Pulse figure with a realistic free,
 * keyless data source (open.er-api.com; no API key, no signup, updates
 * roughly hourly). The other five figures deliberately stay manual-entry
 * only (see addon.php's docblock): NGX All-Share and Brent Crude would
 * need a paid market-data vendor this project hasn't chosen yet, and
 * inflation/MPR/FX-reserves are periodic CBN releases with no live feed
 * to poll in the first place — not a gap in this integration, just what
 * those numbers actually are.
 *
 * A WP-Cron event refreshes the cached rate in the background so no
 * visitor's page load ever waits on the external HTTP call; the cached
 * value is read synchronously and is what the homepage actually renders.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BDAY_MARKET_PULSE_NAIRA_TRANSIENT = 'bday_market_pulse_naira_live';
const BDAY_MARKET_PULSE_CRON_HOOK       = 'bday_market_pulse_refresh_naira';

/**
 * Fetches USD→NGN from open.er-api.com and caches it. Called by the cron
 * hook below, and once synchronously on a cache miss so the very first
 * page load after activation isn't stuck waiting for cron to fire.
 *
 * @return array{value: string, change: string}|null
 */
function bday_market_pulse_fetch_naira_live(): ?array {
	$response = wp_remote_get(
		'https://open.er-api.com/v6/latest/USD',
		array( 'timeout' => 5 )
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	$rate = $body['rates']['NGN'] ?? null;

	if ( ! is_numeric( $rate ) ) {
		return null;
	}

	$previous = get_transient( BDAY_MARKET_PULSE_NAIRA_TRANSIENT );
	$change   = '';
	if ( is_array( $previous ) && isset( $previous['raw'] ) && $previous['raw'] > 0 ) {
		$pct    = ( ( (float) $rate - $previous['raw'] ) / $previous['raw'] ) * 100;
		$change = ( $pct >= 0 ? '+' : '' ) . number_format( $pct, 2 ) . '%';
	}

	$result = array(
		'raw'    => (float) $rate,
		'value'  => '₦' . number_format( (float) $rate, 0 ),
		'change' => $change,
	);

	// 6 hours — the free tier's own data only updates roughly hourly
	// anyway; no point polling more often than the source itself refreshes.
	set_transient( BDAY_MARKET_PULSE_NAIRA_TRANSIENT, $result, 6 * HOUR_IN_SECONDS );

	return array( 'value' => $result['value'], 'change' => $result['change'] );
}

/** @return array{value: string, change: string}|null */
function bday_market_pulse_naira_live(): ?array {
	$cached = get_transient( BDAY_MARKET_PULSE_NAIRA_TRANSIENT );
	if ( is_array( $cached ) ) {
		return array( 'value' => $cached['value'], 'change' => $cached['change'] );
	}

	// Cache miss (first run, or the transient expired and cron hasn't
	// caught up yet) — fetch once synchronously rather than showing
	// nothing live at all until the next cron tick.
	return bday_market_pulse_fetch_naira_live();
}

add_action( BDAY_MARKET_PULSE_CRON_HOOK, 'bday_market_pulse_fetch_naira_live' );

add_action(
	'init',
	static function (): void {
		if ( ! wp_next_scheduled( BDAY_MARKET_PULSE_CRON_HOOK ) ) {
			wp_schedule_event( time(), 'twicedaily', BDAY_MARKET_PULSE_CRON_HOOK );
		}
	}
);
