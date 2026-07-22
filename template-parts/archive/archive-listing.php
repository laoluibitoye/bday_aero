<?php
/**
 * Shared listing partial for every "list of articles" page: category
 * archives, tags, author pages, and search results.
 *
 * Consolidates what used to be near-identical, hand-copied markup across
 * archive.php, tag.php, author.php, search.php, and — incorrectly, see
 * below — category-weekender.php/category-womens-hub.php/category-reports.php
 * (byte-for-byte identical to each other; they'd been copy-pasted from the
 * bare PDF-thumbnail-grid pattern meant for e-edition/cartoon rather than
 * built out into a real article listing). category-e-edition.php and
 * category-cartoon.php are NOT part of this consolidation — both render an
 * image grid, not an article list, which is a genuinely different layout,
 * not a duplicate.
 *
 * FIXES rolled in while consolidating (all were real, pre-existing bugs):
 * - archive.php/tag.php/author.php/category-weekender.php etc. referenced
 *   an undefined $paged variable, so "page 2" links silently re-rendered
 *   page 1 — pagination was broken on every one of these page types.
 * - archive.php/tag.php/author.php built each post's category-pill link via
 *   get_category_link(get_cat_ID(get_the_archive_title())) — passing a
 *   *string title* (sometimes the tag/author name, not a category at all)
 *   into a category-ID lookup. Replaced with each post's own real primary
 *   category, which is correct on every page type this partial serves.
 *
 * Caller contract — set these before requiring this file:
 * @var array  $archive_query_args   WP_Query args, WITHOUT 'paged' (added here).
 * @var string $archive_heading      Small eyebrow label, e.g. "Browsing Category".
 * @var string $archive_title        Page <h1> text.
 * @var bool   $archive_show_featured Whether to show the lead-story + 4-up
 *                                    "featured" tier above the flat list
 *                                    (archive.php's original treatment).
 * @var callable|null $archive_before_listing Optional — echoes extra markup
 *                                    right after the breadcrumb (search.php
 *                                    uses this for the search form/input).
 */

if ( ! isset( $archive_query_args ) || ! is_array( $archive_query_args ) ) {
	return;
}

$archive_show_featured = $archive_show_featured ?? false;
$archive_heading       = $archive_heading ?? '';
$archive_title         = $archive_title ?? get_the_archive_title();

$paged = get_query_var( 'paged' ) ? (int) get_query_var( 'paged' ) : 1;
$archive_query_args['paged'] = $paged;

$archive_data  = new WP_Query( $archive_query_args );
$archive_posts = $archive_data->posts;

$archive_upper_feature = [];
$archive_upper_others  = [];
if ( $archive_show_featured ) {
	$archive_upper_feature = array_splice( $archive_posts, 0, 1 );
	$archive_upper_others  = array_splice( $archive_posts, 0, 4 );
}

/**
 * Renders one post's category-pill link using the post's own primary
 * category — correct regardless of whether this partial is serving a
 * category archive, a tag, an author page, or search results.
 */
if ( ! function_exists( 'bd_archive_post_category_pill' ) ) {
	function bd_archive_post_category_pill( $post ) {
		$categories = get_the_category( $post->ID );
		if ( empty( $categories ) ) {
			return;
		}
		$cat = $categories[0];
		printf(
			'<span class="post-category"><a href="%s">%s</a></span>',
			esc_url( get_category_link( $cat->term_id ) ),
			esc_html( $cat->name )
		);
	}
}
?>
<?php if ( bd_page_allows_ads() ) : ?>
	<?php // ads render inline below, every 5 posts — see the loop ?>
<?php endif; ?>
<section id="category-page">
	<div class="breadcrumb">
		<ul>
			<li><a href="<?= esc_url( home_url( '/' ) ) ?>">Home</a></li>
			<li>></li>
			<li><?= esc_html( $archive_title ) ?></li>
		</ul>
	</div>

	<?php if ( ! empty( $archive_before_listing ) && is_callable( $archive_before_listing ) ) : ?>
		<?php $archive_before_listing(); ?>
	<?php endif; ?>

	<?php if ( $archive_show_featured && ! empty( $archive_upper_feature ) ) : ?>
	<div class="category-upper">
		<?php foreach ( $archive_upper_feature as $post ) : ?>
		<div class="featured">
			<article>
				<figure>
					<?php bd_archive_post_category_pill( $post ); ?>
					<a href="<?= get_the_permalink( $post->ID ); ?>"> <?= get_thumbnail( [ 'post_id' => $post->ID, 'size' => 'medium_rectangle' ] ) ?> </a>
				</figure>
				<div class="post-info">
					<h2 class="post-title"><a href="<?= get_the_permalink( $post->ID ); ?>"> <?= $post->post_title; ?> </a></h2>
					<div class="post-meta">
						<span class="post-author"><a href="<?= get_author_posts_url( get_the_author_meta( 'ID', get_post_field( 'post_author', $post->ID ) ) ) ?>"> <?= get_the_author_meta( 'display_name', get_post_field( 'post_author', $post->ID ) ) ?> </a></span>
						<span class="post-date"> <?= custom_time_format( $post->post_date, 'full' ) ?> </span>
					</div>
				</div>
			</article>
		</div>
		<?php endforeach; ?>

		<div class="thumbanils">
			<?php foreach ( $archive_upper_others as $post ) : ?>
			<article>
				<figure>
					<?php bd_archive_post_category_pill( $post ); ?>
					<a href="<?= get_the_permalink( $post->ID ); ?>"> <?= get_thumbnail( [ 'post_id' => $post->ID, 'size' => 'medium_rectangle' ] ) ?> </a>
				</figure>
				<div class="post-info">
					<h2 class="post-title"><a href="<?= get_the_permalink( $post->ID ); ?>"> <?= $post->post_title; ?> </a></h2>
				</div>
			</article>
			<?php endforeach; ?>
		</div>
	</div>
	<?php if ( bd_page_allows_ads() ) : ?>
		<?= do_shortcode('[admanager ad_id="desktop_1" placement="desktop" lazy="false"]'); ?>
		<?= do_shortcode('[adsense ad_id="medium_rectangle" placement="mobile" lazy="false"]'); ?>
	<?php endif; ?>
	<?php endif; ?>

	<?php if ( $archive_heading ) : ?>
	<section class="heading">
		<span><?= esc_html( $archive_heading ) ?></span>
	</section>
	<?php endif; ?>
	<header>
		<h1><?= esc_html( $archive_title ) ?></h1>
	</header>

	<div class="news">
		<?php
		$i = $j = 1;
		foreach ( $archive_posts as $post ) :
			?>
			<article>
				<figure>
					<?php bd_archive_post_category_pill( $post ); ?>
					<a href="<?= get_the_permalink( $post->ID ); ?>">
						<?= get_thumbnail( [ 'post_id' => $post->ID, 'size' => 'medium_rectangle' ] ) ?>
					</a>
				</figure>
				<div class="post-info">
					<h2 class="post-title"><a href="<?= get_the_permalink( $post->ID ); ?>"> <?= $post->post_title; ?> </a></h2>
					<div class="post-meta">
						<span class="post-author"><a href="<?= get_author_posts_url( get_the_author_meta( 'ID', get_post_field( 'post_author', $post->ID ) ) ) ?>"> <?= get_the_author_meta( 'display_name', get_post_field( 'post_author', $post->ID ) ) ?> </a></span>
						<span class="post-date"> <?= custom_time_format( $post->post_date, 'full' ) ?></span>
					</div>
					<p class="post-excerpt"><?= get_the_excerpt( $post->ID ) ?>...</p>
				</div>
			</article>
			<?php
			if ( bd_page_allows_ads() && ( $i % 5 ) === 0 ) {
				if ( $j === 1 ) {
					echo do_shortcode( '[adsense ad_id="half_page" placement="mobile" lazy="false"]' );
					echo do_shortcode( '[adsense ad_id="fluid" placement="desktop" lazy="false" mt mb]' );
				} else {
					echo do_shortcode( '[adsense ad_id="fluid" lazy="false"]' );
				}
				$j++;
			}
			$i++;
		endforeach;
		?>
		<div class="pagination">
			<?php echo paginate_links( [ 'mid_size' => 2, 'total' => $archive_data->max_num_pages, 'next_text' => '»', 'prev_text' => '«' ] ); ?>
		</div>
	</div>
</section>
