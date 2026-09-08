<?php
/**
 * Addon Name: Breaking Ticker
 * Addon Slug: breaking-ticker
 * Description: Scrolling headline ticker below the header — auto-fills from the "bdlead" tag, plus editor-pinned articles.
 * Cache Namespace: breaking_ticker
 * Settings Tab: Breaking Ticker
 * Default: on
 *
 * A scrolling headlines strip below the nav (reader-requested, "as seen
 * on businessday.ng" — their live markup didn't actually expose a ticker
 * to a static fetch, likely script-injected or page-specific, so this
 * builds the standard scrolling-marquee pattern in this theme's own
 * visual language rather than guessing at their exact source). Hooks the
 * existing `bday_header_ticker_zone` action (header.php) — a second,
 * independent listener alongside the optional TradingView FX ticker, same
 * "dead air unless configured" convention every zone hook here follows.
 *
 * Editor-requested rework (2026-09-09): the strip used to just be the N
 * most recent posts site-wide, with nothing to curate. Now it's the same
 * "bdlead" tag the homepage Hero already reads (so it updates itself the
 * moment a fresh story is tagged, no separate step), plus any number of
 * manually pinned articles — which don't need the "bdlead" tag at all —
 * shown first. Pinning something doesn't require re-tagging it, and
 * nothing here affects what the Hero itself shows (a separate query, see
 * core/homepage/data.php's 'lead'/'top_stories' keys).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves each raw string saved in the "Pinned articles" list — a full
 * permalink or a bare numeric post ID — into a real, published post.
 * Resolution happens here, at render time, not at save time: a pinned
 * post that's later trashed/unpublished just silently drops out of the
 * ticker on its own, the same self-healing posture
 * core/homepage/hero-lead-lock.php's lock uses for the same reason. Kept
 * local to this add-on rather than shared with that file, since add-ons
 * in this theme don't depend on each other's code being loaded.
 *
 * @param string[] $raw
 * @return WP_Post[]
 */
function bday_breaking_ticker_resolve_pinned( array $raw ): array {
	$posts = array();
	foreach ( $raw as $entry ) {
		$entry = trim( (string) $entry );
		if ( '' === $entry ) {
			continue;
		}
		$post_id = ctype_digit( $entry ) ? (int) $entry : url_to_postid( $entry );
		if ( $post_id <= 0 ) {
			continue;
		}
		$post = get_post( $post_id );
		if ( $post && 'publish' === $post->post_status ) {
			$posts[] = $post;
		}
	}
	return $posts;
}

add_action(
	'bday_header_ticker_zone',
	static function (): void {
		$settings = get_option( 'bday_addon_breaking_ticker', array() );
		if ( ! isset( $settings['enabled'] ) || ! $settings['enabled'] ) {
			return;
		}
		$count = ! empty( $settings['count'] ) ? (int) $settings['count'] : 8;
		$label = ! empty( $settings['label'] ) ? (string) $settings['label'] : 'Top News';

		// Pinned articles always show in full, in the order they were
		// pinned — an editor who deliberately picked specific stories
		// shouldn't have one silently dropped because of a count meant to
		// bound the *auto-filled* pool. Auto-fill only tops up whatever
		// room is left under $count, excluding anything already pinned so
		// nothing shows twice.
		$pinned_posts = bday_breaking_ticker_resolve_pinned( (array) ( $settings['pinned'] ?? array() ) );
		$remaining    = max( 0, $count - count( $pinned_posts ) );

		$auto_posts = $remaining > 0
			? bday_get_posts(
				array(
					'tag'             => 'bdlead',
					'numberposts'     => $remaining,
					'post__not_in'    => wp_list_pluck( $pinned_posts, 'ID' ),
					'cache_namespace' => 'breaking_ticker',
					'cache_ttl'       => 120,
				)
			)
			: array();

		$posts = array_merge( $pinned_posts, $auto_posts );

		// Safety net for a fresh install (or one that hasn't started using
		// "bdlead" or pinning yet) — falls back to the strip's original
		// unconditional behavior (most recent posts site-wide) rather than
		// just going empty. Never runs once either mechanism has anything
		// in it.
		if ( empty( $posts ) ) {
			$posts = bday_get_posts( array( 'numberposts' => $count, 'cache_namespace' => 'breaking_ticker', 'cache_ttl' => 120 ) );
		}
		if ( empty( $posts ) ) {
			return;
		}
		?>
		<div class="bday-ticker" data-bd-ticker aria-label="<?php echo esc_attr( $label ); ?> headlines">
			<span class="bday-ticker__tag"><?php echo esc_html( $label ); ?></span>
			<div class="bday-ticker__track-wrap">
				<ul class="bday-ticker__track">
					<?php foreach ( $posts as $post ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></li>
					<?php endforeach; ?>
				</ul>
				<?php
				/**
				 * Duplicated so the CSS animation can scroll from 0% to
				 * -50% and loop seamlessly — a single copy would show a
				 * visible jump/gap at the reset point.
				 */
				?>
				<ul class="bday-ticker__track" aria-hidden="true">
					<?php foreach ( $posts as $post ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $post ) ); ?>" tabindex="-1"><?php echo esc_html( get_the_title( $post ) ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}
);

/**
 * Custom render (not the generic field-list loop) — "Pinned articles" is
 * a repeatable row list, the same "+ Add / ↑ / ↓ / ✕" vanilla-JS pattern
 * addons/market-pulse/includes/admin.php already established, so this
 * settings tab doesn't introduce a second UI convention for the same
 * kind of editable list.
 */
function bday_render_breaking_ticker_tab( array $values ): void {
	$label  = ! empty( $values['label'] ) ? (string) $values['label'] : 'Top News';
	$count  = ! empty( $values['count'] ) ? (int) $values['count'] : 8;
	$pinned = is_array( $values['pinned'] ?? null ) ? array_values( $values['pinned'] ) : array();
	?>
	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row">Enable</th>
				<td>
					<label><input type="checkbox" name="bday_addon_breaking_ticker[enabled]" value="1" <?php checked( ! empty( $values['enabled'] ) ); ?>></label>
					<p class="description">Turns the whole strip on or off. Off removes it from the page entirely — no empty gap left behind.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Label</th>
				<td>
					<select name="bday_addon_breaking_ticker[label]">
						<option value="Top News" <?php selected( $label, 'Top News' ); ?>>Top News</option>
						<option value="Breaking News" <?php selected( $label, 'Breaking News' ); ?>>Breaking News</option>
					</select>
					<p class="description">The small tag printed at the start of the strip. Switch to "Breaking News" for as long as a major story is live, then back to "Top News" once it's over — nothing else about the strip changes.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Number of headlines</th>
				<td>
					<input type="number" name="bday_addon_breaking_ticker[count]" value="<?php echo esc_attr( (string) $count ); ?>" min="1" step="1" class="small-text">
					<p class="description">Total headlines in the loop, pinned articles included. Pinned articles (below) always show in full; the rest is filled automatically from the newest "bdlead"-tagged posts, most recent first.</p>
				</td>
			</tr>
		</tbody>
	</table>

	<h3>Pinned articles</h3>
	<p class="description">Shown first, before anything auto-filled — paste an article's URL or its numeric post ID. A pinned article doesn't need the "bdlead" tag; it can be anything published. Remove a row to unpin it.</p>
	<table class="widefat striped bday-breaking-ticker-table" style="max-width:720px;">
		<thead><tr><th>Article URL or ID</th><th style="width:120px;"></th></tr></thead>
		<tbody id="bday-breaking-ticker-rows">
			<?php foreach ( $pinned as $i => $entry ) : ?>
				<?php echo bday_breaking_ticker_row_html( $i, (string) $entry ); ?>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p><button type="button" class="button" id="bday-breaking-ticker-add-row">+ Pin an article</button></p>
	<template id="bday-breaking-ticker-row-template">
		<?php echo bday_breaking_ticker_row_html( '__INDEX__', '' ); ?>
	</template>

	<script>
	(function () {
		var tbody = document.getElementById( 'bday-breaking-ticker-rows' );
		var addBtn = document.getElementById( 'bday-breaking-ticker-add-row' );
		var template = document.getElementById( 'bday-breaking-ticker-row-template' );
		var nextIndex = <?php echo (int) count( $pinned ); ?>;

		function bind( row ) {
			var removeBtn = row.querySelector( '[data-bday-remove-row]' );
			if ( removeBtn ) {
				removeBtn.addEventListener( 'click', function () {
					row.remove();
				} );
			}
			var upBtn = row.querySelector( '[data-bday-move-up]' );
			if ( upBtn ) {
				upBtn.addEventListener( 'click', function () {
					var prev = row.previousElementSibling;
					if ( prev ) { tbody.insertBefore( row, prev ); }
				} );
			}
			var downBtn = row.querySelector( '[data-bday-move-down]' );
			if ( downBtn ) {
				downBtn.addEventListener( 'click', function () {
					var next = row.nextElementSibling;
					if ( next ) { tbody.insertBefore( next, row ); }
				} );
			}
		}

		Array.prototype.forEach.call( tbody.querySelectorAll( 'tr' ), bind );

		addBtn.addEventListener( 'click', function () {
			var html = template.innerHTML.replace( /__INDEX__/g, String( nextIndex ) );
			nextIndex++;
			var wrapper = document.createElement( 'tbody' );
			wrapper.innerHTML = html;
			var row = wrapper.firstElementChild;
			tbody.appendChild( row );
			bind( row );
		} );
	})();
	</script>
	<?php
}

function bday_breaking_ticker_row_html( $index, string $entry ): string {
	ob_start();
	?>
	<tr>
		<td>
			<input type="text" name="bday_addon_breaking_ticker[pinned][<?php echo esc_attr( (string) $index ); ?>]" value="<?php echo esc_attr( $entry ); ?>" class="widefat" placeholder="https://businessday.ng/... or a post ID">
		</td>
		<td>
			<button type="button" class="button" data-bday-move-up title="Move up">↑</button>
			<button type="button" class="button" data-bday-move-down title="Move down">↓</button>
			<button type="button" class="button" data-bday-remove-row title="Remove">✕</button>
		</td>
	</tr>
	<?php
	return (string) ob_get_clean();
}

/** @return array{enabled: bool, count: int, label: string, pinned: string[]} */
function bday_sanitize_breaking_ticker( $input ): array {
	$input = is_array( $input ) ? $input : array();

	$pinned_raw = is_array( $input['pinned'] ?? null ) ? $input['pinned'] : array();
	$pinned     = array();
	foreach ( $pinned_raw as $entry ) {
		$entry = sanitize_text_field( wp_unslash( (string) $entry ) );
		// A row added via "+ Pin an article" but never filled in and never
		// removed shouldn't persist as a dead slot — skip it entirely.
		if ( '' !== $entry ) {
			$pinned[] = $entry;
		}
	}

	return array(
		'enabled' => ! empty( $input['enabled'] ),
		'count'   => max( 1, (int) ( $input['count'] ?? 8 ) ),
		'label'   => 'Breaking News' === ( $input['label'] ?? '' ) ? 'Breaking News' : 'Top News',
		'pinned'  => $pinned,
	);
}

add_filter(
	'bday_settings_schema',
	static function ( array $schema ): array {
		$schema['breaking-ticker'] = array(
			'tab_label' => 'Breaking Ticker',
				'group'     => 'editorial',
			'option'    => 'bday_addon_breaking_ticker',
			'render'    => 'bday_render_breaking_ticker_tab',
			'intro'     => 'The scrolling headline strip below the main navigation. Auto-fills from the "bdlead" tag — the same tag that drives the homepage Hero — so it updates itself the moment a fresh story is tagged, plus any articles pinned below regardless of tag.',
			'about'     => '<p>Pinned articles always show first, in the order pinned. The rest of the strip fills automatically from the newest "bdlead"-tagged posts, most recent first, up to the "Number of headlines" total. Scrolls right to left in a continuous loop, pauses on hover/focus, and disables the animation entirely for readers with "reduce motion" set in their OS.</p>',
		);
		return $schema;
	}
);

add_action(
	'admin_init',
	static function (): void {
		register_setting(
			'bday_addon_breaking_ticker',
			'bday_addon_breaking_ticker',
			array( 'sanitize_callback' => 'bday_sanitize_breaking_ticker' )
		);
	}
);
