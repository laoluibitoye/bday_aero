<?php
/**
 * Addon Name: Popups
 * Addon Slug: popups
 * Description: Create and manage on-site popups — position, trigger, device target, and display rules, with the full block editor as the popup's content builder.
 * Cache Namespace: popups
 * Default: on
 *
 * Reader-requested (2026-10-06), modeled on wppopups.com's feature set:
 * display rules (which page it shows on), triggers (immediate/delay/
 * scroll/exit-intent), multiple positions (center/top bar/bottom bar/
 * bottom-right/fullscreen), a close button, device targeting (desktop/
 * mobile/both), and the ability to create several independent popups and
 * turn each on or off. "Create different popups" + "activate or
 * deactivate" map onto a real post type (bday_popup) rather than a
 * single repeatable-option list — see includes/cpt.php's own docblock
 * for why: it's what makes "content builder" the native block editor
 * instead of a bespoke one, and "activate/deactivate" WordPress's own
 * Publish/Draft status instead of a parallel toggle.
 *
 * includes/cpt.php — the post type.
 * includes/metabox.php — position/trigger/device/display-rule fields.
 * includes/render.php — server-side display-rule matching + the
 *   bday_popup_zone hook (called once, from footer.php) that emits each
 *   matching popup's markup and a JSON trigger/device config.
 * Client-side trigger/device logic + open/close is
 * bdayInitPopups() in assets/src/js/script.js; styling is
 * assets/src/scss/components/_popups.scss.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/cpt.php';
require_once __DIR__ . '/includes/metabox.php';
require_once __DIR__ . '/includes/render.php';
