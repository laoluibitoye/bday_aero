<?php
/**
 * Addon Name: E-Editions
 * Addon Slug: editions
 * Description: Print-edition PDF library (e-paper, Weekender, etc.) with signed downloads for entitled readers.
 * Cache Namespace: editions
 * Settings Tab: E-Editions
 * Default: on
 *
 * Phase 14 (reader request, building out Deep Dive §14's e-paper rebuild
 * beyond just the entitlement backend): a `bday_edition` CPT — one post
 * per print edition, tagged with an `edition_publication` taxonomy term
 * (e-paper, she-means-business, real-estate-digest, weekender, and
 * whatever an editor adds later — a real taxonomy, not a fixed list in
 * code, so "there will likely be more editions in the future" needs zero
 * code changes). Each edition's PDF lives in this site's own local
 * secure-epapers/ folder (secure-storage.php, blocked from direct access
 * at the web-server level — see that file's docblock), referenced by an
 * object-key meta field shaped "local:{postId}:{filename}"; publishing
 * pushes that mapping to subscription-service's /connector/edition-sync,
 * which is what actually gates and signs downloads (Phase 10's
 * ArchiveEntitlementService, unchanged by this addon — only *where* the
 * bytes live changed, not who decides if a reader can have them).
 * download-endpoint.php serves the file once subscription-service hands
 * the reader a signed link. The object key can either be uploaded straight
 * from the PDF metabox (moved into the secure folder) or referenced by
 * filename for a file already dropped in there directly (FileZilla/SFTP) —
 * an older S3-backed storage path still exists for already-migrated
 * content and is documented where it's dormant (metabox.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/cpt.php';
require_once __DIR__ . '/includes/secure-storage.php';
require_once __DIR__ . '/includes/download-endpoint.php';
require_once __DIR__ . '/includes/metabox.php';
require_once __DIR__ . '/includes/publish-push.php';
require_once __DIR__ . '/includes/homepage.php';
require_once __DIR__ . '/includes/flipbook-reader.php';
require_once __DIR__ . '/includes/bulk-import.php';
require_once __DIR__ . '/includes/legacy-migration-core.php';
require_once __DIR__ . '/includes/legacy-migration-wizard.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/includes/legacy-migration.php';
}
