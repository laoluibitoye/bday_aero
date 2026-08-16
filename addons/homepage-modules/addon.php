<?php
/**
 * Addon Name: Homepage Modules
 * Addon Slug: homepage-modules
 * Description: Per-module on-off switches for the homepage's lower rows (BD TV, magazine, Shorts, promo banners).
 * Cache Namespace: homepage_modules
 * Settings Tab: Homepage Modules
 * Default: on
 *
 * Deep Dive §4/Phase 4: per-module on/off switches for the homepage's
 * bottom-widget rows (video/magazine/today's-paper/toon+podcast/events —
 * template-parts/homepage/bottom-widgets.php reads the same option this
 * addon's settings schema writes to), plus two genuinely new modules that
 * have no existing content to extend: landscape/portrait promo banners and
 * a YouTube Shorts rail. Both new modules render on
 * bday_homepage_after_bottom_widgets (bottom-widgets.php) and are off by
 * default until an editor configures them — "dead air when off," never an
 * empty section, same posture as addons/bday-live's hero embed toggle.
 *
 * On by default (unlike most add-ons) because disabling it would silently
 * turn off five already-shipping homepage sections that editors expect to
 * see; it exists to let them turn individual ones off, not to gate all of
 * them behind an opt-in.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'bday_homepage_after_bottom_widgets',
	static function (): void {
		$settings = get_option( 'bday_addon_homepage_modules', array() );

		if ( ! empty( $settings['shorts_enabled'] ) && ! empty( $settings['shorts_video_ids'] ) ) {
			$ids = array_filter( array_map( 'trim', explode( ',', $settings['shorts_video_ids'] ) ) );
			if ( ! empty( $ids ) ) {
				?>
				<section class="bday-shorts-row">
					<div class="bday-container">
						<h2 class="bday-section-heading">Shorts</h2>
						<div class="bday-scroll-row bday-scroll-row--shorts">
							<?php foreach ( $ids as $video_id ) : ?>
								<a class="bday-shorts-card" href="<?php echo esc_url( 'https://www.youtube.com/shorts/' . rawurlencode( $video_id ) ); ?>" target="_blank" rel="noopener">
									<img src="<?php echo esc_url( 'https://i.ytimg.com/vi/' . rawurlencode( $video_id ) . '/hqdefault.jpg' ); ?>" alt="" loading="lazy">
									<span class="bday-shorts-card__play">&#9654;</span>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
				</section>
				<?php
			}
		}

		$has_landscape = ! empty( $settings['landscape_banner_image'] ) && ! empty( $settings['landscape_banner_url'] );
		$has_portrait  = ! empty( $settings['portrait_banner_image'] ) && ! empty( $settings['portrait_banner_url'] );
		if ( $has_landscape || $has_portrait ) {
			?>
			<section class="bday-promo-banners">
				<div class="bday-container bday-promo-banners__inner">
					<?php if ( $has_landscape ) : ?>
						<a class="bday-promo-banner bday-promo-banner--landscape" href="<?php echo esc_url( $settings['landscape_banner_url'] ); ?>" target="_blank" rel="noopener sponsored">
							<img src="<?php echo esc_url( $settings['landscape_banner_image'] ); ?>" alt="">
						</a>
					<?php endif; ?>
					<?php if ( $has_portrait ) : ?>
						<a class="bday-promo-banner bday-promo-banner--portrait" href="<?php echo esc_url( $settings['portrait_banner_url'] ); ?>" target="_blank" rel="noopener sponsored">
							<img src="<?php echo esc_url( $settings['portrait_banner_image'] ); ?>" alt="">
						</a>
					<?php endif; ?>
				</div>
			</section>
			<?php
		}
	}
);

add_filter(
	'bday_settings_schema',
	static function ( array $schema ): array {
		$schema['homepage-modules'] = array(
			'tab_label' => 'Homepage Modules',
			'option'    => 'bday_addon_homepage_modules',
			'intro'     => 'On/off switches for the row of content modules that run down the lower half of the homepage, plus two purely-promotional modules (Shorts, banner ads) that have no other content behind them to show or hide — those two only appear once actually configured below.',
			'about'     => '<p>The first six modules are always-on content pulled automatically from existing posts (video, magazine sections, Today\'s Paper, cartoons/podcast, events, e-editions) — turning one off just removes that row, nothing else needs to change. The Shorts rail and the two banner slots are the only ones that need real configuration to do anything.</p>',
			'fields'    => array(
				array( 'key' => 'enable_video_row', 'type' => 'checkbox', 'label' => 'BD TV row', 'default' => true, 'description' => 'Video posts from the "top-video" category.' ),
				array( 'key' => 'enable_magazine_row', 'type' => 'checkbox', 'label' => 'Magazine row', 'default' => true, 'description' => 'Weekender / Women\'s Hub / Reports teasers.' ),
				array( 'key' => 'enable_todays_paper', 'type' => 'checkbox', 'label' => "Today's Paper teaser", 'default' => true, 'description' => 'A small teaser linking to the full Today\'s Paper page — unrelated to that page\'s own content, which is edited from each post\'s "Feature in Today\'s Paper" checkbox.' ),
				array( 'key' => 'enable_toon_podcast_row', 'type' => 'checkbox', 'label' => 'Toon of the Day + Podcast', 'default' => true, 'description' => 'The day\'s cartoon alongside the latest podcast episode, side by side.' ),
				array( 'key' => 'enable_events_row', 'type' => 'checkbox', 'label' => 'Events row', 'default' => true, 'description' => 'Upcoming events from the Events post type.' ),
				array( 'key' => 'enable_editions_row', 'type' => 'checkbox', 'label' => 'E-Editions row', 'default' => true, 'description' => 'One card per e-edition publication (addons/editions), showing its most recent issue.' ),
				array( 'key' => 'shorts_enabled', 'type' => 'checkbox', 'label' => 'Enable Shorts rail', 'default' => false, 'description' => 'A horizontal-scroll row of YouTube Shorts thumbnails. Needs at least one video ID entered below to actually show anything.' ),
				array( 'key' => 'shorts_video_ids', 'type' => 'text', 'label' => 'Shorts video IDs', 'description' => 'Comma-separated YouTube video IDs, most recent first — the ID is the part of a YouTube URL after "shorts/" or "watch?v=".' ),
				array( 'key' => 'landscape_banner_image', 'type' => 'url', 'label' => 'Landscape banner image URL', 'description' => 'A wide promotional image (a sponsor, a house ad, an event) shown as its own row. Both banners here need an image AND a link URL to appear — either alone stays hidden.' ),
				array( 'key' => 'landscape_banner_url', 'type' => 'url', 'label' => 'Landscape banner link URL', 'description' => 'Where clicking the landscape banner above goes.' ),
				array( 'key' => 'portrait_banner_image', 'type' => 'url', 'label' => 'Portrait banner image URL', 'description' => 'A taller promotional image, shown alongside the landscape one when both are set.' ),
				array( 'key' => 'portrait_banner_url', 'type' => 'url', 'label' => 'Portrait banner link URL', 'description' => 'Where clicking the portrait banner above goes.' ),
			),
		);
		return $schema;
	}
);
