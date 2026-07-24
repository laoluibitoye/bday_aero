<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	static function (): void {
		register_post_type(
			'live_match',
			array(
				'labels'      => array(
					'name'          => 'Live Matches',
					'singular_name' => 'Live Match',
					'add_new_item'  => 'Add New Live Match',
					'all_items'     => 'All Live Matches',
				),
				'public'      => true,
				'has_archive' => true,
				'show_in_nav_menus' => true,
				'supports'    => array( 'title', 'thumbnail', 'excerpt' ),
			)
		);
	}
);

add_action(
	'add_meta_boxes',
	static function (): void {
		add_meta_box( 'bday_live_match_details', 'Match Details', 'bday_live_match_render_metabox', 'live_match', 'normal', 'high' );
	}
);

function bday_live_match_render_metabox( WP_Post $post ): void {
	wp_nonce_field( 'bday_live_match_save', 'bday_live_match_nonce' );
	$home       = get_post_meta( $post->ID, 'home_team', true );
	$home_score = get_post_meta( $post->ID, 'home_team_score', true );
	$away       = get_post_meta( $post->ID, 'away_team', true );
	$away_score = get_post_meta( $post->ID, 'away_team_score', true );
	?>
	<table class="form-table">
		<tr><th><label for="home_team">Home team</label></th><td><input type="text" id="home_team" name="home_team" value="<?php echo esc_attr( $home ); ?>" class="regular-text"></td></tr>
		<tr><th><label for="home_team_score">Home score</label></th><td><input type="number" id="home_team_score" name="home_team_score" value="<?php echo esc_attr( $home_score ); ?>" class="small-text"></td></tr>
		<tr><th><label for="away_team">Away team</label></th><td><input type="text" id="away_team" name="away_team" value="<?php echo esc_attr( $away ); ?>" class="regular-text"></td></tr>
		<tr><th><label for="away_team_score">Away score</label></th><td><input type="number" id="away_team_score" name="away_team_score" value="<?php echo esc_attr( $away_score ); ?>" class="small-text"></td></tr>
	</table>
	<?php
}

add_action(
	'save_post_live_match',
	static function ( int $post_id ): void {
		if ( ! isset( $_POST['bday_live_match_nonce'] ) || ! wp_verify_nonce( $_POST['bday_live_match_nonce'], 'bday_live_match_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, 'home_team', sanitize_text_field( wp_unslash( $_POST['home_team'] ?? '' ) ) );
		update_post_meta( $post_id, 'home_team_score', intval( $_POST['home_team_score'] ?? 0 ) );
		update_post_meta( $post_id, 'away_team', sanitize_text_field( wp_unslash( $_POST['away_team'] ?? '' ) ) );
		update_post_meta( $post_id, 'away_team_score', intval( $_POST['away_team_score'] ?? 0 ) );

		Bday_Query_Cache::forget( 'live_match', 'current' );
	}
);
