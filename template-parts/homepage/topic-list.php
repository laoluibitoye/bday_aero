<?php
/**
 * Reusable headline module (WSJ-layout adoption) — one BusinessDay
 * category per module, called several times from
 * homepage-variants/default.php rather than duplicated per section.
 *
 * Three layouts, reader-requested homepage variety (mixed thumbnail
 * sizes, some sections with no image, some as a bigger card grid) —
 * WSJ/NYT front pages never repeat the exact same module shape section
 * after section, which is what made the previous single-layout version
 * read as flat/uniform however much real content sat behind it:
 *   'list'  (default) — small thumbnail + title + timestamp, unchanged
 *           from the original module.
 *   'text'  — no thumbnails at all, a denser pure-headline list (this
 *           section's own stories are the visual variety, not an image).
 *   'grid'  — bday_card_html()'s larger card grid (same component the
 *           homepage's "In Other News" row already uses), for a section
 *           the editor wants to give more visual weight to.
 *
 * Deliberately doesn't route its heading link through bday_section_url()
 * — see original rationale, unchanged.
 *
 * Args:
 *   'heading'        string
 *   'category_slug'  string
 *   'posts'          WP_Post[]
 *   'layout'         'list'|'text'|'grid', default 'list'
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$heading       = $args['heading'] ?? '';
$category_slug = $args['category_slug'] ?? '';
$posts         = $args['posts'] ?? array();
$layout        = in_array( $args['layout'] ?? 'list', array( 'list', 'text', 'grid' ), true ) ? $args['layout'] : 'list';
if ( empty( $posts ) ) {
	return;
}
$category      = $category_slug ? get_category_by_slug( $category_slug ) : null;
$heading_url   = $category ? get_category_link( $category ) : '#';
?>
<div class="bday-topic-list bday-topic-list--<?php echo esc_attr( $layout ); ?>">
	<h2 class="bday-section-heading"><a href="<?php echo esc_url( $heading_url ); ?>"><?php echo esc_html( $heading ); ?></a></h2>
	<?php if ( 'grid' === $layout ) : ?>
		<div class="bday-card-grid">
			<?php foreach ( $posts as $post ) : ?>
				<?php echo bday_card_html( $post, array( 'show_byline' => true, 'show_excerpt' => true ) ); ?>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<ul class="bday-topic-list__items">
			<?php foreach ( $posts as $post ) : ?>
				<li>
					<?php if ( 'list' === $layout ) : ?>
						<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="bday-topic-list__media"><?php echo bday_get_card_media( $post->ID, 'small' ); ?></a>
					<?php endif; ?>
					<div class="bday-topic-list__body">
						<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="bday-topic-list__title"><?php echo esc_html( get_the_title( $post ) ); ?></a>
						<span class="bday-topic-list__time"><?php echo esc_html( bday_time_ago( $post->post_date ) ); ?></span>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
