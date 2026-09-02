<?php
/**
 * Template Name: Today's Paper
 *
 * Reader-requested — modeled on nytimes.com/section/todayspaper's *shape*
 * (dated header, the day's print edition front-and-center, then marked
 * stories as one masonry) without its page-identifier labels (Page A1/B1
 * etc — explicitly not wanted). Originally grouped marked posts by
 * category into separate per-section masonries; reader-requested removal
 * of that grouping — every marked post now sits in one single masonry,
 * ordered purely by each post's own display-size tier (metabox.php), not
 * by what category it happens to carry. Full site chrome
 * (get_header()/get_footer()), not the minimal funnel template — this is
 * a real content destination, not a funnel step.
 *
 * Two independently-sourced things on this page:
 *  - The e-paper cover + "Read Edition" button reuse the exact mechanism
 *    single-bday_edition.php already established (addons/editions/) — the
 *    same bday_get_posts()+tax_query lookup addons/editions/includes/
 *    homepage.php uses for its own "latest edition per publication" cards,
 *    and the same data-bd-edition-* button contract
 *    sdk/src/edition-download.ts already handles unconditionally on every
 *    page load. No new download code.
 *  - The marked-story list comes from addons/todays-paper/includes/
 *    query.php's bday_todays_paper_posts_for_date() (shared with
 *    templates/template-epaper-articles.php's date-picker version of this
 *    same page) — posts flagged via `_bday_todays_paper` post meta AND
 *    published today; a post flagged today but published on an earlier
 *    day does not appear here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$bday_epaper_edition = null;
if ( post_type_exists( 'bday_edition' ) ) {
	$bday_epaper_term = get_term_by( 'slug', 'e-paper', 'edition_publication' );
	if ( $bday_epaper_term ) {
		$bday_epaper_editions = bday_get_posts(
			array(
				'post_type'       => 'bday_edition',
				'numberposts'     => 1,
				'tax_query'       => array(
					array( 'taxonomy' => 'edition_publication', 'field' => 'term_id', 'terms' => $bday_epaper_term->term_id ),
				),
				'cache_namespace' => 'todays_paper',
			)
		);
		$bday_epaper_edition = $bday_epaper_editions[0] ?? null;
	}
}

// Publish-date scoped (bday_todays_paper_posts_for_date(), addons/todays-
// paper/includes/query.php) — a post flagged for today's paper is only
// shown here if it was also *published* today; flagging an older post
// today does not surface it, and nothing accumulates across days.
$bday_marked_posts = bday_todays_paper_posts_for_date(
	(int) current_time( 'Y' ),
	(int) current_time( 'n' ),
	(int) current_time( 'j' )
);
?>
<section class="bday-todays-paper-page">
	<div class="bday-container">
		<header class="bday-todays-paper-page__header">
			<span class="bday-eyebrow">Today's Paper</span>
			<h1><?php echo esc_html( date_i18n( 'l, F j, Y' ) ); ?></h1>
		</header>

		<?php if ( $bday_epaper_edition ) : ?>
			<div class="bday-todays-paper-page__edition">
				<a href="<?php echo esc_url( get_permalink( $bday_epaper_edition ) ); ?>" class="bday-todays-paper-page__edition-media">
					<?php echo bday_get_thumbnail( $bday_epaper_edition->ID, 'pdf_thumbnail' ); ?>
				</a>
				<div class="bday-todays-paper-page__edition-body">
					<h2>The Print Edition</h2>
					<p><?php echo esc_html( bday_format_date( $bday_epaper_edition->post_date ) ); ?></p>
					<?php
					// Same open/gated branch as single-bday_edition.php — see
					// bday_edition_type_is_restricted()'s docblock.
					$bday_epaper_direct_url = ! bday_edition_type_is_restricted() ? bday_edition_build_signed_download_url( $bday_epaper_edition->ID ) : null;
					?>
					<?php if ( null !== $bday_epaper_direct_url ) : ?>
						<a class="bday-btn-link" href="<?php echo esc_url( bday_edition_reader_url( $bday_epaper_direct_url ) ); ?>" data-bd-edition-open target="_blank" rel="noopener">Download today's edition</a>
					<?php else : ?>
						<button
							type="button"
							class="bday-btn-link"
							data-bd-edition-download
							data-bd-edition-publication="e-paper"
							data-bd-edition-date="<?php echo esc_attr( get_the_date( 'Y-m-d', $bday_epaper_edition ) ); ?>"
						>Download today's edition</button>
						<p class="bday-todays-paper-page__edition-status" data-bd-edition-status role="status"></p>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php bday_todays_paper_render_masonry( $bday_marked_posts, "Nothing has been marked for today's paper yet — check back soon." ); ?>
	</div>
</section>
<?php
get_footer();
