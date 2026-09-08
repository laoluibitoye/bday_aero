<?php
/**
 * Addon Name: Sections
 * Addon Slug: sections
 * Description: Custom section labels and links for homepage or nav headings that aren't a real WordPress category. Managed from the Homepage Sections tab, not its own settings tab.
 * Cache Namespace: sections
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
 *
 * No 'Settings Tab' header / bday_settings_schema entry of its own — its
 * admin UI (includes/admin.php's bday_render_sections_tab()) is rendered
 * *inside* the Homepage Sections tab (core/homepage/admin.php) instead,
 * since both are the same kind of thing (an ordered, drag-reorderable
 * list of section rows) one click apart from each other used to read as
 * two separate features. bday_sections is registered under the
 * 'bday_homepage_sections' settings group precisely so that shared page's
 * one form/submit button saves both tables together.
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

add_action(
	'admin_init',
	static function (): void {
		register_setting(
			'bday_homepage_sections',
			'bday_sections',
			array( 'sanitize_callback' => 'bday_sanitize_sections' )
		);
	}
);
