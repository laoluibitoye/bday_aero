<?php
/**
 * Addon Name: Follow & Notify
 * Addon Slug: follow-notify
 * Description: Lets readers follow a topic or author and get notified when new posts publish.
 * Cache Namespace: follow_notify
 * Settings Tab: Follow & Notify
 * Default: on
 *
 * Deep Dive §11 / Phase 7: pushes "a post was published" to
 * subscription-service's /connector/post-published, which fans out
 * Notification rows to followers of the post's categories/tags (or, for
 * a large-follower term, relies on its own read-time merge instead — see
 * that endpoint's own docs). Reuses the exact wp_remote_post/X-Api-Key
 * pattern and connector base-url/api-key options already used by
 * addons/aero-paywall/includes/class-premium-map.php's premium-map sync
 * (read directly via get_option(), not through that add-on's
 * Bday_Aero_Settings class — this add-on has no real reason to depend on
 * whether the separate, Default:-off paywall add-on is enabled) — same
 * connector/System-B relationship, just a different endpoint, no new
 * settings screen needed.
 *
 * Hooks transition_post_status rather than save_post (which
 * class-premium-map.php uses) specifically because that fires on every
 * save including drafts/autosaves — this needs the bounded, low-rate
 * "an editor actually published something" trigger the roadmap requires,
 * not a per-save trigger.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/publish-push.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/includes/backfill-published-posts.php';
}
