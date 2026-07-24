<?php
/** 404 template — never renders ads (bday_page_allows_ads() returns false for is_404()). */
get_header();
?>
<section class="bday-container">
	<div class="page-not-found text-center mx-auto">
		<h1>404</h1>
		<h2>Page Not Found!</h2>
		<p>We're sorry, but we can't find the page you were looking for. Try one of these options:</p>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Go to Homepage</a>
		<div class="search">
			<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<input type="search" class="search-field" placeholder="Search..." value="" name="s" title="Search for:" autocomplete="off">
				<input type="submit" class="search-submit" value="Search">
			</form>
		</div>
	</div>
</section>
<?php
get_footer();
