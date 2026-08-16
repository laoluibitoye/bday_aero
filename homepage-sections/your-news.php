<?php
/**
 * Section Name: Your News
 * Section Slug: your-news
 * Description: A horizontally scrolling rail of desk cards, each listing that category's (or tag's) most recent stories with thumbnails. Desks are admin-configurable — Settings → News Carousel.
 * Default Enabled: yes
 *
 * "Desk" = a WP category (or tag) here. Which desks show and what each is
 * titled is now admin-configurable from Settings → News Carousel (see that
 * addon's own docblock for why it's still called that) via
 * bday_get_redesign_your_news_desks() (core/homepage/redesign-data.php),
 * which falls back to auto-picking the busiest categories if nothing's
 * configured yet. Every item in every desk carries its own thumbnail,
 * matching the source design's revised layout (previously only each desk's
 * lead item had one) — that function already fetches full post objects per
 * item, so no extra query happens here, just rendering.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data  = $args['data'] ?? array();
$desks = $data['rd_your_news'] ?? array();
if ( empty( $desks ) ) {
	return;
}
?>
<section class="bday-rd-your-news" data-screen-label="Your News">
	<div class="bday-container">
		<div class="bday-rd-section-head">
			<h2>Your News</h2>
			<span class="bday-rd-rule"></span>
		</div>
		<div class="bday-scroll-row bday-rd-your-news__rail" data-hide-scrollbar>
			<?php foreach ( $desks as $desk ) : ?>
				<div class="bday-rd-desk-card">
					<div class="bday-rd-desk-card__head">
						<span class="bday-rd-dot" aria-hidden="true"></span>
						<a href="<?php echo esc_url( $desk['url'] ); ?>" class="bday-rd-kicker"><?php echo esc_html( $desk['name'] ); ?></a>
					</div>
					<div class="bday-rd-desk-card__items">
						<?php foreach ( $desk['posts'] as $post ) : ?>
							<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="bday-rd-desk-card__item">
								<?php if ( has_post_thumbnail( $post->ID ) ) : ?><?php echo bday_get_thumbnail( $post->ID, 'small_category' ); ?><?php endif; ?>
								<span class="bday-rd-desk-card__item-body">
									<span class="bday-rd-desk-card__item-title"><?php echo esc_html( get_the_title( $post ) ); ?></span>
									<span class="bday-rd-kicker bday-rd-kicker--faint"><?php echo esc_html( bday_time_ago( $post->post_date ) ); ?></span>
								</span>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
