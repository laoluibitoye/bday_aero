<?php
/**
 * Daily cartoon view (WordPress's native single-{post_type}.php convention
 * for the 'cartoons' CPT — previously absent, so cartoon posts fell back to
 * the generic single.php article layout instead of a dedicated presentation).
 *
 * Shows the day's cartoon large, with prev/next chronological navigation so
 * a reader can page through past editions day-by-day without bouncing back
 * to the archive grid each time (the standard comic-strip-archive pattern).
 * Prev/next use WordPress's own adjacent-post lookup (indexed, cheap — not
 * a fresh WP_Query) rather than re-querying the whole archive.
 */
get_header();

if ( have_posts() ) :
	the_post();
	// get_adjacent_post() (which these wrap) restricts to the current
	// post's own post type automatically, so this stays within 'cartoons'
	// without needing a taxonomy filter — a cheap indexed lookup, not a
	// fresh WP_Query.
	$prev_cartoon = get_previous_post();
	$next_cartoon = get_next_post();
	?>
	<section id="cartoon-single">
		<div class="breadcrumb">
			<ul>
				<li><a href="<?= esc_url( home_url( '/' ) ) ?>">Home</a></li>
				<li>></li>
				<li><a href="<?= esc_url( get_post_type_archive_link( 'cartoons' ) ) ?>">Cartoons</a></li>
				<li>></li>
				<li><?= esc_html( get_the_title() ) ?></li>
			</ul>
		</div>

		<article class="cartoon-daily">
			<header class="cartoon-daily__header">
				<h1><?= esc_html( get_the_title() ) ?></h1>
				<span class="cartoon-daily__date"><?= esc_html( custom_time_format( get_the_date( 'c' ), 'full' ) ) ?></span>
			</header>

			<figure class="cartoon-daily__image">
				<?= get_thumbnail( [ 'post_id' => get_the_ID(), 'size' => 'featured', 'classes' => 'cartoon-daily__img' ] ) ?>
			</figure>

			<?php if ( bd_page_allows_ads() ) : ?>
				<?= do_shortcode('[admanager ad_id="desktop_1" placement="desktop" lazy="false"]'); ?>
			<?php endif; ?>

			<nav class="cartoon-daily__nav" aria-label="Cartoon navigation">
				<?php if ( $prev_cartoon ) : ?>
					<a class="cartoon-daily__prev" href="<?= esc_url( get_permalink( $prev_cartoon ) ) ?>">
						« <?= esc_html( get_the_title( $prev_cartoon ) ) ?>
					</a>
				<?php endif; ?>
				<a class="cartoon-daily__all" href="<?= esc_url( get_post_type_archive_link( 'cartoons' ) ) ?>">All editions</a>
				<?php if ( $next_cartoon ) : ?>
					<a class="cartoon-daily__next" href="<?= esc_url( get_permalink( $next_cartoon ) ) ?>">
						<?= esc_html( get_the_title( $next_cartoon ) ) ?> »
					</a>
				<?php endif; ?>
			</nav>
		</article>

		<?php get_template_part( 'template-parts/cartoon/past-editions-grid', null, [ 'exclude_id' => get_the_ID(), 'limit' => 8, 'heading' => 'More editions' ] ); ?>
	</section>
	<?php
endif;

get_footer();
