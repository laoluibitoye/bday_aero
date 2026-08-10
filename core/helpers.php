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
