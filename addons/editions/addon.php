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
 * code changes). Each edition's PDF lives on an external storage
 * platform, referenced by an object-key meta field; publishing pushes
 * that mapping to subscription-service's /connector/edition-sync, which
 * is what actually gates and signs downloads (Phase 10's
 * ArchiveEntitlementService, unchanged by this addon). The object key can
 * either be uploaded straight from the PDF metabox (streamed through to
 * the storage platform, never written to WordPress) or pasted in directly
 * for a file already sitting in the bucket — the latter is also what the
 * bulk-import screen uses to create many past editions at once.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/cpt.php';
require_once __DIR__ . '/includes/metabox.php';
require_once __DIR__ . '/includes/publish-push.php';
require_once __DIR__ . '/includes/homepage.php';
require_once __DIR__ . '/includes/bulk-import.php';
