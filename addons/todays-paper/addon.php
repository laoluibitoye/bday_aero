<?php
/**
 * Addon Name: Todays Paper
 * Addon Slug: todays-paper
 * Description: The Today's Paper page: editor-marked stories grouped by section, plus the day's e-edition.
 * Cache Namespace: todays_paper
 * Settings Tab: Todays Paper
 * Default: on
 *
 * Editorial "feature this in Today's Paper" flag (reader-requested —
 * "news editors should be able to mark a post as today's paper") plus the
 * page that displays everything currently marked, alongside the day's
 * e-paper cover/download. The mark-a-post mechanism lives in
 * includes/metabox.php, same shape as the podcast/cartoon metaboxes
 * already in this theme; the page itself is templates/template-todays-
 * paper.php (a real page template, assigned to a WP Page the same way
 * every other dedicated page this session was set up).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/metabox.php';
require_once __DIR__ . '/includes/bulk-actions.php';
require_once __DIR__ . '/includes/query.php';
