<?php
/**
 * The "post content type" sections — homepage-sections/*.php files whose
 * entire content is exactly one tag or category query, and are therefore
 * safe to let a Technical Team member repoint at a different tag/category
 * (Bday_Section_Content::source()) without having to touch template code.
 *
 * Deliberately not every section: composite ones that blend multiple
 * queries (Hero, Editor's Pick & Most Read, Headlines, Topics three-up,
 * Browse the Desks, Watch & Listen), ones sourced from a non-taxonomy
 * signal (Toon of the Day and In Pictures — a post type and a post
 * format, not a term), and ones already admin-configurable elsewhere
 * (Your News via Settings -> News Carousel, Off the Clock via its own
 * admin screen) are left out — same conservative "only wire up a real,
 * single source" posture core/homepage/redesign-data.php's own docblock
 * already commits to.
 *
 * @return array<string, array{label: string, taxonomy: string, term: string, count: int}>
 */
function bday_section_sources(): array {
	return array(
		'columnists'      => array( 'label' => 'Columnists',     'taxonomy' => 'category', 'term' => 'Columnist',              'count' => 6 ),
		'opinion'         => array( 'label' => 'Opinion',        'taxonomy' => 'category', 'term' => 'opinion',                'count' => 7 ),
		'premium'         => array( 'label' => 'Premium',        'taxonomy' => 'post_tag', 'term' => 'premium',                'count' => 11 ),
		'investigates'    => array( 'label' => 'BD Investigates', 'taxonomy' => 'post_tag', 'term' => 'bdinvestigates',        'count' => 4 ),
		'interview'       => array( 'label' => 'The Interview',  'taxonomy' => 'post_tag', 'term' => 'bd-interview',           'count' => 4 ),
		'partner-content' => array( 'label' => 'Partner Content', 'taxonomy' => 'post_tag', 'term' => 'sponsored',             'count' => 4 ),
		'ysot'            => array( 'label' => 'YSoT',           'taxonomy' => 'category', 'term' => 'yaba-school-of-thought', 'count' => 7 ),
		'latest-stories'  => array( 'label' => 'Latest Stories', 'taxonomy' => 'post_tag', 'term' => 'bdrecent',               'count' => 8 ),
	);
}

/**
 * Runs the query for one single-source section, honoring a saved
 * Technical Team override (Bday_Section_Content::source()) if there is
 * one, otherwise falling back to the tag/category this theme ships with.
 * An unknown $slug (not in bday_section_sources()) returns an empty
 * array rather than fatal — callers already treat "no posts" as "don't
 * render this section", the same as every other empty-data case.
 *
 * @return WP_Post[]
 */
function bday_section_source_posts( string $slug ): array {
	$defaults = bday_section_sources();
	if ( ! isset( $defaults[ $slug ] ) ) {
		return array();
	}

	$default  = $defaults[ $slug ];
	$override = Bday_Section_Content::source( $slug );
	$taxonomy = $override['taxonomy'] ?? $default['taxonomy'];
	$term     = $override['term'] ?? $default['term'];

	$args = array( 'numberposts' => $default['count'], 'cache_namespace' => 'homepage' );
	$args[ 'post_tag' === $taxonomy ? 'tag' : 'category_name' ] = $term;

	return bday_get_posts( $args );
}
