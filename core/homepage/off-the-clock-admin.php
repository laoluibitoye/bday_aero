<?php
/**
 * "Off the Clock" categories — an admin-editable, ordered list of {label, category slug} rows
 * feeding the homepage's "Off the Clock" section (homepage-sections/weekender.php), same
 * add/remove/drag-reorder pattern as addons/sections/includes/admin.php. Previously this list was
 * a hardcoded PHP array in core/homepage/redesign-data.php (Weekender/Life & Arts/Sports/Cooking);
 * reader-requested to drop Weekender and make the list itself admin-editable, since new lifestyle
 * categories get added over time and shouldn't need a code change each time.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BDAY_OFF_THE_CLOCK_OPTION = 'bday_off_the_clock_categories';

add_action(
	'after_setup_theme',
	static function (): void {
		add_option(
			BDAY_OFF_THE_CLOCK_OPTION,
			array(
				array( 'label' => 'Cooking', 'category_slug' => 'cooking' ),
				array( 'label' => 'Sports', 'category_slug' => 'sports' ),
				array( 'label' => 'Life & Arts', 'category_slug' => 'life-arts' ),
			)
		);
	}
);

/** @return array<int, array{label: string, category_slug: string}> */
function bday_off_the_clock_categories(): array {
	$rows = get_option( BDAY_OFF_THE_CLOCK_OPTION, array() );
	return is_array( $rows ) ? $rows : array();
}

function bday_render_off_the_clock_tab( array $values ): void {
	$rows       = bday_off_the_clock_categories();
	$categories = get_categories( array( 'hide_empty' => false, 'number' => 300 ) );
	?>
	<p class="description" style="margin-bottom:16px;">
		Each row becomes one column of the homepage's "Off the Clock" section, in this order. A category with no
		published posts is skipped automatically rather than showing an empty column.
	</p>
	<table class="widefat bday-sections-table" id="bday-off-the-clock-table">
		<thead>
			<tr>
				<th style="width:24px"></th>
				<th>Column label</th>
				<th>Category</th>
				<th style="width:60px"></th>
			</tr>
		</thead>
		<tbody id="bday-off-the-clock-tbody">
			<?php foreach ( $rows as $i => $row ) : ?>
				<?php bday_render_off_the_clock_row( $i, $row, $categories ); ?>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p><button type="button" class="button" id="bday-off-the-clock-add">Add Category</button></p>

	<template id="bday-off-the-clock-row-template">
		<?php bday_render_off_the_clock_row( '__INDEX__', array(), $categories ); ?>
	</template>

	<style>
		.bday-sections-table tr.is-dragging { opacity: 0.4; }
		.bday-sections-table td { vertical-align: middle; }
		.bday-sections-table .bday-drag-handle { cursor: grab; color: #999; }
	</style>
	<script>
	(function () {
		var tbody = document.getElementById( 'bday-off-the-clock-tbody' );
		var addBtn = document.getElementById( 'bday-off-the-clock-add' );
		var template = document.getElementById( 'bday-off-the-clock-row-template' );
		var dragged = null;
		var counter = <?php echo (int) count( $rows ); ?>;

		function bindRow( row ) {
			row.setAttribute( 'draggable', 'true' );
			row.addEventListener( 'dragstart', function () {
				dragged = row;
				row.classList.add( 'is-dragging' );
			} );
			row.addEventListener( 'dragend', function () {
				row.classList.remove( 'is-dragging' );
			} );
			row.addEventListener( 'dragover', function ( e ) {
				e.preventDefault();
			} );
			row.addEventListener( 'drop', function ( e ) {
				e.preventDefault();
				if ( ! dragged || dragged === row ) {
					return;
				}
				var rect = row.getBoundingClientRect();
				var before = ( e.clientY - rect.top ) < rect.height / 2;
				row.parentNode.insertBefore( dragged, before ? row : row.nextSibling );
			} );
			var remove = row.querySelector( '.bday-off-the-clock-remove' );
			if ( remove ) {
				remove.addEventListener( 'click', function () {
					row.remove();
				} );
			}
		}

		Array.prototype.forEach.call( tbody.querySelectorAll( 'tr' ), bindRow );

		addBtn.addEventListener( 'click', function () {
			var html = template.innerHTML.replace( /__INDEX__/g, 'new' + ( counter++ ) );
			var wrapper = document.createElement( 'tbody' );
			wrapper.innerHTML = html.trim();
			var row = wrapper.firstElementChild;
			tbody.appendChild( row );
			bindRow( row );
		} );
	})();
	</script>
	<?php
}

/** @param int|string $index @param array{label?: string, category_slug?: string} $row @param WP_Category[] $categories */
function bday_render_off_the_clock_row( $index, array $row, array $categories ): void {
	$label = $row['label'] ?? '';
	$slug  = $row['category_slug'] ?? '';
	?>
	<tr>
		<td><span class="bday-drag-handle dashicons dashicons-menu"></span></td>
		<td><input type="text" class="regular-text" name="<?php echo esc_attr( BDAY_OFF_THE_CLOCK_OPTION ); ?>[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" placeholder="e.g. Cooking"></td>
		<td>
			<select name="<?php echo esc_attr( BDAY_OFF_THE_CLOCK_OPTION ); ?>[<?php echo esc_attr( $index ); ?>][category_slug]">
				<option value="">— Select a category —</option>
				<?php foreach ( $categories as $category ) : ?>
					<option value="<?php echo esc_attr( $category->slug ); ?>" <?php selected( $slug, $category->slug ); ?>><?php echo esc_html( $category->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
		<td><button type="button" class="button-link bday-off-the-clock-remove" aria-label="Remove category">&times;</button></td>
	</tr>
	<?php
}

/** @return array<int, array{label: string, category_slug: string}> */
function bday_sanitize_off_the_clock( $input ): array {
	$rows = is_array( $input ) ? $input : array();
	$out  = array();

	foreach ( $rows as $row ) {
		$label = isset( $row['label'] ) ? sanitize_text_field( wp_unslash( $row['label'] ) ) : '';
		$slug  = isset( $row['category_slug'] ) ? sanitize_title( wp_unslash( $row['category_slug'] ) ) : '';
		if ( '' === $label || '' === $slug ) {
			continue;
		}
		$out[] = array( 'label' => $label, 'category_slug' => $slug );
	}

	return $out;
}

add_filter(
	'bday_settings_schema',
	static function ( array $schema ): array {
		$schema['off_the_clock'] = array(
			'tab_label' => 'Off the Clock',
				'group'     => 'editorial',
			'option'    => BDAY_OFF_THE_CLOCK_OPTION,
			'render'    => 'bday_render_off_the_clock_tab',
			'intro'     => 'Which categories make up the homepage\'s "Off the Clock" lifestyle section, and in what order. Add or remove rows as new lifestyle categories come online.',
		);
		return $schema;
	}
);

add_action(
	'admin_init',
	static function (): void {
		register_setting(
			BDAY_OFF_THE_CLOCK_OPTION,
			BDAY_OFF_THE_CLOCK_OPTION,
			array( 'sanitize_callback' => 'bday_sanitize_off_the_clock' )
		);
	}
);
