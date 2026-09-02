<?php
/**
 * Section Name: Topics (three-up)
 * Section Slug: topic-triple
 * Description: Three category columns side by side, each with one lead story and a short list beneath it.
 * Default Enabled: yes
 *
 * The source design labels this "Tech / Economy / Legal" — this theme has
 * no Tech category, so Economy / World / Law fill the three columns
 * instead (all three already fetched by bday_get_homepage_data() for the
 * classic layout's own topic-list modules).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data = $args['data'] ?? array();
$cols = array_values(
	array_filter(
		array(
			array( 'label' => 'Economy', 'url' => bday_category_url( 'economy' ), 'posts' => $data['topic_economy'] ?? array() ),
			array( 'label' => 'World', 'url' => bday_category_url( 'world' ), 'posts' => $data['topic_world'] ?? array() ),
			array( 'label' => 'Law', 'url' => bday_category_url( 'law' ), 'posts' => $data['topic_law'] ?? array() ),
		),
		static fn( array $c ): bool => ! empty( $c['posts'] )
	)
);
if ( empty( $cols ) ) {
	return;
}
?>
<section class="bday-rd-topic-triple" data-screen-label="Tech Economy Legal">
	<div class="bday-container bday-rd-topic-triple__grid">
		<?php foreach ( $cols as $col ) : $lead = $col['posts'][0]; $more = array_slice( $col['posts'], 1, 3 ); ?>
			<div class="bday-rd-topic-triple__col">
				<div class="bday-rd-topic-triple__head">
					<a href="<?php echo esc_url( $col['url'] ); ?>" class="bday-rd-kicker"><?php echo esc_html( $col['label'] ); ?></a>
					<span class="bday-rd-rule"></span>
					<a href="<?php echo esc_url( $col['url'] ); ?>" class="bday-rd-kicker bday-rd-kicker--accent">More</a>
				</div>
				<a href="<?php echo esc_url( get_permalink( $lead ) ); ?>" class="bday-rd-topic-triple__lead">
					<?php if ( has_post_thumbnail( $lead->ID ) ) : ?><?php echo bday_get_card_media( $lead->ID, 'top_story' ); ?><?php endif; ?>
					<h3><?php echo esc_html( get_the_title( $lead ) ); ?></h3>
					<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $lead ), 18 ) ); ?></p>
				</a>
				<div class="bday-rd-topic-triple__more">
					<?php foreach ( $more as $post ) : ?>
						<a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</section>
