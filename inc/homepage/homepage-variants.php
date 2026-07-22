<?php
/**
 * Homepage variant-switching system.
 *
 * Design constraint (user-specified): WordPress's own front-page assignment
 * never changes — the homepage stays one fixed WP Page, always rendered
 * through templates/masterpage.php. "Switching homepages" means changing
 * which layout masterpage.php dispatches to internally, not which template/
 * Page WordPress thinks is the front page. Nobody needs to touch
 * Settings > Reading to change the site's homepage style.
 */

/**
 * Variant registry — key => [label, template]. Adding a future variant is
 * "drop in one template-part file + one entry here," not copy-pasting an
 * entire homepage file (the way templates/stage.php used to duplicate
 * templates/homepage.php — both are archived now, see _archive/templates/).
 */
function bd_get_homepage_variants(): array {
	return [
		'default'       => [
			'label'    => 'Default (Weekday)',
			'template' => 'template-parts/homepage/variant-default.php',
		],
		'weekend'       => [
			'label'    => 'Weekend',
			'template' => 'template-parts/homepage/variant-weekend.php',
		],
		'breaking-news' => [
			'label'    => 'Breaking News',
			'template' => 'template-parts/homepage/variant-breaking-news.php',
		],
	];
}

/**
 * Resolves which homepage variant should render right now:
 *   1. An admin-set forced override (for manually flipping into "Breaking
 *      News" during a major story) — set on the theme settings screen.
 *   2. Day-of-week: Sat/Sun -> weekend, else default.
 *   3. 'default' if nothing else resolves.
 */
function bd_get_active_homepage_variant(): string {
	$variants = bd_get_homepage_variants();

	$override = get_option( 'bd_homepage_variant_override', '' );
	if ( $override && $override !== 'auto' && isset( $variants[ $override ] ) ) {
		return $override;
	}

	$day_of_week = (int) wp_date( 'N' ); // 1 (Mon) .. 7 (Sun)
	if ( $day_of_week >= 6 && isset( $variants['weekend'] ) ) {
		return 'weekend';
	}

	return 'default';
}

/**
 * Renders the currently-active homepage variant. Called by templates/
 * masterpage.php, which stays a thin dispatcher — WordPress still sees one
 * fixed template assigned to one fixed front-page Page; only what that
 * template includes internally changes.
 */
function bd_render_active_homepage_variant(): void {
	$variants = bd_get_homepage_variants();
	$active   = bd_get_active_homepage_variant();

	$template = $variants[ $active ]['template'] ?? $variants['default']['template'];

	get_template_part( str_replace( '.php', '', $template ) );
}
