<?php
/**
 * "Big Read" feature module (WSJ-layout adoption's Documentaries-module
 * equivalent) — one large feature story plus a short list of more from
 * the same source. BusinessDay has no documentary vertical, so this pulls
 * from the Editorial category instead of inventing placeholder content
 * for a brand that doesn't exist here.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// See hero.php's comment for why this normalization is needed — this
// WordPress core doesn't extract get_template_part()'s $args for us.
$data    = $args['data'] ?? array();
$posts   = $data['feature_spotlight'] ?? array();
$feature = $posts[0] ?? null;
$more    = array_slice( $posts, 1, 3 );
if ( ! $feature ) {
	return;
}
?>
<section class="bday-feature-spotlight">
	<div class="bday-container bday-feature-spotlight__inner">
		<article class="bday-feature-spotlight__main">
			<h2 class="bday-eyebrow"><a href="<?php echo esc_url( bday_section_url( 'editorial' ) ); ?>">Big Read</a></h2>
			<a href="<?php echo esc_url( get_permalink( $feature ) ); ?>" class="bday-feature-spotlight__media">
				<?php echo bday_get_card_media( $feature->ID, 'featured' ); ?>
			</a>
			<h3 class="bday-feature-spotlight__title"><a href="<?php echo esc_url( get_permalink( $feature ) ); ?>"><?php echo esc_html( get_the_title( $feature ) ); ?></a></h3>
			<p class="bday-feature-spotlight__excerpt"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $feature->post_excerpt ?: $feature->post_content ), 32, '…' ) ); ?></p>
		</article>

		<?php if ( ! empty( $more ) ) : ?>
			<ul class="bday-feature-spotlight__list">
				<?php foreach ( $more as $post ) : ?>
					<li>
						<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="bday-feature-spotlight__list-media"><?php echo bday_get_card_media( $post->ID, 'small' ); ?></a>
						<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="bday-feature-spotlight__list-title"><?php echo esc_html( get_the_title( $post ) ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</section>
