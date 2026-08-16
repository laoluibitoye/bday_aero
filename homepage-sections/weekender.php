<?php
/**
 * Section Name: Off the Clock
 * Section Slug: weekender
 * Description: Weekender / Life & Arts / Sports / Reports, side by side. Gated by Homepage Modules' "Magazine row" toggle, same as the classic homepage.
 * Default Enabled: yes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$modules = wp_parse_args( get_option( 'bday_addon_homepage_modules', array() ), array( 'enable_magazine_row' => true ) );
if ( empty( $modules['enable_magazine_row'] ) ) {
	return;
}

$data = $args['data'] ?? array();
$cols = $data['rd_weekender_cols'] ?? array();
if ( empty( $cols ) ) {
	return;
}
?>
<section class="bday-rd-weekender" data-screen-label="Weekender and life">
	<div class="bday-container">
		<div class="bday-rd-section-head">
			<h2>Off the Clock</h2>
			<span class="bday-rd-rule"></span>
			<span class="bday-rd-kicker bday-rd-kicker--faint"><?php echo esc_html( implode( ' · ', wp_list_pluck( $cols, 'label' ) ) ); ?></span>
		</div>
		<div class="bday-rd-weekender__grid">
			<?php foreach ( $cols as $col ) : $lead = $col['posts'][0]; $more = array_slice( $col['posts'], 1, 2 ); ?>
				<div class="bday-rd-weekender__col">
					<a href="<?php echo esc_url( get_permalink( $lead ) ); ?>" class="bday-rd-weekender__lead">
						<?php if ( has_post_thumbnail( $lead->ID ) ) : ?><?php echo bday_get_thumbnail( $lead->ID, 'medium_standard' ); ?><?php endif; ?>
						<span class="bday-rd-kicker bday-rd-kicker--accent"><?php echo esc_html( $col['label'] ); ?></span>
						<h4><?php echo esc_html( get_the_title( $lead ) ); ?></h4>
					</a>
					<?php foreach ( $more as $post ) : ?>
						<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="bday-rd-weekender__more"><?php echo esc_html( get_the_title( $post ) ); ?></a>
					<?php endforeach; ?>
					<?php if ( ! empty( $col['url'] ) && '#' !== $col['url'] ) : ?>
						<a href="<?php echo esc_url( $col['url'] ); ?>" class="bday-rd-kicker bday-rd-kicker--accent bday-rd-weekender__see-more">See more →</a>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
