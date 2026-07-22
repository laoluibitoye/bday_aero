<?php
/**
 * Template Name: masterpage
 *
 * The theme's one fixed homepage template — WordPress's Settings > Reading
 * front-page assignment always points here and never needs to change.
 * What changes is which layout this dispatches to internally: see
 * inc/homepage/homepage-variants.php for the registry/resolver (admin
 * override -> day-of-week -> default) and Appearance > BusinessDay Theme
 * for the override control.
 */
get_header();

bd_render_active_homepage_variant();

get_footer();
