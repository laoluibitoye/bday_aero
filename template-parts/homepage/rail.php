<?php
/**
 * Columnists row. Pure data-receiving partial — the post list itself
 * still comes from core/homepage/data.php's category fetch, but the
 * heading and its link come from the Sections registry (addons/sections)
 * so an admin can rename/repoint it from the dashboard.
 *
 * The "In Other News" card grid and the Opinion box previously here both
 * moved elsewhere as part of the WSJ-layout homepage adoption: other_news
 * now sits right after the podcast carousel (homepage-variants/default.php),
 * and Opinion moved into the hero's third column (hero.php) — WSJ's own
 * Top-News/Lead/Opinion row shape, which this theme's hero grid already
 * matched structurally before this change.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// See hero.php's comment for why this normalization is needed — this
// WordPress core doesn't extract get_template_part()'s $args for us.
$data = $args['data'] ?? array();
?>
<section class="bday-rail">
	<div class="bday-container">
		<div class="bday-columnists">
			<h2 class="bday-section-heading"><a href="<?php echo esc_url( bday_section_url( 'columnist' ) ); ?>"><?php echo esc_html( bday_section_label( 'columnist' ) ); ?></a></h2>
			<div class="bday-columnists__grid">
				<?php foreach ( $data['columnists'] as $post ) : ?>
					<div class="bday-columnist">
						<?php echo get_avatar( $post->post_author, 40 ); ?>
						<div>
							<h4><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></h4>
							<span><?php echo esc_html( get_the_author_meta( 'display_name', $post->post_author ) ); ?></span>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<?php bday_ad_zone( 'sidebar' ); ?>
	</div>
</section>
