<?php
/**
 * General-purpose template helpers used across core templates and add-ons.
 * Ported from the previous theme's functions.php/functions/widgets.php —
 * behavior unchanged, just consolidated into one place instead of scattered
 * across three files.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post thumbnail with a graceful fallback image when a post has none, so
 * templates never have to special-case a missing thumbnail themselves.
 */
function bday_get_thumbnail( int $post_id, string $size, string $classes = '' ): string {
	if ( has_post_thumbnail( $post_id ) ) {
		return get_the_post_thumbnail( $post_id, $size, array( 'class' => $classes ) );
	}
	$fallback = apply_filters( 'bday_thumbnail_fallback_url', 'https://cdn.businessday.ng/wp-content/uploads/2023/11/Business-Day-Grey-e1691776368938.jpg' );
	return '<img src="' . esc_url( $fallback ) . '" alt="BusinessDay" class="' . esc_attr( $classes ) . '">';
}

/**
 * Card-media HTML: the thumbnail, or a video facade when the post has a
 * _featured_video_id and the featured-video-cards add-on is on. Routed
 * through a filter (rather than checking the meta here) so this stays
 * addon-agnostic — bday_get_thumbnail() alone is unaware video cards exist
 * at all, same separation the add-on loader already enforces everywhere
 * else (a disabled add-on's file is never required, so the filter simply
 * has no listener and this silently degrades to a plain thumbnail).
 */
function bday_get_card_media( int $post_id, string $size, string $classes = '' ): string {
	$html = bday_get_thumbnail( $post_id, $size, $classes );
	/**
	 * Small play badge over the thumbnail for any video-format post,
	 * everywhere a card/topic-list media slot renders — one place rather
	 * than re-checking has_post_format() at every homepage call site.
	 * Purely visual (the video-facade addon's own click-to-play swap is a
	 * separate, opt-in mechanism keyed off _featured_video_id, not this).
	 */
	if ( has_post_format( 'video', $post_id ) ) {
		$html .= '<span class="bday-media-badge bday-media-badge--video" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span>';
	}
	return apply_filters( 'bday_card_media_html', $html, $post_id, $size, $classes );
}

/**
 * The one `.bday-card` renderer, consolidating what used to be five
 * near-identical copies of this markup (rail.php, listing.php,
 * bottom-widgets.php's magazine row, single-default.php's "You Might Also
 * Like", and weekend.php's printf-built version) — Phase 2 of the Bday_Aero
 * roadmap. Returns a string (not an echo) so weekend.php's printf-style
 * homepage-variant building keeps working unchanged.
 *
 * $args:
 *   size          string  thumbnail size, default 'medium_rectangle'
 *   show_byline   bool    author + time-ago row, default false
 *   show_excerpt  bool    trimmed excerpt paragraph, default false
 *   excerpt_words int     default 20
 *   card_class    string  extra class(es) appended to the <article>, default ''
 */
function bday_card_html( WP_Post $post, array $args = array() ): string {
	$args = wp_parse_args(
		$args,
		array(
			'size'          => 'medium_rectangle',
			'show_byline'   => false,
			'show_excerpt'  => false,
			'excerpt_words' => 20,
			'card_class'    => '',
		)
	);

	$permalink = get_permalink( $post );
	$card_class = trim( 'bday-card ' . $args['card_class'] );

	ob_start();
	?>
	<article class="<?php echo esc_attr( $card_class ); ?>">
		<a href="<?php echo esc_url( $permalink ); ?>" class="bday-card__media"><?php echo bday_get_card_media( $post->ID, $args['size'] ); ?></a>
		<h3 class="bday-card__title"><a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></h3>
		<?php if ( $args['show_byline'] ) : ?>
			<div class="bday-byline">
				<span><?php echo esc_html( get_the_author_meta( 'display_name', $post->post_author ) ); ?></span>
				<span><?php echo esc_html( bday_time_ago( $post->post_date ) ); ?></span>
			</div>
		<?php endif; ?>
		<?php if ( $args['show_excerpt'] ) : ?>
			<p class="bday-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt( $post ), $args['excerpt_words'] ) ); ?></p>
		<?php endif; ?>
	</article>
	<?php
	return (string) ob_get_clean();
}

/**
 * The "Opinion-style" homepage section: one lead card (thumbnail, title, excerpt, a kicker line)
 * plus a grid of shorter pieces each with the author's avatar — shared by Opinion, Partner &
 * Sponsored Content, and YSoT, so restyling one restyles all three consistently rather than
 * hand-rolling the same markup three times. `author_position` is the one real layout difference
 * between them: Opinion shows the avatar/name above the title (byline-first), while Partner &
 * Sponsored Content and YSoT show it under the excerpt instead (reader-requested, so a partnered
 * post doesn't read as if the author is the headline).
 *
 * $args:
 *   posts            WP_Post[]  required — posts[0] is the lead, up to 6 more form the grid
 *   heading          string     section <h2> text
 *   see_more_url     string     link target for the section-head "See more" kicker
 *   see_more_label   string     default 'See more →'
 *   lead_kicker      string     text before " · <date>" under the lead card, default 'Editorial'
 *   author_position  string     'above' (Opinion's default) or 'below' the excerpt
 *   screen_label     string     data-screen-label on the <section>, for analytics
 */
function bday_render_editorial_grid_section( array $args ): void {
	$args = wp_parse_args(
		$args,
		array(
			'posts'           => array(),
			'heading'         => '',
			'see_more_url'    => '',
			'see_more_label'  => 'See more →',
			'lead_kicker'     => 'Editorial',
			'author_position' => 'above',
			'screen_label'    => '',
		)
	);

	$posts = $args['posts'];
	if ( empty( $posts ) ) {
		return;
	}

	$lead = $posts[0];
	$grid = array_slice( $posts, 1, 6 );
	?>
	<section class="bday-rd-opinion" data-screen-label="<?php echo esc_attr( $args['screen_label'] ); ?>">
		<div class="bday-container">
			<div class="bday-rd-section-head">
				<h2><?php echo esc_html( $args['heading'] ); ?></h2>
				<span class="bday-rd-rule"></span>
				<?php if ( $args['see_more_url'] ) : ?>
					<a href="<?php echo esc_url( $args['see_more_url'] ); ?>" class="bday-rd-kicker bday-rd-kicker--accent"><?php echo esc_html( $args['see_more_label'] ); ?></a>
				<?php endif; ?>
			</div>
			<div class="bday-rd-opinion__grid">
				<a href="<?php echo esc_url( get_permalink( $lead ) ); ?>" class="bday-rd-opinion__lead">
					<?php if ( has_post_thumbnail( $lead->ID ) ) : ?><?php echo bday_get_thumbnail( $lead->ID, 'medium_standard' ); ?><?php endif; ?>
					<h3><?php echo esc_html( get_the_title( $lead ) ); ?></h3>
					<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $lead ), 22 ) ); ?></p>
					<span class="bday-rd-kicker bday-rd-kicker--accent"><?php echo esc_html( $args['lead_kicker'] ); ?> · <?php echo esc_html( bday_format_date( $lead->post_date ) ); ?></span>
				</a>
				<div class="bday-rd-opinion__list">
					<?php foreach ( $grid as $post ) : ?>
						<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="bday-rd-opinion__item bday-rd-opinion__item--author-<?php echo esc_attr( $args['author_position'] ); ?>">
							<?php if ( 'above' === $args['author_position'] ) : ?>
								<span class="bday-rd-opinion__author"><?php echo get_avatar( $post->post_author, 40 ); ?><span class="bday-rd-kicker bday-rd-kicker--faint"><?php echo esc_html( get_the_author_meta( 'display_name', $post->post_author ) ); ?></span></span>
							<?php endif; ?>
							<h4><?php echo esc_html( get_the_title( $post ) ); ?></h4>
							<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $post ), 16 ) ); ?></p>
							<?php if ( 'below' === $args['author_position'] ) : ?>
								<span class="bday-rd-opinion__author"><?php echo get_avatar( $post->post_author, 32 ); ?><span class="bday-rd-kicker bday-rd-kicker--faint"><?php echo esc_html( get_the_author_meta( 'display_name', $post->post_author ) ); ?></span></span>
							<?php endif; ?>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</section>
	<?php
}

/**
 * A "Load more" button replacing page-number pagination sitewide (reader-requested — no
 * `?paged=2` links anywhere in the theme anymore). Deliberately not a new AJAX/REST endpoint:
 * `assets/src/js/load-more.js` just fetches the next page's full URL (a plain, cacheable GET,
 * identical to what a page-number link would have loaded) and lifts the matching
 * `$target_selector` element's children out of it — every pagination call site already renders a
 * normal, cacheable page for `?paged=N`, so this reuses that instead of standing up a parallel
 * "fetch just the fragment" endpoint for the same content.
 */
function bday_render_load_more_button( string $target_selector, int $current_page, int $max_pages ): void {
	if ( $max_pages <= $current_page ) {
		return;
	}
	?>
	<div class="bday-load-more" data-bday-load-more data-target="<?php echo esc_attr( $target_selector ); ?>" data-next-url="<?php echo esc_url( get_pagenum_link( $current_page + 1 ) ); ?>">
		<button type="button" class="bday-load-more__button">Load more</button>
	</div>
	<?php
}

/**
 * Points at the e-paper category archive rather than a guessed page slug
 * Points at the real Today's Paper page (templates/template-todays-
 * paper.php, addons/todays-paper/) when a Page using that template
 * exists — the actual editor-curated destination (marked stories +
 * e-paper cover/download), not just a plain category archive. Falls back
 * to the e-paper category link when no such Page has been created yet,
 * same "never a dead link" posture this helper always had. Extracted
 * from header.php (utility-bar "Today's Paper" link) so every other
 * "Today's Paper" link in the theme (bottom-widgets.php's homepage
 * teaser, the new masthead button, the footer) shares one resolution
 * path rather than each hardcoding its own.
 */
function bday_epaper_url(): string {
	$pages = bday_get_posts(
		array(
			'post_type'       => 'page',
			'numberposts'     => 1,
			'meta_key'        => '_wp_page_template',
			'meta_value'      => 'templates/template-todays-paper.php',
			'cache_namespace' => 'core',
		)
	);
	if ( ! empty( $pages ) ) {
		return (string) get_permalink( $pages[0] );
	}

	$category = get_category_by_slug( 'e-paper' );
	return $category ? (string) get_category_link( $category ) : home_url( '/' );
}

function bday_category_url( string $slug ): string {
	$category = get_category_by_slug( $slug );
	return $category ? (string) get_category_link( $category->term_id ) : '#';
}

/** Human-relative time ("3 hours ago") for compact listing rows. */
function bday_time_ago( string $datetime ): string {
	$diff = time() - strtotime( $datetime );

	if ( $diff <= 60 ) {
		return 'just now';
	}
	if ( $diff <= HOUR_IN_SECONDS ) {
		$m = (int) round( $diff / 60 );
		return 1 === $m ? 'one minute ago' : "{$m} minutes ago";
	}
	if ( $diff <= DAY_IN_SECONDS ) {
		$h = (int) round( $diff / HOUR_IN_SECONDS );
		return 1 === $h ? 'an hour ago' : "{$h} hrs ago";
	}
	if ( $diff <= 7 * DAY_IN_SECONDS ) {
		$d = (int) round( $diff / DAY_IN_SECONDS );
		return 1 === $d ? 'yesterday' : "{$d} days ago";
	}
	if ( $diff <= 30 * DAY_IN_SECONDS ) {
		$w = (int) round( $diff / WEEK_IN_SECONDS );
		return 1 === $w ? 'a week ago' : "{$w} weeks ago";
	}
	if ( $diff <= YEAR_IN_SECONDS ) {
		$mo = (int) round( $diff / ( 30 * DAY_IN_SECONDS ) );
		return 1 === $mo ? 'a month ago' : "{$mo} months ago";
	}
	$y = (int) round( $diff / YEAR_IN_SECONDS );
	return 1 === $y ? 'one year ago' : "{$y} years ago";
}

/** "Mon 03, 2026"-style absolute date used on listing/byline rows. */
function bday_format_date( string $datetime ): string {
	return date_i18n( 'M d, Y', strtotime( $datetime ) );
}

/**
 * Same "not on a Page, except the front page" policy as
 * addons/vendors/addon.php's bday_page_allows_ads() — kept as an
 * independent check here (rather than a shared cross-addon call) since
 * social-share has no dependency on the ads/vendors addon at all, and
 * disabling that addon shouldn't silently change sharing behavior too.
 */
function bday_social_share_html( int $post_id ): string {
	if ( is_page() && ! is_front_page() ) {
		return '';
	}

	$url   = get_permalink( $post_id );
	$title = get_the_title( $post_id );

	ob_start();
	?>
	<div class="social-share">
		<span class="social-share__label">Share</span>
		<a class="social-share__item social-share__item--facebook" rel="nofollow noreferrer" aria-label="Facebook" target="_blank" href="<?php echo esc_url( 'https://www.facebook.com/sharer/sharer.php?u=' . $url ); ?>"><i class="bi bi-facebook"></i></a>
		<a class="social-share__item social-share__item--twitter" rel="nofollow noreferrer" aria-label="X (Twitter)" target="_blank" href="<?php echo esc_url( 'https://twitter.com/share?text=' . $title . '&url=' . $url ); ?>"><i class="bi bi-twitter"></i></a>
		<a class="social-share__item social-share__item--linkedin" rel="nofollow noreferrer" aria-label="LinkedIn" target="_blank" href="<?php echo esc_url( 'https://linkedin.com/shareArticle?mini=true&url=' . $url ); ?>"><i class="bi bi-linkedin"></i></a>
		<a class="social-share__item social-share__item--telegram" rel="nofollow noreferrer" aria-label="Telegram" target="_blank" href="<?php echo esc_url( 'https://telegram.me/share/url?url=' . $url . '&text=' . $title ); ?>"><i class="bi bi-telegram"></i></a>
		<a class="social-share__item social-share__item--whatsapp" rel="nofollow noreferrer" aria-label="WhatsApp" target="_blank" href="<?php echo esc_url( 'https://api.whatsapp.com/send?text=' . $url ); ?>"><i class="bi bi-whatsapp"></i></a>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Estimated reading time in whole minutes (225 wpm, the commonly-cited
 * average for adult prose reading — same figure Medium/WordPress.com use).
 * Phase 3 of the Bday_Aero roadmap: "reading is a feature surface."
 */
function bday_estimate_read_time( int $post_id ): int {
	$word_count = str_word_count( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ) );
	return max( 1, (int) ceil( $word_count / 225 ) );
}

/**
 * Slugifies every <h2>/<h3> in already-rendered article HTML with a
 * stable, collision-safe id (so a same-titled heading twice in one
 * article doesn't produce two identical anchors), and returns both the
 * modified HTML and a flat table-of-contents array for the caller to
 * render as nav links. Regex over trusted, editor-authored post content
 * (not user input) — consistent with how the rest of this theme already
 * treats post content, e.g. bday_rss_featured_image() above.
 *
 * @return array{content: string, toc: array<int, array{id: string, text: string, level: int}>}
 */
function bday_add_heading_anchors( string $content ): array {
	$toc  = array();
	$seen = array();

	$content = preg_replace_callback(
		'/<h([23])([^>]*)>(.*?)<\/h\1>/is',
		static function ( array $m ) use ( &$toc, &$seen ): string {
			$level = (int) $m[1];
			$text  = trim( wp_strip_all_tags( $m[3] ) );
			if ( '' === $text ) {
				return $m[0];
			}
			$slug = sanitize_title( $text );
			if ( isset( $seen[ $slug ] ) ) {
				++$seen[ $slug ];
				$slug .= '-' . $seen[ $slug ];
			} else {
				$seen[ $slug ] = 1;
			}
			$toc[] = array( 'id' => $slug, 'text' => $text, 'level' => $level );
			return '<h' . $level . $m[2] . ' id="' . esc_attr( $slug ) . '">' . $m[3] . '</h' . $level . '>';
		},
		$content
	);

	return array( 'content' => (string) $content, 'toc' => $toc );
}

add_filter( 'excerpt_more', '__return_empty_string' );
add_filter( 'excerpt_length', static fn() => 20, 999 );

add_filter(
	'get_the_archive_title',
	static function ( string $title ): string {
		if ( is_category() ) {
			return single_cat_title( '', false );
		}
		if ( is_tag() ) {
			return single_tag_title( '', false );
		}
		if ( is_author() ) {
			return '<span class="vcard">' . get_the_author() . '</span>';
		}
		if ( is_post_type_archive() ) {
			return post_type_archive_title( '', false );
		}
		if ( is_tax() ) {
			return single_term_title( '', false );
		}
		return $title;
	}
);
