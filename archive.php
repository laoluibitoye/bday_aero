<?php
get_header();

/**
 * Follow button — only meaningful on a category/tag archive (a reader
 * "follows" a topic, not e.g. a date archive or the author archive).
 * WP's own taxonomy slug for tags is 'post_tag'; subscription-service's
 * Follow model only accepts the literal strings 'category'/'tag'
 * (create-follow.dto.ts), so that's normalized here rather than leaking
 * the WP-specific slug into the SDK. sdk/src/follows.ts reads these
 * data-attributes straight off the mount, the same "SDK reaches into the
 * page itself" pattern bookmark-button.ts already uses for its own
 * article-page data — no aeroPaywallContext changes needed.
 */
$bday_queried    = get_queried_object();
$bday_follow_tax = null;
if ( $bday_queried instanceof WP_Term ) {
	if ( 'category' === $bday_queried->taxonomy ) {
		$bday_follow_tax = 'category';
	} elseif ( 'post_tag' === $bday_queried->taxonomy ) {
		$bday_follow_tax = 'tag';
	}
}
?>
<header class="bday-container bday-archive-header">
	<h1 class="bday-archive-title"><?php echo get_the_archive_title(); // phpcs:ignore ?></h1>
	<?php if ( $bday_follow_tax ) : ?>
		<span
			id="aero-paywall-follow-mount"
			class="bday-archive-follow"
			data-aero-follow-taxonomy="<?php echo esc_attr( $bday_follow_tax ); ?>"
			data-aero-follow-term-id="<?php echo esc_attr( (string) $bday_queried->term_id ); ?>"
			data-aero-follow-term-label="<?php echo esc_attr( $bday_queried->name ); ?>"
		></span>
	<?php endif; ?>
</header>
<?php get_template_part( 'template-parts/archive/listing' ); ?>
<?php get_footer(); ?>
