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
			<article>
				<div class="post-content">
					<?php the_content(); ?>
				</div>
			</article>
		</main>
	</section>
	<?php
endif;

get_footer();
