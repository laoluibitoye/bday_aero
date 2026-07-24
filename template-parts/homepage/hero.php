<?php
/**
 * Homepage hero: lead story + a top-stories list + a recent list. Pure
 * data-receiving partial — no querying. $data comes from
 * bday_get_homepage_data(); $layout picks a visual variant
 * ('split' | 'stacked' | 'takeover').
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$layout = $args['layout'] ?? 'split';
$lead   = $data['lead'][0] ?? null;
?>
<section class="bday-hero bday-hero--<?php echo esc_attr( $layout ); ?>">
	<div class="bday-container">
		<?php if ( 'takeover' !== $layout ) : ?>
		<aside class="bday-hero__col bday-hero__col--top">
			<h2 class="bday-eyebrow">Top News</h2>
			<ul class="bday-list">
				<?php foreach ( $data['top_stories'] as $post ) : ?>
					<li><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a><time><?php echo esc_html( bday_time_ago( $post->post_date ) ); ?></time></li>
				<?php endforeach; ?>
			</ul>
		</aside>
		<?php endif; ?>

		<?php if ( $lead ) : ?>
		<article class="bday-hero__lead">
			<a href="<?php echo esc_url( get_permalink( $lead ) ); ?>" class="bday-hero__lead-media">
				<?php echo bday_get_thumbnail( $lead->ID, 'featured' ); ?>
			</a>
			<h1 class="bday-hero__title"><a href="<?php echo esc_url( get_permalink( $lead ) ); ?>"><?php echo esc_html( get_the_title( $lead ) ); ?></a></h1>
			<p class="bday-hero__excerpt"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $lead->post_excerpt ?: $lead->post_content ), 50, '…' ) ); ?></p>
			<div class="bday-byline">
				<span><?php echo esc_html( get_the_author_meta( 'display_name', $lead->post_author ) ); ?></span>
				<span><?php echo esc_html( bday_time_ago( $lead->post_date ) ); ?></span>
			</div>
		</article>
		<?php endif; ?>

		<?php if ( 'takeover' !== $layout ) : ?>
		<aside class="bday-hero__col bday-hero__col--recent">
			<?php do_action( 'bday_hero_before_recent' ); ?>
			<h2 class="bday-eyebrow">Recent</h2>
			<ul class="bday-list">
				<?php foreach ( $data['recent'] as $post ) : ?>
					<li><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a><time><?php echo esc_html( bday_time_ago( $post->post_date ) ); ?></time></li>
				<?php endforeach; ?>
			</ul>
		</aside>
		<?php endif; ?>
	</div>
	<?php bday_ad_zone( 'homepage_leaderboard' ); ?>
</section>
