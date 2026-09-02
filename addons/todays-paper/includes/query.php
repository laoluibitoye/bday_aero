<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared query + display-tier logic for both templates/template-todays-
 * paper.php (today only) and templates/template-epaper-articles.php (any
 * date, via its calendar picker) — one implementation so the two pages
 * can never drift on what "marked for today's paper on date X" actually
 * means.
 *
 * Scoped by post_date, not by _bday_todays_paper_date (when the checkbox
 * was last (re-)saved, still stamped by metabox.php but no longer what
 * this queries against) — reader-requested: the page should reflect
 * content actually published that day, not old content an editor merely
 * flagged today. Marking an old post for today's paper without it having
 * been published today means it simply won't appear here.
 *
 * $publication is one of bday_todays_paper_publications()'s keys
 * ('e-paper'/'weekender', matching edition_publication term slugs — see
 * that function's docblock). Every post flagged before the Weekender
 * option existed has no `_bday_todays_paper_publication` meta at all;
 * treated as 'e-paper' (what "featured" implicitly meant back then), not
 * excluded — the OR clause below covers both an explicit 'e-paper' value
 * and that legacy unset case, only for the 'e-paper' query itself.
 *
 * @return WP_Post[]
 */
function bday_todays_paper_posts_for_date( int $year, int $month, int $day, string $publication = 'e-paper' ): array {
	$meta_query = array(
		array( 'key' => '_bday_todays_paper', 'value' => '1' ),
	);
	if ( 'e-paper' === $publication ) {
		$meta_query[] = array(
			'relation' => 'OR',
			array( 'key' => '_bday_todays_paper_publication', 'value' => 'e-paper' ),
			array( 'key' => '_bday_todays_paper_publication', 'compare' => 'NOT EXISTS' ),
		);
	} else {
		$meta_query[] = array( 'key' => '_bday_todays_paper_publication', 'value' => $publication );
	}

	return bday_get_posts(
		array(
			'post_type'       => 'post',
			'numberposts'     => -1,
			'meta_query'      => $meta_query,
			'date_query'      => array(
				array(
					'year'  => $year,
					'month' => $month,
					'day'   => $day,
				),
			),
			'cache_namespace' => 'todays_paper',
		)
	);
}

/**
 * Weekend dates default to the Weekender edition, weekdays to Today's
 * Paper — reader-requested editorial pattern: "Weekender is the edition
 * that needs to be marked on weekends." Used by template-epaper-
 * articles.php to decide, from the selected date alone, which edition
 * (bday_edition PDF) and which marked-articles channel to show, with no
 * separate publication picker needed on that page.
 */
function bday_todays_paper_publication_for_date( DateTime $date ): string {
	$weekday = (int) $date->format( 'w' ); // 0 = Sunday, 6 = Saturday
	return ( 0 === $weekday || 6 === $weekday ) ? 'weekender' : 'e-paper';
}

/**
 * Reader-requested masonry: each marked post carries its own display-size
 * tier (addons/todays-paper/includes/metabox.php's "Display size" field,
 * `_bday_todays_paper_size` meta) — an editor picks how much visual
 * weight a story gets, independent of which category it's in. Maps each
 * tier to one of the theme's already-registered image sizes (core/theme-
 * setup.php) rather than a new crop; 'no-image' skips bday_card_html()'s
 * media block entirely via CSS (see .bday-todays-paper-masonry--no-image
 * in _premium.scss), not a second code path here.
 *
 * @return array{tier: string, class: string, image_size: string}
 */
function bday_todays_paper_tier( WP_Post $post ): array {
	$tier = (string) get_post_meta( $post->ID, '_bday_todays_paper_size', true ) ?: 'small';
	$map  = array(
		'large'    => 'featured',
		'medium'   => 'top_story',
		'small'    => 'medium_rectangle',
		'xsmall'   => 'small',
		'no-image' => 'small',
	);
	if ( ! isset( $map[ $tier ] ) ) {
		$tier = 'small';
	}
	return array(
		'tier'       => $tier,
		'class'      => 'bday-todays-paper-card bday-todays-paper-card--' . $tier,
		'image_size' => $map[ $tier ],
	);
}

/**
 * Renders the marked-posts masonry for a set of posts — shared by both
 * page templates so the card markup/options can't drift between them.
 */
/**
 * Jump-to-date picker for templates/template-epaper-articles.php — a
 * plain GET form, no JS required. Previously paired with a full
 * server-rendered month grid; reader-requested removal of the grid
 * (redundant once the native date input's own picker does the same job,
 * plus jumps years back in one interaction instead of dozens of
 * prev/next-month clicks) left this as the page's only date control.
 */
function bday_todays_paper_render_date_picker( string $selected_ymd, string $page_url ): void {
	$today_ymd = current_time( 'Y-m-d' );
	?>
	<form method="get" action="<?php echo esc_url( $page_url ); ?>" class="bday-epaper-date-picker">
		<label for="bday-epaper-jump-date" class="bday-epaper-date-picker__label">Jump to date</label>
		<input
			type="date"
			id="bday-epaper-jump-date"
			name="date"
			value="<?php echo esc_attr( $selected_ymd ); ?>"
			max="<?php echo esc_attr( $today_ymd ); ?>"
		/>
		<button type="submit" class="bday-btn-link bday-epaper-date-picker__submit">Go</button>
	</form>
	<?php
}

/**
 * The edition cover + "Read Edition" button for a given publication +
 * date — mirrors the block templates/template-todays-paper.php has
 * always had, and single-bday_edition.php's own open/gated branch,
 * pulled out here so template-epaper-articles.php can show the same
 * block for whichever date/edition a reader navigates to (not just
 * today's). Renders nothing if the editions/ addon isn't active or
 * there's simply no edition for that publication on that date — a date
 * with only marked articles and no edition is still a useful page.
 */
function bday_todays_paper_render_edition_block( string $publication_slug, int $year, int $month, int $day ): void {
	if ( ! post_type_exists( 'bday_edition' ) || ! function_exists( 'bday_edition_type_is_restricted' ) ) {
		return;
	}
	$term = get_term_by( 'slug', $publication_slug, 'edition_publication' );
	if ( ! $term || is_wp_error( $term ) ) {
		return;
	}
	$editions = bday_get_posts(
		array(
			'post_type'       => 'bday_edition',
			'numberposts'     => 1,
			'tax_query'       => array(
				array( 'taxonomy' => 'edition_publication', 'field' => 'term_id', 'terms' => $term->term_id ),
			),
			'date_query'      => array(
				array( 'year' => $year, 'month' => $month, 'day' => $day ),
			),
			'cache_namespace' => 'todays_paper',
		)
	);
	$edition = $editions[0] ?? null;
	if ( ! $edition ) {
		return;
	}

	$direct_url = ! bday_edition_type_is_restricted() ? bday_edition_build_signed_download_url( $edition->ID ) : null;
	?>
	<div class="bday-todays-paper-page__edition">
		<a href="<?php echo esc_url( get_permalink( $edition ) ); ?>" class="bday-todays-paper-page__edition-media">
			<?php echo bday_get_thumbnail( $edition->ID, 'pdf_thumbnail' ); ?>
		</a>
		<div class="bday-todays-paper-page__edition-body">
			<h2><?php echo esc_html( $term->name ); ?></h2>
			<p><?php echo esc_html( bday_format_date( $edition->post_date ) ); ?></p>
			<?php if ( null !== $direct_url ) : ?>
				<a class="bday-btn-link" href="<?php echo esc_url( bday_edition_reader_url( $direct_url ) ); ?>" data-bd-edition-open target="_blank" rel="noopener">Read Edition</a>
			<?php else : ?>
				<button
					type="button"
					class="bday-btn-link"
					data-bd-edition-download
					data-bd-edition-publication="<?php echo esc_attr( $publication_slug ); ?>"
					data-bd-edition-date="<?php echo esc_attr( get_the_date( 'Y-m-d', $edition ) ); ?>"
				>Read Edition</button>
				<p class="bday-todays-paper-page__edition-status" data-bd-edition-status role="status"></p>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

function bday_todays_paper_render_masonry( array $posts, string $empty_message = "Nothing was marked for today's paper on this date." ): void {
	if ( empty( $posts ) ) {
		printf( '<p class="bday-todays-paper-page__empty">%s</p>', esc_html( $empty_message ) );
		return;
	}
	echo '<div class="bday-todays-paper-masonry">';
	foreach ( $posts as $bday_post ) {
		$bday_tier = bday_todays_paper_tier( $bday_post );
		echo bday_card_html( // phpcs:ignore WordPress.Security.EscapeOutput -- bday_card_html() escapes its own output
			$bday_post,
			array(
				'show_byline'  => true,
				'show_excerpt' => in_array( $bday_tier['tier'], array( 'large', 'medium' ), true ),
				'card_class'   => $bday_tier['class'],
				'size'         => $bday_tier['image_size'],
			)
		);
	}
	echo '</div>';
}
