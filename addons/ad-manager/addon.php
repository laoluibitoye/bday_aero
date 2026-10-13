<?php
/**
 * Addon Name: Ad Manager
 * Addon Slug: ad-manager
 * Description: One place to manage every ad on the site — GAM, AdSense, pasted third-party ad tags and local banners — with per-location placements, creative-size guidance and subscriber-aware ad gating.
 * Cache Namespace: ad_manager
 * Settings Tab: Ad Manager
 * Default: on
 *
 * Replaces four earlier pieces that each owned a fragment of ad delivery
 * and never agreed with each other: the GAM vendor driver (hardcoded slot
 * paths in PHP), Ads & Sharing Matrix (on/off switches only),
 * Premium Leaderboard, Sidebar Promo and Homepage Rail Banner (three
 * separate local-banner tabs with no size guidance). Templates still call
 * bday_ad_zone( 'placement' ) — only the name of a location, never ad code.
 *
 *   includes/catalog.php     the fixed list of locations (and their recommended sizes)
 *   includes/class-store.php the three options + first-run seeds
 *   includes/render.php      bday_ad_zone(), in-article injection, loader config
 *   includes/admin.php       the three admin tabs
 *   assets/ad-manager.js     the single front-end loader + reader-policy gating
 *
 * Gating is client-side by design: the site is page-cached (Redis,
 * Cloudflare), so HTML can't vary per reader. See ad-manager.js.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/catalog.php';
require_once __DIR__ . '/includes/class-store.php';
require_once __DIR__ . '/includes/render.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/early-gate.php';
