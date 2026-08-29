<?php
/**
 * Section Name: YSoT
 * Section Slug: ysot
 * Description: Yaba School of Thought column pieces, styled like Opinion (lead + avatar grid) with the author credit under the excerpt, same treatment as Partner & Sponsored Content. Sourced from the "yaba-school-of-thought" category.
 * Default Enabled: yes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data = $args['data'] ?? array();

bday_render_editorial_grid_section(
	array(
		'posts'           => $data['rd_ysot'] ?? array(),
		'heading'         => 'YSoT',
		'see_more_url'    => bday_category_url( 'yaba-school-of-thought' ),
		'see_more_label'  => 'See more →',
		'lead_kicker'     => 'YSoT',
		'author_position' => 'below',
		'screen_label'    => 'YSoT',
	)
);
