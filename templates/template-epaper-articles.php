<?php
/**
 * Template Name: E-Paper Articles
 *
 * Date-driven browse of any day's edition + "Today's Paper"/"Weekender"
 * marked posts — templates/template-todays-paper.php's any-date sibling,
 * sharing the exact same query (bday_todays_paper_posts_for_date()),
 * edition-block (bday_todays_paper_render_edition_block()), and card
 * markup (bday_todays_paper_render_masonry()), all in addons/todays-
 * paper/includes/query.php, so "today" and "any date here" can never show
 * different content for the same calendar day.
 *
 * `?date=Y-m-d` selects the day; missing/invalid falls back to today.
 * The publication (which edition + which marked-post channel) is not a
 * separate picker — bday_todays_paper_publication_for_date() derives it
 * from the day of week alone (weekend -> Weekender, weekday -> Today's
 * Paper), matching the editorial pattern of marking weekend posts under
 * the Weekender option in the post metabox.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$bday_epaper_requested = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : '';
$bday_epaper_selected  = false;
if ( '' !== $bday_epaper_requested ) {
	$bday_epaper_selected = DateTime::createFromFormat( 'Y-m-d', $bday_epaper_requested );
	// createFromFormat() is lenient about e.g. "2026-02-30" silently rolling
	// over into March — re-rendering the parsed value back through
	// format() and comparing against the original input catches anything
	// that didn't cleanly round-trip, rather than quietly showing a
	// different date than what was actually in the URL.
	if ( false !== $bday_epaper_selected && $bday_epaper_selected->format( 'Y-m-d' ) !== $bday_epaper_requested ) {
		$bday_epaper_selected = false;
	}
}
if ( false === $bday_epaper_selected ) {
	$bday_epaper_selected = new DateTime( current_time( 'Y-m-d' ) );
}

$bday_epaper_year        = (int) $bday_epaper_selected->format( 'Y' );
$bday_epaper_month       = (int) $bday_epaper_selected->format( 'n' );
$bday_epaper_day         = (int) $bday_epaper_selected->format( 'j' );
$bday_epaper_ymd         = $bday_epaper_selected->format( 'Y-m-d' );
$bday_epaper_publication = bday_todays_paper_publication_for_date( $bday_epaper_selected );

$bday_epaper_posts = bday_todays_paper_posts_for_date( $bday_epaper_year, $bday_epaper_month, $bday_epaper_day, $bday_epaper_publication );
?>
<section class="bday-todays-paper-page bday-epaper-articles-page">
	<div class="bday-container">
		<header class="bday-todays-paper-page__header">
			<span class="bday-eyebrow">E-Paper Articles</span>
			<h1><?php echo esc_html( date_i18n( 'l, F j, Y', $bday_epaper_selected->getTimestamp() ) ); ?></h1>
		</header>

		<?php bday_todays_paper_render_date_picker( $bday_epaper_ymd, get_permalink() ); ?>

		<?php bday_todays_paper_render_edition_block( $bday_epaper_publication, $bday_epaper_year, $bday_epaper_month, $bday_epaper_day ); ?>

		<?php bday_todays_paper_render_masonry( $bday_epaper_posts ); ?>
	</div>
</section>
<?php
get_footer();
