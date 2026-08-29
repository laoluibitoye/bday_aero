<?php
/**
 * Section Name: Partner Content
 * Section Slug: partner-content
 * Description: Sponsored-content tiles, sourced from the "sponsored" tag, styled like Opinion (lead + avatar grid) but with the author credit under the excerpt rather than above the title. No existing content uses the "sponsored" tag yet — the section stays hidden until an editor tags something this way.
 * Default Enabled: yes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data = $args['data'] ?? array();
$tag  = get_term_by( 'slug', 'sponsored', 'post_tag' );

bday_render_editorial_grid_section(
	array(
		'posts'           => $data['rd_partner'] ?? array(),
		'heading'         => 'Partnered & Sponsored Content',
		'see_more_url'    => $tag ? (string) get_tag_link( $tag ) : '',
		'see_more_label'  => 'See more →',
		'lead_kicker'     => 'Sponsored',
		'author_position' => 'below',
		'screen_label'    => 'Partner content',
	)
);
