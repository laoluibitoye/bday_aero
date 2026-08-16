<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'add_meta_boxes',
	static function (): void {
		add_meta_box( 'bday-podcast-episode', 'Episode', 'bday_podcast_episode_metabox', 'podcast' );
	}
);

/**
 * Spotify is now the primary player (reader request — the old
 * SoundCloud-only version was hardcoded to one legacy creator account,
 * not tied to this CPT at all). The direct-audio-URL field stays as an
 * optional fallback for an episode with no Spotify listing yet, used by
 * single-podcast.php only when no Spotify URL is set.
 */
function bday_podcast_episode_metabox( WP_Post $post ): void {
	wp_nonce_field( 'bday_podcast_episode', 'bday_podcast_episode_nonce' );
	printf(
		'<p><label for="bday-podcast-spotify-url">Spotify episode URL:</label><br><input type="text" id="bday-podcast-spotify-url" name="spotify_url" value="%s" class="widefat" placeholder="https://open.spotify.com/episode/..." /></p>',
		esc_attr( get_post_meta( $post->ID, '_podcast_spotify_url', true ) )
	);
	printf(
		'<p><label for="bday-podcast-audio-url">Fallback audio URL (used only if no Spotify URL is set):</label><br><input type="text" id="bday-podcast-audio-url" name="audio_url" value="%s" class="widefat" /></p>',
		esc_attr( get_post_meta( $post->ID, '_podcast_audio_url', true ) )
	);
	printf(
		'<p><label for="bday-podcast-show-name">Show name:</label><br><input type="text" id="bday-podcast-show-name" name="show_name" value="%s" class="widefat" /></p>',
		esc_attr( get_post_meta( $post->ID, '_podcast_show_name', true ) )
	);
	printf(
		'<p><label for="bday-podcast-duration">Duration (e.g. 32:10):</label><br><input type="text" id="bday-podcast-duration" name="duration" value="%s" class="widefat" /></p>',
		esc_attr( get_post_meta( $post->ID, '_podcast_duration', true ) )
	);
}

add_action(
	'save_post_podcast',
	static function ( int $post_id ): void {
		if ( ! isset( $_POST['bday_podcast_episode_nonce'] ) || ! wp_verify_nonce( $_POST['bday_podcast_episode_nonce'], 'bday_podcast_episode' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$map = array(
			'spotify_url' => '_podcast_spotify_url',
			'audio_url'   => '_podcast_audio_url',
			'show_name'   => '_podcast_show_name',
			'duration'    => '_podcast_duration',
		);
		foreach ( $map as $field => $meta_key ) {
			if ( isset( $_POST[ $field ] ) ) {
				$is_url = in_array( $meta_key, array( '_podcast_spotify_url', '_podcast_audio_url' ), true );
				$value  = $is_url
					? esc_url_raw( wp_unslash( $_POST[ $field ] ) )
					: sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
				update_post_meta( $post_id, $meta_key, $value );
			}
		}
	}
);

/**
 * Spotify's embed player expects /embed/episode/{id} (or /embed/show/{id}
 * for a show link), not the plain open.spotify.com share URL editors will
 * actually paste — this rewrites either share-link shape into the embed
 * form so the metabox can stay a plain "paste the URL" field.
 */
function bday_podcast_spotify_embed_url( string $spotify_url ): string {
	if ( '' === $spotify_url ) {
		return '';
	}
	if ( false !== strpos( $spotify_url, '/embed/' ) ) {
		return $spotify_url;
	}
	$path = (string) wp_parse_url( $spotify_url, PHP_URL_PATH );
	if ( preg_match( '#^/(episode|show|track)/([A-Za-z0-9]+)#', $path, $matches ) ) {
		return 'https://open.spotify.com/embed/' . $matches[1] . '/' . $matches[2];
	}
	return '';
}
