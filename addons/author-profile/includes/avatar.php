<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reader-requested: authors should be able to upload their own photo
 * rather than only ever getting whatever Gravatar has on file for their
 * email (which most staff accounts never set up). Stored as a normal
 * Media Library attachment ID in user meta — every existing get_avatar()
 * call site in the theme (byline, co-author list, author-bio block)
 * picks the uploaded photo up automatically via the filter below, no
 * template changes needed anywhere else.
 */

/**
 * Reader-reported: a non-square uploaded photo was coming out visibly
 * stretched/oval in the byline and author-bio avatar. The old filter below
 * asked wp_get_attachment_image_src() for an arbitrary `[$size, $size]`
 * box, which WordPress only ever treats as a fit-within max, not a hard
 * crop — the file it returns keeps the original aspect ratio, while
 * get_avatar() still renders it inside a forced-square `width`/`height`
 * box, so the browser stretches it to fill. A dedicated size registered
 * with `crop = true` is what actually guarantees a true square file, no
 * matter what aspect ratio the author's original upload was.
 */
add_action( 'after_setup_theme', static function (): void {
	add_image_size( 'bday_author_avatar', 300, 300, true );
} );

add_action( 'show_user_profile', 'bday_render_author_photo_field' );
add_action( 'edit_user_profile', 'bday_render_author_photo_field' );

function bday_render_author_photo_field( WP_User $user ): void {
	if ( ! current_user_can( 'edit_user', $user->ID ) ) {
		return;
	}
	wp_enqueue_media();
	$attachment_id = (int) get_user_meta( $user->ID, '_bday_author_avatar_id', true );
	$image_url     = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';
	?>
	<h2>Author Photo</h2>
	<table class="form-table">
		<tr>
			<th><label for="bday-author-avatar-button">Byline photo</label></th>
			<td>
				<div id="bday-author-avatar-preview" style="margin-bottom:10px;">
					<?php if ( $image_url ) : ?>
						<img src="<?php echo esc_url( $image_url ); ?>" alt="" style="width:96px;height:96px;object-fit:cover;border-radius:50%;display:block;">
					<?php endif; ?>
				</div>
				<input type="hidden" name="bday_author_avatar_id" id="bday-author-avatar-id" value="<?php echo esc_attr( (string) $attachment_id ); ?>">
				<button type="button" class="button" id="bday-author-avatar-button">Select image</button>
				<button type="button" class="button" id="bday-author-avatar-remove" style="<?php echo $attachment_id ? '' : 'display:none;'; ?>">Remove</button>
				<p class="description">Used across the site (article bylines, author page) in place of Gravatar. Falls back to Gravatar if no photo is uploaded.</p>
			</td>
		</tr>
	</table>
	<script>
	(function () {
		var frame;
		var button = document.getElementById( 'bday-author-avatar-button' );
		var removeButton = document.getElementById( 'bday-author-avatar-remove' );
		var input = document.getElementById( 'bday-author-avatar-id' );
		var preview = document.getElementById( 'bday-author-avatar-preview' );
		if ( ! button ) return;

		button.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			if ( frame ) { frame.open(); return; }
			frame = wp.media( { title: 'Select author photo', multiple: false, library: { type: 'image' } } );
			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				input.value = attachment.id;
				preview.innerHTML = '<img src="' + attachment.url + '" alt="" style="width:96px;height:96px;object-fit:cover;border-radius:50%;display:block;">';
				removeButton.style.display = '';
			} );
			frame.open();
		} );

		removeButton.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			input.value = '';
			preview.innerHTML = '';
			removeButton.style.display = 'none';
		} );
	})();
	</script>
	<?php
}

add_action( 'personal_options_update', 'bday_save_author_photo_field' );
add_action( 'edit_user_profile_update', 'bday_save_author_photo_field' );

function bday_save_author_photo_field( int $user_id ): void {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}
	if ( ! isset( $_POST['bday_author_avatar_id'] ) ) {
		return;
	}
	$attachment_id = absint( $_POST['bday_author_avatar_id'] );
	if ( $attachment_id ) {
		update_user_meta( $user_id, '_bday_author_avatar_id', $attachment_id );
	} else {
		delete_user_meta( $user_id, '_bday_author_avatar_id' );
	}
}

/**
 * get_avatar_url() is the one choke point every get_avatar()/get_avatar_url()
 * call in WordPress core (and this theme) resolves through, so filtering
 * here — rather than wrapping bday_get_thumbnail()-style — covers every
 * existing byline/avatar call site without touching a single template.
 */
add_filter(
	'get_avatar_url',
	static function ( string $url, $id_or_email, array $args ) {
		$user = false;
		if ( $id_or_email instanceof WP_User ) {
			$user = $id_or_email;
		} elseif ( is_numeric( $id_or_email ) ) {
			$user = get_userdata( (int) $id_or_email );
		} elseif ( is_string( $id_or_email ) ) {
			$user = get_user_by( 'email', $id_or_email );
		} elseif ( is_object( $id_or_email ) && isset( $id_or_email->user_id ) ) {
			$user = get_userdata( (int) $id_or_email->user_id );
		}
		if ( ! $user ) {
			return $url;
		}

		$attachment_id = (int) get_user_meta( $user->ID, '_bday_author_avatar_id', true );
		if ( ! $attachment_id ) {
			return $url;
		}

		// Always the hard-cropped square size registered above, regardless
		// of the requested display $size — get_avatar() scales the
		// resulting <img> down via width/height attributes anyway, and a
		// guaranteed-square source is what keeps that scaling from
		// distorting a non-square original.
		$custom = wp_get_attachment_image_src( $attachment_id, 'bday_author_avatar' );
		return $custom ? $custom[0] : $url;
	},
	10,
	3
);
