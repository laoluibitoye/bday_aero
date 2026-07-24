<?php
/**
 * Addon Name: Newsletter (FluentCRM)
 * Addon Slug: newsletter-fluentcrm
 * Cache Namespace: newsletter
 * Settings Tab: Newsletter
 * Default: on
 *
 * The real, working newsletter system (a REST bridge to a remote FluentCRM
 * install). The previous theme also had a second, dead "Newsletter
 * Template" page whose submit handler just did alert('not connected to
 * API') — unrelated to this system and not carried forward.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/client.php';
require_once __DIR__ . '/includes/shortcode.php';
require_once __DIR__ . '/includes/settings.php';
