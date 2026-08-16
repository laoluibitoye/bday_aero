<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bday_news_carousel_columns(): array {
	$settings = get_option( 'bday_addon_news_carousel', array() );
	return is_array( $settings['columns'] ?? null ) ? $settings['columns'] : array();
}

function bday_news_carousel_render(): void {
	$settings = get_option( 'bday_addon_news_carousel', array() );
	$columns  = bday_news_carousel_columns();
	if ( empty( $columns ) ) {
		return;
	}

	$auto_scroll  = ! empty( $settings['auto_scroll'] );
	$scroll_speed = (int) ( $settings['scroll_speed'] ?? 5000 );

	$rendered = array();
	foreach ( $columns as $col ) {
		if ( empty( $col['slug'] ) ) {
			continue;
		}
		$args = array( 'numberposts' => 5, 'cache_namespace' => 'news_carousel' );
		if ( 'tag' === ( $col['type'] ?? 'category' ) ) {
			$args['tag'] = $col['slug'];
		} else {
			$args['category_name'] = $col['slug'];
		}
		$posts = bday_get_posts( $args );
		if ( empty( $posts ) ) {
			continue;
		}
		$rendered[] = array( 'title' => $col['title'] ?? '', 'posts' => $posts );
	}

	if ( empty( $rendered ) ) {
		return;
	}
	?>
	<section class="bday-news-carousel" data-auto-scroll="<?php echo $auto_scroll ? esc_attr( $scroll_speed ) : ''; ?>">
		<div class="bday-container">
			<h2 class="bday-section-heading">News Carousel</h2>
			<div class="bday-news-carousel__nav">
				<button type="button" class="bday-news-carousel__prev" aria-label="Previous">‹</button>
				<button type="button" class="bday-news-carousel__next" aria-label="Next">›</button>
			</div>
			<div class="bday-news-carousel__track">
				<?php foreach ( $rendered as $index => $col ) : ?>
					<div class="bday-news-carousel__item<?php echo 0 === $index ? ' is-active' : ''; ?>">
						<div class="bday-news-carousel__card">
							<h3><?php echo esc_html( $col['title'] ); ?></h3>
							<ul>
								<?php foreach ( $col['posts'] as $post ) : ?>
									<li>
										<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="bday-news-carousel__title"><?php echo esc_html( get_the_title( $post ) ); ?></a>
										<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="bday-news-carousel__thumb" tabindex="-1" aria-hidden="true"><?php echo bday_get_card_media( $post->ID, 'small' ); ?></a>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<?php if ( count( $rendered ) > 1 ) : ?>
				<div class="bday-news-carousel__dots">
					<?php foreach ( $rendered as $index => $col ) : ?>
						<button type="button" class="bday-news-carousel__dot<?php echo 0 === $index ? ' is-active' : ''; ?>" data-index="<?php echo (int) $index; ?>" aria-label="<?php echo esc_attr( 'Go to ' . $col['title'] ); ?>"></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<?php
}

function bday_news_carousel_settings_tab(): void {
	$settings = get_option( 'bday_addon_news_carousel', array() );
	$columns  = bday_news_carousel_columns();
	$count    = max( 1, count( $columns ) ?: 4 );
	?>
	<table class="form-table" role="presentation"><tbody>
		<tr><th scope="row">Number of columns</th><td><input type="number" name="bday_addon_news_carousel[column_count]" value="<?php echo esc_attr( (string) $count ); ?>" min="1" max="6" /><p class="description">How many columns to configure below (1–6). Reducing this number doesn't delete a column's saved settings, it just stops showing it — raise the number again to bring it back.</p></td></tr>
		<tr><th scope="row">Auto-scroll</th><td><label><input type="checkbox" name="bday_addon_news_carousel[auto_scroll]" value="1" <?php checked( ! empty( $settings['auto_scroll'] ) ); ?> /> Enabled</label><p class="description">Automatically advances the carousel on a timer, pausing whenever a reader's mouse is over it. Off by default so a reader controls the pace themselves via the arrow buttons or drag.</p></td></tr>
		<tr><th scope="row">Scroll speed (ms)</th><td><input type="number" name="bday_addon_news_carousel[scroll_speed]" value="<?php echo esc_attr( (string) ( $settings['scroll_speed'] ?? 5000 ) ); ?>" min="1000" /><p class="description">Only matters if Auto-scroll is enabled above — milliseconds between each automatic advance (5000 = 5 seconds).</p></td></tr>
	</tbody></table>
	<?php for ( $i = 0; $i < $count; $i++ ) :
		$col = $columns[ $i ] ?? array();
		?>
		<h4>Column <?php echo (int) ( $i + 1 ); ?></h4>
		<table class="form-table" role="presentation"><tbody>
			<tr><th scope="row">Title</th><td><input type="text" name="bday_addon_news_carousel[columns][<?php echo $i; ?>][title]" value="<?php echo esc_attr( $col['title'] ?? '' ); ?>" class="regular-text" /><p class="description">The heading shown at the top of this column's card — this is display text only, it doesn't have to match the category/tag name below.</p></td></tr>
			<tr><th scope="row">Source type</th><td>
				<select name="bday_addon_news_carousel[columns][<?php echo $i; ?>][type]">
					<option value="category" <?php selected( $col['type'] ?? 'category', 'category' ); ?>>Category</option>
					<option value="tag" <?php selected( $col['type'] ?? 'category', 'tag' ); ?>>Tag</option>
				</select>
			</td></tr>
			<tr><th scope="row">Slug</th><td><input type="text" name="bday_addon_news_carousel[columns][<?php echo $i; ?>][slug]" value="<?php echo esc_attr( $col['slug'] ?? '' ); ?>" class="regular-text" /><p class="description">The category or tag's own URL slug (find it under Posts → Categories/Tags), not its display name — e.g. "life-arts" for a "Life & Arts" category. A wrong or misspelled slug quietly shows an empty column rather than an error.</p></td></tr>
		</tbody></table>
	<?php endfor;
}

/** @param mixed $input @return array<string, mixed> */
function bday_news_carousel_sanitize( $input ): array {
	$input  = is_array( $input ) ? $input : array();
	$count  = max( 1, min( 6, (int) ( $input['column_count'] ?? 4 ) ) );
	$output = array(
		'auto_scroll'  => ! empty( $input['auto_scroll'] ),
		'scroll_speed' => max( 1000, (int) ( $input['scroll_speed'] ?? 5000 ) ),
		'columns'      => array(),
	);
	for ( $i = 0; $i < $count; $i++ ) {
		$col = is_array( $input['columns'][ $i ] ?? null ) ? $input['columns'][ $i ] : array();
		$output['columns'][] = array(
			'title' => sanitize_text_field( $col['title'] ?? '' ),
			'type'  => in_array( $col['type'] ?? '', array( 'category', 'tag' ), true ) ? $col['type'] : 'category',
			'slug'  => sanitize_title( $col['slug'] ?? '' ),
		);
	}
	return $output;
}
