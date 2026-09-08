<?php
/**
 * "Homepage Sections" settings tab — a drag-reorderable, enable/disable
 * checklist of every file auto-discovered by Bday_Section_Registry, plus
 * (new) a Title and Source column per row backed by Bday_Section_Content:
 * Title free-text-overrides the <h2> a section prints (falls back to the
 * shipped default the moment it's cleared); Source only appears for the
 * sections listed in bday_section_sources() — the ones driven by exactly
 * one tag/category — and lets that tag/category be swapped for another
 * one, e.g. repurposing "Columnists" to source from a different category
 * without touching a template file. Same native-HTML5-drag-and-drop
 * pattern as addons/sections/includes/admin.php (no jQuery/library), but
 * simpler: this list's *rows* are fixed (one per section file on disk),
 * so there's no add/remove-row UI, only reorder + toggle — a section is
 * added or removed by shipping/deleting a file under homepage-sections/,
 * not from this screen.
 *
 * This tab (and the older, similarly-shaped "Sections" tab —
 * addons/sections/) default to Technical Team + Administrator only (see
 * the bday_settings_tab_roles seed below) since retitling a section or
 * repointing its content source changes what a section *is*, not the
 * day-to-day editorial call of what's published through it — an admin
 * can still widen or narrow that from Access Control at any time, this
 * is only the out-of-the-box default.
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
					'ysot',
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
	<p class="description" style="margin-bottom:16px;">
		<strong>Title</strong> overrides the heading a section prints on the homepage — leave blank to use the
		shipped default shown as its placeholder. <strong>Source</strong> and <strong>Style</strong> only apply
		to the same eight sections built from a single tag or category (Columnists, Opinion, Premium, BD
		Investigates, The Interview, Partner Content, YSoT, Latest Stories) — Source repoints which tag/category
		feeds a section, Style swaps its visual layout for one of three reusable ones (Grid: lead + author grid,
		Investigative: dark wide feature, Premium: feature + medium + list), so a section can be fully
		repurposed — different content, different look — without touching a template file. Sections built from
		more than one source, or already configurable elsewhere (Your News, Off the Clock), show "—" for both.
	</p>
	<table class="widefat bday-sections-table" id="bday-homepage-sections-table">
		<thead>
			<tr>
				<th style="width:24px"></th>
				<th style="width:70px">Enabled</th>
				<th style="width:13%">Section</th>
				<th style="width:19%">Title</th>
				<th style="width:16%">Source</th>
				<th style="width:14%">Style</th>
				<th style="width:22%">Description</th>
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
		/* Scoped by #bday-homepage-sections-table, not the shared
		   .bday-sections-table class — the embedded Sections table further
		   down this page (addons/sections/) has a completely different
		   column layout and must not inherit these widths. table-layout:
		   fixed makes the <th> widths above authoritative instead of hints
		   content can still override, which is what was letting a long
		   Description value squeeze Source/Style down to an unusably
		   narrow, wrapped column. */
		#bday-homepage-sections-table { table-layout: fixed; }
		#bday-homepage-sections-table td { overflow-wrap: break-word; }
		#bday-homepage-sections-table input[type="text"],
		#bday-homepage-sections-table select { max-width: 100%; box-sizing: border-box; }
		#bday-homepage-sections-table td:nth-child(5) { display: flex; gap: 6px; flex-wrap: wrap; }
		#bday-homepage-sections-table td:nth-child(5) select { flex: 0 0 auto; }
		#bday-homepage-sections-table td:nth-child(5) input { flex: 1 1 80px; min-width: 80px; }
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
	// Embedded, not its own settings tab (see addons/sections/addon.php's
	// docblock for why) — guarded by function_exists() because the
	// Sections add-on can be turned off from the General tab, in which
	// case its admin.php was never require()'d and this function simply
	// doesn't exist.
	if ( function_exists( 'bday_render_sections_tab' ) ) {
		echo '<hr style="margin:32px 0;">';
		bday_render_sections_tab( $values );
	}
}

/** @param int $index @param array{slug: string, enabled: bool} $row @param array<string, mixed> $meta */
function bday_render_homepage_section_row( int $index, array $row, array $meta ): void {
	$slug            = $row['slug'];
	$title_defaults  = Bday_Section_Content::title_defaults();
	$sources         = bday_section_sources();
	$content         = Bday_Section_Content::row( $slug );
	$has_title       = isset( $title_defaults[ $slug ] );
	$source_default  = $sources[ $slug ] ?? null;
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
		<td>
			<?php if ( $has_title ) : ?>
				<input
					type="text"
					class="regular-text"
					name="bday_homepage_section_content[<?php echo esc_attr( $slug ); ?>][title]"
					value="<?php echo esc_attr( $content['title'] ?? '' ); ?>"
					placeholder="<?php echo esc_attr( $title_defaults[ $slug ] ); ?>"
				>
			<?php else : ?>
				<span class="description">—</span>
			<?php endif; ?>
		</td>
		<td>
			<?php if ( null !== $source_default ) : ?>
				<select name="bday_homepage_section_content[<?php echo esc_attr( $slug ); ?>][source_taxonomy]">
					<option value="category" <?php selected( ( $content['source_taxonomy'] ?? $source_default['taxonomy'] ), 'category' ); ?>>Category</option>
					<option value="post_tag" <?php selected( ( $content['source_taxonomy'] ?? $source_default['taxonomy'] ), 'post_tag' ); ?>>Tag</option>
				</select>
				<input
					type="text"
					class="small-text"
					name="bday_homepage_section_content[<?php echo esc_attr( $slug ); ?>][source_term]"
					value="<?php echo esc_attr( $content['source_term'] ?? '' ); ?>"
					placeholder="<?php echo esc_attr( $source_default['term'] ); ?>"
				>
			<?php else : ?>
				<span class="description">—</span>
			<?php endif; ?>
		</td>
		<td>
			<?php if ( null !== $source_default ) : ?>
				<select name="bday_homepage_section_content[<?php echo esc_attr( $slug ); ?>][style]">
					<option value="" <?php selected( $content['style'] ?? '', '' ); ?>>Default (this section's usual look)</option>
					<option value="grid" <?php selected( $content['style'] ?? '', 'grid' ); ?>>Grid — lead + author grid</option>
					<option value="investigative" <?php selected( $content['style'] ?? '', 'investigative' ); ?>>Investigative — dark wide feature</option>
					<option value="premium" <?php selected( $content['style'] ?? '', 'premium' ); ?>>Premium rail — feature + medium + list</option>
				</select>
			<?php else : ?>
				<span class="description">—</span>
			<?php endif; ?>
		</td>
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
				'group'     => 'editorial',
			'option'    => 'bday_homepage_sections',
			'render'    => 'bday_render_homepage_sections_tab',
			'intro'     => 'Every section available to the "Redesign 2026" homepage layout, in the order it renders. Toggle a section off to skip it entirely (no query runs, nothing renders) — this does not affect the classic Default/Weekend homepage layouts, which are unrelated template files. Title and Source (Technical Team only by default) let a section be relabeled or repurposed onto a different tag/category without editing a template file. A new section shows up here automatically the first time it\'s deployed, appended to the end and on by default.',
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
		// Registered to the same settings *group* as the option above (not
		// a new group of its own) so both save from the one "Homepage
		// Sections" form in a single submit — settings_fields() only prints
		// one group's nonce, and the Settings API saves every option
		// registered to that group on submit, not just the one the nonce
		// was named after.
		register_setting(
			'bday_homepage_sections',
			'bday_homepage_section_content',
			array( 'sanitize_callback' => array( 'Bday_Section_Content', 'sanitize' ) )
		);
	}
);

/**
 * First-run default only — add_option() is a no-op once the option
 * exists, so this never overwrites an admin's later Access Control
 * changes. Retitling a section, repointing its tag/category, or changing
 * its layout Style changes what a section *is*, not the day-to-day
 * editorial call of what's published through it, so this tab (which now
 * also embeds the older "Sections" table — see addons/sections/addon.php)
 * defaults to Technical Team + Administrator only, out of the box — an
 * admin can still widen or narrow that later from Access Control, same
 * as every other tab.
 */
add_action(
	'after_setup_theme',
	static function (): void {
		add_option(
			Bday_Settings_Visibility::OPTION,
			array(
				'homepage_sections' => array( 'bday_technical_team' ),
			)
		);
	}
);
