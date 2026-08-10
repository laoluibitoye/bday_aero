<?php
/**
 * Standard article template. The previous version had a hardcoded, broken
 * FlashTalking iframe (unfilled ${GDPR}/[CACHEBUSTER] macros) and an
 * orphaned GAM div with no matching slot registration anywhere — neither
 * is carried forward. In-article ad placement now goes through the
 * ads-sharing-matrix's zone system instead of being hardcoded here.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id    = get_the_ID();
$categories = get_the_category( $post_id );
$primary_category = $categories[0] ?? null;
?>
<section id="article-page" class="bday-container bday-two-col">
	<main class="bday-article-main">
		<h1 class="post-title"><?php the_title(); ?></h1>
		<div class="bday-byline">
			<span><?php the_author_posts_link(); ?></span>
			<span><?php the_date(); ?></span>
		</div>

		<article>
			<?php if ( has_post_format( 'video' ) ) : ?>
				<div class="bday-video-embed">
					<iframe src="https://www.youtube.com/embed/<?php echo esc_attr( get_post_meta( $post_id, '_youtube_id', true ) ); ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
				</div>
			<?php else : ?>
				<figure><?php echo bday_get_thumbnail( $post_id, 'featured', 'post-thumbnail' ); ?></figure>
			<?php endif; ?>

			<?php echo bday_social_share_html( $post_id ); ?>
			<?php bday_ad_zone( 'in_article_after_p2', get_post() ); ?>

			<div class="post-content">
				<?php
				/**
				 * Explicit call, not a the_content filter — the native
				 * AeroPaywall add-on (addons/aero-paywall) truncates/locks
				 * this on a gated post; the related-content block right
				 * below is then also skipped, since splicing "Related
				 * News" into a 120-word teaser is exactly the layout
				 * hazard the old connector-plugin's own docs warned about.
				 * No-ops (returns $content unchanged) if the add-on isn't
				 * active for this request.
				 */
				echo bday_aero_gate_content( $post_id, apply_filters( 'the_content', get_the_content() ) );
				?>

				<?php if ( $primary_category && ! bday_aero_is_post_gated( $post_id ) ) :
					$tags = get_the_tags( $post_id );
					if ( ! empty( $tags ) ) :
						$tag_ids   = wp_list_pluck( $tags, 'term_id' );
						$read_also = bday_get_posts( array( 'tag__in' => $tag_ids, 'post__not_in' => array( $post_id ), 'numberposts' => 3, 'cache_namespace' => 'article' ) );
						if ( ! empty( $read_also ) ) :
							?>
							<div class="bday-read-also">
								<h4>Related News</h4>
								<ul>
									<?php foreach ( $read_also as $rp ) : ?>
										<li><a href="<?php echo esc_url( get_permalink( $rp ) ); ?>"><?php echo esc_html( get_the_title( $rp ) ); ?></a></li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif;
					endif;
				endif;
				?>

				<?php
				$author_bio = get_the_author_meta( 'description', get_post_field( 'post_author', $post_id ) );
				if ( $author_bio ) :
					?>
					<div class="bday-author-bio">
						<strong><?php the_author_posts_link(); ?></strong>
						<p><?php echo esc_html( $author_bio ); ?></p>
					</div>
				<?php endif; ?>

				<?php bday_ad_zone( 'below_share_buttons', get_post() ); ?>
				<?php echo bday_social_share_html( $post_id ); ?>

				<?php if ( is_active_sidebar( 'article_page_text_link' ) ) : ?>
					<?php dynamic_sidebar( 'article_page_text_link' ); ?>
				<?php endif; ?>
			</div>

			<?php if ( $primary_category ) :
				$ymal = bday_get_posts( array( 'category_name' => $primary_category->slug, 'post__not_in' => array( $post_id ), 'numberposts' => 3, 'cache_namespace' => 'article' ) );
				if ( ! empty( $ymal ) ) :
					?>
					<div class="bday-ymal">
						<h2 class="bday-section-heading">You Might Also Like</h2>
						<div class="bday-card-grid">
							<?php foreach ( $ymal as $rp ) : ?>
								<article class="bday-card">
									<a href="<?php echo esc_url( get_permalink( $rp ) ); ?>" class="bday-card__media"><?php echo bday_get_thumbnail( $rp->ID, 'medium_rectangle' ); ?></a>
									<h3 class="bday-card__title"><a href="<?php echo esc_url( get_permalink( $rp ) ); ?>"><?php echo esc_html( get_the_title( $rp ) ); ?></a></h3>
								</article>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif;
			endif;
			?>

			<?php bday_ad_zone( 'below_article_recirculation', get_post() ); ?>
		</article>
	</main>

	<aside class="bday-sidebar desktop-only">
		<?php if ( is_active_sidebar( 'page_sidebar' ) ) : ?>
			<?php dynamic_sidebar( 'page_sidebar' ); ?>
		<?php endif; ?>
		<?php bday_ad_zone( 'sidebar', get_post() ); ?>
	</aside>
</section>
