<?php
/** Cartoon archive — WordPress's native archive-{post_type}.php convention for the 'cartoons' CPT (addons/cartoons/). */
get_header();
?>
<section id="cartoon-archive" class="bday-container">
	<header class="bday-gallery-header">
		<span class="bday-eyebrow">Gallery</span>
		<h1>Editorial Cartoons</h1>
		<p class="bday-gallery-header__dek">Browse past artwork from the BusinessDay cartoon desk.</p>
	</header>
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
