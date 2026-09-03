<?php
/**
 * Google Preferred Source + Google News badge pair. Shared partial so the
 * same markup/CSS isn't duplicated between the two call sites in
 * template-parts/single-default.php (below the byline, end of article).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<style>
	.bday-google-badges {
		display: flex;
		align-items: center;
		gap: 12px;
		margin: 16px 0;
	}
	.bday-google-badges img {
		height: 38px;
		width: auto;
		border-radius: 6px;
		display: block;
	}
	/**
	 * When this partial renders inside .post-content (the end-of-article
	 * call site), style.scss's `#article-page main article .post-content
	 * img { height: auto; ... }` is higher specificity (an ID in the
	 * selector) than the plain class rule above, so it wins regardless of
	 * source order and the badges render at native image size instead of
	 * 38px — same bug/fix shape as .bday-author-bio__avatar in
	 * _premium.scss. Matching that selector's specificity here rather
	 * than reaching for !important.
	 */
	#article-page main article .post-content .bday-google-badges img {
		height: 38px;
		width: auto;
	}
</style>
<div class="bday-google-badges">
	<a href="https://www.google.com/preferences/source?q=businessday.ng" target="_blank" rel="noopener noreferrer">
		<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/google-preferred-source.jpg' ); ?>" alt="Add BusinessDay as a preferred source on Google">
	</a>
	<a href="https://news.google.com/publications/CAAqKQgKIiNDQklTRkFnTWFoQUtEbUoxYzJsdVpYTnpaR0Y1TG01bktBQVAB?hl=en-NG&gl=NG&ceid=NG%3Aen" target="_blank" rel="noopener noreferrer">
		<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/google-news.png' ); ?>" alt="Follow BusinessDay on Google News">
	</a>
</div>
