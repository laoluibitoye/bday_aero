<?php
/**
 * Section Name: E-editions
 * Section Slug: editions
 * Description: A full-width grid of recent edition covers, each with its own View + Past editions actions (different publications have different archives, so one shared action pair at the section's end didn't make sense).
 * Default Enabled: yes
 *
 * Gated by Homepage Modules' "E-Editions row" toggle, same as the classic
 * homepage's own editions row.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$modules = wp_parse_args( get_option( 'bday_addon_homepage_modules', array() ), array( 'enable_editions_row' => true ) );
if ( empty( $modules['enable_editions_row'] ) ) {
	return;
}

$data  = $args['data'] ?? array();
$cards = $data['rd_editions'] ?? array();
if ( empty( $cards ) ) {
	return;
}
?>
<section class="bday-rd-editions" data-screen-label="E-editions">
	<div class="bday-container">
		<div class="bday-rd-section-head">
			<h2>E-editions</h2>
			<span class="bday-rd-rule"></span>
		</div>
		<div class="bday-rd-editions__grid">
			<?php foreach ( $cards as $card ) : $edition = $card['edition']; $publication = $card['publication']; $has_thumb = has_post_thumbnail( $edition->ID ); ?>
				<div class="bday-rd-edition-card<?php echo $has_thumb ? '' : ' bday-rd-edition-card--no-thumb'; ?>">
					<a href="<?php echo esc_url( get_permalink( $edition ) ); ?>" class="bday-rd-edition-card__media">
						<?php if ( $has_thumb ) : ?><?php echo bday_get_thumbnail( $edition->ID, 'pdf_thumbnail' ); ?><?php endif; ?>
					</a>
					<span class="bday-rd-edition-card__name"><?php echo esc_html( $publication ? $publication->name : get_the_title( $edition ) ); ?></span>
					<span class="bday-rd-kicker bday-rd-kicker--faint"><?php echo esc_html( bday_format_date( $edition->post_date ) ); ?></span>
					<div class="bday-rd-edition-card__actions">
						<a href="<?php echo esc_url( get_permalink( $edition ) ); ?>" class="bday-rd-btn bday-rd-btn--solid">View</a>
						<?php if ( $publication ) : ?>
							<a href="<?php echo esc_url( get_term_link( $publication ) ); ?>" class="bday-rd-btn bday-rd-btn--outline">Past editions</a>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
