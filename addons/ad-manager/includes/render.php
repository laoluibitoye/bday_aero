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

	/* ── Eligibility ──────────────────────────────────────────────── */

	/** Whether a placement may render here (page type, enabled flag, post type, excluded categories). */
	public static function placement_applies( string $placement, ?WP_Post $post ): bool {
		if ( ! bday_page_allows_ads() ) {
			return false;
		}
		$catalog = bday_ads_placement_catalog();
		if ( ! isset( $catalog[ $placement ] ) ) {
			return false;
		}
		$conf = Bday_Ads_Store::placement( $placement );
		if ( empty( $conf['enabled'] ) || empty( $conf['units'] ) ) {
			return false;
		}

		$post = $post ?: ( is_singular() ? get_post() : null );
		if ( $post ) {
			if ( ! empty( $conf['post_types'] ) && ! in_array( $post->post_type, (array) $conf['post_types'], true ) ) {
				return false;
			}
			if ( ! empty( $conf['exclude_categories'] ) ) {
				$slugs = wp_get_post_categories( $post->ID, array( 'fields' => 'slugs' ) );
				if ( array_intersect( (array) $slugs, (array) $conf['exclude_categories'] ) ) {
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
		if ( ! self::placement_applies( $placement, $post ) ) {
			return;
		}
		$conf = Bday_Ads_Store::placement( $placement );
		foreach ( (array) $conf['units'] as $unit_id ) {
			$unit = Bday_Ads_Store::unit( (string) $unit_id );
			if ( ! $unit || 'active' !== $unit['status'] ) {
				continue;
			}
			// Out-of-page GAM formats have no container; the loader
			// defines them from the config instead.
			if ( 'gam' === $unit['partner'] && 'standard' !== $unit['format'] ) {
				self::$used[ $unit['id'] ] = self::js_config( $unit );
				continue;
			}
			if ( 'banner' === $unit['partner'] && ( ! self::in_schedule( $unit ) || ! self::banner_creatives( $unit ) ) ) {
				continue;
			}
			self::render_unit( $unit, $placement );
		}
	}

	private static function render_unit( array $unit, string $placement ): void {
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
			default: // gam
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
		return array_values(
			array_filter(
				(array) $unit['creatives'],
				static fn( $c ) => is_array( $c ) && ( ! empty( $c['image'] ) || ! empty( $c['image_url'] ) )
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
		return array( $url, 0, 0 );
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
			$href  = (string) ( $c['url'] ?? '' );
			$tag   = $href ? 'a' : 'div';
			$open  = $href
				? sprintf( '<a class="bday-ad__creative" href="%s" rel="sponsored noopener"%s data-bday-ad-click', esc_url( $href ), $unit['new_tab'] ? ' target="_blank"' : '' )
				: '<div class="bday-ad__creative"';
			echo $open . sprintf( ' style="--bday-ad-ar-d:%s;--bday-ad-ar-m:%s;"%s>', esc_attr( $ar_d ), esc_attr( $ar_m ), $i > 0 ? ' hidden' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput
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
			if ( 'content' !== $def['kind'] || isset( self::$injected[ $id ] ) || ! self::placement_applies( $id, $post ) ) {
				continue;
			}
			$conf = Bday_Ads_Store::placement( $id );
			$wanted[ max( 1, (int) $conf['paragraph'] ) ][] = array( $id, (int) $conf['min_paragraphs'] );
		}
		if ( ! $wanted ) {
			return $html;
		}

		$segments = preg_split( '/(?<=<\/p>)/i', $html );
		$depth    = 0;
		$top      = array(); // segment index => top-level paragraph number ending there
		$n        = 0;
		foreach ( $segments as $i => $seg ) {
			$depth += preg_match_all( '/<(blockquote|figure|ul|ol|table|aside|details)\b[^>]*>/i', $seg ) - preg_match_all( '/<\/(blockquote|figure|ul|ol|table|aside|details)>/i', $seg );
			if ( $depth <= 0 && preg_match( '/<\/p>\s*$/i', $seg ) ) {
				$depth      = 0;
				$top[ $i ]  = ++$n;
			}
		}
		if ( 0 === $n ) {
			return $html;
		}

		$out = '';
		foreach ( $segments as $i => $seg ) {
			$out .= $seg;
			$para = $top[ $i ] ?? 0;
			if ( $para && $para < $n && isset( $wanted[ $para ] ) ) {
				foreach ( $wanted[ $para ] as list( $id, $min ) ) {
					if ( $n < $min ) {
						continue;
					}
					self::$injected[ $id ] = true;
					ob_start();
					self::zone( $id, $post );
					$out .= ob_get_clean();
				}
			}
		}
		return $out;
	}

	/* ── Loader config ────────────────────────────────────────────── */

	public static function print_config(): void {
		if ( ! bday_page_allows_ads() ) {
			return;
		}

		// Sticky / full-screen formats have no template call; they ride on their own placement.
		if ( self::placement_applies( 'global_oop', null ) ) {
			foreach ( (array) Bday_Ads_Store::placement( 'global_oop' )['units'] as $unit_id ) {
				$unit = Bday_Ads_Store::unit( (string) $unit_id );
				if ( $unit && 'active' === $unit['status'] ) {
					self::$used[ $unit['id'] ] = self::js_config( $unit );
				}
			}
		}
		if ( ! self::$used ) {
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
	return array( 'mode' => $mode, 'hide' => $hide, 'max' => max( 0, (int) ( $raw['max'] ?? 0 ) ) );
}

/* ── Public template API ────────────────────────────────────────── */

/** Renders the units assigned to a placement, if the placement is allowed here. */
function bday_ad_zone( string $zone, ?WP_Post $post = null ): void {
	Bday_Ads_Render::zone( $zone, $post );
}

/** Same, for already-rendered article HTML (used by the article template). */
function bday_ads_inject_in_content( string $html, ?WP_Post $post = null ): string {
	return Bday_Ads_Render::inject( $html, $post );
}

add_action( 'wp_footer', array( 'Bday_Ads_Render', 'print_config' ), 5 );

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
