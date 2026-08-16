<?php
/**
 * Podcast episode carousel (WSJ-layout adoption) — replaces the old
 * single-episode card that used to pair with Toon of the Day in
 * bottom-widgets.php. Each card is the same play-facade pattern that
 * single card used (art + play button, click lazily injects the Spotify
 * iframe rather than paying its cost for every homepage visit) —
 * script.js's bdayInitPodcastFacade() was widened from querySelector to
 * querySelectorAll specifically so it binds every card here, not just one.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// See hero.php's comment for why this normalization is needed — this
// WordPress core doesn't extract get_template_part()'s $args for us.
$data     = $args['data'] ?? array();
$episodes = $data['podcasts'] ?? array();
if ( empty( $episodes ) ) {
	return;
}
?>
<section class="bday-podcast-carousel">
	<div class="bday-container">
		<h2 class="bday-section-heading"><a href="<?php echo esc_url( get_post_type_archive_link( 'podcast' ) ); ?>">Podcasts</a></h2>
		<div class="bday-scroll-row">
			<?php foreach ( $episodes as $episode ) :
				$episode_id           = $episode->ID;
				$episode_spotify_url  = get_post_meta( $episode_id, '_podcast_spotify_url', true );
				$episode_embed        = $episode_spotify_url ? bday_podcast_spotify_embed_url( $episode_spotify_url ) : '';
				$episode_show         = get_post_meta( $episode_id, '_podcast_show_name', true );
				$episode_length       = get_post_meta( $episode_id, '_podcast_duration', true );
				?>
				<div class="bday-podcast-carousel__card" data-bd-podcast-facade <?php echo $episode_embed ? 'data-podcast-embed="' . esc_attr( $episode_embed ) . '"' : ''; ?>>
					<div class="bday-podcast-carousel__art">
						<?php echo bday_get_thumbnail( $episode_id, 'medium_rectangle', 'post-thumbnail' ); ?>
						<?php if ( $episode_embed ) : ?>
							<button type="button" class="bday-podcast-carousel__play" data-bd-podcast-play aria-label="Play episode: <?php echo esc_attr( get_the_title( $episode ) ); ?>">
								<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M8 5v14l11-7z"/></svg>
							</button>
						<?php endif; ?>
					</div>
					<a href="<?php echo esc_url( get_permalink( $episode ) ); ?>" class="bday-podcast-carousel__title"><?php echo esc_html( get_the_title( $episode ) ); ?></a>
					<div class="bday-podcast-carousel__meta">
						<?php if ( $episode_show ) : ?><span><?php echo esc_html( $episode_show ); ?></span><?php endif; ?>
						<?php if ( $episode_length ) : ?><span><?php echo esc_html( $episode_length ); ?></span><?php endif; ?>
					</div>
					<div class="bday-podcast-carousel__player" data-bd-podcast-player hidden></div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
