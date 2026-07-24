<?php
/**
 * Template Name: Newsletter Landing
 *
 * Landing page for the working FluentCRM newsletter system
 * (addons/newsletter-fluentcrm). Replaces both the old
 * template-newsletter-landing.php (300 lines of inline CSS) and the dead
 * templates/newsletter.php whose submit button literally did
 * alert('not connected to API') — that one is not carried forward.
 */
get_header();

if ( have_posts() ) :
	the_post();
	?>
	<section class="bday-container bday-newsletter-landing">
		<header>
			<h1><?php the_title(); ?></h1>
		</header>
		<div class="post-content"><?php the_content(); ?></div>
		<?php echo do_shortcode( '[fluentcrm_remote_form title="" description="Choose the newsletters you want to receive." button_text="Subscribe"]' ); ?>
	</section>
	<?php
endif;

get_footer();
