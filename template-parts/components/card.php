<?php
/**
 * Thin get_template_part() wrapper around bday_card_html() (core/helpers.php)
 * for call sites that already use the template-part convention rather than
 * calling the function directly (weekend.php calls bday_card_html() itself,
 * since it builds its markup via printf and has no template-part loop).
 *
 * Usage: get_template_part( 'template-parts/components/card', null, array(
 *     'post' => $post, 'size' => 'medium_rectangle', 'show_byline' => true,
 * ) );
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $args['post'] ) || ! ( $args['post'] instanceof WP_Post ) ) {
	return;
}

echo bday_card_html( $args['post'], $args );
