<?php
/**
 * Addon Name: Podcasts
 * Addon Slug: podcasts
 * Description: Podcast episode post type, shown on the homepage and its own archive.
 * Cache Namespace: podcasts
 * Settings Tab: Podcasts
 * Default: on
 *
 * Phase 9 / Deep Dive §13 (universal content gating): BusinessDay's fifth
 * content type — text, video (both already the 'post' CPT), cartoons
 * (addons/cartoons/), e-paper (Phase 10), and now audio podcasts. Episode
 * audio is a hosted URL (SoundCloud/Podcast-host embed or direct file),
 * not a WordPress media upload — same "don't self-host heavy media"
 * posture as bday-live's YouTube embed and the video facade. Registered
 * with category/tag support specifically so episodes can participate in
 * Phase 7's follow-a-taxonomy system exactly like standard posts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/cpt.php';
require_once __DIR__ . '/includes/metabox.php';
