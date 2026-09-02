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

		<?php
		bday_todays_paper_render_edition_block(
			'e-paper',
			(int) current_time( 'Y' ),
			(int) current_time( 'n' ),
			(int) current_time( 'j' )
		);
		?>

		<?php bday_todays_paper_render_masonry( $bday_marked_posts, "Nothing has been marked for today's paper yet — check back soon." ); ?>
	</div>
</section>
<?php
get_footer();
