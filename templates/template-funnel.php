<?php
/**
 * Template Name: Funnel / Landing Page
 *
 * For pages that shouldn't inherit the site's masthead/nav/footer chrome
 * or a boxed article layout — sales funnels, campaign landing pages,
 * newsletter/account-style flows. No auto-printed page title, no ads
 * (bday_page_allows_ads() already excludes Pages), no social-share, no
 * breadcrumbs. An author who wants a heading puts one in the block-editor
 * content itself.
 *
 * get_header('minimal') / get_footer('minimal') load header-minimal.php /
 * footer-minimal.php — WordPress's own header/footer-variant convention,
 * not a bespoke mechanism. header-minimal.php opens <main class="bday-
 * minimal-main"> without closing it; this file closes it before
 * get_footer('minimal'), matching the "content wrapper spans header to
 * template, template closes it" shape page.php already uses for its own
 * #article-page wrapper.
 */

get_header( 'minimal' );

if ( have_posts() ) :
	the_post();
	the_content();
endif;
?>
</main>
<?php
get_footer( 'minimal' );
