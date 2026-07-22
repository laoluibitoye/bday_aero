<?php
/**
 * The template for displaying 404 pages (not found).
 *
 * @link https://codex.wordpress.org/Creating_an_Error_404_Page
 *
 * @since  v1.0.0
 * @package BDay
 */

get_header(); ?>
	<?php
	/*
	 * This page never calls any ad slot to begin with, so there's nothing
	 * to gate here — bd_page_allows_ads() (inc/ads/ad-slots.php) returns
	 * false for is_404() regardless, for any ad markup added here later.
	 * The old id="no-ads" marker was read by assets/src/js/ads.js, itself
	 * dead code (never enqueued, registered GAM slots absent from every
	 * current template) — removed along with that file.
	 */
	?>
	<section class="container">
        <div class="breadcrumb">
            <ul>
                <li><a href="<?= get_site_url() ?>">Home </a></li>
                <li>></li>
                <li>404 Not Found</li>
            </ul>
        </div>
        <div class="page-not-found">
            <h1>404</h1>
            <h2>Page Not Found!</h2>
            <p>We're sorry, but we can't find the page you were looking for. It's probably some thing we've done wrong but now we know about it and we'll try to fix it. In the meantime, try one of these options:</p>
            <a href="<?= get_site_url() ?>"> <i class="bi bi-chevron-double-right"></i> Go to Homepage</a>
            <div class="search">
                <form role="search" method="get" action="<?= get_site_url() ?>">
                    <input type="search" class="search-field" placeholder="Search..." value="" name="s" title="Search for:" autocomplete="off">
                    <input type="submit" class="search-submit" value="Search">
                </form>
            </div>
        </div>
    </section>
<?php get_footer(); ?>