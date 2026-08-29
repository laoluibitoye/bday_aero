<?php
/**
 * Section Name: Opinion
 * Section Slug: opinion
 * Description: One lead editorial plus a grid of shorter opinion pieces, each with the author's avatar above the title.
 * Default Enabled: yes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data = $args['data'] ?? array();

bday_render_editorial_grid_section(
	array(
		'posts'           => $data['rd_opinion'] ?? array(),
		'heading'         => 'Opinion',
		'see_more_url'    => bday_section_url( 'opinion' ),
		'see_more_label'  => 'All opinion →',
		'lead_kicker'     => 'Editorial',
		'author_position' => 'above',
		'screen_label'    => 'Opinion',
	)
);
