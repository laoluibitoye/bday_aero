<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'add_meta_boxes',
	static function (): void {
		add_meta_box( 'bday-todays-paper', "Today's Paper", 'bday_todays_paper_metabox', 'post', 'side' );
	}
);

/** @return array<string, string> tier key => admin-facing label */
function bday_todays_paper_sizes(): array {
	return array(
		'large'     => 'Large (lead story)',
		'medium'    => 'Medium',
		'small'     => 'Small',
		'xsmall'    => 'Extra small',
		'no-image'  => 'No image (headline only)',
	);
}

/**
 * The two channels a featured post can belong to — slugs deliberately
 * match the edition_publication taxonomy's own term slugs (addons/
 * editions/includes/cpt.php) so a marked post's channel and a
 * bday_edition's publication are the same concept end to end: the
 * E-Paper Articles page (template-epaper-articles.php) looks up both the
 * day's edition PDF and its marked articles by this one value.
 *
 * @return array<string, string> publication slug => admin-facing label
 */
function bday_todays_paper_publications(): array {
	return array(
		'e-paper'   => "Today's Paper",
		'weekender' => 'Weekender',
	);
}

function bday_todays_paper_metabox( WP_Post $post ): void {
	wp_nonce_field( 'bday_todays_paper', 'bday_todays_paper_nonce' );
	$checked     = (bool) get_post_meta( $post->ID, '_bday_todays_paper', true );
	$size        = (string) get_post_meta( $post->ID, '_bday_todays_paper_size', true ) ?: 'small';
	$publication = (string) get_post_meta( $post->ID, '_bday_todays_paper_publication', true ) ?: 'e-paper';
	printf(
		'<label><input type="checkbox" id="bday-todays-paper-flag" name="todays_paper" value="1" %s /> Feature this post</label>' .
		'<p class="description">Shown on the matching page below, grouped under this post\'s category.</p>',
		checked( $checked, true, false )
	);
	?>
	<p style="margin-top:10px;">
		<label style="display:block;margin-bottom:4px;">Feature as</label>
		<?php foreach ( bday_todays_paper_publications() as $bday_pub_slug => $bday_pub_label ) : ?>
			<label style="display:block;font-weight:normal;">
				<input type="radio" name="todays_paper_publication" value="<?php echo esc_attr( $bday_pub_slug ); ?>" <?php checked( $publication, $bday_pub_slug ); ?> />
				<?php echo esc_html( $bday_pub_label ); ?>
			</label>
		<?php endforeach; ?>
		<span class="description">Today's Paper for a normal weekday feature, Weekender for a weekend-edition feature — matches the E-Paper Articles page's own edition lookup for that date.</span>
	</p>
	<p style="margin-top:10px;">
		<label for="bday-todays-paper-size" style="display:block;margin-bottom:4px;">Display size on that page</label>
		<select id="bday-todays-paper-size" name="todays_paper_size" style="width:100%;">
			<?php foreach ( bday_todays_paper_sizes() as $bday_size_key => $bday_size_label ) : ?>
				<option value="<?php echo esc_attr( $bday_size_key ); ?>" <?php selected( $size, $bday_size_key ); ?>><?php echo esc_html( $bday_size_label ); ?></option>
			<?php endforeach; ?>
		</select>
		<span class="description">Controls how much visual weight this story gets in the masonry layout — a "Large" lead story per section reads best, with the rest at Small/Extra small.</span>
	</p>
	<?php
}

add_action(
	'save_post_post',
	static function ( int $post_id ): void {
		if ( ! isset( $_POST['bday_todays_paper_nonce'] ) || ! wp_verify_nonce( $_POST['bday_todays_paper_nonce'], 'bday_todays_paper' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$flagged = isset( $_POST['todays_paper'] );
		update_post_meta( $post_id, '_bday_todays_paper', $flagged ? '1' : '' );
		// Stamped fresh every time the box is (re)checked — the page/feed only ever shows posts
		// flagged *as of today*, so a post left checked from a previous day silently drops off
		// without an editor having to remember to uncheck it. Re-saving the post with the box
		// still checked (even unchanged) re-stamps it back to today.
		if ( $flagged ) {
			update_post_meta( $post_id, '_bday_todays_paper_date', current_time( 'Y-m-d' ) );
		}

		$publication = is_string( $_POST['todays_paper_publication'] ?? null ) ? sanitize_key( $_POST['todays_paper_publication'] ) : 'e-paper';
		if ( ! array_key_exists( $publication, bday_todays_paper_publications() ) ) {
			$publication = 'e-paper';
		}
		update_post_meta( $post_id, '_bday_todays_paper_publication', $publication );

		$size = is_string( $_POST['todays_paper_size'] ?? null ) ? sanitize_key( $_POST['todays_paper_size'] ) : 'small';
		if ( ! array_key_exists( $size, bday_todays_paper_sizes() ) ) {
			$size = 'small';
		}
		update_post_meta( $post_id, '_bday_todays_paper_size', $size );
	}
);
