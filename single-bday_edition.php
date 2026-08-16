<?php
/**
 * Single e-edition landing page — WordPress's native single-{post_type}.php
 * convention for the bday_edition CPT (addons/editions/). The PDF itself
 * is never served from here: "Read Edition" is a plain button
 * (sdk/src/edition-download.ts) that fetches a short-lived signed URL
 * from subscription-service only once clicked, checking the reader's
 * archive-access entitlement at that moment (Phase 10) — this page
 * itself has nothing to gate, it's just the cover + metadata.
 */
get_header();

if ( have_posts() ) :
	the_post();
	$post_id = get_the_ID();
	$terms   = wp_get_post_terms( $post_id, 'edition_publication' );
	$publication = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? $terms[0] : null;
	?>
	<section id="edition-single" class="bday-container">
		<article class="bday-edition-single">
			<header class="bday-edition-single__header">
				<?php if ( $publication ) : ?>
					<span class="bday-eyebrow"><a href="<?php echo esc_url( get_term_link( $publication ) ); ?>"><?php echo esc_html( $publication->name ); ?></a></span>
				<?php endif; ?>
				<h1><?php the_title(); ?></h1>
				<span class="bday-edition-single__date"><?php echo esc_html( bday_format_date( get_the_date( 'c' ) ) ); ?></span>
			</header>

			<figure class="bday-edition-single__cover">
				<?php echo bday_get_thumbnail( $post_id, 'top_story', 'bday-edition-single__cover-img' ); ?>
			</figure>

			<?php if ( $publication ) : ?>
				<button
					type="button"
					class="bday-btn-link bday-edition-single__read-btn"
					data-bd-edition-download
					data-bd-edition-publication="<?php echo esc_attr( $publication->slug ); ?>"
					data-bd-edition-date="<?php echo esc_attr( get_the_date( 'Y-m-d' ) ); ?>"
				>Read Edition</button>
				<p class="bday-edition-single__status" data-bd-edition-status role="status"></p>

				<a class="bday-edition-single__archive-link" href="<?php echo esc_url( get_term_link( $publication ) ); ?>">See past editions of <?php echo esc_html( $publication->name ); ?></a>
			<?php endif; ?>
		</article>
	</section>
	<?php
endif;

get_footer();
