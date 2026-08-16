<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All bylined authors for a post: the native WordPress author first
 * (post_author — never dropped, so every existing post keeps working
 * unchanged), then any additionally-credited co-authors from the
 * metabox below, de-duplicated. Returns WP_User objects, not IDs, since
 * every call site immediately wants a name/avatar/link.
 *
 * @return WP_User[]
 */
function bday_get_post_authors( int $post_id ): array {
	$ids = array( (int) get_post_field( 'post_author', $post_id ) );

	$co_author_ids = get_post_meta( $post_id, '_bday_co_authors', true );
	if ( is_array( $co_author_ids ) ) {
		foreach ( $co_author_ids as $co_author_id ) {
			$ids[] = (int) $co_author_id;
		}
	}

	$ids = array_values( array_unique( array_filter( $ids ) ) );

	$authors = array();
	foreach ( $ids as $id ) {
		$user = get_userdata( $id );
		if ( $user ) {
			$authors[] = $user;
		}
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
				<?php echo get_avatar( $author->ID, 24, '', '', array( 'class' => 'bday-byline__author-avatar' ) ); ?>
				<a href="<?php echo esc_url( get_author_posts_url( $author->ID ) ); ?>"><?php echo esc_html( $author->display_name ); ?></a>
			</span>
		<?php endforeach; ?>
	</span>
	<?php
	return (string) ob_get_clean();
}
