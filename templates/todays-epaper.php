<?php
/**
 * Template Name: Todays Epaper
 */
get_header();

$e_paper = bday_get_posts( array( 'category_name' => 'e-paper', 'numberposts' => 1, 'cache_namespace' => 'e_edition' ) );

foreach ( $e_paper as $post ) :
	setup_postdata( $post );
	?>
	<section id="article-page" class="bday-container">
		<h1 class="post-title"><?php echo esc_html( get_the_title( $post ) ); ?></h1>
		<article>
			<?php echo bday_social_share_html( $post->ID ); ?>
			<div class="post-content">
				<?php bday_render_pdf_viewer( get_post_meta( $post->ID, '_bday_pdf_preview_link', true ) ); ?>
			</div>
		</article>
	</section>
	<?php
endforeach;
wp_reset_postdata();

get_footer();
