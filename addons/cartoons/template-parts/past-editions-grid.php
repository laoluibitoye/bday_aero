<?php
/**
 * Thin wrapper over the shared gallery grid (template-parts/components/
 * gallery-grid.php) — kept as its own file since both of this addon's
 * call sites (bottom-widgets.php's "More editions" strip, archive-
 * cartoons.php's full browser) already reference this path; only the
 * body was generalized.
 *
 * @var array $args { limit:int, paginate:bool, exclude_id:int, heading:string }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part(
	'template-parts/components/gallery-grid',
	null,
	array_merge(
		$args,
		array(
			'post_type'       => 'cartoons',
			'cache_namespace' => 'cartoons',
			'grid_class'      => 'bday-cartoon-grid',
		)
	)
);
