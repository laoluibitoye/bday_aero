<?php
/**
 * Addon Name: Breaking Ticker
 * Addon Slug: breaking-ticker
 * Description: Scrolling Top News headline ticker below the header.
 * Cache Namespace: breaking_ticker
 * Settings Tab: Breaking Ticker
 * Default: on
 *
 * A scrolling latest-headlines strip below the nav (reader-requested,
 * "as seen on businessday.ng" — their live markup didn't actually expose
 * a ticker to a static fetch, likely script-injected or page-specific, so
 * this builds the standard scrolling-marquee pattern in this theme's own
 * visual language rather than guessing at their exact source). Hooks the
 * existing `bday_header_ticker_zone` action (header.php) — a second,
 * independent listener alongside the optional TradingView FX ticker, same
 * "dead air unless configured" convention every zone hook here follows.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'bday_header_ticker_zone',
	static function (): void {
		$settings = get_option( 'bday_addon_breaking_ticker', array() );
		if ( ! isset( $settings['enabled'] ) || ! $settings['enabled'] ) {
			return;
		}
		$count = ! empty( $settings['count'] ) ? (int) $settings['count'] : 8;

		$posts = bday_get_posts(
			array(
				'numberposts'     => $count,
				'cache_namespace' => 'breaking_ticker',
				'cache_ttl'       => 120,
			)
		);
		if ( empty( $posts ) ) {
			return;
		}
		?>
		<div class="bday-ticker" data-bd-ticker aria-label="Top News headlines">
			<span class="bday-ticker__tag">Top News</span>
			<div class="bday-ticker__track-wrap">
				<ul class="bday-ticker__track">
					<?php foreach ( $posts as $post ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></li>
					<?php endforeach; ?>
				</ul>
				<?php
				/**
				 * Duplicated so the CSS animation can scroll from 0% to
				 * -50% and loop seamlessly — a single copy would show a
				 * visible jump/gap at the reset point.
				 */
				?>
				<ul class="bday-ticker__track" aria-hidden="true">
					<?php foreach ( $posts as $post ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $post ) ); ?>" tabindex="-1"><?php echo esc_html( get_the_title( $post ) ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}
);

add_filter(
	'bday_settings_schema',
	static function ( array $schema ): array {
		$schema['breaking-ticker'] = array(
			'tab_label' => 'Breaking Ticker',
				'group'     => 'editorial',
			'option'    => 'bday_addon_breaking_ticker',
			'intro'     => 'The scrolling "Top News" strip below the main navigation. It always shows the site\'s most recent posts automatically — there\'s no content to curate here, only how many headlines are pulled in and whether the strip shows at all.',
			'about'     => '<p>Pulls the N most recent published posts site-wide (no category filter) and scrolls them right to left in a continuous loop. Pauses automatically when a reader hovers or focuses it, and disables the animation entirely for readers with "reduce motion" set in their OS.</p>',
			'fields'    => array(
				array( 'key' => 'enabled', 'type' => 'checkbox', 'label' => 'Enable', 'default' => true, 'description' => 'Turns the whole strip on or off. Off removes it from the page entirely — no empty gap left behind.' ),
				array( 'key' => 'count', 'type' => 'number', 'label' => 'Number of headlines', 'default' => 8, 'description' => 'How many of the most recent posts to include in the loop. More headlines means a longer scroll before it repeats; fewer means readers see the same handful more often.' ),
			),
		);
		return $schema;
	}
);
