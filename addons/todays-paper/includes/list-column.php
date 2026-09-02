<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "Today's Paper" column on wp-admin → Posts → All Posts — same pattern
 * as aero-paywall's own Premium column (class-post-list-badge.php), so an
 * editor can see at a glance which posts are currently flagged without
 * opening each one. Shows the date it was (re-)flagged, since that's the
 * detail an editor scanning a long list actually wants to confirm (is
 * this still today's, or a stale flag from a previous day that just
 * hasn't shown up on the public page because it isn't scoped to that flag
 * date anymore — see query.php's docblock).
 */
add_filter(
	'manage_post_posts_columns',
	static function ( array $columns ): array {
		$columns['bday_todays_paper'] = "Today's Paper";
		return $columns;
	}
);

add_action(
	'manage_post_posts_custom_column',
	static function ( string $column, int $post_id ): void {
		if ( 'bday_todays_paper' !== $column ) {
			return;
		}
		if ( '1' !== (string) get_post_meta( $post_id, '_bday_todays_paper', true ) ) {
			echo '<span style="color:#6b7280;">—</span>';
			return;
		}
		$flagged_date = (string) get_post_meta( $post_id, '_bday_todays_paper_date', true );
		$publication  = (string) get_post_meta( $post_id, '_bday_todays_paper_publication', true ) ?: 'e-paper';
		$label        = bday_todays_paper_publications()[ $publication ] ?? "Today's Paper";
		printf( '<span style="color:#b45309;font-weight:600;">&#9733; %s</span>', esc_html( $label ) );
		if ( '' !== $flagged_date ) {
			$timestamp = strtotime( $flagged_date );
			if ( false !== $timestamp ) {
				printf(
					'<br /><span style="font-size:11px;color:#6b7280;">Flagged %s</span>',
					esc_html( date_i18n( 'M j, Y', $timestamp ) )
				);
			}
		}
	},
	10,
	2
);

/**
 * Per-post "Remove from Today's Paper"/"Remove from Weekender" row
 * action (label matches whichever publication the post is currently
 * under) — only shown when the post is actually flagged, so an editor
 * who added one by mistake (or whose flag has simply gone stale) doesn't
 * have to open the full edit screen and find the metabox checkbox just
 * to undo it.
 */
add_filter(
	'post_row_actions',
	static function ( array $actions, WP_Post $post ): array {
		if ( 'post' !== $post->post_type ) {
			return $actions;
		}
		if ( '1' !== (string) get_post_meta( $post->ID, '_bday_todays_paper', true ) ) {
			return $actions;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'bday_remove_todays_paper',
					'post'   => $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			'bday_remove_todays_paper_' . $post->ID
		);

		$publication = (string) get_post_meta( $post->ID, '_bday_todays_paper_publication', true ) ?: 'e-paper';
		$label       = bday_todays_paper_publications()[ $publication ] ?? "Today's Paper";

		$actions['bday_remove_todays_paper'] = sprintf(
			'<a href="%s" style="color:#b91c1c;">Remove from %s</a>',
			esc_url( $url ),
			esc_html( $label )
		);
		return $actions;
	},
	10,
	2
);

add_action(
	'admin_post_bday_remove_todays_paper',
	static function (): void {
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		check_admin_referer( 'bday_remove_todays_paper_' . $post_id );

		if ( $post_id && current_user_can( 'edit_post', $post_id ) ) {
			update_post_meta( $post_id, '_bday_todays_paper', '' );
		}

		$redirect = wp_get_referer();
		wp_safe_redirect( add_query_arg( 'bday_todays_paper_removed', 1, $redirect ?: admin_url( 'edit.php' ) ) );
		exit;
	}
);
