<?php
/**
 * Section Name: Headlines
 * Section Slug: headlines
 * Description: A dark strip of three category-labeled headlines (Economy, Companies, Politics).
 * Default Enabled: yes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data = $args['data'] ?? array();
$cols = $data['rd_headlines'] ?? array();
if ( empty( $cols ) ) {
	return;
}
?>
<section class="bday-rd-headlines" data-screen-label="Headlines">
	<div class="bday-container bday-rd-headlines__grid">
		<div class="bday-rd-headlines__label">
			<h2>Headlines</h2>
			<span class="bday-rd-kicker bday-rd-kicker--muted">Updated <?php echo esc_html( date_i18n( 'H:i' ) ); ?></span>
		</div>
		<?php foreach ( $cols as $col ) : $post = $col['posts'][0] ?? null; if ( ! $post ) { continue; } ?>
			<div class="bday-rd-headlines__item">
				<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="bday-rd-headlines__item-link">
					<span class="bday-rd-kicker bday-rd-kicker--tint"><?php echo esc_html( $col['label'] ); ?></span>
					<span class="bday-rd-headlines__title"><?php echo esc_html( get_the_title( $post ) ); ?></span>
					<span class="bday-rd-kicker bday-rd-kicker--faint"><?php echo esc_html( bday_estimate_read_time( $post->ID ) ); ?> min read</span>
				</a>
				<?php if ( ! empty( $col['url'] ) && '#' !== $col['url'] ) : ?>
					<a href="<?php echo esc_url( $col['url'] ); ?>" class="bday-rd-kicker bday-rd-kicker--accent bday-rd-headlines__more">More <?php echo esc_html( $col['label'] ); ?> →</a>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
</section>
