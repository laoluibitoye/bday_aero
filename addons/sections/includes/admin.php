<?php
/**
 * Sections settings page: an ordered table of {key, label, category}
 * rows, reorderable by native HTML5 drag-and-drop (no jQuery/library —
 * same "dependency-free vanilla JS" convention as core/nav-menu.php's
 * walker). Reordering only ever moves the actual <tr> in the DOM; the
 * saved order is whatever order the rows physically submit in, so no
 * hidden "position" field bookkeeping is needed on top of that.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bday_render_sections_tab( array $values ): void {
	$sections  = bday_sections();
	$categories = get_categories( array( 'hide_empty' => false, 'number' => 300 ) );
	?>
	<p>Sections are the labeled links/headings used across the site (e.g. the homepage rail's "In Other News" / "Columnists" / "Opinion" headings). Reorder by dragging a row; each section's link is resolved from the category you assign it.</p>

	<table class="widefat bday-sections-table" id="bday-sections-table">
		<thead>
			<tr>
				<th style="width:24px"></th>
				<th>Key</th>
				<th>Label</th>
				<th>Category</th>
				<th style="width:60px"></th>
			</tr>
		</thead>
		<tbody id="bday-sections-tbody">
			<?php foreach ( $sections as $i => $section ) : ?>
				<?php bday_render_section_row( $i, $section, $categories ); ?>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p><button type="button" class="button" id="bday-sections-add">Add Section</button></p>

	<template id="bday-section-row-template">
		<?php bday_render_section_row( '__INDEX__', array(), $categories ); ?>
	</template>

	<?php submit_button(); ?>

	<script>
	(function () {
		var tbody = document.getElementById( 'bday-sections-tbody' );
		var addBtn = document.getElementById( 'bday-sections-add' );
		var template = document.getElementById( 'bday-section-row-template' );
		var dragged = null;
		var counter = <?php echo (int) count( $sections ); ?>;

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
			var remove = row.querySelector( '.bday-section-remove' );
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
	<style>
		.bday-sections-table tr.is-dragging { opacity: 0.4; }
		.bday-sections-table td { vertical-align: middle; }
		.bday-sections-table .bday-drag-handle { cursor: grab; color: #999; }
	</style>
	<?php
}

/** @param int|string $index @param array<string, string> $section @param WP_Category[] $categories */
function bday_render_section_row( $index, array $section, array $categories ): void {
	$key       = $section['key'] ?? '';
	$label     = $section['label'] ?? '';
	$term_slug = $section['term_slug'] ?? '';
	?>
	<tr>
		<td><span class="bday-drag-handle dashicons dashicons-menu"></span></td>
		<td><input type="text" class="regular-text" name="bday_sections[<?php echo esc_attr( $index ); ?>][key]" value="<?php echo esc_attr( $key ); ?>" placeholder="e.g. news"></td>
		<td><input type="text" class="regular-text" name="bday_sections[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" placeholder="e.g. In Other News"></td>
		<td>
			<select name="bday_sections[<?php echo esc_attr( $index ); ?>][term_slug]">
				<option value="">— Select a category —</option>
				<?php foreach ( $categories as $category ) : ?>
					<option value="<?php echo esc_attr( $category->slug ); ?>" <?php selected( $term_slug, $category->slug ); ?>><?php echo esc_html( $category->name ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="hidden" name="bday_sections[<?php echo esc_attr( $index ); ?>][taxonomy]" value="category">
		</td>
		<td><button type="button" class="button-link bday-section-remove" aria-label="Remove section">&times;</button></td>
	</tr>
	<?php
}

/** @return array<int, array{key: string, label: string, taxonomy: string, term_slug: string}> */
function bday_sanitize_sections( $input ): array {
	$rows = is_array( $input ) ? $input : array();
	$out  = array();

	foreach ( $rows as $row ) {
		$key   = isset( $row['key'] ) ? sanitize_key( wp_unslash( $row['key'] ) ) : '';
		$label = isset( $row['label'] ) ? sanitize_text_field( wp_unslash( $row['label'] ) ) : '';
		if ( '' === $key || '' === $label ) {
			continue; // an incomplete row (e.g. an added-then-abandoned "Add Section" row) is dropped, not saved half-filled
		}

		$out[] = array(
			'key'       => $key,
			'label'     => $label,
			'taxonomy'  => isset( $row['taxonomy'] ) ? sanitize_key( wp_unslash( $row['taxonomy'] ) ) : 'category',
			'term_slug' => isset( $row['term_slug'] ) ? sanitize_title( wp_unslash( $row['term_slug'] ) ) : '',
		);
	}

	return $out;
}
