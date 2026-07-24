<?php
/**
 * Addon Name: Premium Leaderboard
 * Addon Slug: premium-leaderboard
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
			'option'    => 'bday_addon_premium_leaderboard',
			'render'    => 'bday_premium_leaderboard_settings_tab',
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
		<tr><th scope="row">Number of items</th><td><input type="number" name="bday_addon_premium_leaderboard[count]" value="<?php echo esc_attr( (string) $count ); ?>" min="1" max="10" /></td></tr>
		<tr><th scope="row">Slider speed (ms)</th><td><input type="number" name="bday_addon_premium_leaderboard[slider_speed]" value="<?php echo esc_attr( (string) ( $settings['slider_speed'] ?? 20000 ) ); ?>" min="1000" /></td></tr>
	</tbody></table>
	<?php for ( $i = 0; $i < $count; $i++ ) :
		$slide = $slides[ $i ] ?? array();
		?>
		<h4>Slide <?php echo (int) ( $i + 1 ); ?></h4>
		<table class="form-table" role="presentation"><tbody>
			<tr><th scope="row">Image URL</th><td><input type="text" name="bday_addon_premium_leaderboard[slides][<?php echo $i; ?>][image]" value="<?php echo esc_attr( $slide['image'] ?? '' ); ?>" class="regular-text" /></td></tr>
			<tr><th scope="row">Landing URL</th><td><input type="text" name="bday_addon_premium_leaderboard[slides][<?php echo $i; ?>][url]" value="<?php echo esc_attr( $slide['url'] ?? '' ); ?>" class="regular-text" /></td></tr>
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
