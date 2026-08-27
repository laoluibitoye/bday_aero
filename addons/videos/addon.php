<?php
/**
 * Addon Name: Videos
 * Addon Slug: videos
 * Description: Standalone video post type (YouTube embed + optional description), shown on its own archive.
 * Cache Namespace: videos
 * Settings Tab: Videos
 * Default: on
 *
 * A video previously had no content type of its own — it was either a
 * standard post in the 'video' post-format (YouTube ID in `_youtube_id`,
 * see core/editorial-meta.php) or a card-display facade
 * (addons/featured-video-cards/). This addon is a dedicated CPT, modeled
 * directly on addons/podcasts/ (same "external, hosted media, don't
 * self-host it" posture). Grouped by its own `video_playlist` taxonomy
 * (not the article category/tag system — a playlist isn't an article
 * section) so videos sort and archive by playlist, mirroring
 * addons/editions' edition_publication and addons/podcasts'
 * podcast_series. Gating is opt-in and requires zero code here: adding
 * `bday_video` to the Aero Paywall "Restricted Post Types" setting is
 * enough, since class-premium-map.php / class-content-gate.php already
 * work generically over whatever post types that setting lists.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/cpt.php';
require_once __DIR__ . '/includes/metabox.php';
