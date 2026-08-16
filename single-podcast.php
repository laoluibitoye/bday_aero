<?php
/**
 * Single podcast episode — WordPress's native single-{post_type}.php
 * convention for the 'podcast' CPT (addons/podcasts/). Mirrors
 * template-parts/single-default.php's structure (byline, gated content,
 * related/recirculation) but swaps the article body's role for an audio
 * player + show notes.
 */
get_header();

if ( have_posts() ) :
	the_post();
	$post_id       = get_the_ID();
	$spotify_url   = get_post_meta( $post_id, '_podcast_spotify_url', true );
	$spotify_embed = $spotify_url ? bday_podcast_spotify_embed_url( $spotify_url ) : '';
	$audio_url     = get_post_meta( $post_id, '_podcast_audio_url', true );
	$show_name     = get_post_meta( $post_id, '_podcast_show_name', true );
	$duration      = get_post_meta( $post_id, '_podcast_duration', true );
	$is_gated      = bday_aero_is_post_gated( $post_id );
	?>
	<section id="podcast-single" class="bday-container bday-two-col">
		<main class="bday-article-main">
			<h1 class="post-title"><?php the_title(); ?></h1>
			<div class="bday-byline">
				<?php if ( $show_name ) : ?><span><?php echo esc_html( $show_name ); ?></span><?php endif; ?>
				<span><?php the_date(); ?></span>
				<?php if ( $duration ) : ?><span><?php echo esc_html( $duration ); ?></span><?php endif; ?>
			</div>

			<article>
				<figure><?php echo bday_get_thumbnail( $post_id, 'featured', 'post-thumbnail' ); ?></figure>

				<?php
				/**
				 * The player is gated, but not through gate_content()'s
				 * word-count preview — found live: that mode strips all
				 * HTML from whatever $content it's given and shows the
				 * *text remaining after stripping* as the "preview," which
				 * for an <audio> tag is its fallback text ("Your browser
				 * does not support…") rather than anything meaningful, and
				 * the surrounding copy ("Subscribe to keep reading this
				 * article") is worded for text, not audio. A soft preview
				 * of an audio player doesn't have a sensible meaning
				 * anyway, so a gated episode gets the lock/mount markup
				 * only (passing empty content), never the raw player.
				 */
				if ( $is_gated ) {
					echo bday_aero_gate_content( $post_id, '' );
				} elseif ( $spotify_embed ) {
					printf(
						'<div class="bday-podcast-player bday-podcast-player--spotify"><iframe src="%s" width="100%%" height="232" frameborder="0" allowfullscreen loading="lazy" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" title="Spotify episode player"></iframe></div>',
						esc_url( $spotify_embed )
					);
				} elseif ( $audio_url ) {
					printf(
						'<audio controls preload="none" class="bday-podcast-player" src="%s">Your browser does not support the audio element.</audio>',
						esc_url( $audio_url )
					);
				}
				?>

				<?php echo bday_social_share_html( $post_id ); ?>
				<?php if ( ! $is_gated ) : ?>
					<?php bday_ad_zone( 'in_article_after_p2', get_post() ); ?>
				<?php endif; ?>

				<div class="post-content">
					<?php echo bday_aero_gate_content( $post_id, apply_filters( 'the_content', get_the_content() ) ); ?>

					<?php if ( ! $is_gated ) : ?>
						<?php bday_ad_zone( 'below_share_buttons', get_post() ); ?>
						<?php echo bday_social_share_html( $post_id ); ?>
					<?php endif; ?>
				</div>

				<?php if ( ! $is_gated ) :
					$categories = get_the_category( $post_id );
					$primary_category = $categories[0] ?? null;
					if ( $primary_category ) :
						$more_episodes = bday_get_posts( array( 'post_type' => 'podcast', 'category_name' => $primary_category->slug, 'post__not_in' => array( $post_id ), 'numberposts' => 3, 'cache_namespace' => 'podcast' ) );
						if ( ! empty( $more_episodes ) ) :
							?>
							<div class="bday-ymal">
								<h2 class="bday-section-heading">More Episodes</h2>
								<div class="bday-card-grid">
									<?php foreach ( $more_episodes as $ep ) : ?>
										<?php echo bday_card_html( $ep ); ?>
									<?php endforeach; ?>
								</div>
							</div>
							<?php
						endif;
					endif;
					bday_ad_zone( 'below_article_recirculation', get_post() );
				endif; ?>
			</article>
		</main>

		<aside class="bday-sidebar desktop-only">
			<?php if ( is_active_sidebar( 'page_sidebar' ) ) : ?>
				<?php dynamic_sidebar( 'page_sidebar' ); ?>
			<?php endif; ?>
			<?php if ( ! $is_gated ) : ?>
				<?php bday_ad_zone( 'sidebar', get_post() ); ?>
			<?php endif; ?>
		</aside>
	</section>
	<?php
endif;

get_footer();
