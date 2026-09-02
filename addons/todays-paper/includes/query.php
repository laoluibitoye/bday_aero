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
 * @return WP_Post[]
 */
function bday_todays_paper_posts_for_date( int $year, int $month, int $day ): array {
	return bday_get_posts(
		array(
			'post_type'       => 'post',
			'numberposts'     => -1,
			'meta_query'      => array(
				array( 'key' => '_bday_todays_paper', 'value' => '1' ),
			),
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
 * Server-rendered month calendar for templates/template-epaper-articles.php
 * — no JS required, every day is a plain link to `?date=Y-m-d` on the
 * given page. $selected_ymd (already-validated 'Y-m-d') gets its own
 * highlight distinct from "today"'s, since browsing to a past/future
 * month's calendar and today is neither in view nor selected.
 */
function bday_todays_paper_render_calendar( int $year, int $month, string $selected_ymd, string $page_url ): void {
	$first_of_month = DateTime::createFromFormat( 'Y-n-j', "{$year}-{$month}-1" );
	if ( false === $first_of_month ) {
		return;
	}
	$days_in_month = (int) $first_of_month->format( 't' );
	$start_of_week = (int) get_option( 'start_of_week', 0 ); // 0 = Sunday, WP core option
	$first_weekday = (int) $first_of_month->format( 'w' ); // 0 (Sun) .. 6 (Sat)
	$leading_blanks = ( $first_weekday - $start_of_week + 7 ) % 7;

	$prev = ( clone $first_of_month )->modify( '-1 month' );
	$next = ( clone $first_of_month )->modify( '+1 month' );
	$today_ymd = current_time( 'Y-m-d' );

	$weekday_labels = array( 'Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa' );
	$weekday_labels = array_merge( array_slice( $weekday_labels, $start_of_week ), array_slice( $weekday_labels, 0, $start_of_week ) );
	?>
	<div class="bday-epaper-calendar">
		<div class="bday-epaper-calendar__nav">
			<a href="<?php echo esc_url( add_query_arg( 'date', $prev->format( 'Y-m-01' ), $page_url ) ); ?>" class="bday-epaper-calendar__nav-link" aria-label="Previous month">&lsaquo;</a>
			<span class="bday-epaper-calendar__month"><?php echo esc_html( $first_of_month->format( 'F Y' ) ); ?></span>
			<a href="<?php echo esc_url( add_query_arg( 'date', $next->format( 'Y-m-01' ), $page_url ) ); ?>" class="bday-epaper-calendar__nav-link" aria-label="Next month">&rsaquo;</a>
		</div>
		<?php
		// Jump-to-date — reader-requested: paging month-by-month to reach
		// something from a year ago is tedious. Plain GET form, no JS
		// required; the native date input's own picker UI handles year/month
		// selection far faster than repeated prev/next clicks.
		?>
		<form method="get" action="<?php echo esc_url( $page_url ); ?>" class="bday-epaper-calendar__jump">
			<label for="bday-epaper-jump-date" class="screen-reader-text">Jump to date</label>
			<input
				type="date"
				id="bday-epaper-jump-date"
				name="date"
				value="<?php echo esc_attr( $selected_ymd ); ?>"
				max="<?php echo esc_attr( $today_ymd ); ?>"
			/>
			<button type="submit" class="bday-btn-link bday-epaper-calendar__jump-btn">Go</button>
		</form>
		<div class="bday-epaper-calendar__grid">
			<?php foreach ( $weekday_labels as $bday_cal_label ) : ?>
				<span class="bday-epaper-calendar__weekday"><?php echo esc_html( $bday_cal_label ); ?></span>
			<?php endforeach; ?>
			<?php for ( $bday_cal_i = 0; $bday_cal_i < $leading_blanks; $bday_cal_i++ ) : ?>
				<span class="bday-epaper-calendar__day bday-epaper-calendar__day--blank"></span>
			<?php endfor; ?>
			<?php for ( $bday_cal_day = 1; $bday_cal_day <= $days_in_month; $bday_cal_day++ ) :
				$bday_cal_ymd = sprintf( '%04d-%02d-%02d', $year, $month, $bday_cal_day );
				$bday_cal_classes = array( 'bday-epaper-calendar__day' );
				if ( $bday_cal_ymd === $selected_ymd ) {
					$bday_cal_classes[] = 'bday-epaper-calendar__day--selected';
				}
				if ( $bday_cal_ymd === $today_ymd ) {
					$bday_cal_classes[] = 'bday-epaper-calendar__day--today';
				}
				?>
				<a
					href="<?php echo esc_url( add_query_arg( 'date', $bday_cal_ymd, $page_url ) ); ?>"
					class="<?php echo esc_attr( implode( ' ', $bday_cal_classes ) ); ?>"
				><?php echo esc_html( (string) $bday_cal_day ); ?></a>
			<?php endfor; ?>
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
