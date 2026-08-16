<?php
/**
 * Addon Name: Sections
 * Addon Slug: sections
 * Description: Custom section labels and links for homepage or nav headings that aren't a real WordPress category.
 * Cache Namespace: sections
 * Settings Tab: Sections
 * Default: on
 *
 * Dashboard-managed "sections" — an ordered, admin-editable list mapping a
 * short key (e.g. "news") to a real WP category term, so nav/homepage
 * template code reads a label + URL from here instead of a slug literal.
 * Deliberately scoped to *link/heading* concepts, not a rewrite of the
 * homepage's tag-driven data fetch (core/homepage/data.php) — several of
 * that file's zones are curated by tag (bdlead/bdothernews/premium/etc),
 * not by a single category, and folding those into "sections" would change
 * what actually populates the homepage, not just how it's labeled/linked.
 * Same reasoning excludes single.php's e-edition post-type dispatch and
 * the e-edition addon's 'e-paper' category lookups — those are structural
 * routing, not a section a reader navigates to.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/admin.php';

// First-run default only — add_option() is a no-op if the option already
// exists, so this never overwrites an admin's edits on subsequent loads.
add_option(
	'bday_sections',
	array(
		array(
			'key'       => 'news',
			'label'     => 'In Other News',
			'taxonomy'  => 'category',
			'term_slug' => 'news',
		),
		array(
			'key'       => 'columnist',
			'label'     => 'Columnists',
			'taxonomy'  => 'category',
			'term_slug' => 'columnist',
		),
		array(
			'key'       => 'opinion',
			'label'     => 'Opinion',
			'taxonomy'  => 'category',
			'term_slug' => 'opinion',
		),
	)
);

add_filter(
	'bday_settings_schema',
	static function ( array $schema ): array {
		$schema['sections'] = array(
			'tab_label' => 'Sections',
			'option'    => 'bday_sections',
			'render'    => 'bday_render_sections_tab',
			'intro'     => 'Sections are the labeled links/headings used across the site — e.g. the homepage rail\'s "In Other News" or "Columnists" headings — each one a short internal key mapped to a real WordPress category, so the wording an editor sees and the URL a reader lands on can both be changed here without editing a template file.',
			'about'     => '<p>Reorder by dragging a row — order here is the order these headings appear wherever the theme reads them by key. A section with no category assigned still saves, but its heading link goes nowhere until one is chosen.</p>',
		);
		return $schema;
	}
);

add_action(
	'admin_init',
	static function (): void {
		register_setting(
			'bday_sections',
			'bday_sections',
			array( 'sanitize_callback' => 'bday_sanitize_sections' )
		);
	}
);
