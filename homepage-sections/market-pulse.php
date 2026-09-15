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

// Every figure here (including NGN/USD) is a plain manually-entered
// value now — no live feed. See addon.php's docblock for why that was
// removed (a real contributing cause of intermittent server 502/504s).
$cells = array();
foreach ( $state['items'] as $item ) {
	if ( '' === $item['value'] ) {
		continue;
	}

	$cells[] = array(
		'id'          => $item['id'],
		'label'       => $item['label'],
		'value'       => $item['value'],
		'note'        => $item['note'],
		'note_type'   => $item['note_type'],
		'description' => $item['description'] ?? '',
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
 * @param array<int, array{id: string, label: string, value: string, note: string, note_type: string, description: string}> $cells
 */
$bday_market_pulse_render_cells = function ( array $cells, bool $hidden = false ): void {
	foreach ( $cells as $cell ) {
		// Mobile-app parity (MarketTickerStrip.tsx): a percentage note reads green when it's a
		// gain, red when it's a decline — not the old red-for-positive/grey-for-negative scheme,
		// which was actually backwards from the reader's-eye-view convention the app already
		// established. A text note (e.g. "July est.", "Held") is never color-coded this way even
		// if it happens to start with "+" or "-" — note_type decides that, not a string sniff,
		// since notes are admin-typed free text.
		$is_percent = 'percent' === $cell['note_type'];
		$is_up      = $is_percent && 0 !== strpos( trim( $cell['note'] ), '-' );
		$note_class = ! $is_percent ? 'bday-rd-market-pulse__note--faint' : ( $is_up ? 'bday-rd-market-pulse__note--up' : 'bday-rd-market-pulse__note--down' );
		$has_detail = ! $hidden && '' !== $cell['description'];
		$tag        = $has_detail ? 'button' : 'div';
		?>
		<<?php echo esc_html( $tag ); ?>
			class="bday-rd-market-pulse__cell<?php echo $has_detail ? ' bday-rd-market-pulse__cell--clickable' : ''; ?>"
			<?php echo $hidden ? ' aria-hidden="true" tabindex="-1"' : ''; ?>
			<?php if ( $has_detail ) : ?>
				type="button"
				data-bd-pulse-id="<?php echo esc_attr( $cell['id'] ); ?>"
				data-bd-pulse-label="<?php echo esc_attr( $cell['label'] ); ?>"
				data-bd-pulse-value="<?php echo esc_attr( $cell['value'] ); ?>"
				data-bd-pulse-description="<?php echo esc_attr( $cell['description'] ); ?>"
				aria-haspopup="dialog"
			<?php endif; ?>
		>
			<span class="bday-rd-market-pulse__label-row">
				<span class="bday-rd-kicker bday-rd-kicker--faint"><?php echo esc_html( $cell['label'] ); ?></span>
			</span>
			<span class="bday-rd-market-pulse__value"><?php echo esc_html( $cell['value'] ); ?></span>
			<?php if ( '' !== $cell['note'] ) : ?>
				<span class="bday-rd-kicker <?php echo esc_attr( $note_class ); ?>">
					<?php echo $is_percent ? ( $is_up ? '&#9650; ' : '&#9660; ' ) : ''; ?><?php echo esc_html( $cell['note'] ); ?>
				</span>
			<?php endif; ?>
		</<?php echo esc_html( $tag ); ?>>
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

<div class="bday-market-pulse-popup" id="bd-market-pulse-popup" role="dialog" aria-modal="true" aria-labelledby="bd-market-pulse-popup-title" hidden>
	<div class="bday-market-pulse-popup__card">
		<button type="button" class="bday-market-pulse-popup__close" data-bd-pulse-popup-close aria-label="Close">&times;</button>
		<span class="bday-rd-kicker bday-rd-kicker--faint" data-bd-pulse-popup-title id="bd-market-pulse-popup-title"></span>
		<span class="bday-market-pulse-popup__value" data-bd-pulse-popup-value></span>
		<p class="bday-market-pulse-popup__body" data-bd-pulse-popup-body></p>
	</div>
</div>
