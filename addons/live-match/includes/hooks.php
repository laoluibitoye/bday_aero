<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'bday_header_ticker_zone',
	static function (): void {
		$matches = bday_live_match_get_current();
		if ( empty( $matches ) ) {
			return;
		}
		?>
		<div class="bday-live-ticker">
			<span class="bday-live-ticker__badge">LIVE SCORE</span>
			<div class="bday-live-ticker__scroll">
				<?php foreach ( $matches as $match ) : ?>
					<span class="bday-live-ticker__match">
						<?php echo esc_html( get_post_meta( $match->ID, 'home_team', true ) ); ?>
						<strong><?php echo esc_html( get_post_meta( $match->ID, 'home_team_score', true ) ); ?></strong>
						vs
						<strong><?php echo esc_html( get_post_meta( $match->ID, 'away_team_score', true ) ); ?></strong>
						<?php echo esc_html( get_post_meta( $match->ID, 'away_team', true ) ); ?>
					</span>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
);

add_filter(
	'bday_settings_schema',
	static function ( array $schema ): array {
		$schema['live-match'] = array(
			'tab_label' => 'Live Match',
			'option'    => 'bday_addon_live_match',
			'fields'    => array(
				array( 'key' => 'enabled', 'type' => 'checkbox', 'label' => 'Enable ticker', 'default' => false ),
				array( 'key' => 'cache_ttl', 'type' => 'number', 'label' => 'Cache TTL (seconds)', 'default' => 60, 'min' => 30 ),
				array( 'key' => 'max_matches', 'type' => 'number', 'label' => 'Max matches shown', 'default' => 5, 'min' => 1 ),
			),
		);
		return $schema;
	}
);
