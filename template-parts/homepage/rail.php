<?php
/** Other-news grid + columnists/opinion row. Pure data-receiving partial. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="bday-rail">
	<div class="bday-container">
		<h2 class="bday-section-heading"><a href="<?php echo esc_url( bday_category_url( 'news' ) ); ?>">In Other News</a></h2>
		<div class="bday-card-grid">
			<?php foreach ( $data['other_news'] as $post ) : ?>
				<article class="bday-card">
					<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="bday-card__media"><?php echo bday_get_thumbnail( $post->ID, 'medium_rectangle' ); ?></a>
					<h3 class="bday-card__title"><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></h3>
					<div class="bday-byline">
						<span><?php echo esc_html( get_the_author_meta( 'display_name', $post->post_author ) ); ?></span>
						<span><?php echo esc_html( bday_time_ago( $post->post_date ) ); ?></span>
					</div>
				</article>
			<?php endforeach; ?>
		</div>

		<div class="bday-columnists-row">
			<div class="bday-columnists">
				<h2 class="bday-section-heading"><a href="<?php echo esc_url( bday_category_url( 'columnist' ) ); ?>">Columnists</a></h2>
				<div class="bday-columnists__grid">
					<?php foreach ( $data['columnists'] as $post ) : ?>
						<div class="bday-columnist">
							<?php echo get_avatar( $post->post_author, 40 ); ?>
							<div>
								<h4><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></h4>
								<span><?php echo esc_html( get_the_author_meta( 'display_name', $post->post_author ) ); ?></span>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="bday-opinion">
				<h2 class="bday-section-heading"><a href="<?php echo esc_url( bday_category_url( 'opinion' ) ); ?>">Opinion</a></h2>
				<ul class="bday-list">
					<?php foreach ( $data['opinion'] as $post ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>

		<?php bday_ad_zone( 'sidebar' ); ?>
	</div>
</section>
