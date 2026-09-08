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

// This section has no shared-layout equivalent of its own (a plain card
// grid via bday_card_html(), unlike the other 7 single-source sections)
// — it only ever dispatches to one of the three shared layouts when a
// Technical Team member explicitly opts it into one; otherwise it falls
// through to its own bespoke markup below, unchanged.
$style = Bday_Section_Content::style( 'latest-stories' );
if ( '' !== $style ) {
	$recent_term     = get_term_by( 'slug', 'bdrecent', 'post_tag' );
	$recent_term_url = ( $recent_term && ! is_wp_error( $recent_term ) ) ? (string) get_tag_link( $recent_term ) : '';
	bday_render_section_by_style(
		$style,
		array(
			'posts'           => $posts,
			'heading'         => bday_section_title( 'latest-stories' ),
			'see_more_url'    => $recent_term_url,
			'see_more_label'  => 'See more →',
			'lead_kicker'     => 'Latest',
			'author_position' => 'above',
			'screen_label'    => 'Latest stories',
		)
	);
	return;
}
?>
<section class="bday-rd-latest" data-screen-label="Latest stories">
	<div class="bday-container">
		<div class="bday-rd-section-head">
			<h2><?php echo esc_html( bday_section_title( 'latest-stories' ) ); ?></h2>
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
