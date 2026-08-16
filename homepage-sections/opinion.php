<?php
/**
 * Section Name: Opinion
 * Section Slug: opinion
 * Description: One lead editorial plus a grid of shorter opinion pieces, each with the author's avatar.
 * Default Enabled: yes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data  = $args['data'] ?? array();
$posts = $data['rd_opinion'] ?? array();
if ( empty( $posts ) ) {
	return;
}

$lead = $posts[0];
$grid = array_slice( $posts, 1, 6 );
?>
<section class="bday-rd-opinion" data-screen-label="Opinion">
	<div class="bday-container">
		<div class="bday-rd-section-head">
			<h2>Opinion</h2>
			<span class="bday-rd-rule"></span>
			<a href="<?php echo esc_url( bday_section_url( 'opinion' ) ); ?>" class="bday-rd-kicker bday-rd-kicker--accent">All opinion →</a>
		</div>
		<div class="bday-rd-opinion__grid">
			<a href="<?php echo esc_url( get_permalink( $lead ) ); ?>" class="bday-rd-opinion__lead">
				<?php if ( has_post_thumbnail( $lead->ID ) ) : ?><?php echo bday_get_thumbnail( $lead->ID, 'medium_standard' ); ?><?php endif; ?>
				<h3><?php echo esc_html( get_the_title( $lead ) ); ?></h3>
				<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $lead ), 22 ) ); ?></p>
				<span class="bday-rd-kicker bday-rd-kicker--accent">Editorial · <?php echo esc_html( bday_format_date( $lead->post_date ) ); ?></span>
			</a>
			<div class="bday-rd-opinion__list">
				<?php foreach ( $grid as $post ) : ?>
					<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="bday-rd-opinion__item">
						<span class="bday-rd-opinion__author"><?php echo get_avatar( $post->post_author, 40 ); ?><span class="bday-rd-kicker bday-rd-kicker--faint"><?php echo esc_html( get_the_author_meta( 'display_name', $post->post_author ) ); ?></span></span>
						<h4><?php echo esc_html( get_the_title( $post ) ); ?></h4>
						<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $post ), 16 ) ); ?></p>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</section>
