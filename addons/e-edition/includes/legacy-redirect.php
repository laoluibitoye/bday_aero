<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends traffic away from the legacy, unauthenticated PDF-embed path before
 * it ever reaches bday_render_pdf_viewer() — deployed ahead of retiring
 * that path entirely, so bookmarked/indexed legacy URLs keep working. Three
 * things get redirected: individual legacy posts that the
 * migrate-legacy-editions WP-CLI command has already moved to a real
 * bday_edition post, the legacy category archives (both slugs — see the
 * migration script's note on the e-paper/e-edition slug inconsistency), and
 * any page still assigned the superseded "Todays Epaper" page template.
 */
add_action(
	'template_redirect',
	static function (): void {
		if ( is_singular( 'post' ) ) {
			$new_id = (int) get_post_meta( get_the_ID(), '_bday_migrated_to_edition_id', true );
			if ( $new_id && get_post_status( $new_id ) ) {
				wp_safe_redirect( get_permalink( $new_id ), 301 );
				exit;
			}
			return;
		}

		if ( is_category( 'e-paper' ) || is_category( 'e-edition' ) ) {
			$term = get_term_by( 'slug', 'e-paper', 'edition_publication' );
			if ( $term && ! is_wp_error( $term ) ) {
				$link = get_term_link( $term );
				if ( ! is_wp_error( $link ) ) {
					wp_safe_redirect( $link, 301 );
					exit;
				}
			}
			return;
		}

		if ( is_page() && 'templates/todays-epaper.php' === get_page_template_slug() ) {
			$target = get_posts(
				array(
					'post_type'      => 'page',
					'posts_per_page' => 1,
					'meta_key'       => '_wp_page_template',
					'meta_value'     => 'templates/template-todays-paper.php',
				)
			);
			if ( ! empty( $target ) ) {
				wp_safe_redirect( get_permalink( $target[0] ), 301 );
				exit;
			}
		}
	},
	5
);
