<?php
/**
 * Section Name: Market Pulse
 * Section Slug: market-pulse
 * Description: NGX All-Share, Naira/USD, Brent Crude, inflation, MPR, and FX reserves — manually entered under Appearance > BusinessDay Theme > Market Pulse.
 * Default Enabled: yes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pulse = get_option( 'bday_market_pulse', array() );
$pulse = is_array( $pulse ) ? $pulse : array();

// Naira/USD is live where possible (open.er-api.com, refreshed by
// WP-Cron — includes/live-feed.php) and only falls back to the manually
// entered value if the feed hasn't returned anything yet.
$naira_live  = function_exists( 'bday_market_pulse_naira_live' ) ? bday_market_pulse_naira_live() : null;
$naira_value = $naira_live['value'] ?? ( $pulse['naira_value'] ?? '' );
$naira_note  = $naira_live ? $naira_live['change'] : ( $pulse['naira_change'] ?? '' );
$naira_is_live = null !== $naira_live;

$cells = array_values(
	array_filter(
		array(
			array( 'label' => 'NGX All-Share', 'value' => $pulse['ngx_value'] ?? '', 'note' => $pulse['ngx_change'] ?? '' ),
			array( 'label' => 'Naira / USD', 'value' => $naira_value, 'note' => $naira_note, 'live' => $naira_is_live ),
			array( 'label' => 'Brent Crude', 'value' => $pulse['brent_value'] ?? '', 'note' => $pulse['brent_change'] ?? '' ),
			array( 'label' => 'Inflation', 'value' => $pulse['inflation_value'] ?? '', 'note' => $pulse['inflation_note'] ?? '' ),
			array( 'label' => 'MPR', 'value' => $pulse['mpr_value'] ?? '', 'note' => $pulse['mpr_note'] ?? '' ),
			array( 'label' => 'Reserves', 'value' => $pulse['reserves_value'] ?? '', 'note' => $pulse['reserves_change'] ?? '' ),
		),
		static fn( array $c ): bool => '' !== $c['value']
	)
);
if ( empty( $cells ) ) {
	return;
}
?>
<section class="bday-rd-market-pulse" data-screen-label="Market pulse">
	<div class="bday-container bday-rd-market-pulse__grid">
		<?php foreach ( $cells as $cell ) :
				// A positive change (starts with "+") reads in the accent
				// red, matching the source design exactly (+0.82%, +1.24%,
				// +0.4% are red; -0.31% and the static "July est."/"Held"
				// notes stay the muted grey every other kicker uses).
				$note_class = 0 === strpos( $cell['note'], '+' ) ? 'bday-rd-kicker--accent' : 'bday-rd-kicker--faint';
				?>
			<div class="bday-rd-market-pulse__cell">
				<span class="bday-rd-market-pulse__label-row">
					<span class="bday-rd-kicker bday-rd-kicker--faint"><?php echo esc_html( $cell['label'] ); ?></span>
					<?php if ( ! empty( $cell['live'] ) ) : ?><span class="bday-rd-market-pulse__live" title="Refreshed automatically">LIVE</span><?php endif; ?>
				</span>
				<span class="bday-rd-market-pulse__value"><?php echo esc_html( $cell['value'] ); ?></span>
				<?php if ( '' !== $cell['note'] ) : ?><span class="bday-rd-kicker <?php echo esc_attr( $note_class ); ?>"><?php echo esc_html( $cell['note'] ); ?></span><?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
</section>
