<?php
/**
 * Addon Name: BDay Live
 * Addon Slug: bday-live
 * Cache Namespace: bday_live
 * Settings Tab: BDay Live
 * Default: off
 *
 * A YouTube live-stream takeover in the homepage hero's "Recent" column —
 * unrelated to the Live Match addon despite the similar name (that's a
 * football score ticker; this is a video embed). No queries at all: this
 * is pure option-read + iframe embed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'bday_hero_before_recent',
	static function (): void {
		$settings = get_option( 'bday_addon_bday_live', array() );
		if ( empty( $settings['enabled'] ) || empty( $settings['youtube_id'] ) ) {
			return;
		}
		?>
		<div class="bday-live-embed">
			<span class="bday-live-embed__badge">LIVE</span>
			<iframe
				src="https://www.youtube.com/embed/<?php echo esc_attr( $settings['youtube_id'] ); ?>?autoplay=1&mute=1"
				allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
				allowfullscreen loading="lazy"></iframe>
			<h3><?php echo esc_html( $settings['title'] ?? '' ); ?></h3>
		</div>
		<?php
	}
);

add_filter(
	'bday_settings_schema',
	static function ( array $schema ): array {
		$schema['bday-live'] = array(
			'tab_label' => 'BDay Live',
			'option'    => 'bday_addon_bday_live',
			'fields'    => array(
				array( 'key' => 'enabled', 'type' => 'checkbox', 'label' => 'Enable', 'default' => false ),
				array( 'key' => 'youtube_id', 'type' => 'text', 'label' => 'YouTube live video ID' ),
				array( 'key' => 'title', 'type' => 'text', 'label' => 'Title shown under the video' ),
			),
		);
		return $schema;
	}
);
