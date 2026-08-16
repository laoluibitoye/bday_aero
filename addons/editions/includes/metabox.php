<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'add_meta_boxes',
	static function (): void {
		add_meta_box( 'bday-edition-pdf', 'Edition PDF', 'bday_edition_pdf_metabox', 'bday_edition', 'side' );
	}
);

function bday_edition_pdf_metabox( WP_Post $post ): void {
	wp_nonce_field( 'bday_edition_pdf', 'bday_edition_pdf_nonce' );
	printf(
		'<p><label for="bday-edition-object-key">Storage link / object key:</label><br><input type="text" id="bday-edition-object-key" name="object_key" value="%s" class="widefat" placeholder="editions/e-paper/2026-08-14.pdf" /></p>' .
		'<p class="description">The PDF is never uploaded to WordPress — paste the path/URL from the storage platform (Phase 10). This is only ever read server-side, signed into a short-lived download link when an entitled reader requests it.</p>',
		esc_attr( get_post_meta( $post->ID, '_bday_edition_object_key', true ) )
	);
}

add_action(
	'save_post_bday_edition',
	static function ( int $post_id ): void {
		if ( ! isset( $_POST['bday_edition_pdf_nonce'] ) || ! wp_verify_nonce( $_POST['bday_edition_pdf_nonce'], 'bday_edition_pdf' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( isset( $_POST['object_key'] ) ) {
			update_post_meta( $post_id, '_bday_edition_object_key', sanitize_text_field( wp_unslash( $_POST['object_key'] ) ) );
		}
	}
);
