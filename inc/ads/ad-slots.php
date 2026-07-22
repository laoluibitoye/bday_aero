<?php
/**
 * Ad placement policy + slot-rendering helpers.
 *
 * WHAT THIS FILE OWNS: (1) the deny-list deciding which page types carry
 * ads at all, and (2) the repeated per-slot HTML/JS boilerplate that used
 * to be hand-copied ~12x across templates/masterpage.php.
 *
 * WHAT THIS FILE DELIBERATELY DOES NOT TOUCH: GAM slot registration
 * (googletag.defineSlot calls, ad-unit paths, sizes) — that logic stays in
 * header.php's Dochase engine and its companion static bd_desktop_N /
 * bd_mobile_N block exactly as it runs in production today. Re-deriving
 * slot config from scratch here would risk silently breaking live ad
 * revenue with no way to verify GAM serves correctly outside production.
 */

/**
 * Ad placement policy — industry-standard for a news site: ads run on
 * every editorial/listing surface (homepage, articles, category/tag/
 * author/search, static pages, cartoons, e-edition) and are held back
 * only on transactional pages where an ad competes with the one task the
 * reader is there to complete (404, the AeroPaywall account/auth page,
 * the newsletter signup funnel). Implemented as a deny-list — new
 * templates get ads by default — because the theme's old opt-out
 * mechanism (#no-ads, checked only by 404.php) defaulted every other
 * page, including transactional ones, to ads-on with no policy behind it.
 */
function bd_page_allows_ads(): bool {
	if ( is_404() ) {
		return false;
	}

	if ( is_singular( 'page' ) ) {
		global $post;
		if ( $post && has_shortcode( $post->post_content, 'aeropaywall_account' ) ) {
			return false;
		}
		if ( is_page_template( 'templates/newsletter.php' ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Renders one GAM ad slot's display markup — the
 * `<div id="..."><script>googletag.display(...)</script></div>` boilerplate
 * every slot needs, wrapping it in the ad-container silent/filled treatment
 * (see the .ad-container CSS + the slotRenderEnded listener in header.php)
 * so an unfilled slot collapses cleanly instead of leaving a reserved gap.
 *
 * $div_id must match an id already registered by header.php's ad-slot
 * setup — this function only renders the display call, it does not
 * register slots.
 */
function bd_gam_slot( string $div_id, int $min_width = 300, int $min_height = 50, string $extra_classes = '' ): void {
	if ( ! bd_page_allows_ads() ) {
		return;
	}
	$classes = trim( $extra_classes );
	?>
	<div id="<?php echo esc_attr( $div_id ); ?>"<?php echo $classes ? ' class="' . esc_attr( $classes ) . '"' : ''; ?> style="min-width:<?php echo (int) $min_width; ?>px; min-height:<?php echo (int) $min_height; ?>px;">
		<script>
			googletag.cmd.push(function () { googletag.display('<?php echo esc_js( $div_id ); ?>'); });
		</script>
	</div>
	<?php
}

/**
 * Renders a hand-sold "direct ad" placement — a plain clickable image, not
 * a GAM slot. These rotate periodically (advertiser/image/link change) but
 * the surrounding markup was copy-pasted 3x in masterpage.php with only
 * the values swapped.
 */
function bd_direct_ad_slot( string $url, string $img_src, string $title, int $width = 970, int $height = 250 ): void {
	if ( ! bd_page_allows_ads() ) {
		return;
	}
	?>
	<div class="ad-container" style="text-align: center; line-height: 0; width: 100%;">
		<iframe
			srcdoc="<style>body{margin:0;padding:0;overflow:hidden;} img{display:block;width:100%;height:auto;border:0;}</style><a href='<?php echo esc_url( $url ); ?>' target='_parent'><img src='<?php echo esc_url( $img_src ); ?>' alt='<?php echo esc_attr( $title ); ?>'></a>"
			width="<?php echo (int) $width; ?>"
			height="<?php echo (int) $height; ?>"
			frameborder="0"
			scrolling="no"
			style="display: block; margin: 0 auto; border: none; max-width: 100%; vertical-align: bottom;"
			title="<?php echo esc_attr( $title ); ?>">
		</iframe>
	</div>
	<?php
}
