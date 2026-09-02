<?php
/**
 * Section Name: BD Investigates
 * Section Slug: investigates
 * Description: Dark investigative-feature band, sourced from the "bdinvestigates" tag. No existing content uses this tag yet — the section stays hidden until an editor tags something this way.
 * Default Enabled: yes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data  = $args['data'] ?? array();
$posts = $data['rd_investigates'] ?? array();
if ( empty( $posts ) ) {
	return;
}

$lead   = $posts[0];
$more   = array_slice( $posts, 1, 3 );
$has_thumb = has_post_thumbnail( $lead->ID );
?>
<section class="bday-rd-investigates<?php echo $has_thumb ? '' : ' bday-rd-investigates--no-thumb'; ?>" data-screen-label="BD Investigates">
	<div class="bday-container">
		<div class="bday-rd-section-head bday-rd-section-head--invert">
			<h2>BD Investigates</h2>
			<span class="bday-rd-rule bday-rd-rule--invert"></span>
			<a href="<?php echo esc_url( get_tag_link( get_term_by( 'slug', 'bdinvestigates', 'post_tag' ) ) ); ?>" class="bday-rd-kicker bday-rd-kicker--accent">See more →</a>
		</div>
		<div class="bday-rd-investigates__grid">
			<?php if ( $has_thumb ) : ?>
				<a href="<?php echo esc_url( get_permalink( $lead ) ); ?>" class="bday-rd-investigates__media"><?php echo bday_get_card_media( $lead->ID, 'featured' ); ?></a>
			<?php endif; ?>
			<div class="bday-rd-investigates__body">
				<span class="bday-rd-kicker bday-rd-kicker--accent">Investigation · <?php echo esc_html( bday_format_date( $lead->post_date ) ); ?></span>
				<a href="<?php echo esc_url( get_permalink( $lead ) ); ?>" class="bday-rd-investigates__title"><?php echo esc_html( get_the_title( $lead ) ); ?></a>
				<p class="bday-rd-investigates__dek"><?php echo esc_html( wp_trim_words( get_the_excerpt( $lead ), 26 ) ); ?></p>
				<?php if ( ! empty( $more ) ) : ?>
					<div class="bday-rd-investigates__more">
						<?php foreach ( $more as $post ) : ?>
							<a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
