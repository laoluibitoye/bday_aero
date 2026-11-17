<?php
/**
 * Front-end side of the Ad Manager: the template API (bday_ad_zone()), the
 * in-article injector, and the page config the loader script consumes.
 *
 * Everything here is cache-safe by construction. The site sits behind
 * Redis and Cloudflare, so the HTML must be identical for every reader —
 * the server never decides "this reader gets no ads". It renders the same
 * inert containers for everyone and ad-manager.js applies the reader's
 * policy (anonymous / registered / subscriber / plan-specific) in the
 * browser, before any ad network script is requested.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Master ads kill switch. Live by default; off on staging hostnames so ads
 * never pollute staging metrics, and off when the master switch is.
 */
function bday_ads_allowed(): bool {
	static $allowed = null;
	if ( null !== $allowed ) {
		return $allowed;
	}

	if ( empty( Bday_Ads_Store::settings()['master'] ) ) {
		$allowed = false;
		return $allowed;
	}

	$host       = $_SERVER['HTTP_HOST'] ?? '';
	$is_staging = ( false !== strpos( $host, 'stg' ) || false !== strpos( $host, 'staging' ) )
		|| ( defined( 'WP_HOME' ) && ( false !== strpos( WP_HOME, 'stg' ) || false !== strpos( WP_HOME, 'staging' ) ) );

	$allowed = ! $is_staging;
	return $allowed;
}

/**
 * Ads run on posts, archives/search and the front page — never on a
 * WordPress Page (as likely to be a sales funnel or account screen as an
 * article), and never on a 404. The front page is the exception even when
 * it's a static Page, because it is the main editorial surface.
 */
function bday_page_allows_ads(): bool {
	if ( ! bday_ads_allowed() || is_admin() || is_feed() || is_404() ) {
		return false;
	}
	if ( is_page() && ! is_front_page() ) {
		return false;
	}
	return true;
}

/**
 * The Taboola publisher account, or '' when Taboola is switched off. One source of truth for both
 * the Ad Manager's Taboola units and the loader: Integrations → Taboola ("Enable Taboola" + account).
 */
function bday_ads_taboola_account(): string {
	static $account = null;
	if ( null !== $account ) {
		return $account;
	}
	$all     = get_option( 'bday_addon_vendors', array() );
	$opt     = is_array( $all ) && is_array( $all['taboola'] ?? null ) ? $all['taboola'] : array();
	$account = ! empty( $opt['enabled'] ) && ! empty( $opt['account'] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $opt['account'] ) : '';
	return (string) $account;
}

/** Taboola's page-type call for this request: article / home / category / other. */
function bday_ads_taboola_page_type(): string {
	if ( is_singular() && ! is_front_page() ) {
		return 'article';
	}
	if ( is_front_page() || is_home() ) {
		return 'home';
	}
	if ( is_category() || is_tag() || is_tax() || is_post_type_archive() ) {
		return 'category';
	}
	return 'other';
}

/** True while the AMP plugin is rendering the AMP version of a page (no JavaScript runs there). */
function bday_ads_is_amp(): bool {
	return function_exists( 'amp_is_request' ) && amp_is_request();
}

/** "300x250,728x90,fluid" → [[300,250],[728,90],'fluid'] */
function bday_ads_parse_sizes( string $raw ): array {
	$out = array();
	foreach ( preg_split( '/[\s,;]+/', strtolower( trim( $raw ) ) ) as $token ) {
		if ( 'fluid' === $token ) {
			$out[] = 'fluid';
		} elseif ( preg_match( '/^(\d{1,4})x(\d{1,4})$/', $token, $m ) ) {
			$out[] = array( (int) $m[1], (int) $m[2] );
		}
	}
	return $out;
}

/** Smallest numeric height in a size list — the space reserved before an ad loads (prevents layout shift). */
function bday_ads_min_height( array $sizes ): int {
	$heights = array();
	foreach ( $sizes as $s ) {
		if ( is_array( $s ) ) {
			$heights[] = $s[1];
		}
	}
	return $heights ? min( $heights ) : 0;
}

final class Bday_Ads_Render {

	private static int $counter = 0;

	/** @var array<string, array<string, mixed>> units rendered on this page, keyed by unit id (feeds the loader config) */
	private static array $used = array();

	/** @var array<string, bool> content placements already output this request */
	private static array $injected = array();

	/** @var array<string, bool> placements a template actually asked for on this page */
	private static array $called = array();

	/** @var array<string, string[]> why each placement did or didn't render on this page (shown to admins as an HTML comment) */
	private static array $trace = array();

	private static function trace( string $placement, string $message ): void {
		self::$trace[ $placement ][] = $message;
	}

	/* ── Eligibility ──────────────────────────────────────────────── */

	/** Whether a placement may render here (page type, enabled flag, post type, excluded categories). */
	public static function placement_applies( string $placement, ?WP_Post $post ): bool {
		if ( ! bday_page_allows_ads() ) {
			self::trace( $placement, 'not rendered: this page does not allow ads (404, feed, a Page other than the homepage, or ads are switched off)' );
			return false;
		}
		$catalog = bday_ads_placement_catalog();
		if ( ! isset( $catalog[ $placement ] ) ) {
			self::trace( $placement, 'not rendered: unknown placement' );
			return false;
		}
		$conf = Bday_Ads_Store::placement( $placement );
		if ( empty( $conf['enabled'] ) ) {
			self::trace( $placement, 'not rendered: switched off in Ad Placements' );
			return false;
		}
		if ( empty( $conf['units'] ) ) {
			self::trace( $placement, 'not rendered: no units are assigned to it' );
			return false;
		}

		// Post-type and category rules describe *articles*. The homepage is a WordPress Page, so
		// applying "Only on: Posts" there would silently hide every homepage placement.
		$post = $post ?: ( is_singular() && ! is_front_page() ? get_post() : null );
		if ( $post ) {
			if ( ! empty( $conf['post_types'] ) && ! in_array( $post->post_type, (array) $conf['post_types'], true ) ) {
				self::trace( $placement, sprintf( 'not rendered: restricted to %s, this is a "%s"', implode( '/', (array) $conf['post_types'] ), $post->post_type ) );
				return false;
			}
			if ( ! empty( $conf['exclude_categories'] ) ) {
				$slugs = wp_get_post_categories( $post->ID, array( 'fields' => 'slugs' ) );
				if ( array_intersect( (array) $slugs, (array) $conf['exclude_categories'] ) ) {
					self::trace( $placement, 'not rendered: this article is in an excluded category' );
					return false;
				}
			}
		}
		return true;
	}

	/** Banner schedule check. Evaluated here for correctness and again in the browser, since HTML may be cached. */
	private static function in_schedule( array $unit ): bool {
		$now = time();
		if ( '' !== (string) $unit['start'] && self::to_ts( (string) $unit['start'] ) > $now ) {
			return false;
		}
		if ( '' !== (string) $unit['end'] && self::to_ts( (string) $unit['end'] ) <= $now ) {
			return false;
		}
		return true;
	}

	private static function to_ts( string $local ): int {
		try {
			return ( new DateTimeImmutable( $local, wp_timezone() ) )->getTimestamp();
		} catch ( Exception $e ) {
			return 0;
		}
	}

	/* ── Placement output ─────────────────────────────────────────── */

	public static function zone( string $placement, ?WP_Post $post = null ): void {
		self::$called[ $placement ] = true;
		if ( ! self::placement_applies( $placement, $post ) ) {
			return;
		}
		$conf = Bday_Ads_Store::placement( $placement );
		foreach ( (array) $conf['units'] as $unit_id ) {
			$unit = Bday_Ads_Store::unit( (string) $unit_id );
			if ( ! $unit ) {
				self::trace( $placement, sprintf( 'unit "%s" skipped: it no longer exists', $unit_id ) );
				continue;
			}
			if ( 'active' !== $unit['status'] ) {
				self::trace( $placement, sprintf( 'unit "%s" skipped: paused', $unit['id'] ) );
				continue;
			}
			// Out-of-page GAM formats have no container; the loader
			// defines them from the config instead.
			if ( 'gam' === $unit['partner'] && 'standard' !== $unit['format'] ) {
				self::$used[ $unit['id'] ] = self::js_config( $unit );
				continue;
			}
			// A network unit with nothing to ask for can never fill; rendering its container would only
			// leave an empty gap.
			if ( 'gam' === $unit['partner'] && '' === trim( (string) $unit['path'] ) ) {
				self::trace( $placement, sprintf( 'unit "%s" skipped: it has no valid GAM ad unit path', $unit['id'] ) );
				continue;
			}
			if ( 'taboola' === $unit['partner'] && '' === bday_ads_taboola_account() ) {
				self::trace( $placement, sprintf( 'unit "%s" skipped: Taboola is switched off or has no account name under Integrations → Taboola', $unit['id'] ) );
				continue;
			}
			if ( 'adsense' === $unit['partner'] && '' === trim( (string) $unit['adsense_slot'] ) ) {
				self::trace( $placement, sprintf( 'unit "%s" skipped: it has no AdSense slot ID', $unit['id'] ) );
				continue;
			}
			if ( 'banner' === $unit['partner'] ) {
				if ( ! self::in_schedule( $unit ) ) {
					self::trace( $placement, sprintf( 'unit "%s" skipped: outside its schedule', $unit['id'] ) );
					continue;
				}
				if ( ! self::banner_creatives( $unit ) ) {
					self::trace( $placement, sprintf( 'unit "%s" skipped: the banner has no usable image (none chosen, or it was deleted from the media library)', $unit['id'] ) );
					continue;
				}
			}
			self::render_unit( $unit, $placement );
			self::trace( $placement, sprintf( 'unit "%s" rendered (%s, %s)', $unit['id'], $unit['partner'], 'all' === $unit['device'] ? 'all devices' : $unit['device'] . ' only' ) );
		}
	}

	private static function render_unit( array $unit, string $placement ): void {
		if ( bday_ads_is_amp() ) {
			self::render_amp_unit( $unit, $placement );
			return;
		}
		++self::$counter;
		$dom_id  = 'bday-ad-' . self::$counter;
		$bp      = (int) Bday_Ads_Store::settings()['breakpoint'];
		$size_d  = bday_ads_parse_sizes( (string) $unit['sizes_desktop'] );
		$size_m  = bday_ads_parse_sizes( (string) $unit['sizes_mobile'] );
		$min_d   = 'mobile' === $unit['device'] ? 0 : bday_ads_min_height( $size_d );
		$min_m   = 'desktop' === $unit['device'] ? 0 : bday_ads_min_height( $size_m ?: $size_d );

		self::$used[ $unit['id'] ] = self::js_config( $unit );

		$classes = array( 'bday-ad', 'bday-ad--' . $unit['partner'] );
		if ( 'all' !== $unit['device'] ) {
			$classes[] = 'bday-ad--' . $unit['device'] . '-only';
		}

		$attrs = sprintf(
			'class="%s" id="%s" data-bday-ad data-unit="%s" data-placement="%s" data-partner="%s" data-category="%s" data-device="%s" style="--bday-ad-min-d:%dpx;--bday-ad-min-m:%dpx;"',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( $dom_id ),
			esc_attr( $unit['id'] ),
			esc_attr( $placement ),
			esc_attr( $unit['partner'] ),
			esc_attr( $unit['category'] ),
			esc_attr( $unit['device'] ),
			$min_d,
			$min_m
		);
		if ( 'banner' === $unit['partner'] ) {
			$attrs .= sprintf( ' data-rotate="%d"', (int) $unit['rotate_seconds'] );
			if ( '' !== (string) $unit['start'] ) {
				$attrs .= ' data-start="' . (int) self::to_ts( (string) $unit['start'] ) . '"';
			}
			if ( '' !== (string) $unit['end'] ) {
				$attrs .= ' data-end="' . (int) self::to_ts( (string) $unit['end'] ) . '"';
			}
		}

		echo '<div ' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput -- every dynamic part escaped above.
		echo '<span class="bday-ad__label">' . esc_html( '' !== (string) $unit['sponsor_label'] ? $unit['sponsor_label'] : 'Advertisement' ) . '</span>';

		switch ( $unit['partner'] ) {
			case 'banner':
				self::banner_inner( $unit, $placement, $bp );
				break;
			case 'adsense':
				$fixed = $size_d[0] ?? ( $size_m[0] ?? null );
				$style = is_array( $fixed ) ? sprintf( 'display:inline-block;width:%dpx;height:%dpx', $fixed[0], $fixed[1] ) : 'display:block';
				printf(
					'<ins class="adsbygoogle" style="%s" data-ad-client="%s" data-ad-slot="%s"%s data-bday-adsense></ins>',
					esc_attr( $style ),
					esc_attr( (string) Bday_Ads_Store::settings()['adsense_client'] ),
					esc_attr( (string) $unit['adsense_slot'] ),
					is_array( $fixed ) ? '' : ' data-ad-format="auto" data-full-width-responsive="true"'
				);
				break;
			case 'html':
				// Inert until the loader decides this reader may see it.
				echo '<div class="bday-ad__slot" data-bday-html></div>';
				echo '<template class="bday-ad__html">' . $unit['body_code'] . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput -- admin-authored tag, by design.
				break;
			default: // gam, taboola
				echo '<div class="bday-ad__slot" id="' . esc_attr( $dom_id ) . '-slot"></div>';
		}

		// A house/direct-sold banner shown only if the primary unit comes back empty.
		$fb = '' !== (string) $unit['fallback'] ? Bday_Ads_Store::unit( (string) $unit['fallback'] ) : null;
		if ( $fb && 'banner' === $fb['partner'] && self::in_schedule( $fb ) && self::banner_creatives( $fb ) ) {
			echo '<template class="bday-ad__fallback">';
			self::banner_inner( $fb, $placement, $bp );
			echo '</template>';
		}

		echo '</div>';
	}

	/** @return array<int, array<string, mixed>> creatives that have something to show */
	private static function banner_creatives( array $unit ): array {
		// Only creatives whose image actually resolves: an attachment that was deleted from the media
		// library leaves an id behind, and a unit built from those would print an "Advertisement"
		// label with nothing under it.
		return array_values(
			array_filter(
				(array) $unit['creatives'],
				static function ( $c ): bool {
					if ( ! is_array( $c ) ) {
						return false;
					}
					list( $src ) = self::image_src( (int) ( $c['image'] ?? 0 ), (string) ( $c['image_url'] ?? '' ) );
					return '' !== $src;
				}
			)
		);
	}

	/** @return array{0:string,1:int,2:int} url, width, height */
	private static function image_src( int $id, string $url ): array {
		if ( $id ) {
			$src = wp_get_attachment_image_src( $id, 'full' );
			if ( $src ) {
				return array( (string) $src[0], (int) $src[1], (int) $src[2] );
			}
		}
		if ( '' !== $url ) {
			$dims = self::url_dims( $url );
			return array( $url, $dims[0], $dims[1] );
		}
		return array( $url, 0, 0 );
	}

	/**
	 * Pixel size of a banner that was saved as a plain URL (the migrated sponsor banners are). Without
	 * a size the browser cannot reserve space for the image, so a 1900×300 top banner pushes the whole
	 * page down by ~230px when it arrives — the main source of the homepage's layout shift. Read from
	 * the local uploads folder (no remote fetch) and remembered.
	 *
	 * @return array{0:int,1:int}
	 */
	private static function url_dims( string $url ): array {
		$key = 'bday_ad_dim_' . md5( $url );
		$hit = get_transient( $key );
		if ( is_array( $hit ) && 2 === count( $hit ) ) {
			return array( (int) $hit[0], (int) $hit[1] );
		}
		$dims = array( 0, 0 );
		if ( preg_match( '#/wp-content/uploads/([^?\#]+)#', $url, $m ) && false === strpos( $m[1], '..' ) ) {
			$up   = wp_get_upload_dir();
			$file = $up['basedir'] . '/' . rawurldecode( $m[1] );
			$info = is_readable( $file ) ? @getimagesize( $file ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors -- a non-image file just means "unknown size".
			if ( $info && $info[0] > 0 && $info[1] > 0 ) {
				$dims = array( (int) $info[0], (int) $info[1] );
			}
		}
		set_transient( $key, $dims, $dims[0] ? 30 * DAY_IN_SECONDS : HOUR_IN_SECONDS );
		return $dims;
	}

	private static function banner_inner( array $unit, string $placement, int $bp ): void {
		$eager = in_array( $placement, array( 'top_leaderboard' ), true );
		echo '<div class="bday-ad__banners">';
		foreach ( self::banner_creatives( $unit ) as $i => $c ) {
			list( $src, $w, $h ) = self::image_src( (int) ( $c['image'] ?? 0 ), (string) ( $c['image_url'] ?? '' ) );
			if ( '' === $src ) {
				continue;
			}
			list( $msrc, $mw, $mh ) = self::image_src( (int) ( $c['image_mobile'] ?? 0 ), '' );
			$ar_d  = ( $w && $h ) ? sprintf( '%d / %d', $w, $h ) : 'auto';
			$ar_m  = ( $mw && $mh ) ? sprintf( '%d / %d', $mw, $mh ) : $ar_d;
			// The creative's own width, so its aspect-ratio box can never be wider than the image it
			// holds. Without it the box stretched to the full width of the placement: a 300×250 banner
			// in a 1,533px-wide homepage spot became a 1,278px-tall mostly-empty block.
			$wd    = $w ? $w . 'px' : '100%';
			$wm    = $mw ? $mw . 'px' : $wd;
			$href  = (string) ( $c['url'] ?? '' );
			$tag   = $href ? 'a' : 'div';
			$open  = $href
				? sprintf( '<a class="bday-ad__creative" href="%s" rel="sponsored noopener"%s data-bday-ad-click', esc_url( $href ), $unit['new_tab'] ? ' target="_blank"' : '' )
				: '<div class="bday-ad__creative"';
			echo $open . sprintf( ' style="--bday-ad-ar-d:%s;--bday-ad-ar-m:%s;--bday-ad-w-d:%s;--bday-ad-w-m:%s;"%s>', esc_attr( $ar_d ), esc_attr( $ar_m ), esc_attr( $wd ), esc_attr( $wm ), $i > 0 ? ' hidden' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<picture>';
			if ( $msrc ) {
				printf( '<source media="(max-width: %dpx)" srcset="%s">', max( 0, $bp - 1 ), esc_url( $msrc ) );
			}
			printf(
				'<img src="%s"%s alt="%s" loading="%s" decoding="async">',
				esc_url( $src ),
				$w && $h ? sprintf( ' width="%d" height="%d"', $w, $h ) : '',
				esc_attr( (string) ( $c['alt'] ?? '' ) ),
				$eager ? 'eager' : 'lazy'
			);
			echo '</picture></' . $tag . '>';
		}
		echo '</div>';
	}

	/* ── AMP ──────────────────────────────────────────────────────── */

	/**
	 * AMP forbids author JavaScript, so the loader (and with it GPT, AdSense and the gating) cannot run
	 * there. A page is still monetised the AMP way: <amp-ad> for Google Ad Manager and AdSense, plain
	 * <amp-img> for local banners. Pasted third-party code can't run in AMP and is skipped. Reader
	 * policies cannot be applied (the reader's token is not readable on AMP pages), so AMP readers
	 * always see the network ads; local banners are never gated anyway.
	 *
	 * @return array<int, array{0:int,1:int}> phone-fitting sizes of the unit, largest first
	 */
	private static function amp_sizes( array $unit ): array {
		$fit = array();
		foreach ( array( (string) $unit['sizes_mobile'], (string) $unit['sizes_desktop'] ) as $raw ) {
			foreach ( bday_ads_parse_sizes( $raw ) as $sz ) {
				if ( is_array( $sz ) && $sz[0] <= 336 ) {
					$fit[ $sz[0] . 'x' . $sz[1] ] = $sz;
				}
			}
			if ( $fit ) {
				break; // the mobile list wins when it has anything usable
			}
		}
		$fit = array_values( $fit );
		usort( $fit, static fn( $a, $b ) => ( $b[0] * $b[1] ) <=> ( $a[0] * $a[1] ) );
		return $fit;
	}

	/** The <amp-ad> element for a network unit, or '' if this unit cannot run on AMP. */
	private static function amp_network_tag( array $unit, string $placement ): string {
		$sizes = self::amp_sizes( $unit );
		if ( 'gam' === $unit['partner'] ) {
			if ( ! $sizes ) {
				self::trace( $placement, sprintf( 'unit "%s" not shown on AMP: it has no size 336px wide or narrower', $unit['id'] ) );
				return '';
			}
			$primary = array_shift( $sizes );
			$json    = array();
			if ( ! empty( Bday_Ads_Store::settings()['targeting'] ) ) {
				foreach ( self::page_targeting() as $k => $v ) {
					$json['targeting'][ $k ] = array( (string) $v );
				}
			}
			$json['targeting']['reader'] = array( 'amp' );
			$multi = array_map( static fn( $z ) => $z[0] . 'x' . $z[1], $sizes );
			return sprintf(
				'<amp-ad width="%d" height="%d" type="doubleclick" data-slot="%s"%s data-lazy-fetch="true" data-loading-strategy="prefer-viewability-over-views" json="%s"></amp-ad>',
				$primary[0],
				$primary[1],
				esc_attr( (string) $unit['path'] ),
				$multi ? ' data-multi-size="' . esc_attr( implode( ',', $multi ) ) . '" data-multi-size-validation="false"' : '',
				esc_attr( (string) wp_json_encode( $json ) )
			);
		}
		// AdSense.
		$client = (string) Bday_Ads_Store::settings()['adsense_client'];
		if ( '' === $client ) {
			self::trace( $placement, sprintf( 'unit "%s" not shown on AMP: the AdSense publisher ID is not set under Settings', $unit['id'] ) );
			return '';
		}
		if ( $sizes ) {
			return sprintf(
				'<amp-ad width="%d" height="%d" type="adsense" data-ad-client="%s" data-ad-slot="%s" data-loading-strategy="prefer-viewability-over-views"></amp-ad>',
				$sizes[0][0],
				$sizes[0][1],
				esc_attr( $client ),
				esc_attr( (string) $unit['adsense_slot'] )
			);
		}
		return sprintf(
			'<amp-ad width="100vw" height="320" type="adsense" data-ad-client="%s" data-ad-slot="%s" data-auto-format="rspv" data-full-width data-loading-strategy="prefer-viewability-over-views"><div overflow></div></amp-ad>',
			esc_attr( $client ),
			esc_attr( (string) $unit['adsense_slot'] )
		);
	}

	/** Taboola on AMP is <amp-embed type="taboola">; its height follows the screen width. */
	private static function amp_taboola_tag( array $unit, string $placement ): string {
		// AMP cannot collapse a Taboola widget that comes back empty: it stays a blank box about 2x as tall
		// as the screen is wide. On the normal page the loader gives that space back, so a mid-article
		// unit whose placement name Taboola has not set up is harmless there, but would be a huge gap on
		// AMP. Only fixed end-of-article spots (verified live) are shown on AMP.
		$kind = (string) ( bday_ads_placement_catalog()[ $placement ]['kind'] ?? '' );
		if ( 'content' === $kind ) {
			self::trace( $placement, sprintf( 'unit "%s" not shown on AMP: Taboola on AMP is limited to the end-of-article spot, because AMP cannot collapse an empty in-article widget', $unit['id'] ) );
			return '';
		}
		$account = bday_ads_taboola_account();
		if ( '' === $account ) {
			self::trace( $placement, sprintf( 'unit "%s" not shown on AMP: Taboola is switched off or has no account name', $unit['id'] ) );
			return '';
		}
		return sprintf(
			'<amp-embed width="100" height="100" type="taboola" layout="responsive" heights="(min-width:1907px) 39%%, (min-width:1200px) 47%%, (min-width:780px) 60%%, (min-width:480px) 70%%, 220%%" data-publisher="%s" data-mode="%s" data-placement="%s" data-target_type="%s" data-article="auto" data-url=""></amp-embed>',
			esc_attr( $account ),
			esc_attr( (string) $unit['taboola_mode'] ),
			esc_attr( (string) $unit['taboola_placement'] ),
			esc_attr( (string) $unit['taboola_target'] )
		);
	}

	/** First usable creative of a local banner as AMP-safe markup (one image per screen size, no rotation). */
	private static function amp_banner_tag( array $unit, string $placement ): string {
		$creatives = self::banner_creatives( $unit );
		$c         = $creatives[0] ?? null;
		if ( ! $c ) {
			return '';
		}
		list( $src, $w, $h )   = self::image_src( (int) ( $c['image'] ?? 0 ), (string) ( $c['image_url'] ?? '' ) );
		list( $msrc, $mw, $mh ) = self::image_src( (int) ( $c['image_mobile'] ?? 0 ), '' );
		if ( '' === $src || ! $w || ! $h ) {
			self::trace( $placement, sprintf( 'unit "%s" not shown on AMP: AMP needs the banner image\'s width and height', $unit['id'] ) );
			return '';
		}
		$alt  = esc_attr( (string) ( $c['alt'] ?? '' ) );
		$has_m = false;
		$imgs  = sprintf( '<img class="bday-ad__img-d" src="%s" width="%d" height="%d" alt="%s">', esc_url( $src ), $w, $h, $alt );
		if ( $msrc && $mw && $mh ) {
			$has_m = true;
			$imgs .= sprintf( '<img class="bday-ad__img-m" src="%s" width="%d" height="%d" alt="%s">', esc_url( $msrc ), $mw, $mh, $alt );
		}
		$href = (string) ( $c['url'] ?? '' );
		if ( '' === $href ) {
			return '<div class="bday-ad__creative' . ( $has_m ? ' has-m' : '' ) . '">' . $imgs . '</div>';
		}
		return sprintf(
			'<a class="bday-ad__creative%s" href="%s" rel="sponsored noopener"%s>%s</a>',
			$has_m ? ' has-m' : '',
			esc_url( $href ),
			$unit['new_tab'] ? ' target="_blank"' : '',
			$imgs
		);
	}

	private static function render_amp_unit( array $unit, string $placement ): void {
		if ( 'desktop' === $unit['device'] ) {
			self::trace( $placement, sprintf( 'unit "%s" not shown on AMP: it is desktop-only', $unit['id'] ) );
			return;
		}
		switch ( $unit['partner'] ) {
			case 'banner':
				$tag = self::amp_banner_tag( $unit, $placement );
				break;
			case 'gam':
			case 'adsense':
				$tag = self::amp_network_tag( $unit, $placement );
				break;
			case 'taboola':
				$tag = self::amp_taboola_tag( $unit, $placement );
				break;
			default:
				self::trace( $placement, sprintf( 'unit "%s" not shown on AMP: pasted code cannot run on AMP pages', $unit['id'] ) );
				return;
		}
		if ( '' === $tag ) {
			return;
		}
		$label = 'banner' === $unit['partner'] ? '<span class="bday-ad__label">' . esc_html( '' !== (string) $unit['sponsor_label'] ? $unit['sponsor_label'] : 'Advertisement' ) . '</span>' : '';
		printf(
			'<div class="bday-ad bday-ad--amp bday-ad--%s" data-placement="%s">%s%s</div>',
			esc_attr( $unit['partner'] ),
			esc_attr( $placement ),
			$label, // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts above.
			$tag // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts above.
		);
	}

	/** Bottom-anchor GAM format on AMP: one <amp-sticky-ad>, phones only. */
	public static function print_amp_anchor(): void {
		if ( ! bday_ads_is_amp() || ! bday_page_allows_ads() || ! self::placement_applies( 'global_oop', null ) ) {
			return;
		}
		foreach ( (array) Bday_Ads_Store::placement( 'global_oop' )['units'] as $unit_id ) {
			$unit = Bday_Ads_Store::unit( (string) $unit_id );
			if ( ! $unit || 'active' !== $unit['status'] || 'gam' !== $unit['partner'] || 'bottom_anchor' !== $unit['format'] || 'desktop' === $unit['device'] || '' === trim( (string) $unit['path'] ) ) {
				continue;
			}
			printf(
				'<amp-sticky-ad layout="nodisplay"><amp-ad width="320" height="50" type="doubleclick" data-slot="%s"></amp-ad></amp-sticky-ad>' . "\n",
				esc_attr( (string) $unit['path'] )
			);
			return; // AMP allows a single sticky ad per page
		}
	}

	/**
	 * Ad tags pasted into old article bodies (AdSense <ins>, GPT <div class="bd-admanager">) are dead
	 * on AMP: the script that fills them is stripped, so they leave blank gaps. Turn an AdSense tag
	 * into an <amp-ad> (unless the editor already added one right after it) and drop the empty GPT div.
	 */
	public static function amp_clean_legacy_content( string $html ): string {
		if ( ! bday_ads_is_amp() || ( false === stripos( $html, 'adsbygoogle' ) && false === stripos( $html, 'bd-admanager' ) ) ) {
			return $html;
		}
		$html = preg_replace( '#<div\b[^>]*\bclass="[^"]*\bbd-admanager\b[^"]*"[^>]*>\s*</div>#i', '', $html ) ?? $html;
		return preg_replace_callback(
			'#<ins\b[^>]*\bclass="[^"]*\badsbygoogle\b[^"]*"[^>]*>\s*</ins>(\s*<amp-ad\b)?#i',
			static function ( array $m ): string {
				if ( ! empty( $m[1] ) ) {
					return ltrim( $m[1] ); // already has its AMP twin: keep that one only
				}
				if ( ! preg_match( '/data-ad-client="([^"]+)"/i', $m[0], $c ) || ! preg_match( '/data-ad-slot="([^"]+)"/i', $m[0], $sl ) ) {
					return '';
				}
				return sprintf(
					'<amp-ad width="100vw" height="320" type="adsense" data-ad-client="%s" data-ad-slot="%s" data-auto-format="rspv" data-full-width data-loading-strategy="prefer-viewability-over-views"><div overflow></div></amp-ad>',
					esc_attr( $c[1] ),
					esc_attr( $sl[1] )
				);
			},
			$html
		) ?? $html;
	}

	/** Per-unit data the loader needs. */
	private static function js_config( array $unit ): array {
		$cfg = array(
			'partner'  => $unit['partner'],
			'category' => $unit['category'],
			'device'   => $unit['device'],
		);
		if ( 'gam' === $unit['partner'] ) {
			$cfg['path']    = $unit['path'];
			$cfg['format']  = $unit['format'];
			$cfg['refresh'] = ! empty( $unit['refresh'] );
			$cfg['sizesD']  = bday_ads_parse_sizes( (string) $unit['sizes_desktop'] );
			$cfg['sizesM']  = bday_ads_parse_sizes( (string) $unit['sizes_mobile'] );
		}
		if ( 'taboola' === $unit['partner'] ) {
			$cfg['mode']      = $unit['taboola_mode'];
			$cfg['placement'] = $unit['taboola_placement'];
			$cfg['target']    = $unit['taboola_target'];
		}
		if ( 'html' === $unit['partner'] && '' !== trim( (string) $unit['head_code'] ) ) {
			$cfg['head'] = $unit['head_code'];
		}
		return $cfg;
	}

	/* ── In-article injection ─────────────────────────────────────── */

	/**
	 * Inserts every enabled in-article placement into already-rendered
	 * article HTML. Counts only top-level paragraphs (a </p> inside a
	 * blockquote, list, figure or table doesn't count) so an ad never lands
	 * mid-quote, and skips short articles entirely.
	 */
	public static function inject( string $html, ?WP_Post $post ): string {
		if ( ! bday_page_allows_ads() || false !== strpos( $html, 'id="no-ads"' ) ) {
			return $html;
		}

		$wanted = array(); // paragraph index => placement ids
		foreach ( bday_ads_placement_catalog() as $id => $def ) {
			if ( 'content' !== $def['kind'] || isset( self::$injected[ $id ] ) ) {
				continue;
			}
			self::$called[ $id ] = true;
			if ( ! self::placement_applies( $id, $post ) ) {
				continue;
			}
			$conf = Bday_Ads_Store::placement( $id );
			$wanted[ max( 1, (int) $conf['paragraph'] ) ][] = array( $id, (int) $conf['min_paragraphs'] );
		}
		if ( ! $wanted ) {
			return $html;
		}

		$ends = self::paragraph_ends( $html );
		$n    = count( $ends );
		if ( 0 === $n ) {
			self::trace_all( array_merge( ...array_values( array_map( static fn( $l ) => array_column( $l, 0 ), $wanted ) ) ), 'not rendered: no paragraphs were found in the article text' );
			return $html;
		}
		foreach ( $wanted as $list ) {
			foreach ( $list as list( $id, $min ) ) {
				if ( $n < $min ) {
					self::trace( $id, sprintf( 'not rendered: the article has %d paragraphs and this spot needs at least %d', $n, $min ) );
				}
			}
		}

		// Insertion offset => placements that go there (after paragraph N, never after the last one).
		$at = array();
		foreach ( $wanted as $para => $list ) {
			if ( $para >= $n ) {
				continue;
			}
			foreach ( $list as list( $id, $min ) ) {
				if ( $n >= $min ) {
					$at[ $ends[ $para - 1 ] ][] = $id;
				}
			}
		}
		ksort( $at );

		$out  = '';
		$from = 0;
		foreach ( $at as $offset => $ids ) {
			$out .= substr( $html, $from, $offset - $from );
			$from = $offset;
			foreach ( $ids as $id ) {
				self::$injected[ $id ] = true;
				ob_start();
				self::zone( $id, $post );
				$out .= ob_get_clean();
			}
		}
		return $out . substr( $html, $from );
	}

	/** @param string[] $ids */
	private static function trace_all( array $ids, string $message ): void {
		foreach ( $ids as $id ) {
			self::trace( (string) $id, $message );
		}
	}

	/**
	 * Where each top-level paragraph of an article ends: a list of byte offsets, one per paragraph, in order.
	 *
	 * Real article text is not tidy HTML. A lot of it was written as <p>…<p>…<p>… with no closing tags
	 * (HTML allows that: a new block simply ends the open paragraph), and splitting on "</p>" found no
	 * paragraphs in it at all, so no in-article ad ever rendered on those articles. Here a paragraph also
	 * ends where the next paragraph, division, heading or other block starts. Paragraphs inside quotes,
	 * lists, figures and tables are skipped (an ad must never land mid-quote) and empty ones
	 * ("<p>&nbsp;</p>", used as spacers) are not counted.
	 *
	 * @return int[]
	 */
	public static function paragraph_ends( string $html ): array {
		// Blank out scripts, styles and comments (same length, so offsets stay valid): a "<p" inside a
		// string in a pasted <script> must not be mistaken for a paragraph.
		$scan = preg_replace_callback(
			'#<(script|style)\b.*?</\1\s*>|<!--.*?-->#is',
			static fn( array $m ): string => str_repeat( ' ', strlen( $m[0] ) ),
			$html
		);
		if ( ! is_string( $scan ) || ! preg_match_all( '/<(\/?)([a-z][a-z0-9]*)\b[^>]*>/i', $scan, $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return array();
		}

		$containers = array( 'blockquote', 'figure', 'ul', 'ol', 'table', 'aside', 'details' );
		$breakers   = array( 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'pre', 'hr', 'section', 'header', 'footer', 'form', 'nav' );
		$ends       = array();
		$depth      = 0;
		$open       = false;
		$start      = 0;

		$close = static function ( int $to ) use ( &$ends, &$start, $scan ): void {
			$text = html_entity_decode( strip_tags( substr( $scan, $start, $to - $start ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			if ( '' !== trim( (string) preg_replace( '/[\s\x{00A0}]+/u', ' ', $text ) ) ) {
				$ends[] = $to;
			}
		};

		foreach ( $tags as $tag ) {
			$pos     = $tag[0][1];
			$after   = $pos + strlen( $tag[0][0] );
			$closing = '/' === $tag[1][0];
			$name    = strtolower( $tag[2][0] );

			if ( in_array( $name, $containers, true ) ) {
				if ( $closing ) {
					$depth = max( 0, $depth - 1 );
				} else {
					if ( $open && 0 === $depth ) {
						$close( $pos );
						$open = false;
					}
					++$depth;
				}
				continue;
			}
			if ( $depth > 0 ) {
				continue;
			}
			if ( 'p' === $name ) {
				if ( $closing ) {
					if ( $open ) {
						$close( $after );
						$open = false;
					}
				} else {
					if ( $open ) {
						$close( $pos );
					}
					$open  = true;
					$start = $pos;
				}
			} elseif ( $open && in_array( $name, $breakers, true ) ) {
				$close( $pos );
				$open = false;
			}
		}
		if ( $open ) {
			$close( strlen( $html ) );
		}
		return $ends;
	}

	/* ── Homepage "between sections" slots ────────────────────────── */

	/** Renders every between-sections slot whose chosen section is the one that just rendered. */
	public static function after_section( string $slug ): void {
		foreach ( bday_ads_placement_catalog() as $id => $def ) {
			if ( 'section' !== $def['kind'] ) {
				continue;
			}
			if ( ( Bday_Ads_Store::placement( $id )['after_section'] ?? '' ) === $slug ) {
				self::zone( $id, null );
			}
		}
	}

	/** One HTML comment, for logged-in admins only, saying what every configured placement did on this page. */
	private static function print_trace(): void {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$catalog = bday_ads_placement_catalog();
		$lines   = array();
		foreach ( Bday_Ads_Store::placements() as $id => $conf ) {
			if ( empty( $conf['units'] ) || ! isset( $catalog[ $id ] ) || 'global' === $catalog[ $id ]['kind'] ) {
				continue;
			}
			$def = $catalog[ $id ];
			if ( ! empty( self::$called[ $id ] ) ) {
				$lines[] = $id . ': ' . implode( '; ', self::$trace[ $id ] ?? array( 'asked for on this page, nothing to show' ) );
				continue;
			}
			if ( ! bday_ads_placement_on_live_layout( $def ) ) {
				$why = sprintf( 'this spot does not exist on the live homepage layout ("%s") — it only exists on: %s', bday_ads_active_layout(), implode( ', ', (array) $def['layouts'] ) );
			} elseif ( 'section' === $def['kind'] ) {
				$why = '' === ( $conf['after_section'] ?? '' ) ? 'no section has been chosen for it' : sprintf( 'its section "%s" is not showing on this page', $conf['after_section'] );
			} else {
				$why = 'no template renders this spot on this kind of page';
			}
			$lines[] = $id . ': NOT ON THIS PAGE — ' . $why;
		}
		if ( $lines ) {
			echo "\n<!-- Ad Manager trace (shown to administrators only)\n" . str_replace( '--', '- -', implode( "\n", $lines ) ) . "\n-->\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	/* ── Loader config ────────────────────────────────────────────── */

	public static function print_config(): void {
		if ( ! bday_page_allows_ads() || bday_ads_is_amp() ) {
			return;
		}
		self::print_trace();

		// Sticky / full-screen formats have no template call; they ride on their own placement.
		if ( self::placement_applies( 'global_oop', null ) ) {
			foreach ( (array) Bday_Ads_Store::placement( 'global_oop' )['units'] as $unit_id ) {
				$unit = Bday_Ads_Store::unit( (string) $unit_id );
				if ( $unit && 'active' === $unit['status'] ) {
					self::$used[ $unit['id'] ] = self::js_config( $unit );
				}
			}
		}
		$taboola = bday_ads_taboola_account();
		if ( ! self::$used && '' === $taboola ) {
			return;
		}

		$s        = Bday_Ads_Store::settings();
		$policies = array();
		foreach ( array( 'anonymous', 'registered', 'subscriber' ) as $state ) {
			$policies[ $state ] = bday_ads_clean_policy( $s[ 'policy_' . $state ] ?? array() );
		}

		$config = array(
			'bp'         => (int) $s['breakpoint'],
			'refresh'    => array( 'sec' => (int) $s['refresh_seconds'], 'max' => (int) $s['refresh_max'] ),
			'lazy'       => array( 'fetch' => (int) $s['lazy_fetch_pct'], 'render' => (int) $s['lazy_render_pct'] ),
			'collapse'   => ! empty( $s['collapse_empty'] ),
			'debug'      => ! empty( $s['debug'] ),
			'adsense'    => (string) $s['adsense_client'],
			'taboola'    => '' !== $taboola ? array( 'account' => $taboola ) : null,
			'targeting'  => ! empty( $s['targeting'] ) ? self::page_targeting() : null,
			'policies'   => $policies,
			'categories' => array_keys( (array) $s['categories'] ),
			'units'      => self::$used,
		);

		$dir = get_template_directory() . '/addons/ad-manager/assets/ad-manager.js';
		wp_enqueue_script( 'bday-ad-manager', get_template_directory_uri() . '/addons/ad-manager/assets/ad-manager.js', array(), file_exists( $dir ) ? (string) filemtime( $dir ) : null, array( 'in_footer' => true, 'strategy' => 'defer' ) );

		echo '<script type="application/json" id="bday-ads-config">' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/** Page-level key-values for GAM (non-personal; reader state is added client-side). */
	private static function page_targeting(): array {
		$t = array();
		if ( is_front_page() ) {
			$t['page_type'] = 'home';
		} elseif ( is_singular() ) {
			$t['page_type'] = 'article';
			$cats           = get_the_category();
			if ( $cats ) {
				$t['category'] = $cats[0]->slug;
			}
		} elseif ( is_search() ) {
			$t['page_type'] = 'search';
		} elseif ( is_archive() ) {
			$t['page_type'] = 'section';
			$obj            = get_queried_object();
			if ( $obj && isset( $obj->slug ) ) {
				$t['category'] = $obj->slug;
			}
		}
		// Kept for continuity with the key GAM line items already target.
		$t['sections'] = function_exists( 'bday_current_page_section' ) ? bday_current_page_section() : '';
		return array_filter( $t );
	}
}

/**
 * Normalises a reader policy: mode all|none|limited, categories to hide,
 * and a per-page cap (0 = no cap). Same shape as Plan.adPolicy in
 * subscription-service, so a plan's policy can be passed straight through.
 *
 * @param mixed $raw
 * @return array{mode:string,hide:string[],max:int}
 */
function bday_ads_clean_policy( $raw ): array {
	$raw  = is_array( $raw ) ? $raw : array();
	$mode = in_array( $raw['mode'] ?? '', array( 'all', 'none', 'limited' ), true ) ? $raw['mode'] : 'all';
	$hide = array_values( array_filter( array_map( 'sanitize_key', (array) ( $raw['hide'] ?? array() ) ) ) );
	$max  = max( 0, (int) ( $raw['max'] ?? 0 ) );
	// "Show all" and "Ad-free" mean exactly that. The category list and the cap belong to "Limited"
	// only; a stale ticked box under "Show all ads" must never remove anything (it once stripped every
	// Google ad from anonymous readers on the live site).
	if ( 'limited' !== $mode ) {
		$hide = array();
		$max  = 0;
	}
	return array( 'mode' => $mode, 'hide' => $hide, 'max' => $max );
}

/* ── Public template API ────────────────────────────────────────── */

/** Renders the units assigned to a placement, if the placement is allowed here. */
function bday_ad_zone( string $zone, ?WP_Post $post = null ): void {
	Bday_Ads_Render::zone( $zone, $post );
}

/** Renders the between-sections slots that follow the homepage section that just rendered. */
function bday_ads_after_section( string $slug ): void {
	Bday_Ads_Render::after_section( $slug );
}

/** Same, for already-rendered article HTML (used by the article template). */
function bday_ads_inject_in_content( string $html, ?WP_Post $post = null ): string {
	return Bday_Ads_Render::inject( $html, $post );
}

add_action( 'wp_footer', array( 'Bday_Ads_Render', 'print_config' ), 5 );
// Front-end only: on admin requests the Ad Manager's form sanitizers are registered and would treat a programmatic save as a cut-off form.
add_action(
	'init',
	static function (): void {
		if ( ! is_admin() && ! wp_doing_cron() ) {
			Bday_Ads_Store::migrate_taboola();
		}
	},
	30
);
add_action( 'wp_footer', array( 'Bday_Ads_Render', 'print_amp_anchor' ), 6 );
add_filter( 'the_content', array( 'Bday_Ads_Render', 'amp_clean_legacy_content' ), 99 );

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( ! bday_page_allows_ads() ) {
			return;
		}
		$file = get_template_directory() . '/addons/ad-manager/assets/ad-manager.css';
		wp_enqueue_style( 'bday-ad-manager', get_template_directory_uri() . '/addons/ad-manager/assets/ad-manager.css', array(), file_exists( $file ) ? (string) filemtime( $file ) : null );
	}
);
