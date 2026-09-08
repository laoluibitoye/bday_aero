<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All bylined authors for a post: the primary byline first — normally
 * the native WordPress author (post_author), or, if
 * bday_get_post_author_override() (metabox.php) has a name set, that
 * override in its place entirely, added for parity with the retired
 * Custom Author Byline plugin's "override giving yourself credit for
 * this post" behavior — then any additionally-credited co-authors
 * (bday_get_post_co_authors(), metabox.php), de-duplicated by WP user id
 * (a guest entry has no id to de-dupe against, so two differently-typed
 * guest entries with the same name would both render — an edge case not
 * worth guarding against a genuinely intentional duplicate credit).
 *
 * Editor-requested rework (2026-09-08): a co-author no longer has to be a
 * real WP_User (most bylined writers aren't staff with an account here),
 * so this returns the same normalized shape metabox.php's
 * bday_co_author_normalize() does — {type, id, name, url} — rather than
 * WP_User objects. The primary entry is 'guest' when overridden, 'user'
 * otherwise.
 *
 * @return array{type:string,id:?int,name:string,url:string}[]
 */
function bday_get_post_authors( int $post_id ): array {
	$primary_id = (int) get_post_field( 'post_author', $post_id );
	$authors    = array();
	$seen_ids   = array();

	$override = function_exists( 'bday_get_post_author_override' ) ? bday_get_post_author_override( $post_id ) : array( 'name' => '' );
	if ( '' !== $override['name'] ) {
		$authors[] = array(
			'type' => 'guest',
			'id'   => null,
			'name' => $override['name'],
			'url'  => $override['url'],
		);
		// Not added to $seen_ids: the real WP account is still a distinct
		// person who could, in principle, also be explicitly credited as
		// a co-author alongside the name that replaced their byline slot.
	} else {
		$primary_user = get_userdata( $primary_id );
		if ( $primary_user ) {
			$authors[]               = array(
				'type' => 'user',
				'id'   => (int) $primary_user->ID,
				'name' => $primary_user->display_name,
				'url'  => get_author_posts_url( $primary_user->ID ),
			);
			$seen_ids[ $primary_id ] = true;
		}
	}

	foreach ( bday_get_post_co_authors( $post_id ) as $co_author ) {
		if ( 'user' === $co_author['type'] && isset( $seen_ids[ $co_author['id'] ] ) ) {
			continue;
		}
		if ( 'user' === $co_author['type'] ) {
			$seen_ids[ $co_author['id'] ] = true;
		}
		$authors[] = $co_author;
	}

	return $authors;
}

/**
 * Byline HTML for however many authors a post has: one linked name +
 * avatar for a single author (the common case, unchanged in spirit from
 * before), "X and Y" for two, "X, Y and Z" for three or more — the
 * conventional written-English joining pattern, not a comma-separated
 * dump. Returns a string so callers can drop it straight into existing
 * markup the same way bday_card_html() already returns a string.
 *
 * A guest author (no WP account) gets no avatar — there's no gravatar
 * email or uploaded display picture to pull one from — and their name is
 * only linked if they were given a URL; a staff co-author always gets
 * both, exactly as before this addon supported guest bylines.
 */
function bday_authors_byline_html( int $post_id ): string {
	$authors = bday_get_post_authors( $post_id );
	if ( empty( $authors ) ) {
		return '';
	}

	ob_start();
	?>
	<span class="bday-byline__authors">
		<?php foreach ( $authors as $index => $author ) : ?>
			<?php if ( 0 !== $index ) : ?>
				<span class="bday-byline__authors-sep"><?php echo esc_html( $index === count( $authors ) - 1 ? ( count( $authors ) > 2 ? ', and ' : ' and ' ) : ', ' ); ?></span>
			<?php endif; ?>
			<span class="bday-byline__author">
				<?php if ( 'user' === $author['type'] ) : ?>
					<?php echo get_avatar( $author['id'], 24, '', '', array( 'class' => 'bday-byline__author-avatar' ) ); ?>
				<?php endif; ?>
				<?php if ( '' !== $author['url'] ) : ?>
					<a href="<?php echo esc_url( $author['url'] ); ?>"><?php echo esc_html( $author['name'] ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $author['name'] ); ?>
				<?php endif; ?>
			</span>
		<?php endforeach; ?>
	</span>
	<?php
	return (string) ob_get_clean();
}
