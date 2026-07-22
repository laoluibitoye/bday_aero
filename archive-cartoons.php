<?php
/**
 * Cartoon archive — WordPress's native archive-{post_type}.php convention
 * for the 'cartoons' CPT (has_archive => true in functions/features.php,
 * default archive URL /cartoons/).
 *
 * FIX (2026-07-22): this URL previously had NO matching template at all —
 * category-cartoon.php (now archived, see _archive/) targeted a WordPress
 * *category* taxonomy term with slug "cartoon" (a completely different URL,
 * /category/cartoon/), which is unrelated to this CPT's own archive. The
 * taxonomy-vs-CPT-rewrite-slug mismatch the original audit flagged wasn't
 * a filter bug to patch — the file was in the wrong slot in the template
 * hierarchy entirely. This is the actual template WordPress resolves for
 * /cartoons/.
 */
get_header();
?>
<section id="cartoon-archive" class="container">
	<div class="breadcrumb">
		<ul>
			<li><a href="<?= esc_url( home_url( '/' ) ) ?>">Home</a></li>
			<li>></li>
			<li>Cartoons</li>
		</ul>
	</div>
	<header>
		<h1>Cartoons — Past Editions</h1>
	</header>

	<?php
	get_template_part( 'template-parts/cartoon/past-editions-grid', null, [
		'limit'    => 12,
		'paginate' => true,
		'heading'  => 'All Editions',
	] );
	?>
</section>
<?php
get_footer();
