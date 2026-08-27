<?php
/**
 * Addon Name: E-Edition
 * Addon Slug: e-edition
 * Description: Legacy single e-edition PDF viewer (superseded by E-Editions for new content).
 * Cache Namespace: e_edition
 * Settings Tab: E-Edition
 * Default: on
 *
 * The e-paper PDF viewer + its category archive. Consolidates what used to
 * be two copy-pasted PDF-embed implementations (template-parts/single-
 * edition.php and templates/todays-epaper.php) into one shared render
 * function, and fixes the e-edition category archive's undefined $paged
 * bug (page 2 silently re-rendered page 1) and its lack of caching.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/pdf-viewer.php';
require_once __DIR__ . '/includes/legacy-redirect.php';
