<?php
/**
 * Addon Name: Live Match
 * Addon Slug: live-match
 * Cache Namespace: live_match
 * Settings Tab: Live Match
 * Default: off
 *
 * The previous theme's single worst performance finding: an uncached
 * WP_Query for this feature ran on every pageview site-wide regardless of
 * whether the ticker was even enabled. Rebuilt cache-first: the query is
 * gated behind this add-on's own enabled check AND its own settings
 * toggle, then routed through the mandatory cache wrapper with a short,
 * explicit TTL — never a live query in the request path.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/cpt.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/hooks.php';
