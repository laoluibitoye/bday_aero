<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'add_meta_boxes',
	static function (): void {
		add_meta_box( 'bday-co-authors', 'Co-Authors', 'bday_co_authors_metabox', 'post', 'side' );
	}
);

/**
 * Anyone who can actually be credited as a WordPress author (post_author
 * itself only ever offers the same three roles via wp_dropdown_users'
 * default capability check) — same eligible set, so a co-author is never
 * someone the native Author field couldn't have picked anyway.
 */
function bday_co_author_candidates(): array {
	return get_users(
		array(
			'role__in' => array( 'administrator', 'editor', 'author', 'contributor' ),
			'orderby'  => 'display_name',
			'order'    => 'ASC',
		)
	);
}

function bday_co_authors_metabox( WP_Post $post ): void {
	wp_nonce_field( 'bday_co_authors', 'bday_co_authors_nonce' );

	$selected     = get_post_meta( $post->ID, '_bday_co_authors', true );
	$selected     = is_array( $selected ) ? array_map( 'intval', $selected ) : array();
	$primary_id   = (int) $post->post_author;
	$candidates   = bday_co_author_candidates();

	echo '<p class="description">The Author field above sets the primary byline. Check any additional writers to credit them too.</p>';
	echo '<div style="max-height:180px;overflow-y:auto;">';
	foreach ( $candidates as $user ) {
		if ( (int) $user->ID === $primary_id ) {
			continue; // already the primary byline, offering it again as a co-author is redundant
		}
		printf(
			'<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="bday_co_authors[]" value="%1$d" %2$s /> %3$s</label>',
			(int) $user->ID,
			checked( in_array( (int) $user->ID, $selected, true ), true, false ),
			esc_html( $user->display_name )
		);
	}
	echo '</div>';
}

add_action(
	'save_post_post',
	static function ( int $post_id ): void {
		if ( ! isset( $_POST['bday_co_authors_nonce'] ) || ! wp_verify_nonce( $_POST['bday_co_authors_nonce'], 'bday_co_authors' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$submitted = isset( $_POST['bday_co_authors'] ) && is_array( $_POST['bday_co_authors'] )
			? array_map( 'intval', wp_unslash( $_POST['bday_co_authors'] ) )
			: array();
		update_post_meta( $post_id, '_bday_co_authors', $submitted );
	}
);
