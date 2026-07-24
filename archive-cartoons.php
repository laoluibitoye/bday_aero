<?php
/** Cartoon archive — WordPress's native archive-{post_type}.php convention for the 'cartoons' CPT (addons/cartoons/). */
get_header();
?>
<section id="cartoon-archive" class="bday-container">
	<header><h1>Cartoons — Past Editions</h1></header>
	<?php
	get_template_part(
		'addons/cartoons/template-parts/past-editions-grid',
		null,
		array( 'limit' => 12, 'paginate' => true, 'heading' => 'All Editions' )
	);
	?>
</section>
<?php
get_footer();
