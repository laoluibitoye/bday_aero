<?php
/**
 * Single event page — WordPress's native single-{post_type}.php convention
 * for the 'events' CPT (addons/events/). Previously missing entirely, so an
 * event's own permalink fell through to the generic post template and
 * showed nothing but the title — none of the venue/date/time/link fields
 * an editor fills in via the metabox, which until now only ever surfaced
 * in the homepage widget's excerpt (homepage-sections/events.php).
 *
 * Not gated (bday_aero_gate_content()) — the CPT has no 'editor' support
 * (title/thumbnail/excerpt only), so there's no long-form body to preview
 * or lock, matching how the homepage widget already treats these as open
 * listings, not premium editorial content.
 */
get_header();

if ( have_posts() ) :
	the_post();
	$post_id  = get_the_ID();
	$venue    = get_post_meta( $post_id, '_bday_event_venue', true );
	$link     = get_post_meta( $post_id, '_bday_event_link', true );
	$raw_date = get_post_meta( $post_id, '_bday_event_date', true );
	$time     = get_post_meta( $post_id, '_bday_event_time', true );

	// Same opportunistic free-text date parsing homepage-sections/events.php
	// uses — _bday_event_date has no date picker, so it's shown as typed
	// when it isn't a recognisable date rather than guessing.
	$timestamp  = $raw_date ? strtotime( $raw_date ) : false;
	$date_label = $timestamp ? date_i18n( 'l, F j, Y', $timestamp ) : $raw_date;
	?>
	<section id="event-single" class="bday-container bday-two-col">
		<main class="bday-article-main">
			<h1 class="post-title"><?php the_title(); ?></h1>
			<div class="bday-byline">
				<?php if ( $date_label ) : ?><span><?php echo esc_html( $date_label ); ?></span><?php endif; ?>
				<?php if ( $time ) : ?><span><?php echo esc_html( $time ); ?></span><?php endif; ?>
				<?php if ( $venue ) : ?><span><?php echo esc_html( $venue ); ?></span><?php endif; ?>
			</div>

			<article>
				<?php if ( has_post_thumbnail() ) : ?>
					<figure><?php echo bday_get_thumbnail( $post_id, 'featured', 'post-thumbnail' ); ?></figure>
				<?php endif; ?>

				<?php if ( has_excerpt() ) : ?>
					<div class="post-content"><?php the_excerpt(); ?></div>
				<?php endif; ?>

				<?php if ( $link ) : ?>
					<p><a href="<?php echo esc_url( $link ); ?>" class="bday-rd-btn bday-rd-btn--solid" target="_blank" rel="noopener noreferrer">Register</a></p>
				<?php endif; ?>

				<?php bday_ad_zone( 'below_article_recirculation', get_post() ); ?>

				<?php
				$more_events = bday_get_posts(
					array(
						'post_type'      => 'events',
						'post__not_in'   => array( $post_id ),
						'numberposts'    => 3,
						'cache_namespace' => 'events',
					)
				);
				if ( ! empty( $more_events ) ) :
					?>
					<div class="bday-ymal">
						<h2 class="bday-section-heading">More upcoming events</h2>
						<div class="bday-card-grid">
							<?php foreach ( $more_events as $event ) : ?>
								<?php echo bday_card_html( $event ); ?>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<p><a href="<?php echo esc_url( (string) get_post_type_archive_link( 'events' ) ); ?>">All events →</a></p>
			</article>
		</main>

		<aside class="bday-sidebar desktop-only">
			<?php if ( is_active_sidebar( 'page_sidebar' ) ) : ?>
				<?php dynamic_sidebar( 'page_sidebar' ); ?>
			<?php endif; ?>
			<?php bday_ad_zone( 'sidebar', get_post() ); ?>
		</aside>
	</section>
	<?php
endif;

get_footer();
