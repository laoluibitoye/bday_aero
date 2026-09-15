<?php
/**
 * Addon Name: Search Results Page
 * Addon Slug: search-results
 * Description: Serves /search-results/?q=... as a real page of search results. This exact URL shape is where an edge-level redirect (Cloudflare, confirmed live on the staging site) already sends every native ?s= search — that redirect target never corresponded to an actual WordPress route, so every search 404'd regardless of whether matching articles existed. This addon doesn't touch or depend on that redirect; it just makes its destination real.
 * Default: on
 *
 * Deliberately not a real WP Page + rewrite rule (same reasoning as
 * addons/editions/includes/flipbook-reader.php's ?bday_reader= route): a
 * rewrite rule needs a flush_rewrite_rules() to take effect, which means a
 * manual wp-admin step (or a fragile auto-flush-on-init) before this would
 * do anything on a site that's already live. Matching the raw request path
 * directly in template_redirect works immediately on deploy, no admin step,
 * no rewrite flush, and follows the same pattern this theme already uses
 * for exactly this kind of problem.
 *
 * The theme's own search forms (search.php, header.php's overlay) are left
 * exactly as they are — still a plain ?s= submit to home_url('/'). Native
 * WordPress search already works correctly (verified directly against the
 * live backend); this addon only makes /search-results/ itself a working
 * destination, on the reasonable assumption that whatever originally
 * pointed the edge redirect there is easier to leave in place than to
 * track down and remove. If that redirect is ever removed, ?s= search
 * keeps working through search.php exactly as it does today — this route
 * is a second, independent front door, not a replacement for the first.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'template_redirect',
	static function (): void {
		$path = trim( (string) parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
		if ( 'search-results' !== $path ) {
			return;
		}

		$query = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

		// The main query already resolved this path to a 404 (nothing in WordPress's own
		// rewrite rules matches it) — override that status and the document title/robots
		// meta it drives before any output starts, so this reads as a real page, not an
		// error page that happens to show content.
		status_header( 200 );
		add_filter(
			'document_title_parts',
			static function ( array $parts ) use ( $query ): array {
				$parts['title'] = '' !== $query ? sprintf( 'Search results for "%s"', $query ) : 'Search';
				return $parts;
			}
		);
		add_filter( 'wp_robots', 'wp_robots_noindex' );

		$paged = max( 1, (int) ( $_GET['paged'] ?? 1 ) );

		get_header();
		?>
		<header class="bday-container">
			<h1 class="bday-archive-title">
				<?php if ( '' !== $query ) : ?>
					Search results for "<?php echo esc_html( $query ); ?>"
				<?php else : ?>
					Search
				<?php endif; ?>
			</h1>
			<form role="search" method="get" action="<?php echo esc_url( home_url( '/search-results/' ) ); ?>" class="bday-search-form">
				<input type="search" name="q" value="<?php echo esc_attr( $query ); ?>" placeholder="Search…">
				<input type="submit" value="Search">
			</form>
		</header>
		<div class="bday-container">
			<?php if ( '' === $query ) : ?>
				<p>Enter a search term above.</p>
			<?php else : ?>
				<?php
				$query_args = array(
					'post_type'      => 'post',
					'posts_per_page' => 12,
					'paged'          => $paged,
					's'              => $query,
				);
				$results = Bday_Query_Cache::query( 'search_results', md5( wp_json_encode( $query_args ) ), $query_args, MINUTE_IN_SECONDS );
				?>
				<?php if ( $results->have_posts() ) : ?>
					<div class="bday-card-grid">
						<?php while ( $results->have_posts() ) : $results->the_post(); ?>
							<?php echo bday_card_html( get_post(), array( 'show_byline' => true, 'show_excerpt' => true ) ); ?>
							<?php if ( 0 === ( $results->current_post + 1 ) % 6 ) : ?>
								<?php bday_ad_zone( 'below_article_recirculation' ); ?>
							<?php endif; ?>
						<?php endwhile; ?>
					</div>
					<div class="bday-pagination">
						<?php
						// Not bday_render_load_more_button() here: that helper builds the "next page"
						// URL via get_pagenum_link(), which derives it from the current request's
						// rewrite-matched path — this route has no matching rewrite rule at all (see
						// this file's own docblock), so on a pretty-permalinks site it would mangle
						// the query string instead of producing a working /search-results/ URL. Built
						// explicitly instead; same data-* contract assets/src/js/script.js's load-more
						// handler already expects, so no JS change needed.
						if ( $paged < $results->max_num_pages ) :
							// Built explicitly rather than via add_query_arg(), whose encoding of the
							// values passed to it is inconsistent enough across WP versions to be
							// worth not depending on here — this is the one, unambiguous encoding
							// pass, and esc_url() below is safe to layer on top of it since it
							// preserves already-valid %XX sequences rather than re-encoding them.
							$next_url = home_url( '/search-results/' ) . '?q=' . rawurlencode( $query ) . '&paged=' . ( $paged + 1 );
							?>
							<div class="bday-load-more" data-bday-load-more data-target=".bday-card-grid" data-next-url="<?php echo esc_url( $next_url ); ?>">
								<button type="button" class="bday-load-more__button">Load more</button>
							</div>
						<?php endif; ?>
					</div>
					<?php wp_reset_postdata(); ?>
				<?php else : ?>
					<p>No results for "<?php echo esc_html( $query ); ?>".</p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
		get_footer();
		exit;
	},
	5
);
