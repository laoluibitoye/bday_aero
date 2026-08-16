<?php
/**
 * Addon Name: Featured Video Cards
 * Addon Slug: featured-video-cards
 * Description: Swaps a post card's thumbnail for a click-to-play video facade when a featured video is set.
 * Cache Namespace: featured_video_cards
 * Settings Tab: Featured Video Cards
 * Default: off
 *
 * Deep Dive §9: NYT-style muted/autoplaying video in place of a static card
 * thumbnail. Hooks core/helpers.php's bday_get_card_media() filter seam
 * (bday_card_media_html) rather than being called directly by any of the
 * five .bday-card templates — disabling this addon means the filter simply
 * has no listener and every card silently reverts to a plain thumbnail, no
 * template changes needed either way.
 *
 * Ships as a *facade* (web.dev's third-party-embed pattern), same posture
 * as addons/bday-live's hero embed: a static poster pulled from YouTube's
 * own thumbnail CDN (no request to YouTube at all until a reader actually
 * scrolls the card into view), with the real iframe only swapped in by
 * script.js's IntersectionObserver-gated bdayInitFeaturedVideoCards(), and
 * only when the reader hasn't asked for reduced motion or a data saver.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'bday_card_media_html',
	static function ( string $html, int $post_id, string $size, string $classes ): string {
		$video_id = get_post_meta( $post_id, '_featured_video_id', true );
		if ( empty( $video_id ) ) {
			return $html;
		}

		$poster = 'https://i.ytimg.com/vi/' . rawurlencode( $video_id ) . '/hqdefault.jpg';

		ob_start();
		?>
		<span class="bday-video-facade <?php echo esc_attr( $classes ); ?>" data-bd-video-facade data-video-id="<?php echo esc_attr( $video_id ); ?>">
			<img src="<?php echo esc_url( $poster ); ?>" alt="" class="bday-video-facade__poster" loading="lazy">
			<span class="bday-video-facade__badge">&#9654; Video</span>
			<span class="bday-video-facade__controls" hidden>
				<button type="button" class="bday-video-facade__btn" data-bd-video-mute aria-label="Unmute video" aria-pressed="true">
					<svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 9v6h4l5 5V4L8 9H4z" fill="currentColor"/><path d="M16 8l5 8M21 8l-5 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
				</button>
				<button type="button" class="bday-video-facade__btn" data-bd-video-pause aria-label="Pause video" aria-pressed="false">
					<svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 4h4v16H6zM14 4h4v16h-4z" fill="currentColor"/></svg>
				</button>
			</span>
		</span>
		<?php
		return (string) ob_get_clean();
	},
	10,
	4
);
