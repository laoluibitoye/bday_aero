<?php
/**
 * Section Name: Premium
 * Section Slug: premium
 * Description: BusinessDay Pro rail — one big feature, two medium stories, and an "Also in Pro" list. Sourced from the "premium" tag.
 * Default Enabled: yes
 *
 * Sources the same "premium" tag the classic homepage's Premium module
 * already uses — see the homepage-rebuild-plan review doc's §04 note that
 * this tag can drift from AeroPaywall's real per-post premium resolution
 * (class-premium-map.php); left as-is here rather than changed
 * speculatively, same scope boundary the Sections addon draws for itself.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data  = $args['data'] ?? array();
$posts = $data['rd_premium'] ?? array();
if ( empty( $posts ) ) {
	return;
}

// Only dispatches to one of the three shared layouts when a Technical
// Team member has explicitly opted this section into a different one —
// otherwise falls through to this file's own markup below unchanged,
// since that markup already IS the shared "premium" renderer's origin
// (bday_render_premium_style_section() in core/helpers.php is an
// extraction of it, not a different look).
$style = Bday_Section_Content::style( 'premium' );
if ( '' !== $style ) {
	$premium_term     = get_term_by( 'slug', 'premium', 'post_tag' );
	$premium_term_url = ( $premium_term && ! is_wp_error( $premium_term ) ) ? (string) get_tag_link( $premium_term ) : '';
	bday_render_section_by_style(
		$style,
		array(
			'posts'             => $posts,
			'heading'           => bday_section_title( 'premium' ),
			'badge'             => 'Premium',
			'cta_url'           => bday_epaper_url(),
			'cta_label'         => 'Subscribe →',
			'also_label'        => 'Also in Pro',
			'also_see_more_url' => $premium_term_url,
			'see_more_url'      => $premium_term_url,
			'see_more_label'    => 'See more →',
			'lead_kicker'       => 'Premium',
			'author_position'   => 'above',
			'screen_label'      => 'Premium',
		)
	);
	return;
}

$feature = $posts[0];
$medium  = array_slice( $posts, 1, 2 );
$also    = array_slice( $posts, 3, 8 );
?>
<section class="bday-rd-premium" data-screen-label="Premium">
	<div class="bday-container">
		<div class="bday-rd-premium__head">
			<span class="bday-rd-badge">Premium</span>
			<h2 class="bday-rd-premium__title"><?php echo esc_html( bday_section_title( 'premium' ) ); ?></h2>
			<a class="bday-rd-kicker bday-rd-kicker--tint bday-rd-premium__cta" href="<?php echo esc_url( bday_epaper_url() ); ?>">Subscribe →</a>
		</div>
		<div class="bday-rd-premium__grid">
			<a href="<?php echo esc_url( get_permalink( $feature ) ); ?>" class="bday-rd-premium__feature">
				<?php if ( bday_has_card_media( $feature->ID ) ) : ?><?php echo bday_get_card_media( $feature->ID, 'featured' ); ?><?php endif; ?>
				<h3><?php echo esc_html( get_the_title( $feature ) ); ?></h3>
				<p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $feature->post_excerpt ?: $feature->post_content ), 20, '…' ) ); ?></p>
				<span class="bday-rd-kicker bday-rd-kicker--faint"><?php echo esc_html( bday_format_date( $feature->post_date ) ); ?></span>
			</a>
			<div class="bday-rd-premium__medium">
				<?php foreach ( $medium as $post ) : ?>
					<a href="<?php echo esc_url( get_permalink( $post ) ); ?>">
						<?php if ( bday_has_card_media( $post->ID ) ) : ?><?php echo bday_get_card_media( $post->ID, 'top_story' ); ?><?php endif; ?>
						<h4><?php echo esc_html( get_the_title( $post ) ); ?></h4>
						<span class="bday-rd-kicker bday-rd-kicker--faint"><?php echo esc_html( bday_format_date( $post->post_date ) ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
			<?php if ( ! empty( $also ) ) : ?>
			<div class="bday-rd-premium__also">
				<div class="bday-rd-premium__also-head">
					<span class="bday-rd-kicker bday-rd-kicker--faint">Also in Pro</span>
					<a href="<?php echo esc_url( get_tag_link( get_term_by( 'slug', 'premium', 'post_tag' ) ) ); ?>" class="bday-rd-kicker bday-rd-kicker--accent bday-rd-premium__see-more">See more →</a>
				</div>
				<?php foreach ( $also as $post ) : ?>
					<a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>
	</div>
</section>
