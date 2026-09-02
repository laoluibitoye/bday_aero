<?php
/**
 * Section Name: Browse the Desks
 * Section Slug: browse-desks
 * Description: Tiles for the six busiest categories, each with a recent thumbnail and post count.
 * Default Enabled: yes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data  = $args['data'] ?? array();
$tiles = $data['rd_desk_tiles'] ?? array();
if ( empty( $tiles ) ) {
	return;
}
?>
<section class="bday-rd-desk-tiles" data-screen-label="Sections">
	<div class="bday-container">
		<div class="bday-rd-section-head">
			<h2>Browse the Desks</h2>
			<span class="bday-rd-rule"></span>
		</div>
		<div class="bday-rd-desk-tiles__grid">
			<?php foreach ( $tiles as $tile ) : $category = $tile['category']; $post = $tile['thumbnail_post']; ?>
				<a href="<?php echo esc_url( get_category_link( $category ) ); ?>" class="bday-rd-desk-tile">
					<?php if ( $post && has_post_thumbnail( $post->ID ) ) : ?><?php echo bday_get_card_media( $post->ID, 'medium_rectangle' ); ?><?php endif; ?>
					<span class="bday-rd-desk-tile__name"><?php echo esc_html( $category->name ); ?></span>
					<span class="bday-rd-kicker bday-rd-kicker--faint"><?php echo esc_html( number_format_i18n( $category->count ) ); ?> stories</span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
