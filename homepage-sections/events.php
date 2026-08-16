<?php
/**
 * Section Name: BD Conferences
 * Section Slug: events
 * Description: Upcoming events (the "events" CPT) as a list with venue and date. Gated by Homepage Modules' "Events row" toggle, same as the classic homepage.
 * Default Enabled: yes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$modules = wp_parse_args( get_option( 'bday_addon_homepage_modules', array() ), array( 'enable_events_row' => true ) );
if ( empty( $modules['enable_events_row'] ) ) {
	return;
}

$data   = $args['data'] ?? array();
$events = $data['rd_events'] ?? array();
if ( empty( $events ) ) {
	return;
}
?>
<section class="bday-rd-events" data-screen-label="Events">
	<div class="bday-container bday-rd-events__grid">
		<div class="bday-rd-events__copy">
			<span class="bday-rd-kicker bday-rd-kicker--accent">BD Conferences</span>
			<h2>Upcoming events</h2>
			<p>Events across industries and sectors, built to surround you with information, inspiration and a network that sharpens the next business decision.</p>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'events' ) ); ?>" class="bday-rd-btn bday-rd-btn--solid">Explore all events</a>
		</div>
		<div class="bday-rd-events__list">
			<?php foreach ( $events as $event ) :
				$venue     = get_post_meta( $event->ID, '_bday_event_venue', true );
				$raw_date  = get_post_meta( $event->ID, '_bday_event_date', true );
				$link      = get_post_meta( $event->ID, '_bday_event_link', true ) ?: get_permalink( $event );
				// _bday_event_date is free-text (metabox.php has no date
				// picker) — parsed opportunistically for the design's
				// stacked month/day treatment; falls back to the raw
				// string in the day slot (no month line) when it isn't a
				// recognisable date, same graceful-degradation posture as
				// every other free-text field in this theme.
				$timestamp = $raw_date ? strtotime( $raw_date ) : strtotime( $event->post_date );
				$month     = $timestamp ? date_i18n( 'M', $timestamp ) : '';
				$day       = $timestamp ? date_i18n( 'j', $timestamp ) : ( $raw_date ?: bday_format_date( $event->post_date ) );
				?>
				<a href="<?php echo esc_url( $link ); ?>" class="bday-rd-events__row">
					<span class="bday-rd-events__date">
						<?php if ( $month ) : ?><span class="bday-rd-events__month"><?php echo esc_html( $month ); ?></span><?php endif; ?>
						<span class="bday-rd-events__day"><?php echo esc_html( $day ); ?></span>
					</span>
					<span class="bday-rd-events__body">
						<span class="bday-rd-events__title"><?php echo esc_html( get_the_title( $event ) ); ?></span>
						<?php if ( $venue ) : ?><span class="bday-rd-kicker bday-rd-kicker--faint"><?php echo esc_html( $venue ); ?></span><?php endif; ?>
					</span>
					<span class="bday-rd-kicker bday-rd-kicker--accent">Register</span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
