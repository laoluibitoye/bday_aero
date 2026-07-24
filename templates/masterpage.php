<?php
/**
 * Template Name: masterpage
 *
 * Kept as the exact same template name/slug as before so the live front
 * page's existing Page-template assignment in the database keeps working
 * unchanged — WordPress's Settings > Reading front-page assignment always
 * points here. What changes is which variant this dispatches to
 * internally (see core/homepage/class-variant-registry.php and
 * Appearance > BusinessDay Theme > Homepage Variants for the override).
 */
get_header();
Bday_Variant_Registry::render_active();
get_footer();
