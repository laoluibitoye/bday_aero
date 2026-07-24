<?php
/**
 * Bottom-of-homepage content rows: video, magazine (weekender/womens-hub/
 * reports/e-paper teasers), today's cartoon + podcast, upcoming events.
 * Each row is its own cached query — small enough not to warrant separate
 * add-ons, but still routed through bday_get_posts() like everything else.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$videos = bday_get_posts( array( 'category_name' => 'top-video', 'numberposts' => 8, 'cache_namespace' => 'homepage' ) );
$events = bday_get_posts( array( 'post_type' => 'events', 'numberposts' => 3, 'cache_namespace' => 'homepage' ) );
$cartoon_of_day = post_type_exists( 'cartoons' )
	? bday_get_posts( array( 'post_type' => 'cartoons', 'numberposts' => 1, 'cache_namespace' => 'homepage' ) )
	: array();
?>

<?php if ( ! empty( $videos ) ) : ?>
<section class="bday-video-row">
	<div class="bday-container">
		<h2 class="bday-section-heading bday-section-heading--inverse">BD TV</h2>
		<div class="bday-scroll-row">
			<?php foreach ( $videos as $post ) : ?>
				<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="bday-video-card">
					<?php echo bday_get_thumbnail( $post->ID, 'medium_rectangle' ); ?>
					<h4><?php echo esc_html( get_the_title( $post ) ); ?></h4>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="bday-magazine-row">
	<div class="bday-container bday-card-grid">
		<?php foreach ( array(
			'weekender'  => $data['weekender'][0] ?? null,
			'womens_hub' => $data['womens_hub'][0] ?? null,
			'reports'    => $data['reports'][0] ?? null,
		) as $slug => $post ) :
			if ( ! $post ) continue;
			?>
			<article class="bday-card">
				<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="bday-card__media"><?php echo bday_get_thumbnail( $post->ID, 'pdf_thumbnail' ); ?></a>
				<h3 class="bday-card__title"><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></h3>
			</article>
		<?php endforeach; ?>
	</div>
</section>

<?php if ( ! empty( $cartoon_of_day ) ) : ?>
<section class="bday-toon-podcast-row">
	<div class="bday-container bday-two-col">
		<div class="bday-toon">
			<h2 class="bday-eyebrow">Toon of the Day</h2>
			<a href="<?php echo esc_url( get_permalink( $cartoon_of_day[0] ) ); ?>"><?php echo bday_get_thumbnail( $cartoon_of_day[0]->ID, 'top_story' ); ?></a>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'cartoons' ) ); ?>" class="bday-btn-link">See past editions</a>
		</div>
		<div class="bday-podcast">
			<h2 class="bday-eyebrow">Podcast</h2>
			<iframe width="100%" height="300" scrolling="no" frameborder="no" loading="lazy" src="https://w.soundcloud.com/player/?url=https%3A//api.soundcloud.com/users/619290771&color=%23ff5500&auto_play=false&hide_related=false&show_comments=true&show_user=true&show_reposts=false&show_teaser=true"></iframe>
		</div>
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
