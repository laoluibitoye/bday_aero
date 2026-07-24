<?php
/** E-edition article view — the PDF viewer, mounted from single.php's e-edition branch. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section id="article-page" class="bday-container">
	<h1 class="post-title"><?php the_title(); ?></h1>
	<article>
		<?php echo bday_social_share_html( get_the_ID() ); ?>
		<div class="post-content">
			<?php bday_render_pdf_viewer( get_post_meta( get_the_ID(), '_bday_pdf_preview_link', true ) ); ?>
		</div>
	</article>
</section>
