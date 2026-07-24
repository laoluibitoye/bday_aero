<?php
/*
	Template Name: Default Page Template
*/
get_header();

if ( have_posts() ) :
	the_post();
	?>
	<section id="article-page" class="bday-container">
		<main>
			<h1 class="post-title"><?php the_title(); ?></h1>
			<article>
				<?php echo bday_social_share_html( get_the_ID() ); ?>
				<div class="post-content">
					<?php the_content(); ?>
					<?php echo bday_social_share_html( get_the_ID() ); ?>
				</div>
			</article>
		</main>
	</section>
	<?php
endif;

get_footer();
