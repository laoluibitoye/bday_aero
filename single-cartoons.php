<?php
/** Daily cartoon view — WordPress's native single-{post_type}.php convention for the 'cartoons' CPT (addons/cartoons/). */
get_header();

if ( have_posts() ) :
	the_post();
	$post_id       = get_the_ID();
	$prev_cartoon  = get_previous_post();
	$next_cartoon  = get_next_post();
	/**
	 * Phase 9 (universal content gating rollout): previously rendered the
	 * full-size cartoon unconditionally, regardless of whether 'cartoons'
	 * was added to the (now admin-configurable, see class-admin.php)
	 * restricted post types — the gating engine itself was already
	 * post-type-agnostic, this template simply never called it. Passing
	 * the image markup itself as bday_aero_gate_content()'s $content: a
	 * non-gated cartoon renders exactly as before (the function returns
	 * $content unchanged), a gated one gets wrapped in the same soft/hard
	 * preview markup an article would.
	 */
	$is_gated = bday_aero_is_post_gated( $post_id );
	?>
	<section id="cartoon-single" class="bday-container">
		<article class="cartoon-daily">
			<header class="cartoon-daily__header">
				<h1><?php the_title(); ?></h1>
				<span class="cartoon-daily__date"><?php echo esc_html( bday_format_date( get_the_date( 'c' ) ) ); ?></span>
			</header>

			<figure class="cartoon-daily__image">
				<?php echo bday_aero_gate_content( $post_id, bday_get_thumbnail( $post_id, 'featured', 'cartoon-daily__img' ) ); ?>
			</figure>

			<?php if ( ! $is_gated ) : ?>
				<?php bday_ad_zone( 'below_article_recirculation' ); ?>
			<?php endif; ?>

			<nav class="cartoon-daily__nav" aria-label="Cartoon navigation">
				<?php if ( $prev_cartoon ) : ?>
					<a class="cartoon-daily__prev" href="<?php echo esc_url( get_permalink( $prev_cartoon ) ); ?>">« <?php echo esc_html( get_the_title( $prev_cartoon ) ); ?></a>
				<?php endif; ?>
				<a class="cartoon-daily__all" href="<?php echo esc_url( get_post_type_archive_link( 'cartoons' ) ); ?>">All editions</a>
				<?php if ( $next_cartoon ) : ?>
					<a class="cartoon-daily__next" href="<?php echo esc_url( get_permalink( $next_cartoon ) ); ?>"><?php echo esc_html( get_the_title( $next_cartoon ) ); ?> »</a>
				<?php endif; ?>
			</nav>
		</article>

		<?php get_template_part( 'addons/cartoons/template-parts/past-editions-grid', null, array( 'exclude_id' => get_the_ID(), 'limit' => 8, 'heading' => 'More editions' ) ); ?>
	</section>
	<?php
endif;

get_footer();
