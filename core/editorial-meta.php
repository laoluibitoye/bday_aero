<?php
/**
 * Editorial post meta: the Video (YouTube ID), PDF, and PRO-URL meta boxes
 * on standard posts, plus the author display-picture profile field and the
 * Telegram publish notifier. These follow posts everywhere posts exist, so
 * they're core editorial support rather than a toggleable add-on.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'add_meta_boxes',
	static function (): void {
		add_meta_box( 'post-meta-video', 'Video Meta', 'bday_video_metabox', 'post' );
		add_meta_box( 'pro-url-meta', 'PRO Landing URL', 'bday_pro_url_metabox', 'post' );
		add_meta_box( 'post-meta-pdf', 'PDF Meta', 'bday_pdf_metabox', 'post' );
	}
);

function bday_video_metabox( WP_Post $post ): void {
	wp_nonce_field( 'bday_editorial_meta', 'bday_editorial_meta_nonce' );
	printf(
		'<label for="bday-youtube-id">YouTube video ID:</label> <input type="text" id="bday-youtube-id" name="youtube_id" value="%s" size="25" placeholder="YouTube video ID" />',
		esc_attr( get_post_meta( $post->ID, '_youtube_id', true ) )
	);
}

function bday_pro_url_metabox( WP_Post $post ): void {
	wp_nonce_field( 'bday_editorial_meta', 'bday_editorial_meta_nonce' );
	printf(
		'<label for="bday-pro-url">PRO URL:</label> <input type="text" id="bday-pro-url" name="pro_url" value="%s" size="80" placeholder="Pro website landing URL" />',
		esc_attr( get_post_meta( $post->ID, '_pro_url', true ) )
	);
}

function bday_pdf_metabox( WP_Post $post ): void {
	wp_nonce_field( 'bday_editorial_meta', 'bday_editorial_meta_nonce' );
	printf(
		'<p><label for="bday-pdf-link">PDF download URL:</label> <input type="text" id="bday-pdf-link" name="bday_pdf_link" value="%s" size="60" /></p>',
		esc_attr( get_post_meta( $post->ID, '_bday_pdf_link', true ) )
	);
	printf(
		'<p><label for="bday-pdf-preview">PDF preview URL:</label> <input type="text" id="bday-pdf-preview" name="bday_pdf_preview_link" value="%s" size="60" /></p>',
		esc_attr( get_post_meta( $post->ID, '_bday_pdf_preview_link', true ) )
	);
}

add_action(
	'save_post_post',
	static function ( int $post_id ): void {
		if ( ! isset( $_POST['bday_editorial_meta_nonce'] ) || ! wp_verify_nonce( $_POST['bday_editorial_meta_nonce'], 'bday_editorial_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$map = array(
			'youtube_id'            => '_youtube_id',
			'pro_url'               => '_pro_url',
			'bday_pdf_link'         => '_bday_pdf_link',
			'bday_pdf_preview_link' => '_bday_pdf_preview_link',
		);
		foreach ( $map as $field => $meta_key ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, $meta_key, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
			}
		}
	}
);

// Author display-picture profile field.
add_action( 'show_user_profile', 'bday_author_dp_field' );
add_action( 'edit_user_profile', 'bday_author_dp_field' );

function bday_author_dp_field( WP_User $user ): void {
	$dp = get_the_author_meta( 'custom_author_dp', $user->ID );
	?>
	<h3>Author's Display Picture</h3>
	<table class="form-table">
		<tr>
			<th><label for="custom_author_dp">Image URL</label></th>
			<td><input type="text" name="custom_author_dp" id="custom_author_dp" value="<?php echo esc_attr( $dp ); ?>" class="regular-text" /></td>
		</tr>
		<?php if ( $dp ) : ?>
			<tr><th>Preview</th><td><img style="object-fit:cover;" src="<?php echo esc_url( $dp ); ?>" height="96" width="96" alt="" /></td></tr>
		<?php endif; ?>
	</table>
	<?php
}

add_action( 'personal_options_update', 'bday_save_author_dp' );
add_action( 'edit_user_profile_update', 'bday_save_author_dp' );

function bday_save_author_dp( int $user_id ): void {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}
	if ( isset( $_POST['custom_author_dp'] ) ) {
		update_user_meta( $user_id, 'custom_author_dp', esc_url_raw( wp_unslash( $_POST['custom_author_dp'] ) ) );
	}
}

/**
 * Telegram on-publish notifier — no-op unless TELEGRAM_BOT_TOKEN and
 * TELEGRAM_CHAT_ID constants are defined in wp-config.php. wp_remote_post
 * instead of the previous raw-curl call, same message format.
 */
add_action(
	'publish_post',
	static function ( int $post_id ): void {
		if ( ! defined( 'TELEGRAM_BOT_TOKEN' ) || ! defined( 'TELEGRAM_CHAT_ID' ) ) {
			return;
		}
		if ( count( wp_get_post_revisions( $post_id ) ) > 1 ) {
			return; // only fire on first publish, not edits
		}
		wp_remote_post(
			'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage',
			array(
				'timeout' => 5,
				'body'    => array(
					'chat_id' => TELEGRAM_CHAT_ID,
					'text'    => html_entity_decode( get_the_title( $post_id ) ) . "\n" . get_permalink( $post_id ) . '?utm_source=telegram&utm_medium=social',
				),
			)
		);
	}
);

// RSS: prepend the featured image to feed content.
add_filter( 'the_excerpt_rss', 'bday_rss_featured_image', 1000 );
add_filter( 'the_content_feed', 'bday_rss_featured_image', 1000 );

function bday_rss_featured_image( string $content ): string {
	global $post;
	if ( isset( $post->ID ) && has_post_thumbnail( $post->ID ) ) {
		return get_the_post_thumbnail( $post->ID, 'large', array( 'no_lazy' => true ) ) . $content;
	}
	return $content;
}
