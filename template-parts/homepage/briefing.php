<?php
/**
 * "The Briefing" — a short-form, image-free headline strip (Africa/World/
 * Politics), styled after Semafor's flagship briefing format: numbered,
 * dense, scannable in a few seconds. Every other homepage section is
 * card/image-based; this is the one deliberately text-only counterpoint,
 * sitting right under the hero where a reader who wants "just tell me
 * what happened" lands first.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// See hero.php's comment for why this normalization is needed — this
// WordPress core doesn't extract get_template_part()'s $args for us.
$data     = $args['data'] ?? array();
$briefing = $data['briefing'] ?? array();
if ( empty( $briefing ) ) {
	return;
}
?>
<section class="bday-briefing">
	<div class="bday-container">
		<h2 class="bday-eyebrow">The Briefing</h2>
		<ol class="bday-briefing__list">
			<?php foreach ( $briefing as $i => $post ) : ?>
				<li>
					<span class="bday-briefing__index"><?php echo esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
					<a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a>
					<?php
					$categories = get_the_category( $post->ID );
					$primary    = $categories[0] ?? null;
					if ( $primary ) :
						?>
						<span class="bday-briefing__tag"><?php echo esc_html( $primary->name ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
</section>
