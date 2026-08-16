<?php
/**
 * Section Name: Shorts & Promo Banners
 * Section Slug: bottom-widgets-hooks
 * Description: Mounts Homepage Modules' Shorts rail and landscape/portrait promo banners (bday_homepage_after_bottom_widgets) — both stay invisible until actually configured under Appearance > BusinessDay Theme > Homepage Modules.
 * Default Enabled: yes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
do_action( 'bday_homepage_after_bottom_widgets' );
