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
				'group'     => 'editorial',
			'option'    => 'bday_addon_live_match',
			'intro'     => 'A live sports score/commentary strip for an in-progress match — most relevant on a major football day. Scores refresh automatically on the interval set below rather than requiring a manual update.',
			'about'     => '<p>Renders into the same header ticker zone the breaking-news ticker uses, so only one of the two typically shows at a time in practice, depending on which is enabled.</p>',
			'fields'    => array(
				array( 'key' => 'enabled', 'type' => 'checkbox', 'label' => 'Enable ticker', 'default' => false, 'description' => 'Shows the live-match strip. Turn on before kickoff, off once there\'s nothing live to show.' ),
				array( 'key' => 'cache_ttl', 'type' => 'number', 'label' => 'Cache TTL (seconds)', 'default' => 60, 'min' => 30, 'description' => 'How often scores refresh from the data source. Lower is more up-to-the-minute but calls the source more often; 60 seconds is a reasonable default for a live match.' ),
				array( 'key' => 'max_matches', 'type' => 'number', 'label' => 'Max matches shown', 'default' => 5, 'min' => 1, 'description' => 'How many concurrent matches the strip can display at once, on a busy fixture day.' ),
			),
		);
		return $schema;
	}
);
