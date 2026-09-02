<?php
/**
 * Section Name: In Pictures
 * Section Slug: in-pictures
 * Description: A horizontal photo rail, sourced from posts using the Gallery post format. No existing content uses that format yet — the section stays hidden until an editor publishes one.
 * Default Enabled: yes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data  = $args['data'] ?? array();
$posts = $data['rd_gallery'] ?? array();
if ( empty( $posts ) ) {
	return;
}
?>
<section class="bday-rd-in-pictures" data-screen-label="In pictures">
	<div class="bday-container">
		<div class="bday-rd-section-head">
			<h2>In Pictures</h2>
			<span class="bday-rd-rule"></span>
			<a href="<?php echo esc_url( get_post_format_link( 'gallery' ) ); ?>" class="bday-rd-kicker bday-rd-kicker--accent">See more →</a>
		</div>
		<div class="bday-scroll-row bday-rd-in-pictures__rail">
			<?php foreach ( $posts as $post ) : ?>
				<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="bday-rd-in-pictures__item">
					<?php echo bday_get_card_media( $post->ID, 'medium_standard' ); ?>
					<span class="bday-rd-kicker bday-rd-kicker--faint"><?php echo esc_html( get_the_title( $post ) ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
