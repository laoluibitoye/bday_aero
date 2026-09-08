<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One-off backfill for posts published before this add-on's
 * transition_post_status hook (publish-push.php) existed — those never got
 * a published_posts row in subscription-service, so the admin console's
 * Content Analytics report shows them as a bare "Post #<id>" with no
 * title/link. This walks existing published posts and pushes each one
 * through the exact same bday_follow_notify_sync_post() the live hook uses.
 *
 * Deliberately WP-CLI only, not a wp-admin button: this can mean thousands
 * of outbound HTTP calls (one per post, ~5s timeout each) on a large back
 * catalog, which is exactly the kind of work that shouldn't run inside a
 * web request — a wp-admin-triggered version would either time out well
 * before finishing or tie up a PHP-FPM worker for the whole run. A WP-CLI
 * process runs outside the web server's worker pool entirely, so it can't
 * compete with reader traffic for those workers; --sleep between requests
 * (default 1s) additionally paces the load this puts on subscription-
 * service itself, which every other post-publish call already hits one at
 * a time, never concurrently.
 *
 * wp bday backfill-published-posts [--dry-run] [--limit=<n>] [--sleep=<seconds>] [--post_ids=<id,id,...>]
 *
 * --post_ids targets specific posts directly (comma-separated IDs) instead
 * of walking the whole catalog from the lowest ID — the way to re-sync one
 * known post (e.g. after a failed sync reported by the live hook or a prior
 * backfill run) without re-attempting everything before it again.
 */
WP_CLI::add_command( 'bday backfill-published-posts', 'bday_follow_notify_backfill_command' );

function bday_follow_notify_backfill_command( array $args, array $assoc_args ): void {
	$dry_run  = isset( $assoc_args['dry-run'] );
	$limit    = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : -1;
	$sleep    = isset( $assoc_args['sleep'] ) ? (float) $assoc_args['sleep'] : 1.0;
	$post_ids = isset( $assoc_args['post_ids'] )
		? array_filter( array_map( 'absint', explode( ',', (string) $assoc_args['post_ids'] ) ) )
		: null;

	$post_ids = null !== $post_ids
		? get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'post__in'       => $post_ids,
				'orderby'        => 'post__in',
				'posts_per_page' => count( $post_ids ),
				'fields'         => 'ids',
			)
		)
		: get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

	if ( empty( $post_ids ) ) {
		WP_CLI::success( 'No published posts found.' );
		return;
	}

	$total   = count( $post_ids );
	$synced  = 0;
	$failed  = 0;
	$skipped = 0;

	foreach ( $post_ids as $i => $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			++$skipped;
			continue;
		}

		if ( $dry_run ) {
			WP_CLI::log( sprintf( '[dry-run] would sync #%d "%s"', $post->ID, $post->post_title ) );
			++$synced;
		} else {
			$result = bday_follow_notify_sync_post( $post );
			if ( null === $result ) {
				WP_CLI::warning( sprintf( 'connector not configured — aborting at #%d', $post->ID ) );
				break;
			} elseif ( true === $result ) {
				WP_CLI::log( sprintf( '(%d/%d) synced #%d "%s"', $i + 1, $total, $post->ID, $post->post_title ) );
				++$synced;
			} else {
				// Attempted but failed (network error or non-2xx) — logged
				// with the real reason via error_log by the sync function
				// itself. Counted separately, not as a synced row, and the
				// run keeps going: one flaky request shouldn't abort a
				// backfill of the whole catalog the way "not configured at
				// all" correctly does above.
				WP_CLI::warning( sprintf( '(%d/%d) FAILED to sync #%d "%s" — see server error log', $i + 1, $total, $post->ID, $post->post_title ) );
				++$failed;
			}
		}

		// Paced deliberately (see file docblock) — never fires after the
		// last post, and skipped entirely for --dry-run since that makes no
		// outbound request at all.
		if ( ! $dry_run && $sleep > 0 && $i < $total - 1 ) {
			usleep( (int) ( $sleep * 1_000_000 ) );
		}
	}

	WP_CLI::success( sprintf( '%d synced, %d failed, %d skipped, out of %d published posts.', $synced, $failed, $skipped, $total ) );
}
