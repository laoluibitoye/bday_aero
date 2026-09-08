<?php
/**
 * Section Name: Columnists
 * Section Slug: columnists
 * Description: Same lead+grid layout as the Opinion section (reader-requested) — one lead columnist piece plus a grid of shorter ones, each with the author's avatar. Sourced from the "Columnist" category, same source the classic homepage already reads.
 * Default Enabled: yes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data       = $args['data'] ?? array();
$columnists = $data['columnists'] ?? array();
if ( empty( $columnists ) ) {
	return;
}

// Only dispatches to one of the three shared layouts when a Technical
// Team member has explicitly opted this section into one — otherwise
// falls through to this file's own bespoke markup below, unchanged,
// since that markup (not the shared "grid" renderer) is this section's
// actual current look (its lead kicker shows the author's avatar/name,
// which the shared renderer doesn't do).
$style = Bday_Section_Content::style( 'columnists' );
if ( '' !== $style ) {
	bday_render_section_by_style(
		$style,
		array(
			'posts'           => $columnists,
			'heading'         => bday_section_title( 'columnists' ),
			'see_more_url'    => bday_category_url( 'columnist' ),
			'see_more_label'  => 'All columnists →',
			'lead_kicker'     => 'Columnist',
			'author_position' => 'above',
			'screen_label'    => 'Columnists',
		)
	);
	return;
}

$lead = $columnists[0];
$grid = array_slice( $columnists, 1, 6 );
?>
<section class="bday-rd-columnists" data-screen-label="Columnists">
	<div class="bday-container">
		<div class="bday-rd-section-head">
			<h2><?php echo esc_html( bday_section_title( 'columnists' ) ); ?></h2>
			<span class="bday-rd-rule"></span>
			<a href="<?php echo esc_url( bday_category_url( 'columnist' ) ); ?>" class="bday-rd-kicker bday-rd-kicker--tint">All columnists →</a>
		</div>
		<div class="bday-rd-opinion__grid">
			<a href="<?php echo esc_url( get_permalink( $lead ) ); ?>" class="bday-rd-opinion__lead">
				<?php if ( bday_has_card_media( $lead->ID ) ) : ?><?php echo bday_get_card_media( $lead->ID, 'medium_standard' ); ?><?php endif; ?>
				<h3><?php echo esc_html( get_the_title( $lead ) ); ?></h3>
				<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $lead ), 22 ) ); ?></p>
				<span class="bday-rd-kicker bday-rd-kicker--tint"><?php echo get_avatar( $lead->post_author, 24 ); ?> <?php echo esc_html( get_the_author_meta( 'display_name', $lead->post_author ) ); ?> · <?php echo esc_html( bday_format_date( $lead->post_date ) ); ?></span>
			</a>
			<div class="bday-rd-opinion__list">
				<?php foreach ( $grid as $post ) : ?>
					<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="bday-rd-opinion__item">
						<span class="bday-rd-opinion__author"><?php echo get_avatar( $post->post_author, 40 ); ?><span class="bday-rd-kicker bday-rd-kicker--tint"><?php echo esc_html( get_the_author_meta( 'display_name', $post->post_author ) ); ?></span></span>
						<h4><?php echo esc_html( get_the_title( $post ) ); ?></h4>
						<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $post ), 16 ) ); ?></p>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</section>
