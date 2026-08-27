<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'add_meta_boxes',
	static function (): void {
		add_meta_box( 'bday-video-embed', 'Video', 'bday_video_embed_metabox', 'bday_video', 'side' );
	}
);

function bday_video_embed_metabox( WP_Post $post ): void {
	wp_nonce_field( 'bday_video_embed', 'bday_video_embed_nonce' );
	printf(
		'<p><label for="bday-video-youtube-url">YouTube video URL:</label><br><input type="text" id="bday-video-youtube-url" name="youtube_url" value="%s" class="widefat" placeholder="https://www.youtube.com/watch?v=..." /></p>' .
		'<p class="description">Paste the full YouTube link (watch, youtu.be, or shorts). The optional description/body below becomes the article text under the embed.</p>',
		esc_attr( get_post_meta( $post->ID, '_video_youtube_url', true ) )
	);
}

add_action(
	'save_post_bday_video',
	static function ( int $post_id ): void {
		if ( ! isset( $_POST['bday_video_embed_nonce'] ) || ! wp_verify_nonce( $_POST['bday_video_embed_nonce'], 'bday_video_embed' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( isset( $_POST['youtube_url'] ) ) {
			update_post_meta( $post_id, '_video_youtube_url', esc_url_raw( wp_unslash( $_POST['youtube_url'] ) ) );
		}
	}
);

/**
 * Editors paste whatever YouTube link they have on hand (watch, youtu.be,
 * shorts, or an already-/embed/ URL) — this normalizes any of those shapes
 * into the /embed/{id} form the iframe on single-bday_video.php needs,
 * same role as bday_podcast_spotify_embed_url() but keyed off the video ID
 * living in the query string (?v=) rather than the URL path for the most
 * common (watch) link shape.
 */
function bday_video_youtube_embed_url( string $youtube_url ): string {
	if ( '' === $youtube_url ) {
		return '';
	}

	$parts = wp_parse_url( $youtube_url );
	if ( empty( $parts['host'] ) ) {
		return '';
	}

	$host = strtolower( $parts['host'] );
	$path = isset( $parts['path'] ) ? trim( $parts['path'], '/' ) : '';

	if ( false !== strpos( $host, 'youtube.com' ) && 0 === strpos( $path, 'embed/' ) ) {
		return $youtube_url;
	}

	if ( false !== strpos( $host, 'youtu.be' ) && '' !== $path ) {
		return 'https://www.youtube.com/embed/' . rawurlencode( $path );
	}

	if ( false !== strpos( $host, 'youtube.com' ) ) {
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query_args );
			if ( ! empty( $query_args['v'] ) ) {
				return 'https://www.youtube.com/embed/' . rawurlencode( $query_args['v'] );
			}
		}
		if ( preg_match( '#^shorts/([A-Za-z0-9_-]+)#', $path, $matches ) ) {
			return 'https://www.youtube.com/embed/' . $matches[1];
		}
	}

	return '';
}
