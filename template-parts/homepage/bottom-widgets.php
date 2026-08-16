<?php
/**
 * Bottom-of-homepage content rows: magazine (weekender/womens-hub/reports
 * teasers), today's paper + today's cartoon, upcoming events, then a hook
 * point for addon-owned modules (Phase 4: promo banners, YouTube Shorts
 * rail). Each row is its own cached query — small enough not to warrant
 * separate add-ons of their own, but each is independently toggleable
 * (bday_addon_homepage_modules option, registered by
 * addons/homepage-modules/addon.php's settings schema) per the roadmap's
 * "each independently toggleable" requirement — "dead air" when off, not
 * an empty section, same posture as bday-live's hero embed toggle.
 *
 * The video row and the single-episode podcast card that used to live
 * here both moved as part of the WSJ-layout homepage adoption: video is
 * now its own template-parts/homepage/video-row.php (independently
 * positioned), and the podcast card was superseded by the multi-episode
 * podcast-carousel.php. Toon of the Day, no longer paired with a podcast
 * card, now pairs with Today's Paper instead.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// See template-parts/homepage/hero.php's comment for why this is needed.
$data = $args['data'] ?? array();

$modules = wp_parse_args(
	get_option( 'bday_addon_homepage_modules', array() ),
	array(
		'enable_magazine_row'     => true,
		'enable_todays_paper'     => true,
		'enable_toon_podcast_row' => true,
		'enable_events_row'       => true,
	)
);

$events = $modules['enable_events_row']
	? bday_get_posts( array( 'post_type' => 'events', 'numberposts' => 3, 'cache_namespace' => 'homepage' ) )
	: array();
$cartoon_of_day = ( $modules['enable_toon_podcast_row'] && post_type_exists( 'cartoons' ) )
	? bday_get_posts( array( 'post_type' => 'cartoons', 'numberposts' => 1, 'cache_namespace' => 'homepage' ) )
	: array();
?>

<?php if ( $modules['enable_magazine_row'] ) : ?>
<section class="bday-magazine-row">
	<div class="bday-container bday-card-grid">
		<?php foreach ( array(
			'weekender'  => $data['weekender'][0] ?? null,
			'womens_hub' => $data['womens_hub'][0] ?? null,
			'reports'    => $data['reports'][0] ?? null,
		) as $slug => $post ) :
			if ( ! $post ) continue;
			echo bday_card_html( $post, array( 'size' => 'pdf_thumbnail' ) );
		endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php if ( $modules['enable_todays_paper'] || ! empty( $cartoon_of_day ) ) : ?>
<section class="bday-paper-toon-row">
	<div class="bday-container bday-two-col">
		<?php if ( $modules['enable_todays_paper'] ) : ?>
			<div class="bday-todays-paper">
				<h2 class="bday-eyebrow">Today's Paper</h2>
				<p>The full print edition, laid out exactly as it appeared today — read it page by page or download the PDF.</p>
				<a href="<?php echo esc_url( bday_epaper_url() ); ?>" class="bday-btn-link">Read today's edition</a>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $cartoon_of_day ) ) :
			$toon = $cartoon_of_day[0];
			?>
			<div class="bday-toon-card">
				<h2 class="bday-eyebrow">Toon of the Day</h2>
				<a href="<?php echo esc_url( get_permalink( $toon ) ); ?>" class="bday-toon-card__frame">
					<?php echo bday_get_thumbnail( $toon->ID, 'top_story' ); ?>
					<span class="bday-toon-card__pin" aria-hidden="true"></span>
				</a>
				<div class="bday-toon-card__caption">
					<p><?php echo esc_html( get_the_title( $toon ) ); ?></p>
					<a href="<?php echo esc_url( get_post_type_archive_link( 'cartoons' ) ); ?>" class="bday-btn-link">See past editions</a>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>
<?php endif; ?>

<?php if ( ! empty( $events ) ) : ?>
<section class="bday-events-row">
	<div class="bday-container bday-card-grid">
		<?php foreach ( $events as $event ) : ?>
			<article class="bday-event-card">
				<a href="<?php echo esc_url( get_permalink( $event ) ); ?>">
					<?php echo bday_get_thumbnail( $event->ID, 'medium_rectangle' ); ?>
					<h4><?php echo esc_html( get_the_title( $event ) ); ?></h4>
					<span><?php echo esc_html( get_post_meta( $event->ID, '_bday_event_date', true ) ); ?></span>
				</a>
			</article>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php
/**
 * Genuinely new Phase 4 modules (promo banners, YouTube Shorts rail) live
 * entirely in addons/homepage-modules/ rather than inline here — unlike
 * the sections above, there's no existing content/query for them to
 * extend, so they're addon-owned from the start per the loader's normal
 * convention, off by default until an editor configures them.
 */
do_action( 'bday_homepage_after_bottom_widgets' );
?>
