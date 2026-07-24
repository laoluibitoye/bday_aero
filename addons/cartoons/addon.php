<?php
/**
 * Addon Name: Cartoons
 * Addon Slug: cartoons
 * Cache Namespace: cartoons
 * Settings Tab: Cartoons
 * Default: on
 *
 * The reference add-on other add-ons follow the same shape as — CPT
 * registration, a cached grid partial, and root single/archive templates
 * that stay thin. Ported unchanged from the previous theme (already its
 * best-engineered feature): bounded queries, fields=>ids, no_found_rows,
 * and _prime_post_caches() batching to avoid N+1 lookups.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/cpt.php';
