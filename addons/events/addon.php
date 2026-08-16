<?php
/**
 * Addon Name: Events
 * Addon Slug: events
 * Description: Upcoming BusinessDay events (conferences, summits) post type and listing page.
 * Cache Namespace: events
 * Settings Tab: Events
 * Default: on
 *
 * The 'events' CPT + its venue/link/date/time meta box. Rendering happens
 * in template-parts/homepage/bottom-widgets.php (cached via
 * bday_get_posts, fixing the old events_widget()'s direct uncached
 * get_posts() call).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	static function (): void {
		register_post_type(
			'events',
			array(
				'labels'      => array(
					'name'          => 'Events',
					'singular_name' => 'Event',
					'add_new_item'  => 'Create Event',
					'all_items'     => 'All Events',
				),
				'public'      => true,
				'query_var'   => true,
				'rewrite'     => array( 'slug' => 'events' ),
				'has_archive' => true,
				'supports'    => array( 'title', 'thumbnail', 'excerpt' ),
			)
		);
	}
);

add_action(
	'add_meta_boxes',
	static function (): void {
		add_meta_box( 'bday_event_meta', 'Event Details', 'bday_events_render_metabox', 'events', 'normal', 'high' );
	}
);

function bday_events_render_metabox( WP_Post $post ): void {
	wp_nonce_field( 'bday_events_save', 'bday_events_nonce' );
	$fields = array(
		'_bday_event_venue' => array( 'Venue', 'e.g. Eko Hotel, Lagos' ),
		'_bday_event_link'  => array( 'Link', 'e.g. https://businessday.ng' ),
		'_bday_event_date'  => array( 'Date', 'e.g. July 13, 2026' ),
		'_bday_event_time'  => array( 'Time', 'e.g. 09:00 (24hr format)' ),
	);
	echo '<table class="form-table">';
	foreach ( $fields as $key => list( $label, $placeholder ) ) {
		printf(
			'<tr><th><label for="%1$s">%2$s</label></th><td><input type="text" id="%1$s" name="%1$s" value="%3$s" placeholder="%4$s" class="regular-text"></td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( get_post_meta( $post->ID, $key, true ) ),
			esc_attr( $placeholder )
		);
	}
	echo '</table>';
}

add_action(
	'save_post_events',
	static function ( int $post_id ): void {
		if ( ! isset( $_POST['bday_events_nonce'] ) || ! wp_verify_nonce( $_POST['bday_events_nonce'], 'bday_events_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		foreach ( array( '_bday_event_venue', '_bday_event_link', '_bday_event_date', '_bday_event_time' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
			}
		}
	}
);
