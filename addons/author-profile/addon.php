<?php
/**
 * Addon Name: Author Profile
 * Addon Slug: author-profile
 * Description: Lets writers credit co-authors on a post and upload their own byline photo.
 * Cache Namespace: author_profile
 * Settings Tab: Author Profile
 * Default: on
 *
 * Two reader-requested author features: co-authors (a post can credit more
 * than one byline) and author-uploaded photos (a real image on the profile
 * screen rather than only Gravatar). Neither needs a settings-schema tab of
 * its own — co-authors is a per-post editorial choice (the post-edit
 * metabox below) and photos are a per-user choice (the profile screen
 * below); there's no site-wide toggle either would meaningfully have.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/metabox.php';
require_once __DIR__ . '/includes/avatar.php';
