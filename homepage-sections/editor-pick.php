<?php
/**
 * Section Name: Editor's Pick & Most Read
 * Section Slug: editor-pick
 * Description: A lead story plus two picks from the "editorial" category, beside a Most Read list ranked by comment count (this theme's only real engagement signal — no page-view counter exists).
 * Default Enabled: yes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data      = $args['data'] ?? array();
$spotlight = $data['feature_spotlight'] ?? array();
$lead      = $spotlight[0] ?? null;
$picks     = $data['rd_editor_pick'] ?? array();
$most_read = $data['rd_most_read'] ?? array();
$more_picks = $data['rd_editor_pick_more'] ?? array();
if ( ! $lead && empty( $most_read ) ) {
	return;
}
?>
<section class="bday-rd-editor-pick" data-screen-label="Editors pick and most read">
	<div class="bday-container bday-rd-editor-pick__grid">
		<div class="bday-rd-editor-pick__col">
			<div class="bday-rd-section-head">
				<h2>Editor's Pick</h2>
				<span class="bday-rd-rule"></span>
				<a href="<?php echo esc_url( bday_category_url( 'editorial' ) ); ?>" class="bday-rd-kicker bday-rd-kicker--accent">See more →</a>
			</div>
			<?php if ( $lead ) : ?>
				<a href="<?php echo esc_url( get_permalink( $lead ) ); ?>" class="bday-rd-editor-pick__lead">
					<?php if ( has_post_thumbnail( $lead->ID ) ) : ?><?php echo bday_get_card_media( $lead->ID, 'featured' ); ?><?php endif; ?>
					<?php $cats = get_the_category( $lead->ID ); ?>
					<?php if ( ! empty( $cats ) ) : ?><span class="bday-rd-kicker bday-rd-kicker--accent"><?php echo esc_html( $cats[0]->name ); ?></span><?php endif; ?>
					<h3><?php echo esc_html( get_the_title( $lead ) ); ?></h3>
					<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $lead ), 22 ) ); ?></p>
					<span class="bday-rd-kicker bday-rd-kicker--faint"><?php echo esc_html( strtoupper( get_the_author_meta( 'display_name', $lead->post_author ) ) ); ?> · <?php echo esc_html( bday_estimate_read_time( $lead->ID ) ); ?> MIN READ</span>
				</a>
			<?php endif; ?>
			<?php if ( ! empty( $picks ) ) : ?>
				<div class="bday-rd-editor-pick__more">
					<?php foreach ( $picks as $post ) : ?>
						<a href="<?php echo esc_url( get_permalink( $post ) ); ?>">
							<?php if ( has_post_thumbnail( $post->ID ) ) : ?><?php echo bday_get_card_media( $post->ID, 'medium_rectangle' ); ?><?php endif; ?>
							<span class="bday-rd-editor-pick__more-title"><?php echo esc_html( get_the_title( $post ) ); ?></span>
							<span class="bday-rd-kicker bday-rd-kicker--faint"><?php echo esc_html( bday_time_ago( $post->post_date ) ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $most_read ) ) : ?>
			<div class="bday-rd-editor-pick__col">
				<div class="bday-rd-section-head">
					<h2>Focus</h2>
					<span class="bday-rd-rule"></span>
					<span class="bday-rd-kicker bday-rd-kicker--faint">This week</span>
				</div>
				<ol class="bday-rd-most-read">
					<?php foreach ( $most_read as $i => $post ) : ?>
						<li>
							<a href="<?php echo esc_url( get_permalink( $post ) ); ?>">
								<span class="bday-rd-most-read__n"><?php echo esc_html( $i + 1 ); ?></span>
								<span class="bday-rd-most-read__title"><?php echo esc_html( get_the_title( $post ) ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ol>
				<?php if ( ! empty( $more_picks ) ) : ?>
					<div class="bday-rd-editor-pick__thumb-list">
						<?php foreach ( $more_picks as $post ) : ?>
							<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="bday-rd-editor-pick__thumb-item<?php echo has_post_thumbnail( $post->ID ) ? '' : ' bday-rd-editor-pick__thumb-item--no-thumb'; ?>">
								<?php if ( has_post_thumbnail( $post->ID ) ) : ?><?php echo bday_get_card_media( $post->ID, 'small_category' ); ?><?php endif; ?>
								<span class="bday-rd-editor-pick__thumb-title"><?php echo esc_html( get_the_title( $post ) ); ?></span>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
