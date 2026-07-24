<?php
/**
 * Template Name: Page Template(with ads)
 */
get_header();

if ( have_posts() ) :
	the_post();
	?>
	<section id="article-page" class="bday-container bday-two-col">
		<main class="bday-article-main">
			<h1 class="post-title"><?php the_title(); ?></h1>
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
