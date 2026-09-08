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
 * Two surfaces, one lock: the metabox below (next to the post's own
 * tags/category, same place editors already flag "Feature in Today's
 * Paper" — addons/todays-paper/includes/metabox.php) for locking a story
 * from its own editor, and a Posts -> Lead Story Lock dashboard page
 * further down this file for locking/unlocking by pasting a link or ID
 * without having to open that post at all. Both read and write the exact
 * same option, so there's no separate "dashboard lock" state to drift
 * out of sync with the metabox — whichever surface was used last is
 * simply what's true. Gated to edit_others_posts (Editor and above, not
 * Author) throughout, since this reaches past the one post being edited
 * into a site-wide homepage placement decision.
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
		Keeps this post in the hero's main lead spot even after a newer post is tagged "bdlead". Uncheck to return to normal — the newest bdlead post leads automatically. Can also be set from
		<a href="<?php echo esc_url( admin_url( 'edit.php?page=bday-hero-lead-lock' ) ); ?>">Posts → Lead Story Lock</a>.
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
				stays in the hero's lead spot until unlocked from that post's editor or from
				<a href="<?php echo esc_url( admin_url( 'edit.php?page=bday-hero-lead-lock' ) ); ?>">Posts → Lead Story Lock</a>.
			</p>
		</div>
		<?php
	}
);

/**
 * Posts -> Lead Story Lock: the same lock as the metabox above, reachable
 * without opening the specific post — paste its link (or type its ID)
 * and check a box, instead of navigating there first. A plain, unbranded
 * wp-admin page (no Bday_Admin_UI shell) since this is a Posts-menu
 * utility page, not part of the theme's own tabbed settings screen.
 */
add_action(
	'admin_menu',
	static function (): void {
		add_submenu_page(
			'edit.php',
			'Lead Story Lock',
			'Lead Story Lock',
			'edit_others_posts',
			'bday-hero-lead-lock',
			'bday_render_hero_lead_lock_page'
		);
	}
);

/**
 * Accepts a full permalink or a bare numeric ID and resolves it to a
 * published 'post', or null if either the input is empty/unresolvable or
 * doesn't point at one. url_to_postid() handles every permalink
 * structure this install might use (pretty or plain) — the same resolver
 * WP core's own oEmbed discovery relies on internally, rather than a
 * bespoke URL parser here.
 */
function bday_resolve_hero_lead_lock_input( string $input ): ?WP_Post {
	$input = trim( $input );
	if ( '' === $input ) {
		return null;
	}

	$post_id = ctype_digit( $input ) ? (int) $input : url_to_postid( $input );
	if ( $post_id <= 0 ) {
		return null;
	}

	$post = get_post( $post_id );
	if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
		return null;
	}

	return $post;
}

function bday_render_hero_lead_lock_page(): void {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage this.', 'bday-premium' ) );
	}

	$error   = '';
	$updated = false;

	if ( isset( $_POST['bday_hero_lead_lock_submit'] ) ) {
		check_admin_referer( 'bday_hero_lead_lock_dashboard' );

		$checked = isset( $_POST['bday_hero_lead_lock_enabled'] );
		$input   = isset( $_POST['bday_hero_lead_lock_input'] ) ? sanitize_text_field( wp_unslash( $_POST['bday_hero_lead_lock_input'] ) ) : '';

		if ( ! $checked ) {
			delete_option( BDAY_HERO_LEAD_LOCK_OPTION );
			$updated = true;
		} else {
			$post = bday_resolve_hero_lead_lock_input( $input );
			if ( ! $post ) {
				$error = '' === $input
					? 'Enter the article link or ID to lock, or uncheck the box to leave the lead story unlocked.'
					: 'Could not find a published post at that link or ID — paste the full article URL, or its numeric post ID.';
			} else {
				update_option( BDAY_HERO_LEAD_LOCK_OPTION, $post->ID );
				$updated = true;
			}
		}
	}

	$locked_id   = bday_hero_lead_lock_post_id();
	$locked_post = $locked_id > 0 ? get_post( $locked_id ) : null;

	// The locked post was trashed/unpublished since being locked — clear
	// a lock that no longer points at anything sensible rather than
	// showing a broken reference on this page.
	if ( $locked_id > 0 && ! $locked_post ) {
		delete_option( BDAY_HERO_LEAD_LOCK_OPTION );
		$locked_id = 0;
	}
	?>
	<div class="wrap">
		<h1>Lead Story Lock</h1>
		<p>Keeps a specific post in the hero's main lead spot even after a newer post is tagged "bdlead". This is the same lock as the "Homepage Lead Story" box on that post's own editor — either surface reflects and controls the same thing.</p>

		<?php if ( $updated ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo $locked_id > 0 ? 'Lead story locked.' : 'Lead story unlocked — the newest "bdlead" post leads automatically again.'; ?></p>
			</div>
		<?php endif; ?>
		<?php if ( '' !== $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>

		<p>
			<?php if ( $locked_post ) : ?>
				<strong>Currently locked:</strong>
				<a href="<?php echo esc_url( get_permalink( $locked_post ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_the_title( $locked_post ) ); ?></a>
				— <a href="<?php echo esc_url( (string) get_edit_post_link( $locked_post ) ); ?>">Edit</a>
			<?php else : ?>
				No lead story is currently locked — the newest "bdlead" post leads automatically.
			<?php endif; ?>
		</p>

		<form method="post">
			<?php wp_nonce_field( 'bday_hero_lead_lock_dashboard' ); ?>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="bday-hero-lead-lock-input">Article link or ID</label></th>
						<td>
							<input
								type="text"
								id="bday-hero-lead-lock-input"
								name="bday_hero_lead_lock_input"
								class="regular-text"
								value="<?php echo esc_attr( $locked_post ? get_permalink( $locked_post ) : '' ); ?>"
								placeholder="https://businessday.ng/... or a post ID"
							>
							<p class="description">Paste the article's URL, or its numeric post ID.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Lock</th>
						<td>
							<label>
								<input type="checkbox" name="bday_hero_lead_lock_enabled" value="1" <?php checked( $locked_id > 0 ); ?>>
								Lock this post as the homepage lead story
							</label>
						</td>
					</tr>
				</tbody>
			</table>
			<?php submit_button( 'Save', 'primary', 'bday_hero_lead_lock_submit' ); ?>
		</form>
	</div>
	<?php
}
