<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CLI front door onto the shared migration logic in
 * legacy-migration-core.php — for staging/dev environments with WP-CLI/SSH
 * access. Production sites without that access use the wp-admin wizard at
 * legacy-migration-wizard.php instead; both call the same per-post function.
 *
 * wp bday migrate-legacy-editions [--dry-run] [--limit=<n>]
 */
WP_CLI::add_command( 'bday migrate-legacy-editions', 'bday_edition_migrate_legacy_editions_command' );

function bday_edition_migrate_legacy_editions_command( array $args, array $assoc_args ): void {
	$dry_run = isset( $assoc_args['dry-run'] );
	$limit   = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : -1;

	$legacy_posts = bday_edition_get_legacy_posts( $limit );

	$counts = array();
	foreach ( $legacy_posts as $legacy_post ) {
		$result                        = bday_edition_migrate_one_legacy_post( $legacy_post, $dry_run );
		$counts[ $result['status'] ] = ( $counts[ $result['status'] ] ?? 0 ) + 1;

		$prefix = ( $dry_run && 'would_migrate' === $result['status'] ) ? '[dry-run] ' : '';
		$line   = "{$prefix}#{$legacy_post->ID} \"{$legacy_post->post_title}\": {$result['message']}";

		if ( 'failed' === $result['status'] ) {
			WP_CLI::warning( $line );
		} else {
			WP_CLI::log( $line );
		}
	}

	WP_CLI::success(
		sprintf(
			'%d migrated, %d skipped (no preview link), %d skipped (fetch/upload failed), %d skipped (already migrated).',
			$counts['migrated'] ?? $counts['would_migrate'] ?? 0,
			$counts['no_link'] ?? 0,
			$counts['failed'] ?? 0,
			$counts['already_migrated'] ?? 0
		)
	);
}
