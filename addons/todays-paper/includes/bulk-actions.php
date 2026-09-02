<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bulk mark/unmark from the Posts list screen (wp-admin → Posts → All Posts) — the per-post
 * metabox (metabox.php) only ever lets an editor flag one post at a time from its own edit
 * screen, which doesn't scale on a morning when a desk wants to feature a dozen stories at once
 * (or undo a batch that went out under the wrong edition). Sets the same
 * `_bday_todays_paper`/`_bday_todays_paper_date`/`_bday_todays_paper_publication` meta the
 * metabox does, so a bulk-marked post behaves identically everywhere (website page, app) — no
 * separate code path. Display size defaults to 'small' for a bulk mark (an editor can still open
 * any post afterward and bump it to Large/Medium individually); it's left alone for a post
 * that's already flagged, so re-running the action to refresh today's date/publication doesn't
 * reset a size someone already set.
 */
add_filter(
	'bulk_actions-edit-post',
	static function ( array $actions ): array {
		foreach ( bday_todays_paper_publications() as $bday_pub_slug => $bday_pub_label ) {
			$actions[ 'bday_mark_todays_paper_' . $bday_pub_slug ] = 'Mark as ' . $bday_pub_label;
		}
		// One removal action regardless of which publication a post is
		// currently under — undoes a mistaken bulk-mark (or a batch of
		// stale flags) the same way marking applies to many posts at
		// once, rather than only ever being undoable one post at a time
		// via the row action in list-column.php.
		$actions['bday_unmark_todays_paper'] = 'Remove from Today\'s Paper/Weekender';
		return $actions;
	}
);

add_filter(
	'handle_bulk_actions-edit-post',
	static function ( string $redirect_to, string $doaction, array $post_ids ): string {
		foreach ( array_keys( bday_todays_paper_publications() ) as $bday_pub_slug ) {
			if ( 'bday_mark_todays_paper_' . $bday_pub_slug !== $doaction ) {
				continue;
			}
			$marked = 0;
			foreach ( $post_ids as $post_id ) {
				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					continue;
				}
				update_post_meta( $post_id, '_bday_todays_paper', '1' );
				update_post_meta( $post_id, '_bday_todays_paper_date', current_time( 'Y-m-d' ) );
				update_post_meta( $post_id, '_bday_todays_paper_publication', $bday_pub_slug );
				if ( '' === (string) get_post_meta( $post_id, '_bday_todays_paper_size', true ) ) {
					update_post_meta( $post_id, '_bday_todays_paper_size', 'small' );
				}
				$marked++;
			}
			return add_query_arg( 'bday_todays_paper_marked', $marked, $redirect_to );
		}

		if ( 'bday_unmark_todays_paper' === $doaction ) {
			$removed = 0;
			foreach ( $post_ids as $post_id ) {
				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					continue;
				}
				if ( '1' === (string) get_post_meta( $post_id, '_bday_todays_paper', true ) ) {
					update_post_meta( $post_id, '_bday_todays_paper', '' );
					$removed++;
				}
			}
			return add_query_arg( 'bday_todays_paper_removed', $removed, $redirect_to );
		}

		return $redirect_to;
	},
	10,
	3
);

add_action(
	'admin_notices',
	static function (): void {
		if ( isset( $_GET['bday_todays_paper_marked'] ) ) {
			$count = (int) $_GET['bday_todays_paper_marked'];
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: number of posts marked */
						_n( '%d post marked for Today\'s Paper.', '%d posts marked for Today\'s Paper.', $count, 'bday-aero' ),
						$count
					)
				)
			);
		}

		if ( isset( $_GET['bday_todays_paper_removed'] ) ) {
			$count = (int) $_GET['bday_todays_paper_removed'];
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: number of posts removed */
						_n( '%d post removed from Today\'s Paper.', '%d posts removed from Today\'s Paper.', $count, 'bday-aero' ),
						$count
					)
				)
			);
		}
	}
);
