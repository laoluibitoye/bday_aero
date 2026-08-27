<?php
/**
 * Single video — WordPress's native single-{post_type}.php convention for
 * the 'bday_video' CPT (addons/videos/). Mirrors single-podcast.php's
 * structure (byline, gated content, related/recirculation) but swaps the
 * audio player for a YouTube iframe.
 */
get_header();

if ( have_posts() ) :
	the_post();
	$post_id       = get_the_ID();
	$youtube_url   = get_post_meta( $post_id, '_video_youtube_url', true );
	$youtube_embed = $youtube_url ? bday_video_youtube_embed_url( $youtube_url ) : '';
	$is_gated      = bday_aero_is_post_gated( $post_id );
	?>
	<section id="video-single" class="bday-container bday-two-col">
		<main class="bday-article-main">
			<h1 class="post-title"><?php the_title(); ?></h1>
			<div class="bday-byline">
				<span><?php the_date(); ?></span>
			</div>

			<article>
				<?php
				/**
				 * Same reasoning as single-podcast.php: an iframe has no
				 * meaningful "text preview," so a gated video gets the
				 * lock/mount markup only (empty content passed in), never
				 * the raw embed.
				 */
				if ( $is_gated ) {
					echo bday_aero_gate_content( $post_id, '' );
				} elseif ( $youtube_embed ) {
					printf(
						'<div class="bday-video-embed"><iframe src="%s" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe></div>',
						esc_url( $youtube_embed )
					);
				} else {
					echo '<figure>' . bday_get_thumbnail( $post_id, 'featured', 'post-thumbnail' ) . '</figure>';
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
					$playlist_terms = wp_get_post_terms( $post_id, 'video_playlist' );
					$primary_playlist = ( ! is_wp_error( $playlist_terms ) && ! empty( $playlist_terms ) ) ? $playlist_terms[0] : null;
					if ( $primary_playlist ) :
						$more_videos = bday_get_posts( array( 'post_type' => 'bday_video', 'tax_query' => array( array( 'taxonomy' => 'video_playlist', 'field' => 'term_id', 'terms' => $primary_playlist->term_id ) ), 'post__not_in' => array( $post_id ), 'numberposts' => 3, 'cache_namespace' => 'videos' ) );
						if ( ! empty( $more_videos ) ) :
							?>
							<div class="bday-ymal">
								<h2 class="bday-section-heading">More Videos</h2>
								<div class="bday-card-grid">
									<?php foreach ( $more_videos as $vid ) : ?>
										<?php echo bday_card_html( $vid ); ?>
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
