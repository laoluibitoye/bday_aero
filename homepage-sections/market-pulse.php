<?php
/**
 * Section Name: Market Pulse
 * Section Slug: market-pulse
 * Description: An admin-editable, auto-scrolling market ticker — add, remove, and reorder figures under Appearance > BusinessDay Theme > Market Pulse.
 * Default Enabled: yes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pulse = get_option( 'bday_market_pulse', array() );
$pulse = is_array( $pulse ) ? $pulse : array();
$state = bday_market_pulse_normalize( $pulse );

// NGN/USD is live where possible (open.er-api.com, refreshed by
// WP-Cron — includes/live-feed.php) and only falls back to the manually
// entered value if the feed hasn't returned anything yet. Whichever row
// carries id 'ngn_usd' (there's normally exactly one, but nothing
// enforces that) gets the live value merged in.
$naira_live = function_exists( 'bday_market_pulse_naira_live' ) ? bday_market_pulse_naira_live() : null;

$cells = array();
foreach ( $state['items'] as $item ) {
	$value = $item['value'];
	$note  = $item['note'];
	$live  = false;

	if ( 'ngn_usd' === $item['id'] && $naira_live ) {
		$value = $naira_live['value'];
		$note  = $naira_live['change'];
		$live  = true;
	}

	if ( '' === $value ) {
		continue;
	}

	$cells[] = array(
		'label'     => $item['label'],
		'value'     => $value,
		'note'      => $note,
		'note_type' => $item['note_type'],
		'live'      => $live,
	);
}

if ( empty( $cells ) ) {
	return;
}

/**
 * Renders the cell list — called twice below (back-to-back) so the CSS
 * marquee animation can translateX exactly -50% and loop seamlessly with
 * no visible seam/reset jump, the standard duplicated-track marquee
 * technique. aria-hidden on the second copy: it's a purely visual repeat,
 * not new content a screen reader should announce again.
 *
 * A local closure, not a named function — this template part can be
 * included more than once per request (individual section preview), and
 * a bare `function` declaration here would fatal with "cannot redeclare"
 * the second time.
 *
 * @param array<int, array{label: string, value: string, note: string, note_type: string, live: bool}> $cells
 */
$bday_market_pulse_render_cells = function ( array $cells, bool $hidden = false ): void {
	foreach ( $cells as $cell ) {
		// A positive percentage change reads in the accent red, matching
		// the source design exactly (+0.82%, +1.24%, +0.4% are red; -0.31%
		// stays the muted grey every other kicker uses). A text note (e.g.
		// "July est.", "Held") is never color-coded this way even if it
		// happens to start with "+" — note_type decides that, not a string
		// sniff, now that notes are admin-typed free text.
		$note_class = 'percent' === $cell['note_type'] && 0 === strpos( $cell['note'], '+' )
			? 'bday-rd-kicker--accent'
			: 'bday-rd-kicker--faint';
		?>
		<div class="bday-rd-market-pulse__cell"<?php echo $hidden ? ' aria-hidden="true"' : ''; ?>>
			<span class="bday-rd-market-pulse__label-row">
				<span class="bday-rd-kicker bday-rd-kicker--faint"><?php echo esc_html( $cell['label'] ); ?></span>
				<?php if ( $cell['live'] ) : ?><span class="bday-rd-market-pulse__live" title="Refreshed automatically">LIVE</span><?php endif; ?>
			</span>
			<span class="bday-rd-market-pulse__value"><?php echo esc_html( $cell['value'] ); ?></span>
			<?php if ( '' !== $cell['note'] ) : ?><span class="bday-rd-kicker <?php echo esc_attr( $note_class ); ?>"><?php echo esc_html( $cell['note'] ); ?></span><?php endif; ?>
		</div>
		<?php
	}
};
?>
<section class="bday-rd-market-pulse" data-screen-label="Market pulse">
	<div class="bday-rd-market-pulse__viewport">
		<div class="bday-rd-market-pulse__track" style="--bday-market-pulse-duration: <?php echo esc_attr( (string) $state['scroll_seconds'] ); ?>s;">
			<div class="bday-rd-market-pulse__grid">
				<?php $bday_market_pulse_render_cells( $cells ); ?>
			</div>
			<div class="bday-rd-market-pulse__grid" aria-hidden="true">
				<?php $bday_market_pulse_render_cells( $cells, true ); ?>
			</div>
		</div>
	</div>
</section>
