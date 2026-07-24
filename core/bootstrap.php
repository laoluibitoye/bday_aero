<?php
/**
 * Core load order. Small and stable on purpose — nothing feature-specific
 * lives here; every feature is an add-on under addons/, loaded only if
 * enabled (see Bday_Addon_Loader::boot()).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BDAY_THEME_DIR', get_template_directory() . '/' );
define( 'BDAY_THEME_URI', get_template_directory_uri() . '/' );

require_once BDAY_THEME_DIR . 'core/data/class-query-cache.php';
require_once BDAY_THEME_DIR . 'core/data/helpers.php';
require_once BDAY_THEME_DIR . 'core/helpers.php';
require_once BDAY_THEME_DIR . 'core/theme-setup.php';
require_once BDAY_THEME_DIR . 'core/assets.php';
require_once BDAY_THEME_DIR . 'core/addons/class-addon-loader.php';
require_once BDAY_THEME_DIR . 'core/homepage/class-variant-registry.php';
require_once BDAY_THEME_DIR . 'core/homepage/data.php';
require_once BDAY_THEME_DIR . 'core/boundary/paywall-contract.php';
require_once BDAY_THEME_DIR . 'core/options/field-types/render.php';
require_once BDAY_THEME_DIR . 'core/options/class-options-framework.php';
require_once BDAY_THEME_DIR . 'core/options/core-tabs.php';
require_once BDAY_THEME_DIR . 'core/custom-code.php';
require_once BDAY_THEME_DIR . 'core/nav-menu.php';
require_once BDAY_THEME_DIR . 'core/staff.php';
require_once BDAY_THEME_DIR . 'core/editorial-meta.php';
require_once BDAY_THEME_DIR . 'core/migrate.php';

add_action( 'bday_core_loaded', array( 'Bday_Addon_Loader', 'boot' ) );
do_action( 'bday_core_loaded' );
