<?php
/**
 * Reintroduces an automatic NGN/USD feed for the market ticker — the previous attempt at this
 * (see addon.php's docblock) caused real 502/504s because a stale/missing cache fell through to
 * a synchronous, unlocked `wp_remote_get()` call inside real visitors' own page-render requests.
 *
 * This version never calls the external API from a page render, under any circumstance. A
 * WP-Cron job (its own separate request, not a visitor's) refreshes the value on a schedule and
 * writes it into the existing `bday_market_pulse` option's `ngn_usd` row; every page render just
 * reads that already-cached option like every other manually-entered row, exactly as it does
 * today. If the API is ever down or the cron hasn't run yet, the last known good value simply
 * stays in place — there is no fallback path that blocks a render on a live HTTP call.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BDAY_MARKET_PULSE_CRON_HOOK = 'bday_market_pulse_refresh_naira';

add_action(
	'init',
	static function (): void {
		if ( ! wp_next_scheduled( BDAY_MARKET_PULSE_CRON_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', BDAY_MARKET_PULSE_CRON_HOOK );
		}
	}
);

add_action( BDAY_MARKET_PULSE_CRON_HOOK, 'bday_market_pulse_refresh_naira_rate' );

function bday_market_pulse_refresh_naira_rate(): void {
	// open.er-api.com: free, unauthenticated, no key required — same source used previously.
	// This call only ever happens inside the WP-Cron request itself, never a visitor's.
	$response = wp_remote_get(
		'https://open.er-api.com/v6/latest/USD',
		array( 'timeout' => 5 )
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return; // Leave the last known good value in place — no fallback fetch anywhere else.
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	$rate = $body['rates']['NGN'] ?? null;
	if ( ! is_numeric( $rate ) ) {
		return;
	}

	$pulse = get_option( 'bday_market_pulse' );
	if ( ! is_array( $pulse ) || empty( $pulse['items'] ) ) {
		return;
	}

	$previous = get_option( 'bday_market_pulse_naira_last_value' );
	$formatted = '₦' . number_format( (float) $rate, 2 );

	foreach ( $pulse['items'] as &$item ) {
		if ( ( $item['id'] ?? '' ) !== 'ngn_usd' ) {
			continue;
		}
		$item['value'] = $formatted;
		if ( is_numeric( $previous ) && (float) $previous > 0 ) {
			$deltaPct = ( ( (float) $rate - (float) $previous ) / (float) $previous ) * 100;
			$item['note'] = ( $deltaPct >= 0 ? '+' : '' ) . number_format( $deltaPct, 2 ) . '%';
		}
		$item['note_type'] = 'percent';
		break;
	}
	unset( $item );

	update_option( 'bday_market_pulse', $pulse );
	update_option( 'bday_market_pulse_naira_last_value', $rate );
}
