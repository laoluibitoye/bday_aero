<?php
/**
 * Data-fetch for the "Redesign 2026" homepage variant
 * (homepage-variants/redesign.php) — the sibling of
 * bday_get_homepage_data() for the section-registry-driven layout. Builds
 * on top of bday_get_homepage_data() rather than duplicating its queries
 * (same tag/category slugs, same bday_get_posts() caching), and adds only
 * the extra keys the new section templates (homepage-sections/*.php) need
 * that the classic layout never fetched — every new key is prefixed
 * `rd_` so it's obvious at a glance which keys are shared with the classic
 * homepage vs. new to this variant.
 *
 * Deliberately conservative about invented content: several sections in
 * the source design (BD Investigates, The Interview, Just for You, Plays,
 * Editor's Pick, Most Read-as-a-real-metric, Market Pulse, Partner
 * Content) need either a new editorial convention or a new subsystem this
 * theme doesn't have yet (see the homepage-rebuild-plan review doc) — this
 * file only fetches for the sections that already shipped in
 * homepage-sections/, not for those still-undecided ones.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bday_get_redesign_homepage_data(): array {
	$data = bday_get_homepage_data();

	// offset: 1 — the hero's own lead story ($data['lead'], also queried
	// from the "bdlead" tag with no offset) is item 0 of this same tag;
	// without the offset it would show a second time as "01" in the
	// numbered list right next to itself as the lead article.
	$data['rd_top_news'] = bday_get_posts( array( 'tag' => 'bdlead', 'numberposts' => 8, 'offset' => 1, 'cache_namespace' => 'homepage' ) );

	// Hero's third column ("Latest News") — genuinely the newest posts
	// site-wide (no tag), deliberately not the "bdrecent" tag Latest
	// Stories uses further down the page, so the two lists don't just
	// repeat the same editorially-curated set twice on one page.
	$data['rd_hero_latest'] = bday_get_posts( array( 'numberposts' => 8, 'cache_namespace' => 'homepage' ) );

	// Three real categories standing in for the design's Markets/Economy/
	// Companies trio — this theme has no "Markets" category, so Politics
	// fills the third slot instead of fabricating one.
	$data['rd_headlines'] = array_values(
		array_filter(
			array(
				array( 'label' => 'Economy', 'url' => bday_category_url( 'economy' ), 'posts' => $data['topic_economy'] ),
				array( 'label' => 'Companies', 'url' => bday_category_url( 'companies' ), 'posts' => $data['topic_companies'] ),
				array( 'label' => 'Politics', 'url' => bday_category_url( 'politics' ), 'posts' => $data['topic_politics'] ),
			),
			static fn( array $col ): bool => ! empty( $col['posts'] )
		)
	);

	// 1 feature + 2 medium + 8 "Also in Pro" = 11.
	$data['rd_premium'] = bday_get_posts( array( 'tag' => 'premium', 'numberposts' => 11, 'cache_namespace' => 'homepage' ) );
	$data['rd_opinion']  = bday_get_posts( array( 'category_name' => 'opinion', 'numberposts' => 7, 'cache_namespace' => 'homepage' ) );
	$data['rd_toon']     = post_type_exists( 'cartoons' )
		? bday_get_posts( array( 'post_type' => 'cartoons', 'numberposts' => 1, 'cache_namespace' => 'homepage' ) )
		: array();

	$data['rd_your_news']  = bday_get_redesign_your_news_desks();
	$data['rd_desk_tiles'] = bday_get_redesign_desk_tiles();
	$data['rd_editions']   = bday_get_redesign_editions();

	$data['rd_events'] = post_type_exists( 'events' )
		? bday_get_posts( array( 'post_type' => 'events', 'numberposts' => 4, 'cache_namespace' => 'homepage' ) )
		: array();

	// Editor-requested: "Videos to Watch" (Watch & Listen section) now pulls
	// from the standalone Videos content type (bday_video CPT) instead of the
	// "top-video" category on regular posts — a dedicated video shows up here
	// the moment it's published, with no separate tagging step. The classic
	// layout's own "BD TV row" (template-parts/homepage/video-row.php) is a
	// different homepage element and was deliberately left on "top-video" —
	// not part of this change.
	$data['rd_videos'] = post_type_exists( 'bday_video' )
		? bday_get_posts( array( 'post_type' => 'bday_video', 'numberposts' => 5, 'cache_namespace' => 'homepage' ) )
		: array();
	$data['rd_latest'] = bday_get_posts( array( 'tag' => 'bdrecent', 'numberposts' => 8, 'cache_namespace' => 'homepage' ) );

	// Off the Clock's columns are now an admin-editable list (Appearance -> BusinessDay Theme ->
	// Off the Clock — see core/homepage/off-the-clock-admin.php), not a hardcoded PHP array.
	// Weekender was reader-requested removed from the default set; Cooking/Sports/Life & Arts are
	// the new defaults, and an admin can add further lifestyle categories as they come online.
	$data['rd_weekender_cols'] = array_values(
		array_filter(
			array_map(
				static function ( array $row ): array {
					return array(
						'label' => $row['label'],
						'url'   => bday_category_url( $row['category_slug'] ),
						'posts' => bday_get_posts( array( 'category_name' => $row['category_slug'], 'numberposts' => 3, 'cache_namespace' => 'homepage' ) ),
					);
				},
				bday_off_the_clock_categories()
			),
			static fn( array $col ): bool => ! empty( $col['posts'] )
		)
	);

	// Phase 3 — the remaining sections from the source design. Editor's
	// Pick and Most Read already have a real, non-invented data source
	// (the classic homepage's own 'editorial' category and comment-count
	// proxy, both already fetched above as feature_spotlight/most_popular)
	// so those are wired straight through. BD Investigates / The Interview
	// / Partner Content / In Pictures have no existing content using these
	// conventions in this database — rather than inventing content for
	// them, each is wired to a real, sensible tag/post-format so the
	// section activates itself the moment an editor actually tags
	// something that way; every one of these queries is expected to
	// legitimately return empty right now, same "renders nothing until
	// there's something to render" posture the classic homepage's own
	// Columnists key already has (category_name: 'Columnist' — also
	// currently empty in this database, not something introduced here).
	$data['rd_editor_pick'] = array_slice( $data['feature_spotlight'], 1, 2 );
	$data['rd_most_read']   = $data['most_popular'];
	// Fills the empty space under the "Focus" (Most Read) list, which
	// otherwise runs shorter than the Editor's Pick column beside it —
	// offset: 3 skips the lead + 2 grid picks already shown above, so
	// this is genuinely more editorial-desk content, not a repeat.
	$data['rd_editor_pick_more'] = bday_get_posts( array( 'category_name' => 'editorial', 'numberposts' => 5, 'offset' => 3, 'cache_namespace' => 'homepage' ) );

	$data['rd_investigates'] = bday_get_posts( array( 'tag' => 'bdinvestigates', 'numberposts' => 4, 'cache_namespace' => 'homepage' ) );
	$data['rd_interview']    = bday_get_posts( array( 'tag' => 'bd-interview', 'numberposts' => 4, 'cache_namespace' => 'homepage' ) );
	$data['rd_partner']      = bday_get_posts( array( 'tag' => 'sponsored', 'numberposts' => 4, 'cache_namespace' => 'homepage' ) );
	$data['rd_ysot']         = bday_get_posts( array( 'category_name' => 'yaba-school-of-thought', 'numberposts' => 7, 'cache_namespace' => 'homepage' ) );
	$data['rd_gallery']      = bday_get_posts(
		array(
			'tax_query'       => array(
				array( 'taxonomy' => 'post_format', 'field' => 'slug', 'terms' => 'post-format-gallery' ),
			),
			'numberposts'     => 5,
			'cache_namespace' => 'homepage',
		)
	);

	return $data;
}

/**
 * "Your News" desks. The News Carousel addon (addons/news-carousel/) used
 * to render its own separate multi-column carousel widget on this same
 * homepage — reader-reported as a straight duplicate of this section (both
 * were literally headed "Your News" at one point; see homepage-sections/
 * git history) — so that widget's own homepage placement was removed and
 * its wp-admin settings tab (Settings → News Carousel) is now what drives
 * these desks instead: an admin picks explicit categories/tags with their
 * own display titles there, same "Column 1..N" UI that already existed,
 * just wired to a different renderer. Falls back to the previous
 * auto-pick-the-busiest-categories behavior whenever no columns are
 * configured yet (addon default-off, or a fresh install), so the section
 * never just goes empty for lack of admin setup — same "no config yet =
 * show something sensible" posture as every other auto-discovery
 * mechanism in this theme.
 *
 * @return array<int, array{name: string, url: string, posts: WP_Post[]}>
 */
function bday_get_redesign_your_news_desks( int $desk_count = 8, int $per_desk = 5 ): array {
	$configured = function_exists( 'bday_news_carousel_columns' ) ? bday_news_carousel_columns() : array();

	if ( ! empty( $configured ) ) {
		$desks = array();
		foreach ( $configured as $col ) {
			$slug = $col['slug'] ?? '';
			if ( '' === $slug ) {
				continue;
			}
			$is_tag = 'tag' === ( $col['type'] ?? 'category' );
			$args   = array( 'numberposts' => $per_desk, 'cache_namespace' => 'homepage' );
			$args[ $is_tag ? 'tag' : 'category_name' ] = $slug;

			$posts = bday_get_posts( $args );
			if ( empty( $posts ) ) {
				continue;
			}

			$term = $is_tag ? get_term_by( 'slug', $slug, 'post_tag' ) : get_category_by_slug( $slug );
			$url  = $term ? (string) ( $is_tag ? get_tag_link( $term ) : get_category_link( $term ) ) : '#';

			$desks[] = array(
				'name'  => '' !== ( $col['title'] ?? '' ) ? $col['title'] : ( $term->name ?? $slug ),
				'url'   => $url,
				'posts' => $posts,
			);
		}
		if ( ! empty( $desks ) ) {
			return $desks;
		}
	}

	$desks = array();

	foreach ( get_categories( array( 'hide_empty' => true, 'number' => $desk_count, 'orderby' => 'count', 'order' => 'DESC' ) ) as $category ) {
		$posts = bday_get_posts( array( 'category_name' => $category->slug, 'numberposts' => $per_desk, 'cache_namespace' => 'homepage' ) );
		if ( empty( $posts ) ) {
			continue;
		}
		$desks[] = array(
			'name'  => $category->name,
			'url'   => (string) get_category_link( $category ),
			'posts' => $posts,
		);
	}

	return $desks;
}

/** @return array<int, array{category: WP_Term, thumbnail_post: WP_Post|null}> */
function bday_get_redesign_desk_tiles( int $count = 6 ): array {
	$tiles = array();

	foreach ( get_categories( array( 'hide_empty' => true, 'number' => $count, 'orderby' => 'count', 'order' => 'DESC' ) ) as $category ) {
		$latest          = bday_get_posts( array( 'category_name' => $category->slug, 'numberposts' => 1, 'cache_namespace' => 'homepage' ) );
		$tiles[] = array(
			'category'       => $category,
			'thumbnail_post' => $latest[0] ?? null,
		);
	}

	return $tiles;
}

/**
 * The E-editions carousel: one card per publication (edition_publication taxonomy term), each
 * showing that publication's single most recent edition — reader-requested, so the rail is a
 * carousel of "what's the latest issue of each real publication" rather than a chronological feed
 * that could show the same publication more than once and skip others entirely. Matches
 * addons/editions/includes/homepage.php's classic-homepage row logic exactly, just rendered here
 * as the redesign variant's own carousel (that classic row stays untouched, still used on the
 * classic/weekend homepage variants).
 *
 * @return array<int, array{edition: WP_Post, publication: WP_Term|null}>
 */
function bday_get_redesign_editions(): array {
	if ( ! post_type_exists( 'bday_edition' ) ) {
		return array();
	}

	$publications = get_terms( array( 'taxonomy' => 'edition_publication', 'hide_empty' => true ) );
	if ( is_wp_error( $publications ) || empty( $publications ) ) {
		return array();
	}

	$cards = array();
	foreach ( $publications as $publication ) {
		$latest = bday_get_posts(
			array(
				'post_type'       => 'bday_edition',
				'numberposts'     => 1,
				'tax_query'       => array(
					array( 'taxonomy' => 'edition_publication', 'field' => 'term_id', 'terms' => $publication->term_id ),
				),
				'cache_namespace' => 'homepage',
			)
		);
		if ( empty( $latest ) ) {
			continue;
		}
		$cards[] = array( 'edition' => $latest[0], 'publication' => $publication );
	}

	return $cards;
}
