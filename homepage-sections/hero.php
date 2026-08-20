<?php
/**
 * Section Name: Hero
 * Section Slug: hero
 * Description: Numbered top-news list, the lead story, and a Latest News column — a 3/6/3 grid.
 * Default Enabled: yes
 *
 * Adapts the source design's 3-column hero (numbered list | lead story |
 * live-election box) onto real, evergreen content: the third column is
 * Latest News rather than an election-day live box, since that box has no
 * general-purpose data source (see the homepage-rebuild-plan review doc).
 *
 * Also mounts three existing addon hooks the classic homepage already
 * wires up, so their wp-admin settings keep working here too:
 * bday_hero_before_recent (BDay Live's YouTube embed — reader-requested
 * position, above the Latest News column, same third-column slot the
 * classic hero uses), bday_homepage_leaderboard_zone (Premium
 * Leaderboard's rotating banner), and the homepage_leaderboard ad zone
 * (Ads & Sharing Matrix).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data     = $args['data'] ?? array();
$top_news = $data['rd_top_news'] ?? array();
$lead     = $data['lead'][0] ?? null;

// Same on/off check bday-live/addon.php itself uses before it actually
// renders anything (enabled AND a video ID set — "enabled" alone with no
// ID configured still shows nothing, same as the embed itself). When the
// stream is actually showing above this column, 8 numbered items plus
// the embed makes the column much taller than the lead/top-news columns
// beside it; 5 keeps it visually balanced.
$bday_live_settings = get_option( 'bday_addon_bday_live', array() );
$bday_live_showing  = ! empty( $bday_live_settings['enabled'] ) && ! empty( $bday_live_settings['youtube_id'] );
$latest_news        = array_slice( $data['rd_hero_latest'] ?? array(), 0, $bday_live_showing ? 5 : 8 );

$bdlead_term = get_term_by( 'slug', 'bdlead', 'post_tag' );
$top_news_archive_url = $bdlead_term && ! is_wp_error( $bdlead_term ) ? get_tag_link( $bdlead_term ) : '';
?>
<section class="bday-rd-hero" data-screen-label="Hero">
	<div class="bday-container bday-rd-hero__grid">

		<div class="bday-rd-hero__topnews">
			<div class="bday-rd-hero__topnews-head">
				<span class="bday-rd-dot" aria-hidden="true"></span>
				<span class="bday-rd-kicker">Top News</span>
				<span class="bday-rd-kicker bday-rd-kicker--muted bday-rd-hero__date"><?php echo esc_html( date_i18n( 'D, M j' ) ); ?></span>
			</div>
			<ol class="bday-rd-numbered-list">
				<?php foreach ( $top_news as $i => $post ) : ?>
					<li>
						<a href="<?php echo esc_url( get_permalink( $post ) ); ?>">
							<span class="bday-rd-numbered-list__n"><?php echo esc_html( sprintf( '%02d', $i + 1 ) ); ?></span>
							<span class="bday-rd-numbered-list__body">
								<span class="bday-rd-numbered-list__title"><?php echo esc_html( get_the_title( $post ) ); ?></span>
								<span class="bday-rd-kicker bday-rd-kicker--muted"><?php echo esc_html( bday_time_ago( $post->post_date ) ); ?></span>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ol>
			<?php if ( $top_news_archive_url ) : ?>
				<a href="<?php echo esc_url( $top_news_archive_url ); ?>" class="bday-rd-kicker bday-rd-kicker--accent bday-rd-hero__more">Read more →</a>
			<?php endif; ?>
		</div>

		<?php if ( $lead ) : ?>
			<article class="bday-rd-hero__lead">
				<a href="<?php echo esc_url( get_permalink( $lead ) ); ?>" class="bday-rd-hero__lead-media"><?php echo bday_get_thumbnail( $lead->ID, 'featured' ); ?></a>
				<div class="bday-rd-hero__lead-body">
					<span class="bday-rd-kicker bday-rd-kicker--accent">BD Lead</span>
					<h1 class="bday-rd-hero__lead-title"><a href="<?php echo esc_url( get_permalink( $lead ) ); ?>"><?php echo esc_html( get_the_title( $lead ) ); ?></a></h1>
					<p class="bday-rd-hero__lead-dek"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $lead->post_excerpt ?: $lead->post_content ), 50, '…' ) ); ?></p>
					<div class="bday-byline">
						<span><?php echo esc_html( get_the_author_meta( 'display_name', $lead->post_author ) ); ?></span>
						<span><?php echo esc_html( bday_time_ago( $lead->post_date ) ); ?></span>
					</div>
				</div>
			</article>
		<?php endif; ?>

		<div class="bday-rd-hero__latest">
			<?php do_action( 'bday_hero_before_recent' ); ?>
			<span class="bday-rd-kicker">Recent</span>
			<ol class="bday-rd-numbered-list bday-rd-hero__latest-list">
				<?php foreach ( $latest_news as $i => $post ) : ?>
					<li>
						<a href="<?php echo esc_url( get_permalink( $post ) ); ?>">
							<span class="bday-rd-numbered-list__n"><?php echo esc_html( sprintf( '%02d', $i + 1 ) ); ?></span>
							<span class="bday-rd-numbered-list__body">
								<span class="bday-rd-numbered-list__title"><?php echo esc_html( get_the_title( $post ) ); ?></span>
								<span class="bday-rd-kicker bday-rd-kicker--muted"><?php echo esc_html( bday_time_ago( $post->post_date ) ); ?></span>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>

	</div>
	<?php do_action( 'bday_homepage_leaderboard_zone' ); ?>
	<?php bday_ad_zone( 'homepage_leaderboard' ); ?>
</section>
