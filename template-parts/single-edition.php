<?php
/**
 * E-edition article view — the PDF viewer, mounted from single.php's
 * e-edition branch.
 *
 * Field-tested finding (Gating System Field Test, 2026-09-08): this used
 * to call bday_render_pdf_viewer() directly with no gating call of any
 * kind — a post filed under this legacy category path rendered fully
 * open regardless of premium status or meter_scope_mode, in every mode
 * including Hard Wall. Confirmed dormant on this site today (no category
 * currently exists with the 'e-paper'/'e-edition' slug this dispatch
 * depends on — see single.php), but the legacy 'e-edition' addon it
 * depends on is still enabled, so nothing stops an editor from creating
 * that category and filing a post into it. Routes through the exact same
 * gate every other article template already calls, rather than adding a
 * second, parallel gating implementation just for this path.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

ob_start();
bday_render_pdf_viewer( get_post_meta( get_the_ID(), '_bday_pdf_preview_link', true ) );
$bday_edition_pdf_html = ob_get_clean();
?>
<section id="article-page" class="bday-container">
	<h1 class="post-title"><?php the_title(); ?></h1>
	<article>
		<?php echo bday_social_share_html( get_the_ID() ); ?>
		<div class="post-content">
			<?php echo bday_aero_gate_content( get_the_ID(), $bday_edition_pdf_html ); ?>
		</div>
	</article>
</section>
