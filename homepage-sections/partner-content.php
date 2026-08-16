<?php
/**
 * Section Name: Partner Content
 * Section Slug: partner-content
 * Description: Sponsored-content tiles, sourced from the "sponsored" tag. No existing content uses this tag yet — the section stays hidden until an editor tags something this way.
 * Default Enabled: yes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data  = $args['data'] ?? array();
$posts = $data['rd_partner'] ?? array();
if ( empty( $posts ) ) {
	return;
}
?>
<section class="bday-rd-partner" data-screen-label="Partner content">
	<div class="bday-container">
		<div class="bday-rd-partner__head">
			<span class="bday-rd-kicker bday-rd-kicker--faint">Partner Content &amp; Sponsored</span>
			<span class="bday-rd-rule"></span>
			<a href="<?php echo esc_url( get_tag_link( get_term_by( 'slug', 'sponsored', 'post_tag' ) ) ); ?>" class="bday-rd-kicker bday-rd-kicker--accent">See more →</a>
		</div>
		<div class="bday-rd-partner__grid">
			<?php foreach ( $posts as $post ) : $has_thumb = has_post_thumbnail( $post->ID ); ?>
				<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="bday-rd-partner__item<?php echo $has_thumb ? '' : ' bday-rd-partner__item--no-thumb'; ?>">
					<?php if ( $has_thumb ) : ?><?php echo bday_get_thumbnail( $post->ID, 'small' ); ?><?php endif; ?>
					<span class="bday-rd-partner__body">
						<span class="bday-rd-partner__title"><?php echo esc_html( get_the_title( $post ) ); ?></span>
						<span class="bday-rd-kicker bday-rd-kicker--faint"><?php echo esc_html( get_the_author_meta( 'display_name', $post->post_author ) ); ?></span>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
