<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @return array<int, array{key: string, label: string}> */
function bday_market_pulse_fields(): array {
	return array(
		array( 'key' => 'ngx_value', 'label' => 'NGX All-Share — value' ),
		array( 'key' => 'ngx_change', 'label' => 'NGX All-Share — change (e.g. +0.82%)' ),
		array( 'key' => 'naira_value', 'label' => 'Naira / USD — fallback value (live feed is used when it responds)' ),
		array( 'key' => 'naira_change', 'label' => 'Naira / USD — fallback change' ),
		array( 'key' => 'brent_value', 'label' => 'Brent Crude — value' ),
		array( 'key' => 'brent_change', 'label' => 'Brent Crude — change' ),
		array( 'key' => 'inflation_value', 'label' => 'Inflation — value' ),
		array( 'key' => 'inflation_note', 'label' => 'Inflation — note (e.g. "July est.")' ),
		array( 'key' => 'mpr_value', 'label' => 'MPR — value' ),
		array( 'key' => 'mpr_note', 'label' => 'MPR — note (e.g. "Held")' ),
		array( 'key' => 'reserves_value', 'label' => 'FX Reserves — value' ),
		array( 'key' => 'reserves_change', 'label' => 'FX Reserves — change' ),
	);
}

function bday_render_market_pulse_tab( array $values ): void {
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( bday_market_pulse_fields() as $field ) {
		printf(
			'<tr><th scope="row">%1$s</th><td><input type="text" name="bday_market_pulse[%2$s]" value="%3$s" class="regular-text"></td></tr>',
			esc_html( $field['label'] ),
			esc_attr( $field['key'] ),
			esc_attr( (string) ( $values[ $field['key'] ] ?? '' ) )
		);
	}
	echo '</tbody></table>';
	echo '<p class="description">Leave any field blank to hide that figure from the homepage strip. There is no live feed behind these — update them as often as the desk wants the strip refreshed.</p>';
}

/** @return array<string, string> */
function bday_sanitize_market_pulse( $input ): array {
	$input = is_array( $input ) ? $input : array();
	$out   = array();
	foreach ( bday_market_pulse_fields() as $field ) {
		$out[ $field['key'] ] = isset( $input[ $field['key'] ] ) ? sanitize_text_field( wp_unslash( $input[ $field['key'] ] ) ) : '';
	}
	return $out;
}

add_filter(
	'bday_settings_schema',
	static function ( array $schema ): array {
		$schema['market-pulse'] = array(
			'tab_label' => 'Market Pulse',
			'option'    => 'bday_market_pulse',
			'render'    => 'bday_render_market_pulse_tab',
			'intro'     => 'The homepage\'s market strip — six figures, all manually entered. No vendor feed is wired up yet (see the homepage-rebuild-plan review doc for the tradeoffs); this is the deliberate first-release version.',
		);
		return $schema;
	}
);

add_action(
	'admin_init',
	static function (): void {
		register_setting(
			'bday_market_pulse',
			'bday_market_pulse',
			array( 'sanitize_callback' => 'bday_sanitize_market_pulse' )
		);
	}
);
