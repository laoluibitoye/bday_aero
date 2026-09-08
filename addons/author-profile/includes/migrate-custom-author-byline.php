<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One-off migration from the retired "Custom Author Byline" plugin
 * (github.com/active-interest/custom-author-byline) into this add-on's
 * own byline-override field (_bday_author_override, metabox.php,
 * bday_get_post_author_override()). That plugin stored one name+link
 * pair per post directly under the generic postmeta keys 'author' and
 * 'uri' — confirmed against this site's own installed copy before
 * writing this, not assumed from the plugin's public source alone.
 *
 * Deliberately leaves the plugin's own 'author'/'uri' postmeta
 * untouched — this only ever ADDS the new _bday_author_override value
 * alongside it, never deletes the source data, so the migration is
 * trivially reversible (delete _bday_author_override on any post to
 * undo) and safe to re-run: a post that already has
 * _bday_author_override set — from a prior run of this command, or a
 * genuine manual edit through the new UI since — is skipped rather
 * than overwritten.
 *
 * No post_type restriction: the plugin's own docs note it supports
 * custom post types, and this site's actual usage isn't known ahead of
 * time, so this queries every post type/status for the meta key rather
 * than guessing which ones it was used on.
 *
 * wp bday migrate-custom-author-byline [--dry-run] [--limit=<n>] [--post_ids=<id,id,...>]
 */
WP_CLI::add_command( 'bday migrate-custom-author-byline', 'bday_migrate_custom_author_byline_command' );

function bday_migrate_custom_author_byline_command( array $args, array $assoc_args ): void {
	$dry_run  = isset( $assoc_args['dry-run'] );
	$limit    = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : -1;
	$post_ids = isset( $assoc_args['post_ids'] )
		? array_filter( array_map( 'absint', explode( ',', (string) $assoc_args['post_ids'] ) ) )
		: null;

	$query_args = array(
		'post_type'      => 'any',
		'post_status'    => 'any',
		'posts_per_page' => $limit,
		'orderby'        => 'ID',
		'order'          => 'ASC',
		'fields'         => 'ids',
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one-off CLI migration, not a reader-facing query.
			array(
				'key'     => 'author',
				'value'   => '',
				'compare' => '!=',
			),
		),
	);
	if ( null !== $post_ids ) {
		$query_args['post__in']       = $post_ids;
		$query_args['orderby']        = 'post__in';
		$query_args['posts_per_page'] = count( $post_ids );
	}

	$found = get_posts( $query_args );

	if ( empty( $found ) ) {
		WP_CLI::success( 'No posts found with a Custom Author Byline value set.' );
		return;
	}

	$total    = count( $found );
	$migrated = 0;
	$skipped  = 0;

	foreach ( $found as $i => $post_id ) {
		$name = trim( (string) get_post_meta( $post_id, 'author', true ) );
		if ( '' === $name ) {
			++$skipped; // the meta_query already filters this, but a value that's all-whitespace slips through it
			continue;
		}

		$existing = get_post_meta( $post_id, '_bday_author_override', true );
		if ( is_array( $existing ) && ! empty( $existing['name'] ) ) {
			WP_CLI::log( sprintf( '(%d/%d) #%d already has an override ("%s") — skipping', $i + 1, $total, $post_id, $existing['name'] ) );
			++$skipped;
			continue;
		}

		$url = trim( (string) get_post_meta( $post_id, 'uri', true ) );

		if ( $dry_run ) {
			WP_CLI::log( sprintf( '[dry-run] (%d/%d) would migrate #%d: name="%s" url="%s"', $i + 1, $total, $post_id, $name, $url ) );
		} else {
			update_post_meta(
				$post_id,
				'_bday_author_override',
				array(
					'name' => sanitize_text_field( $name ),
					'url'  => '' !== $url ? esc_url_raw( $url ) : '',
				)
			);
			WP_CLI::log( sprintf( '(%d/%d) migrated #%d: "%s"', $i + 1, $total, $post_id, $name ) );
		}
		++$migrated;
	}

	WP_CLI::success(
		sprintf(
			'%s%d migrated, %d skipped, out of %d posts with a Custom Author Byline value.',
			$dry_run ? '[dry-run] ' : '',
			$migrated,
			$skipped,
			$total
		)
	);
}
