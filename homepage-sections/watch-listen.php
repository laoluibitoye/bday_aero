<?php
/**
 * Section Name: Watch & Listen
 * Section Slug: watch-listen
 * Description: Two media-card rows — "Watch Today's Videos" (the "top-video" category) and "Latest Shows" (podcast episodes), reader-requested to read as an actual media section rather than a feature-plus-list layout. The video row is gated by Homepage Modules' "BD TV row" toggle, the show row by its "Toon of the Day + Podcast" toggle — same two toggles the classic homepage's separate rows already use.
 * Default Enabled: yes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$modules = wp_parse_args(
	get_option( 'bday_addon_homepage_modules', array() ),
	array( 'enable_video_row' => true, 'enable_toon_podcast_row' => true )
);

$data     = $args['data'] ?? array();
$videos   = $modules['enable_video_row'] ? array_slice( $data['rd_videos'] ?? array(), 0, 5 ) : array();
$episodes = $modules['enable_toon_podcast_row'] ? array_slice( $data['podcasts'] ?? array(), 0, 4 ) : array();
if ( empty( $videos ) && empty( $episodes ) ) {
	return;
}
?>
<section class="bday-rd-watch-listen" data-screen-label="Watch and listen">
	<div class="bday-container">

		<?php if ( ! empty( $videos ) ) : ?>
			<div class="bday-rd-watch-listen__subhead">
				<h3>Watch Today's Videos</h3>
				<a href="<?php echo esc_url( bday_category_url( 'top-video' ) ); ?>" class="bday-rd-kicker bday-rd-kicker--accent">All videos →</a>
			</div>
			<div class="bday-rd-watch-listen__video-grid">
				<?php foreach ( $videos as $post ) : $cats = get_the_category( $post->ID ); ?>
					<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="bday-rd-watch-listen__video-card">
						<?php echo bday_get_thumbnail( $post->ID, 'medium_rectangle' ); ?>
						<?php if ( ! empty( $cats ) ) : ?><span class="bday-rd-kicker bday-rd-kicker--faint"><?php echo esc_html( $cats[0]->name ); ?></span><?php endif; ?>
						<h4><?php echo esc_html( get_the_title( $post ) ); ?></h4>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $episodes ) ) : ?>
			<div class="bday-rd-watch-listen__subhead">
				<h3>Latest Shows</h3>
				<?php if ( post_type_exists( 'podcast' ) ) : ?>
					<a href="<?php echo esc_url( (string) get_post_type_archive_link( 'podcast' ) ); ?>" class="bday-rd-kicker bday-rd-kicker--accent">All episodes →</a>
				<?php endif; ?>
			</div>
			<div class="bday-rd-watch-listen__show-grid">
				<?php foreach ( $episodes as $post ) : $has_thumb = has_post_thumbnail( $post->ID ); ?>
					<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="bday-rd-watch-listen__show-card<?php echo $has_thumb ? '' : ' bday-rd-watch-listen__show-card--no-thumb'; ?>">
						<?php if ( $has_thumb ) : ?><?php echo bday_get_thumbnail( $post->ID, 'medium_standard' ); ?><?php endif; ?>
						<span class="bday-rd-watch-listen__play" aria-hidden="true">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
						</span>
						<span class="bday-rd-watch-listen__show-body">
							<span class="bday-rd-kicker bday-rd-kicker--tint">Podcast</span>
							<span class="bday-rd-watch-listen__show-title"><?php echo esc_html( get_the_title( $post ) ); ?></span>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

	</div>
</section>
