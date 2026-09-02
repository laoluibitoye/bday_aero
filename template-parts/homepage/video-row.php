<?php
/**
 * "BD TV" horizontal video carousel — extracted out of bottom-widgets.php
 * into its own template part as part of the WSJ-layout homepage adoption,
 * so homepage-variants/default.php can position it independently (WSJ
 * places its video carousel after the topic-list/sidebar block, not at
 * the very bottom of the page). Still independently toggleable via the
 * same bday_addon_homepage_modules option bottom-widgets.php's other rows
 * use.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$modules = wp_parse_args(
	get_option( 'bday_addon_homepage_modules', array() ),
	array( 'enable_video_row' => true )
);

$videos = $modules['enable_video_row']
	? bday_get_posts( array( 'category_name' => 'top-video', 'numberposts' => 8, 'cache_namespace' => 'homepage' ) )
	: array();

if ( empty( $videos ) ) {
	return;
}
?>
<section class="bday-video-row">
	<div class="bday-container">
		<h2 class="bday-section-heading bday-section-heading--inverse">BD TV</h2>
		<div class="bday-scroll-row">
			<?php foreach ( $videos as $post ) : ?>
				<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="bday-video-card">
					<?php echo bday_get_card_media( $post->ID, 'medium_rectangle' ); ?>
					<h4><?php echo esc_html( get_the_title( $post ) ); ?></h4>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
