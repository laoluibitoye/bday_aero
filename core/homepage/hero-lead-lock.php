<?php
/**
 * Editorial "lock" for the hero's lead story. Normally the lead is just
 * the newest post tagged "bdlead" (bday_get_homepage_data()'s 'lead' key)
 * — tagging a fresh post bumps whatever was leading before it. Locking
 * pins a specific post there instead, so new "bdlead" tags keep landing
 * normally (Top News, the tag archive, everything else is unaffected)
 * without bumping the locked post out of the hero.
 *
 * A single option holds "which post, if any" rather than a scattered
 * per-post meta flag, so there is never more than one locked post by
 * construction — checking the box on post B overwrites post A's lock
 * automatically, no separate "clear the old one" step needed anywhere.
 * The checkbox on the post editor is just this option's read/write
 * surface, not its own source of truth.
 *
 * Deliberately a metabox next to the post's own tags/category (same
 * place editors already flag "Feature in Today's Paper" —
 * addons/todays-paper/includes/metabox.php), not a settings-page post
 * picker: an editor locking a story does it FROM that story, not by
 * hunting for it by ID somewhere in wp-admin. Gated to edit_others_posts
 * (Editor and above, not Author) since this reaches past the one post
 * being edited into a site-wide homepage placement decision.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BDAY_HERO_LEAD_LOCK_OPTION = 'bday_hero_lead_lock_post_id';

/** 0 when nothing is locked. */
function bday_hero_lead_lock_post_id(): int {
	return (int) get_option( BDAY_HERO_LEAD_LOCK_OPTION, 0 );
}

/**
 * The hero's lead story — the locked post if one's set and still
 * published, otherwise the normal "newest bdlead-tagged post" query.
 * Same shape bday_get_posts() already returns (an array with 0 or 1
 * WP_Post) either way, so callers (data.php, hero.php,
 * template-parts/homepage/hero.php) don't need to know which case they
 * got.
 *
 * @return WP_Post[]
 */
function bday_get_hero_lead(): array {
	$locked_id = bday_hero_lead_lock_post_id();
	if ( $locked_id > 0 ) {
		$post = get_post( $locked_id );
		if ( $post && 'publish' === $post->post_status ) {
			return array( $post );
		}
		// The locked post was trashed/unpublished since being locked —
		// clear a lock that no longer points at anything sensible rather
		// than silently falling back forever while the option still
		// claims something is locked.
		delete_option( BDAY_HERO_LEAD_LOCK_OPTION );
	}

	return bday_get_posts( array( 'tag' => 'bdlead', 'numberposts' => 1, 'cache_namespace' => 'homepage' ) );
}

add_action(
	'add_meta_boxes',
	static function (): void {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}
		add_meta_box( 'bday-hero-lead-lock', 'Homepage Lead Story', 'bday_hero_lead_lock_metabox', 'post', 'side' );
	}
);

function bday_hero_lead_lock_metabox( WP_Post $post ): void {
	wp_nonce_field( 'bday_hero_lead_lock', 'bday_hero_lead_lock_nonce' );
	$locked_id    = bday_hero_lead_lock_post_id();
	$is_this_post = $locked_id === $post->ID;
	?>
	<label>
		<input type="checkbox" name="bday_hero_lead_lock" value="1" <?php checked( $is_this_post ); ?>>
		Lock as the homepage lead story
	</label>
	<p class="description">
		Keeps this post in the hero's main lead spot even after a newer post is tagged "bdlead". Uncheck to return to normal — the newest bdlead post leads automatically.
	</p>
	<?php if ( $locked_id > 0 && ! $is_this_post ) : ?>
		<p class="description">
			<?php echo esc_html( sprintf( 'Currently locked to "%s" — checking this box will replace it.', get_the_title( $locked_id ) ) ); ?>
		</p>
	<?php endif; ?>
	<?php
}

add_action(
	'save_post_post',
	static function ( int $post_id ): void {
		if ( ! isset( $_POST['bday_hero_lead_lock_nonce'] ) || ! wp_verify_nonce( $_POST['bday_hero_lead_lock_nonce'], 'bday_hero_lead_lock' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}

		$checked   = isset( $_POST['bday_hero_lead_lock'] );
		$locked_id = bday_hero_lead_lock_post_id();

		if ( $checked ) {
			update_option( BDAY_HERO_LEAD_LOCK_OPTION, $post_id );
		} elseif ( $locked_id === $post_id ) {
			// Only this post's own uncheck can clear the lock — an
			// unchecked box on some OTHER post (e.g. a stale tab open on
			// a post that isn't the locked one) must never wipe it out.
			delete_option( BDAY_HERO_LEAD_LOCK_OPTION );
		}
	}
);

/**
 * A same-page heads-up on the Posts list and post editor (not gated
 * behind any settings tab) so an editor browsing content notices a lock
 * is active without already knowing to look for it. Skipped on the
 * locked post's own edit screen — its metabox above already says the
 * same thing, right there.
 */
add_action(
	'admin_notices',
	static function (): void {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'edit-post', 'post' ), true ) ) {
			return;
		}

		$locked_id = bday_hero_lead_lock_post_id();
		if ( 0 === $locked_id ) {
			return;
		}
		$post = get_post( $locked_id );
		if ( ! $post ) {
			delete_option( BDAY_HERO_LEAD_LOCK_OPTION );
			return;
		}
		if ( 'post' === $screen->id && isset( $_GET['post'] ) && (int) $_GET['post'] === $locked_id ) {
			return;
		}
		?>
		<div class="notice notice-info is-dismissible">
			<p>
				<strong>Homepage lead story locked:</strong>
				<a href="<?php echo esc_url( (string) get_edit_post_link( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a>
				stays in the hero's lead spot until unlocked from that post's editor.
			</p>
		</div>
		<?php
	}
);
