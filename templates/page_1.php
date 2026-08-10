<?php
/**
 * Template Name: Page Template(with ads)
 *
 * Kept for its existing content/sidebar layout, but its ad-zone and
 * social-share calls below are now no-ops on every Page except the front
 * page (bday_page_allows_ads()/bday_social_share_html()'s own sitewide
 * "not on Pages" guard) — the template's own name predates that policy.
 * Not deleted outright so the discrepancy stays visible and easy to
 * revisit rather than silently vanishing.
 */
get_header();

if ( have_posts() ) :
	the_post();
	?>
	<section id="article-page" class="bday-container bday-two-col">
		<main class="bday-article-main">
			<article>
				<?php echo bday_social_share_html( get_the_ID() ); ?>
				<div class="post-content">
					<?php the_content(); ?>
					<?php echo bday_social_share_html( get_the_ID() ); ?>
				</div>
			</article>
		</main>
		<aside class="bday-sidebar">
			<?php if ( is_active_sidebar( 'page_sidebar' ) ) : ?>
				<?php dynamic_sidebar( 'page_sidebar' ); ?>
			<?php endif; ?>
			<?php bday_ad_zone( 'sidebar', get_post() ); ?>
		</aside>
	</section>
	<?php
endif;

get_footer();
