<?php
/**
 * Single e-edition landing page — WordPress's native single-{post_type}.php
 * convention for the bday_edition CPT (addons/editions/). Two modes,
 * decided by bday_edition_type_is_restricted() (secure-storage.php):
 * restricted (the default) renders "Read Edition" as a plain button
 * (sdk/src/edition-download.ts) that fetches a short-lived signed URL
 * from subscription-service only once clicked, checking the reader's
 * archive-access entitlement at that moment; unrestricted — an editor
 * removed bday_edition from aero-paywall's "Restricted Post Types" —
 * renders a direct, self-signed link instead (no click-time fetch, no
 * login, no entitlement check at all), matching how removing any other
 * content type from that list already makes it fully open.
 *
 * Below the cover/download block, also shows this edition date's
 * "Today's Paper"/"Weekender" marked articles (addons/todays-paper/
 * includes/query.php) — reader-requested: someone landing on a specific
 * edition should see that day's featured stories right there, not have
 * to separately go find the E-Paper Articles page and pick the same date
 * again. Uses the edition's own post_date and taxonomy term, not
 * "today" — this page can be for any past edition.
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

			<?php if ( $publication ) :
				$direct_url = ! bday_edition_type_is_restricted() ? bday_edition_build_signed_download_url( $post_id ) : null;
				?>
				<?php if ( null !== $direct_url ) : ?>
					<a
						class="bday-btn-link bday-edition-single__read-btn"
						href="<?php echo esc_url( bday_edition_reader_url( $direct_url ) ); ?>"
						data-bd-edition-open
						target="_blank"
						rel="noopener"
					>Read Edition</a>
				<?php else : ?>
					<button
						type="button"
						class="bday-btn-link bday-edition-single__read-btn"
						data-bd-edition-download
						data-bd-edition-publication="<?php echo esc_attr( $publication->slug ); ?>"
						data-bd-edition-date="<?php echo esc_attr( get_the_date( 'Y-m-d' ) ); ?>"
					>Read Edition</button>
					<p class="bday-edition-single__status" data-bd-edition-status role="status"></p>
				<?php endif; ?>

				<a class="bday-edition-single__archive-link" href="<?php echo esc_url( get_term_link( $publication ) ); ?>">See past editions of <?php echo esc_html( $publication->name ); ?></a>
			<?php endif; ?>
		</article>
	</section>

	<?php
	// Full-width, outside .bday-edition-single (which is capped at 480px
	// for the cover/header block above — too narrow for the masonry grid
	// below). function_exists() guard: the todays-paper addon is
	// independently toggleable, this page shouldn't fatal if it's off.
	if ( $publication && function_exists( 'bday_todays_paper_posts_for_date' ) ) {
		$bday_edition_articles = bday_todays_paper_posts_for_date(
			(int) get_the_date( 'Y', $post_id ),
			(int) get_the_date( 'n', $post_id ),
			(int) get_the_date( 'j', $post_id ),
			$publication->slug
		);
		if ( ! empty( $bday_edition_articles ) ) {
			?>
			<section class="bday-container bday-edition-single__articles">
				<h2 class="bday-section-heading">Articles in this edition</h2>
				<?php bday_todays_paper_render_masonry( $bday_edition_articles ); ?>
			</section>
			<?php
		}
	}
	?>
	<?php
endif;

get_footer();
