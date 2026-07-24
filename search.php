<?php
get_header();
?>
<header class="bday-container">
	<h1 class="bday-archive-title">Search results for "<?php echo esc_html( get_search_query() ); ?>"</h1>
	<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" class="bday-search-form">
		<input type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="Search…">
		<input type="submit" value="Search">
	</form>
</header>
<?php get_template_part( 'template-parts/archive/listing' ); ?>
<?php get_footer(); ?>
