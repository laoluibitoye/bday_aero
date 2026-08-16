<?php
/**
 * Section Name: Latest Stories
 * Section Slug: latest-stories
 * Description: The closing grid of the most recent posts — the "bdrecent" tag.
 * Default Enabled: yes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data  = $args['data'] ?? array();
$posts = $data['rd_latest'] ?? array();
if ( empty( $posts ) ) {
	return;
}
?>
<section class="bday-rd-latest" data-screen-label="Latest stories">
	<div class="bday-container">
		<div class="bday-rd-section-head">
			<h2>Latest Stories</h2>
			<span class="bday-rd-rule"></span>
			<a href="<?php echo esc_url( get_tag_link( get_term_by( 'slug', 'bdrecent', 'post_tag' ) ) ); ?>" class="bday-rd-kicker bday-rd-kicker--accent">See more →</a>
		</div>
		<div class="bday-card-grid bday-card-grid--large">
			<?php foreach ( $posts as $post ) : ?>
				<?php echo bday_card_html( $post, array( 'size' => 'medium_rectangle', 'show_byline' => true ) ); ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>
