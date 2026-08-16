<?php
/**
 * Section Name: The Interview
 * Section Slug: interview
 * Description: A pull-quote interview feature, sourced from the "bd-interview" tag. No existing content uses this tag yet — the section stays hidden until an editor tags something this way.
 * Default Enabled: yes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data  = $args['data'] ?? array();
$posts = $data['rd_interview'] ?? array();
if ( empty( $posts ) ) {
	return;
}

$lead = $posts[0];
$more = array_slice( $posts, 1, 3 );
?>
<section class="bday-rd-interview" data-screen-label="Interview">
	<div class="bday-container bday-rd-interview__grid">
		<div class="bday-rd-interview__col">
			<span class="bday-rd-kicker bday-rd-kicker--accent">The Interview</span>
			<a href="<?php echo esc_url( get_tag_link( get_term_by( 'slug', 'bd-interview', 'post_tag' ) ) ); ?>" class="bday-rd-kicker bday-rd-kicker--faint bday-rd-interview__see-more">All interviews →</a>
			<?php if ( has_post_thumbnail( $lead->ID ) ) : ?>
				<div class="bday-rd-interview__portrait"><?php echo bday_get_thumbnail( $lead->ID, 'featured' ); ?></div>
			<?php endif; ?>
			<a href="<?php echo esc_url( get_permalink( $lead ) ); ?>" class="bday-rd-interview__quote"><?php echo esc_html( get_the_title( $lead ) ); ?></a>
			<span class="bday-rd-kicker bday-rd-kicker--faint"><?php echo esc_html( get_the_author_meta( 'display_name', $lead->post_author ) ); ?></span>
		</div>
		<?php if ( ! empty( $more ) ) : ?>
			<div class="bday-rd-interview__col bday-rd-interview__more">
				<?php foreach ( $more as $post ) : ?>
					<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="bday-rd-interview__more-item">
						<?php if ( has_post_thumbnail( $post->ID ) ) : ?><?php echo bday_get_thumbnail( $post->ID, 'small' ); ?><?php endif; ?>
						<span>
							<span class="bday-rd-interview__more-title"><?php echo esc_html( get_the_title( $post ) ); ?></span>
							<span class="bday-rd-kicker bday-rd-kicker--faint"><?php echo esc_html( wp_trim_words( get_the_excerpt( $post ), 12 ) ); ?></span>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
