<?php
/**
 * Section Name: In Other News
 * Section Slug: in-other-news
 * Description: A story list plus a sidebar (today's e-paper, an Opinion box, Most Popular, and the sidebar ad zone the classic homepage's own rail also carries).
 * Default Enabled: yes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data     = $args['data'] ?? array();
$stories  = array_slice( $data['other_news'] ?? array(), 0, 5 );
$e_paper  = $data['e_paper'][0] ?? null;
$opinion  = array_slice( $data['opinion'] ?? array(), 0, 3 );
$popular  = $data['most_popular'] ?? array();
if ( empty( $stories ) ) {
	return;
}
?>
<section class="bday-rd-in-other-news" data-screen-label="In other news">
	<div class="bday-container bday-rd-in-other-news__grid">
		<div class="bday-rd-in-other-news__col">
			<div class="bday-rd-section-head">
				<h2><a href="<?php echo esc_url( bday_section_url( 'news' ) ); ?>"><?php echo esc_html( bday_section_label( 'news' ) ); ?></a></h2>
				<span class="bday-rd-rule"></span>
			</div>
			<div class="bday-rd-story-list">
				<?php foreach ( $stories as $post ) : ?>
					<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="bday-rd-story-list__item">
						<?php if ( has_post_thumbnail( $post->ID ) ) : ?><?php echo bday_get_card_media( $post->ID, 'medium_rectangle' ); ?><?php endif; ?>
						<span class="bday-rd-story-list__body">
							<?php $cats = get_the_category( $post->ID ); ?>
							<?php if ( ! empty( $cats ) ) : ?><span class="bday-rd-kicker bday-rd-kicker--accent"><?php echo esc_html( $cats[0]->name ); ?></span><?php endif; ?>
							<span class="bday-rd-story-list__title"><?php echo esc_html( get_the_title( $post ) ); ?></span>
							<span class="bday-rd-story-list__dek"><?php echo esc_html( wp_trim_words( get_the_excerpt( $post ), 20 ) ); ?></span>
							<span class="bday-rd-kicker bday-rd-kicker--faint"><?php echo esc_html( get_the_author_meta( 'display_name', $post->post_author ) ); ?></span>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>

		<aside class="bday-rd-rail">
			<?php if ( $e_paper ) : ?>
				<div class="bday-rd-rail__block">
					<span class="bday-rd-kicker">Today's E-paper</span>
					<a href="<?php echo esc_url( bday_epaper_url() ); ?>" class="bday-rd-rail__epaper"><?php echo bday_get_thumbnail( $e_paper->ID, 'pdf_thumbnail' ); ?></a>
					<a href="<?php echo esc_url( bday_epaper_url() ); ?>" class="bday-rd-kicker bday-rd-kicker--tint">Read today's edition →</a>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $opinion ) ) : ?>
				<div class="bday-rd-rail__block bday-rd-rail__block--tint">
					<span class="bday-rd-kicker">Opinion</span>
					<?php foreach ( $opinion as $post ) : ?>
						<a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $popular ) ) : ?>
				<div class="bday-rd-rail__block">
					<span class="bday-rd-kicker">Most Popular</span>
					<ul class="bday-list">
						<?php foreach ( $popular as $post ) : ?>
							<li><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
			<?php bday_ad_zone( 'sidebar' ); ?>
		</aside>
	</div>
</section>
