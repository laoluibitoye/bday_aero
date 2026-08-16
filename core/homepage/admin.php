<?php
/**
 * "Homepage Sections" settings tab — a drag-reorderable, enable/disable
 * checklist of every file auto-discovered by Bday_Section_Registry. Same
 * native-HTML5-drag-and-drop pattern as addons/sections/includes/admin.php
 * (no jQuery/library), but simpler: this list's *rows* are fixed (one per
 * section file on disk), so there's no add/remove-row UI, only reorder +
 * toggle — a section is added or removed by shipping/deleting a file
 * under homepage-sections/, not from this screen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * First-run default order only — add_option() is a no-op once the option
 * exists, so this never overwrites an admin's saved reorder. Without this,
 * Bday_Section_Registry::ordered_active() has nothing saved to reconcile
 * against and falls entirely into its "append newly discovered sections"
 * branch, which orders by glob() — i.e. alphabetically by filename, not
 * the intended editorial sequence (found live: Hero rendered after
 * Browse the Desks because "browse-desks.php" sorts before "hero.php").
 * Same first-run seeding pattern as addons/sections/addon.php.
 */
add_action(
	'after_setup_theme',
	static function (): void {
		add_option(
			'bday_homepage_sections',
			array_map(
				static fn( string $slug ): array => array( 'slug' => $slug, 'enabled' => true ),
				array(
					'hero',
					'market-pulse',
					'premium',
					'your-news',
					'headlines',
					'in-other-news',
					'toon',
					'todays-paper-teaser',
					'editor-pick',
					'columnists',
					'opinion',
					'investigates',
					'in-pictures',
					'browse-desks',
					'topic-triple',
					'newsletter',
					'watch-listen',
					'editions',
					'interview',
					'weekender',
					'partner-content',
					'events',
					'bottom-widgets-hooks',
					'latest-stories',
				)
			)
		);
	}
);

function bday_render_homepage_sections_tab( array $values ): void {
	$rows = Bday_Section_Registry::ordered_all();
	$meta = Bday_Section_Registry::discover();

	if ( empty( $rows ) ) {
		echo '<p>No homepage-sections/*.php files found yet.</p>';
		return;
	}
	?>
	<p class="description" style="margin-bottom:16px;">
		Drag to reorder. Only checked sections render, and only on the
		<strong>Redesign 2026</strong> homepage variant — switch to it under
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bday-theme-settings-homepage' ) ); ?>">Homepage Variants</a>
		to preview or go live with it.
	</p>
	<table class="widefat bday-sections-table" id="bday-homepage-sections-table">
		<thead>
			<tr>
				<th style="width:24px"></th>
				<th style="width:70px">Enabled</th>
				<th>Section</th>
				<th>Description</th>
			</tr>
		</thead>
		<tbody id="bday-homepage-sections-tbody">
			<?php foreach ( $rows as $i => $row ) : ?>
				<?php bday_render_homepage_section_row( $i, $row, $meta[ $row['slug'] ] ?? array() ); ?>
			<?php endforeach; ?>
		</tbody>
	</table>
	<style>
		.bday-sections-table tr.is-dragging { opacity: 0.4; }
		.bday-sections-table td { vertical-align: middle; }
		.bday-sections-table .bday-drag-handle { cursor: grab; color: #999; }
	</style>
	<script>
	(function () {
		var tbody = document.getElementById( 'bday-homepage-sections-tbody' );
		var dragged = null;

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
		}

		Array.prototype.forEach.call( tbody.querySelectorAll( 'tr' ), bindRow );
	})();
	</script>
	<?php
}

/** @param int $index @param array{slug: string, enabled: bool} $row @param array<string, mixed> $meta */
function bday_render_homepage_section_row( int $index, array $row, array $meta ): void {
	?>
	<tr>
		<td><span class="bday-drag-handle dashicons dashicons-menu"></span></td>
		<td>
			<label>
				<input type="hidden" name="bday_homepage_sections[<?php echo esc_attr( $index ); ?>][slug]" value="<?php echo esc_attr( $row['slug'] ); ?>">
				<input type="checkbox" name="bday_homepage_sections[<?php echo esc_attr( $index ); ?>][enabled]" value="1" <?php checked( $row['enabled'] ); ?>>
			</label>
		</td>
		<td><strong><?php echo esc_html( $meta['label'] ?? $row['slug'] ); ?></strong></td>
		<td><span class="description"><?php echo esc_html( $meta['description'] ?? '' ); ?></span></td>
	</tr>
	<?php
}

/** @return array<int, array{slug: string, enabled: bool}> */
function bday_sanitize_homepage_sections( $input ): array {
	$rows = is_array( $input ) ? $input : array();
	$out  = array();

	foreach ( $rows as $row ) {
		$slug = isset( $row['slug'] ) ? sanitize_key( wp_unslash( $row['slug'] ) ) : '';
		if ( '' === $slug ) {
			continue;
		}
		$out[] = array(
			'slug'    => $slug,
			'enabled' => ! empty( $row['enabled'] ),
		);
	}

	return $out;
}

add_filter(
	'bday_settings_schema',
	static function ( array $schema ): array {
		$schema['homepage_sections'] = array(
			'tab_label' => 'Homepage Sections',
			'option'    => 'bday_homepage_sections',
			'render'    => 'bday_render_homepage_sections_tab',
			'intro'     => 'Every section available to the "Redesign 2026" homepage layout, in the order it renders. Toggle a section off to skip it entirely (no query runs, nothing renders) — this does not affect the classic Default/Weekend homepage layouts, which are unrelated template files.',
			'about'     => '<p>A new section shows up here automatically the first time it\'s deployed (appended to the end, on by default) — nothing needs configuring for it to appear, only reordering if the default position isn\'t right.</p>',
		);
		return $schema;
	}
);

add_action(
	'admin_init',
	static function (): void {
		register_setting(
			'bday_homepage_sections',
			'bday_homepage_sections',
			array( 'sanitize_callback' => 'bday_sanitize_homepage_sections' )
		);
	}
);
