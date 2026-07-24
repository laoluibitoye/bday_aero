<?php
/** Homepage sidebar: today's e-paper thumbnail + premium promo + widget area. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$epaper = $data['e_paper'][0] ?? null;
?>
<aside class="bday-sidebar">
	<?php if ( $epaper ) : ?>
		<div class="bday-sidebar__epaper">
			<h2 class="bday-eyebrow">Today's E-Paper</h2>
			<a href="<?php echo esc_url( home_url( '/today-e-paper/' ) ); ?>"><?php echo bday_get_thumbnail( $epaper->ID, 'pdf_thumbnail' ); ?></a>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $data['premium'] ) ) : ?>
		<div class="bday-sidebar__premium">
			<h2 class="bday-eyebrow"><a href="https://premium.businessday.ng/" target="_blank" rel="noopener">Premium</a></h2>
			<ul class="bday-list">
				<?php foreach ( $data['premium'] as $post ) : ?>
					<li><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( is_active_sidebar( 'homepage_sidebar' ) ) : ?>
		<div class="bday-sidebar__widgets"><?php dynamic_sidebar( 'homepage_sidebar' ); ?></div>
	<?php endif; ?>
</aside>
