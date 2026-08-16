<?php
/**
 * Section Name: Toon of the Day
 * Section Slug: toon
 * Description: The latest editorial cartoon, full-size, with a link to the cartoon archive. Gated by Homepage Modules' "Toon of the Day + Podcast" toggle, same as the classic homepage.
 * Default Enabled: yes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$modules = wp_parse_args( get_option( 'bday_addon_homepage_modules', array() ), array( 'enable_toon_podcast_row' => true ) );
if ( empty( $modules['enable_toon_podcast_row'] ) ) {
	return;
}

$data = $args['data'] ?? array();
$toon = $data['rd_toon'][0] ?? null;
if ( ! $toon ) {
	return;
}
?>
<section class="bday-rd-toon" data-screen-label="Toon">
	<div class="bday-container bday-rd-toon__grid">
		<a href="<?php echo esc_url( get_permalink( $toon ) ); ?>" class="bday-rd-toon__media"><?php echo bday_get_thumbnail( $toon->ID, 'featured' ); ?></a>
		<div class="bday-rd-toon__body">
			<span class="bday-rd-kicker bday-rd-kicker--accent">Toon of the Day</span>
			<a href="<?php echo esc_url( get_permalink( $toon ) ); ?>" class="bday-rd-toon__title"><?php echo esc_html( get_the_title( $toon ) ); ?></a>
			<span class="bday-rd-kicker bday-rd-kicker--faint">By the BusinessDay art desk · <?php echo esc_html( bday_format_date( $toon->post_date ) ); ?></span>
			<div class="bday-rd-toon__actions">
				<a href="<?php echo esc_url( get_permalink( $toon ) ); ?>" class="bday-rd-btn bday-rd-btn--solid">View toon</a>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'cartoons' ) ); ?>" class="bday-rd-btn bday-rd-btn--outline">Toon archive</a>
			</div>
		</div>
	</div>
</section>
