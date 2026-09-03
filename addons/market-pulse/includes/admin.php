<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads back a pre-migration `bday_market_pulse` option (the old fixed
 * ngx_value/ngx_change/naira_value/... shape) as the new `items` list, so
 * a site with real data already saved under the old schema doesn't lose
 * it the moment this update lands. Only used when `items` is absent —
 * once an admin saves the settings form again it's stored in the new
 * shape from then on, so this is a read-time fallback, not a one-time
 * migration script that has to be run.
 *
 * @param array<string, mixed> $legacy
 * @return array<int, array{id: string, label: string, value: string, note: string, note_type: string}>
 */
function bday_market_pulse_migrate_legacy_items( array $legacy ): array {
	$map = array(
		'ngx'       => array( 'label' => 'NGX All-Share', 'value_key' => 'ngx_value', 'note_key' => 'ngx_change', 'note_type' => 'percent' ),
		'ngn_usd'   => array( 'label' => 'NGN / USD', 'value_key' => 'naira_value', 'note_key' => 'naira_change', 'note_type' => 'percent' ),
		'brent'     => array( 'label' => 'Brent Crude', 'value_key' => 'brent_value', 'note_key' => 'brent_change', 'note_type' => 'percent' ),
		'inflation' => array( 'label' => 'Inflation', 'value_key' => 'inflation_value', 'note_key' => 'inflation_note', 'note_type' => 'text' ),
		'mpr'       => array( 'label' => 'MPR', 'value_key' => 'mpr_value', 'note_key' => 'mpr_note', 'note_type' => 'text' ),
		'reserves'  => array( 'label' => 'FX Reserves', 'value_key' => 'reserves_value', 'note_key' => 'reserves_change', 'note_type' => 'percent' ),
	);

	$items = array();
	foreach ( $map as $id => $field ) {
		$items[] = array(
			'id'        => $id,
			'label'     => $field['label'],
			'value'     => (string) ( $legacy[ $field['value_key'] ] ?? '' ),
			'note'      => (string) ( $legacy[ $field['note_key'] ] ?? '' ),
			'note_type' => $field['note_type'],
		);
	}
	return $items;
}

/**
 * Normalizes whatever's actually stored in the option (new shape,
 * legacy-migrated, or a fresh install's just-added defaults) into the
 * `items` list, plus the scroll-speed setting.
 *
 * @param array<string, mixed> $values
 * @return array{items: array<int, array{id: string, label: string, value: string, note: string, note_type: string}>, scroll_seconds: int}
 */
function bday_market_pulse_normalize( array $values ): array {
	$items = isset( $values['items'] ) && is_array( $values['items'] )
		? $values['items']
		: bday_market_pulse_migrate_legacy_items( $values );

	$normalized = array();
	foreach ( $items as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$normalized[] = array(
			'id'        => (string) ( $item['id'] ?? '' ),
			'label'     => (string) ( $item['label'] ?? '' ),
			'value'     => (string) ( $item['value'] ?? '' ),
			'note'      => (string) ( $item['note'] ?? '' ),
			'note_type' => in_array( $item['note_type'] ?? '', array( 'percent', 'text' ), true ) ? $item['note_type'] : 'percent',
		);
	}

	$scroll_seconds = (int) ( $values['scroll_seconds'] ?? 30 );
	if ( $scroll_seconds < 5 || $scroll_seconds > 300 ) {
		$scroll_seconds = 30;
	}

	return array( 'items' => $normalized, 'scroll_seconds' => $scroll_seconds );
}

/**
 * Repeatable-row admin UI — one row per figure, "+ Add item" clones a
 * <template> row via vanilla JS (no build step, matches every other
 * native BusinessDay Theme settings tab), ↑/↓ buttons reorder without
 * needing a drag-and-drop library. Every row's inputs are named
 * items[__INDEX__][field] where __INDEX__ is a JS-assigned running
 * counter — PHP just iterates whatever rows actually arrive in $_POST,
 * so gaps/reordering never break submission.
 */
function bday_render_market_pulse_tab( array $values ): void {
	$state = bday_market_pulse_normalize( $values );
	?>
	<p class="description">Each row is one figure on the homepage's scrolling market ticker. Leave a row's value blank (or remove it) to drop it from the strip. Every figure is manually entered — update a value here whenever the desk wants the strip refreshed.</p>

	<table class="widefat striped bday-market-pulse-table" style="max-width:820px;">
		<thead>
			<tr>
				<th style="width:26%;">Label</th>
				<th style="width:20%;">Value</th>
				<th style="width:16%;">Note type</th>
				<th style="width:24%;">Change / note</th>
				<th style="width:14%;"></th>
			</tr>
		</thead>
		<tbody id="bday-market-pulse-rows">
			<?php foreach ( $state['items'] as $i => $item ) : ?>
				<?php echo bday_market_pulse_row_html( $i, $item ); ?>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p><button type="button" class="button" id="bday-market-pulse-add-row">+ Add item</button></p>

	<template id="bday-market-pulse-row-template">
		<?php echo bday_market_pulse_row_html( '__INDEX__', array( 'id' => '', 'label' => '', 'value' => '', 'note' => '', 'note_type' => 'percent' ) ); ?>
	</template>

	<h3>Scrolling</h3>
	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row">Scroll speed</th>
				<td>
					<input type="number" min="5" max="300" name="bday_market_pulse[scroll_seconds]" value="<?php echo esc_attr( (string) $state['scroll_seconds'] ); ?>" class="small-text">
					seconds per full loop
					<p class="description">Lower = faster scroll. The strip auto-scrolls horizontally and pauses while a reader's mouse is over it.</p>
				</td>
			</tr>
		</tbody>
	</table>

	<script>
	(function () {
		var tbody = document.getElementById('bday-market-pulse-rows');
		var addBtn = document.getElementById('bday-market-pulse-add-row');
		var template = document.getElementById('bday-market-pulse-row-template');
		var nextIndex = <?php echo (int) count( $state['items'] ); ?>;

		function bind(row) {
			var removeBtn = row.querySelector('[data-bday-remove-row]');
			if (removeBtn) {
				removeBtn.addEventListener('click', function () {
					row.remove();
				});
			}
			var upBtn = row.querySelector('[data-bday-move-up]');
			if (upBtn) {
				upBtn.addEventListener('click', function () {
					var prev = row.previousElementSibling;
					if (prev) tbody.insertBefore(row, prev);
				});
			}
			var downBtn = row.querySelector('[data-bday-move-down]');
			if (downBtn) {
				downBtn.addEventListener('click', function () {
					var next = row.nextElementSibling;
					if (next) tbody.insertBefore(next, row);
				});
			}
		}

		Array.prototype.forEach.call(tbody.querySelectorAll('tr'), bind);

		addBtn.addEventListener('click', function () {
			var html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
			nextIndex++;
			var wrapper = document.createElement('tbody');
			wrapper.innerHTML = html;
			var row = wrapper.firstElementChild;
			tbody.appendChild(row);
			bind(row);
		});
	})();
	</script>
	<?php
}

/** @param int|string $index */
function bday_market_pulse_row_html( $index, array $item ): string {
	ob_start();
	?>
	<tr>
		<td>
			<input type="hidden" name="bday_market_pulse[items][<?php echo esc_attr( (string) $index ); ?>][id]" value="<?php echo esc_attr( $item['id'] ); ?>">
			<input type="text" name="bday_market_pulse[items][<?php echo esc_attr( (string) $index ); ?>][label]" value="<?php echo esc_attr( $item['label'] ); ?>" class="regular-text" placeholder="e.g. Cocoa">
		</td>
		<td>
			<input type="text" name="bday_market_pulse[items][<?php echo esc_attr( (string) $index ); ?>][value]" value="<?php echo esc_attr( $item['value'] ); ?>" class="regular-text" placeholder="e.g. ₦1,530">
		</td>
		<td>
			<select name="bday_market_pulse[items][<?php echo esc_attr( (string) $index ); ?>][note_type]">
				<option value="percent" <?php selected( $item['note_type'], 'percent' ); ?>>Percentage change</option>
				<option value="text" <?php selected( $item['note_type'], 'text' ); ?>>Note</option>
			</select>
		</td>
		<td>
			<input type="text" name="bday_market_pulse[items][<?php echo esc_attr( (string) $index ); ?>][note]" value="<?php echo esc_attr( $item['note'] ); ?>" class="regular-text" placeholder="e.g. +0.82% or &quot;July est.&quot;">
		</td>
		<td>
			<button type="button" class="button" data-bday-move-up title="Move up">↑</button>
			<button type="button" class="button" data-bday-move-down title="Move down">↓</button>
			<button type="button" class="button" data-bday-remove-row title="Remove">✕</button>
		</td>
	</tr>
	<?php
	return (string) ob_get_clean();
}

/** @return array{items: array<int, array{id: string, label: string, value: string, note: string, note_type: string}>, scroll_seconds: int} */
function bday_sanitize_market_pulse( $input ): array {
	$input = is_array( $input ) ? $input : array();
	$raw_items = is_array( $input['items'] ?? null ) ? $input['items'] : array();

	$items = array();
	foreach ( $raw_items as $raw ) {
		if ( ! is_array( $raw ) ) {
			continue;
		}
		$label = sanitize_text_field( wp_unslash( $raw['label'] ?? '' ) );
		$value = sanitize_text_field( wp_unslash( $raw['value'] ?? '' ) );
		// A row an admin added via "+ Add item" but never filled in and
		// never removed shouldn't persist as a permanently blank slot on
		// the homepage strip — skip it entirely rather than saving it.
		if ( '' === $label && '' === $value ) {
			continue;
		}
		$id = sanitize_key( wp_unslash( $raw['id'] ?? '' ) );
		$items[] = array(
			'id'        => $id, // '' for a brand-new row is fine — no id is looked up specially anymore.
			'label'     => $label,
			'value'     => $value,
			'note'      => sanitize_text_field( wp_unslash( $raw['note'] ?? '' ) ),
			'note_type' => in_array( $raw['note_type'] ?? '', array( 'percent', 'text' ), true ) ? $raw['note_type'] : 'percent',
		);
	}

	$scroll_seconds = (int) ( $input['scroll_seconds'] ?? 30 );
	if ( $scroll_seconds < 5 || $scroll_seconds > 300 ) {
		$scroll_seconds = 30;
	}

	return array( 'items' => $items, 'scroll_seconds' => $scroll_seconds );
}

add_filter(
	'bday_settings_schema',
	static function ( array $schema ): array {
		$schema['market-pulse'] = array(
			'tab_label' => 'Market Pulse',
				'group'     => 'editorial',
			'option'    => 'bday_market_pulse',
			'render'    => 'bday_render_market_pulse_tab',
			'intro'     => "The homepage's auto-scrolling market ticker. Add, remove, and reorder figures freely — there's no fixed list.",
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
