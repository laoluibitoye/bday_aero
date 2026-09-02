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
$read_time  = bday_estimate_read_time( $post_id );

/**
 * Phase 3 of the roadmap: reading progress, read time, table of contents.
 * ToC/heading-anchors only make sense against the real, ungated content —
 * skipped entirely on a gated post (bday_aero_is_post_gated()), same
 * "don't splice extra structure into a paywall teaser" reasoning the
 * existing Related-News block below already follows.
 */
$rendered_content = apply_filters( 'the_content', get_the_content() );
$toc = array();
if ( ! bday_aero_is_post_gated( $post_id ) ) {
	$heading_data      = bday_add_heading_anchors( $rendered_content );
	$rendered_content  = $heading_data['content'];
	$toc               = $heading_data['toc'];
}
$gated_content = bday_aero_gate_content( $post_id, $rendered_content );
?>
<div class="bday-reading-progress" data-bd-reading-progress aria-hidden="true"><span class="bday-reading-progress__bar"></span></div>
<section id="article-page" class="bday-container bday-two-col">
	<main class="bday-article-main">
		<?php
		/**
		 * Eyebrow category label above the headline (WSJ-style redesign,
		 * reader-requested) — the post's primary category, reusing
		 * $primary_category already computed above for the Related News
		 * block further down, not a second lookup.
		 */
		?>
		<?php if ( $primary_category ) : ?>
			<a href="<?php echo esc_url( get_category_link( $primary_category ) ); ?>" class="bday-article-eyebrow"><?php echo esc_html( $primary_category->name ); ?></a>
		<?php endif; ?>
		<h1 class="post-title"><?php the_title(); ?></h1>
		<?php
		/**
		 * Dek/subheadline — only when an editor has actually written an
		 * excerpt; never an auto-truncated snippet of the body standing
		 * in for one (a different, lower-quality thing than a
		 * deliberately-written dek — WSJ's own decks are always
		 * hand-written, never derived).
		 */
		$bday_dek = get_the_excerpt( $post_id );
		?>
		<?php if ( $bday_dek ) : ?>
			<p class="bday-article-dek"><?php echo esc_html( $bday_dek ); ?></p>
		<?php endif; ?>
		<div class="bday-byline">
			<?php
			/**
			 * Reader-requested: posts can credit more than one writer.
			 * bday_authors_byline_html() (addons/author-profile/) renders
			 * the primary WordPress author plus any checked co-authors,
			 * each with their own avatar (which itself picks up an
			 * author-uploaded photo over Gravatar, same addon) — falls
			 * back to the single-author markup this replaced if that
			 * addon is disabled, so the byline never goes empty.
			 */
			if ( function_exists( 'bday_authors_byline_html' ) ) {
				echo bday_authors_byline_html( $post_id );
			} else {
				echo get_avatar( get_the_author_meta( 'ID' ), 32, '', '', array( 'class' => 'bday-byline__avatar' ) );
				echo '<span>';
				the_author_posts_link();
				echo '</span>';
			}
			?>
			<span><?php the_date(); ?></span>
			<span class="bday-byline__read-time"><?php echo esc_html( $read_time ); ?> min read</span>
			<?php
			/**
			 * Populated client-side by sdk/src/bookmark-button.ts — a
			 * no-op mount if the reader isn't signed in (index.ts only
			 * calls initBookmarkButton() when a JWT cookie is present)
			 * or the SDK hasn't loaded yet, same "server always renders
			 * the empty/logged-out shape" posture as every other SDK
			 * mount point in this theme.
			 */
			?>
			<span id="aero-paywall-bookmark-mount" class="bday-byline__bookmark"></span>
		</div>

		<?php
		/**
		 * Text-to-speech (script.js's bdayInitTextToSpeech()) — theme-owned,
		 * not an SDK mount: window.speechSynthesis needs no account/API, so
		 * this renders for every reader regardless of sign-in state.
		 * Reader-requested: its own row below the byline (a circular icon
		 * button + "Listen to this article" label) rather than a small pill
		 * crammed in with the bookmark button, matching the reference
		 * layout this was rebuilt against.
		 */
		?>
		<button type="button" id="bday-tts-toggle" class="bday-audio-cta" data-state="idle" aria-label="Listen to this article">
			<span class="bday-audio-cta__circle">
				<svg class="bday-audio-cta__icon-listen" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 18v-6a9 9 0 0 1 18 0v6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3v5ZM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3v5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
				<svg class="bday-audio-cta__icon-pause" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1" fill="currentColor"/><rect x="14" y="5" width="4" height="14" rx="1" fill="currentColor"/></svg>
			</span>
			<span class="bday-audio-cta__label">Listen to this article</span>
		</button>

		<article data-bd-article-body>
			<?php
			/**
			 * Reader-reported: the article page only ever showed this embed
			 * for a post explicitly set to WordPress's own "Video" Post
			 * Format (a separate control in core's own sidebar panel) — so
			 * an editor who filled in either video ID field in this theme's
			 * own Video Meta box, without also knowing to flip that
			 * unrelated core toggle, saw the card facade work everywhere but
			 * never the article itself. Now driven by whichever ID is
			 * actually set (matching the Featured Video field's own promise
			 * of working "whether or not the post itself is a Video-format
			 * post"), not by the post format — _youtube_id wins if both are
			 * set, since it's the field editors expect to control this.
			 */
			$bday_article_video_id = get_post_meta( $post_id, '_youtube_id', true );
			if ( ! $bday_article_video_id ) {
				$bday_article_video_id = get_post_meta( $post_id, '_featured_video_id', true );
			}
			?>
			<?php if ( $bday_article_video_id ) : ?>
				<div class="bday-video-embed">
					<iframe src="https://www.youtube.com/embed/<?php echo esc_attr( $bday_article_video_id ); ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
				</div>
			<?php else : ?>
				<figure><?php echo bday_get_thumbnail( $post_id, 'featured', 'post-thumbnail' ); ?></figure>
			<?php endif; ?>

			<?php echo bday_social_share_html( $post_id ); ?>
			<?php bday_ad_zone( 'in_article_after_p2', get_post() ); ?>

			<?php if ( count( $toc ) >= 3 ) : ?>
				<nav class="bday-toc" aria-label="Table of contents">
					<h2 class="bday-toc__heading">In this article</h2>
					<ol class="bday-toc__list">
						<?php foreach ( $toc as $item ) : ?>
							<li class="bday-toc__item bday-toc__item--level-<?php echo esc_attr( $item['level'] ); ?>"><a href="#<?php echo esc_attr( $item['id'] ); ?>"><?php echo esc_html( $item['text'] ); ?></a></li>
						<?php endforeach; ?>
					</ol>
				</nav>
			<?php endif; ?>

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
				echo $gated_content;
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
				/**
				 * One bio block per bylined author (primary + co-authors),
				 * not just the primary — a co-author is a real credited
				 * writer, not a footnote, so their bio belongs here too
				 * when they've written one. Authors with no bio filled in
				 * are skipped rather than rendering an empty card.
				 */
				$bday_bio_authors = function_exists( 'bday_get_post_authors' )
					? bday_get_post_authors( $post_id )
					: array( get_userdata( (int) get_post_field( 'post_author', $post_id ) ) );
				foreach ( array_filter( $bday_bio_authors ) as $bday_bio_author ) :
					$author_bio = get_the_author_meta( 'description', $bday_bio_author->ID );
					if ( ! $author_bio ) {
						continue;
					}
					?>
					<div class="bday-author-bio">
						<?php echo get_avatar( $bday_bio_author->ID, 48, '', '', array( 'class' => 'bday-author-bio__avatar' ) ); ?>
						<div>
							<strong><a href="<?php echo esc_url( get_author_posts_url( $bday_bio_author->ID ) ); ?>"><?php echo esc_html( $bday_bio_author->display_name ); ?></a></strong>
							<p><?php echo esc_html( $author_bio ); ?></p>
						</div>
					</div>
				<?php endforeach; ?>

				<?php bday_ad_zone( 'below_share_buttons', get_post() ); ?>
				<?php echo bday_social_share_html( $post_id ); ?>

				<?php if ( is_active_sidebar( 'article_page_text_link' ) ) : ?>
					<?php dynamic_sidebar( 'article_page_text_link' ); ?>
				<?php endif; ?>
			</div>

			<div id="aero-paywall-comments-mount" class="bday-comments" data-aero-comments-post-id="<?php echo esc_attr( (string) $post_id ); ?>"></div>

			<?php if ( $primary_category ) :
				$ymal = bday_get_posts( array( 'category_name' => $primary_category->slug, 'post__not_in' => array( $post_id ), 'numberposts' => 3, 'cache_namespace' => 'article' ) );
				if ( ! empty( $ymal ) ) :
					?>
					<div class="bday-ymal">
						<h2 class="bday-section-heading">You Might Also Like</h2>
						<div class="bday-card-grid">
							<?php foreach ( $ymal as $rp ) : ?>
								<?php echo bday_card_html( $rp ); ?>
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
		<?php
		/**
		 * Editor-uploaded promotional material/custom ads
		 * (addons/sidebar-promo/) — a dedicated image+link+label slot an
		 * editor manages from wp-admin, separate from the GAM/direct-sold
		 * ad_zone below it (which stays code/vendor-configured).
		 */
		do_action( 'bday_article_sidebar_zone' );
		?>
		<?php bday_ad_zone( 'sidebar', get_post() ); ?>
	</aside>
</section>
