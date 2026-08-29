<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bulk "Mark as Today's Paper" from the Posts list screen (wp-admin → Posts → All Posts) — the
 * per-post metabox (metabox.php) only ever lets an editor flag one post at a time from its own
 * edit screen, which doesn't scale on a morning when a desk wants to feature a dozen stories at
 * once. Sets the same `_bday_todays_paper`/`_bday_todays_paper_date` meta the metabox does, so a
 * bulk-marked post behaves identically everywhere (website page, app) — no separate code path.
 * Display size defaults to 'small' for a bulk mark (an editor can still open any post afterward
 * and bump it to Large/Medium individually); it's left alone for a post that's already flagged,
 * so re-running the bulk action to refresh today's date doesn't reset a size someone already set.
 */
add_filter(
	'bulk_actions-edit-post',
	static function ( array $actions ): array {
		$actions['bday_mark_todays_paper'] = "Mark as Today's Paper";
		return $actions;
	}
);

add_filter(
	'handle_bulk_actions-edit-post',
	static function ( string $redirect_to, string $doaction, array $post_ids ): string {
		if ( 'bday_mark_todays_paper' !== $doaction ) {
			return $redirect_to;
		}

		$marked = 0;
		foreach ( $post_ids as $post_id ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			update_post_meta( $post_id, '_bday_todays_paper', '1' );
			update_post_meta( $post_id, '_bday_todays_paper_date', current_time( 'Y-m-d' ) );
			if ( '' === (string) get_post_meta( $post_id, '_bday_todays_paper_size', true ) ) {
				update_post_meta( $post_id, '_bday_todays_paper_size', 'small' );
			}
			$marked++;
		}

		return add_query_arg( 'bday_todays_paper_marked', $marked, $redirect_to );
	},
	10,
	3
);

add_action(
	'admin_notices',
	static function (): void {
		if ( ! isset( $_GET['bday_todays_paper_marked'] ) ) {
			return;
		}
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
);
