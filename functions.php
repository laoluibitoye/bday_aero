<?php
/**
 * BusinessDay Premium — theme entry point.
 *
 * Intentionally tiny: everything else lives in core/ (stable framework —
 * routing glue, cache wrapper, options framework, add-on loader) and
 * addons/ (every feature, each independently toggleable). Nothing
 * feature-specific belongs in this file; add new functionality as a new
 * add-on under addons/, not as an edit here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_template_directory() . '/core/bootstrap.php';
