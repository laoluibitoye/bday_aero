<?php
/**
 * Addon Name: Premium Leaderboard
 * Addon Slug: premium-leaderboard
 * Description: A promotional leaderboard banner shown to non-subscribers.
 * Cache Namespace: premium_leaderboard
 * Settings Tab: Premium Leaderboard
 * Default: on
 *
 * Homepage-only rotating sponsor image/link banner — not an ad-network
 * integration despite the name (that's addons/vendors/). Renders into
 * template-parts/homepage/hero.php's hook.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'bday_homepage_leaderboard_zone', 'bday_premium_leaderboard_render' );

function bday_premium_leaderboard_render(): void {
	if ( ! bday_page_allows_ads() ) {
		return;
	}
	$settings = get_option( 'bday_addon_premium_leaderboard', array() );
	$slides   = array_filter( (array) ( $settings['slides'] ?? array() ), static fn( $s ) => ! empty( $s['image'] ) );
	if ( empty( $slides ) ) {
		return;
	}
	$speed = (int) ( $settings['slider_speed'] ?? 20000 );
	?>
	<div class="bday-leaderboard" data-slider-speed="<?php echo esc_attr( (string) $speed ); ?>">
		<?php foreach ( $slides as $i => $slide ) : ?>
			<a href="<?php echo esc_url( $slide['url'] ?? '#' ); ?>" class="bday-leaderboard__slide" style="<?php echo 0 === $i ? '' : 'display:none;'; ?>">
				<img src="<?php echo esc_url( $slide['image'] ); ?>" alt="Sponsor" loading="lazy">
			</a>
		<?php endforeach; ?>
	</div>
	<?php
}

add_filter(
	'bday_settings_schema',
	static function ( array $schema ): array {
		$schema['premium-leaderboard'] = array(
			'tab_label' => 'Premium Leaderboard',
				'group'     => 'editorial',
			'option'    => 'bday_addon_premium_leaderboard',
			'render'    => 'bday_premium_leaderboard_settings_tab',
			'intro'     => 'A rotating promotional/sponsor banner on the homepage — despite the name, not limited to subscription offers specifically (it\'s a generic image+link slot; a subscribe-promo image is one common use, a paid sponsor placement is another). Shown to every visitor regardless of subscription status, since it isn\'t always subscribe-related content.',
			'about'     => '<p>A simple auto-rotating image slideshow, each slide an image plus the URL it links to. Add as many slides as needed; the slider speed controls how long each one stays on screen before advancing.</p>',
		);
		return $schema;
	}
);

function bday_premium_leaderboard_settings_tab(): void {
	$settings = get_option( 'bday_addon_premium_leaderboard', array() );
	$slides   = (array) ( $settings['slides'] ?? array() );
	$count    = max( 1, count( $slides ) ?: 4 );
	?>
	<table class="form-table" role="presentation"><tbody>
		<tr><th scope="row">Number of items</th><td><input type="number" name="bday_addon_premium_leaderboard[count]" value="<?php echo esc_attr( (string) $count ); ?>" min="1" max="10" /><p class="description">How many slides to configure below.</p></td></tr>
		<tr><th scope="row">Slider speed (ms)</th><td><input type="number" name="bday_addon_premium_leaderboard[slider_speed]" value="<?php echo esc_attr( (string) ( $settings['slider_speed'] ?? 20000 ) ); ?>" min="1000" /><p class="description">Milliseconds each slide stays visible before advancing to the next (20000 = 20 seconds).</p></td></tr>
	</tbody></table>
	<?php for ( $i = 0; $i < $count; $i++ ) :
		$slide = $slides[ $i ] ?? array();
		?>
		<h4>Slide <?php echo (int) ( $i + 1 ); ?></h4>
		<table class="form-table" role="presentation"><tbody>
			<tr><th scope="row">Image URL</th><td><input type="text" name="bday_addon_premium_leaderboard[slides][<?php echo $i; ?>][image]" value="<?php echo esc_attr( $slide['image'] ?? '' ); ?>" class="regular-text" /><p class="description">The banner creative for this slide. A wide, short "leaderboard"-shaped image reads best in this slot.</p></td></tr>
			<tr><th scope="row">Landing URL</th><td><input type="text" name="bday_addon_premium_leaderboard[slides][<?php echo $i; ?>][url]" value="<?php echo esc_attr( $slide['url'] ?? '' ); ?>" class="regular-text" /><p class="description">Where clicking this slide sends a reader — usually the subscribe page.</p></td></tr>
		</tbody></table>
	<?php endfor;
}

add_action(
	'admin_init',
	static function (): void {
		register_setting(
			'bday_addon_premium_leaderboard',
			'bday_addon_premium_leaderboard',
			array(
				'sanitize_callback' => static function ( $input ) {
					$input  = is_array( $input ) ? $input : array();
					$count  = max( 1, min( 10, (int) ( $input['count'] ?? 4 ) ) );
					$output = array(
						'slider_speed' => max( 1000, (int) ( $input['slider_speed'] ?? 20000 ) ),
						'slides'       => array(),
					);
					for ( $i = 0; $i < $count; $i++ ) {
						$slide = is_array( $input['slides'][ $i ] ?? null ) ? $input['slides'][ $i ] : array();
						$output['slides'][] = array(
							'image' => esc_url_raw( $slide['image'] ?? '' ),
							'url'   => esc_url_raw( $slide['url'] ?? '' ),
						);
					}
					return $output;
				},
			)
		);
	}
);
