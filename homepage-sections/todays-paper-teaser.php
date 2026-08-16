<?php
/**
 * Section Name: Today's Paper Teaser
 * Section Slug: todays-paper-teaser
 * Description: A small teaser linking to the full Today's Paper page — gated by Homepage Modules' "Today's Paper teaser" toggle, same as the classic homepage's own teaser. Unrelated to that page's own content, which editors control from each post's "Feature in Today's Paper" checkbox.
 * Default Enabled: yes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$modules = wp_parse_args( get_option( 'bday_addon_homepage_modules', array() ), array( 'enable_todays_paper' => true ) );
if ( empty( $modules['enable_todays_paper'] ) ) {
	return;
}
?>
<section class="bday-rd-todays-paper-teaser" data-screen-label="Today's paper teaser">
	<div class="bday-container bday-rd-todays-paper-teaser__inner">
		<div>
			<span class="bday-rd-kicker bday-rd-kicker--accent">Today's Paper</span>
			<h2>The full print edition, online</h2>
			<p>Laid out exactly as it appeared today — read it page by page or download the PDF.</p>
		</div>
		<a href="<?php echo esc_url( bday_epaper_url() ); ?>" class="bday-rd-btn bday-rd-btn--solid">Read today's edition</a>
	</div>
</section>
