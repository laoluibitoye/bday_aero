<?php
/**
 * Sections settings: labels/links for the handful of homepage headings
 * driven by a real WP category rather than a hardcoded link — "In Other
 * News" and "Columnists" (label + link both configurable here), plus
 * "Opinion" and "Big Read" (link only; their on-page heading text is
 * hardcoded in the template that uses them).
 *
 * Fixed to exactly these four rows on purpose. This used to be an
 * open-ended "Add Section" table where an admin could type any key —
 * but no template ever reads anything beyond these four
 * (bday_section_url()/bday_section_label() call sites), so a row added
 * under a new key silently did nothing. Locking it to the wired set is
 * what fixes that: every row on this screen now does something. Wiring
 * a fifth heading up is a one-line template change (call
 * bday_section_url()/bday_section_label() with a new key), then adding
 * that key to bday_sections_wired_keys() below — not something this
 * screen grows on its own anymore.
 *
 * Rendered *embedded inside* the Homepage Sections tab
 * (core/homepage/admin.php's bday_render_homepage_sections_tab(), guarded
 * by function_exists() in case this add-on is disabled) rather than as
 * its own settings-schema tab/page — see this addon's addon.php docblock.
 * No submit_button() call here for the same reason: the surrounding tab's
 * one shared form/button already covers this table's fields, since
 * bday_sections is registered under the 'bday_homepage_sections' settings
 * group.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The only keys any template actually reads. `label_used` is false for
 * the two whose on-page heading text is hardcoded in their template
 * (Opinion, Big Read) — grep the theme for `bday_section_label(` and
 * `bday_section_url(` to see every call site if this ever needs updating.
 *
 * @return array<string, array{default_label: string, used_for: string, label_used: bool}>
 */
function bday_sections_wired_keys(): array {
	return array(
		'news'      => array(
			'default_label' => 'In Other News',
			'used_for'      => 'Classic homepage + "In Other News" redesign section — heading text and link',
			'label_used'    => true,
		),
		'columnist' => array(
			'default_label' => 'Columnists',
			'used_for'      => 'Redesign homepage rail — heading text and link',
			'label_used'    => true,
		),
		'opinion'   => array(
			'default_label' => 'Opinion',
			'used_for'      => 'Hero "Opinion" link + redesign Opinion section\'s "see more" link',
			'label_used'    => false,
		),
		'editorial' => array(
			'default_label' => 'Big Read',
			'used_for'      => '"Big Read" feature link',
			'label_used'    => false,
		),
	);
}

function bday_render_sections_tab( array $values ): void {
	$categories = get_categories( array( 'hide_empty' => false, 'number' => 300 ) );
	?>
	<h2>Sections</h2>
	<p class="description" style="margin-bottom:16px;">
		Labels/links for the homepage headings backed by a real WordPress category instead of a
		hardcoded link — fixed to these four rows, since no other heading reads anything saved here.
		"Label" only appears where the heading's on-page text is actually driven by this table too;
		the other two rows only control where their heading links to.
	</p>
	<table class="widefat bday-sections-table" id="bday-sections-table">
		<thead>
			<tr>
				<th style="width:14%">Key</th>
				<th style="width:22%">Label</th>
				<th style="width:22%">Category</th>
				<th>Used for</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( bday_sections_wired_keys() as $key => $meta ) : ?>
				<?php bday_render_section_row( $key, bday_section( $key ) ?? array(), $categories, $meta ); ?>
			<?php endforeach; ?>
		</tbody>
	</table>
	<style>
		.bday-sections-table td { vertical-align: middle; }
	</style>
	<?php
}

/**
 * @param string $key
 * @param array<string, string> $section
 * @param WP_Category[] $categories
 * @param array{default_label: string, used_for: string, label_used: bool} $meta
 */
function bday_render_section_row( string $key, array $section, array $categories, array $meta ): void {
	$label     = $section['label'] ?? $meta['default_label'];
	$term_slug = $section['term_slug'] ?? '';
	?>
	<tr>
		<td>
			<code><?php echo esc_html( $key ); ?></code>
		</td>
		<td>
			<?php if ( $meta['label_used'] ) : ?>
				<input type="text" class="regular-text" name="bday_sections[<?php echo esc_attr( $key ); ?>][label]" value="<?php echo esc_attr( $label ); ?>">
			<?php else : ?>
				<span class="description">— fixed in the template</span>
			<?php endif; ?>
		</td>
		<td>
			<select name="bday_sections[<?php echo esc_attr( $key ); ?>][term_slug]">
				<option value="">— Select a category —</option>
				<?php foreach ( $categories as $category ) : ?>
					<option value="<?php echo esc_attr( $category->slug ); ?>" <?php selected( $term_slug, $category->slug ); ?>><?php echo esc_html( $category->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
		<td><span class="description"><?php echo esc_html( $meta['used_for'] ); ?></span></td>
	</tr>
	<?php
}

/** @return array<int, array{key: string, label: string, taxonomy: string, term_slug: string}> */
function bday_sanitize_sections( $input ): array {
	$rows  = is_array( $input ) ? $input : array();
	$out   = array();
	$wired = bday_sections_wired_keys();

	// Only the four wired keys are ever saved, in this fixed order — the
	// form no longer offers a way to submit any other key, and this also
	// self-heals a save made before "editorial" existed here (an install
	// whose bday_sections option predates that row gets it filled in with
	// its default the next time this screen is saved).
	foreach ( $wired as $key => $meta ) {
		$row = is_array( $rows[ $key ] ?? null ) ? $rows[ $key ] : array();

		$label = $meta['label_used'] && isset( $row['label'] ) ? sanitize_text_field( wp_unslash( $row['label'] ) ) : $meta['default_label'];
		$term  = isset( $row['term_slug'] ) ? sanitize_title( wp_unslash( $row['term_slug'] ) ) : '';

		$out[] = array(
			'key'       => $key,
			'label'     => $label,
			'taxonomy'  => 'category',
			'term_slug' => $term,
		);
	}

	return $out;
}
