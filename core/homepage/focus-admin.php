<?php
/**
 * "Focus" list — the manually curated headline list that replaces the
 * "Editor's Pick & Most Read" section's automatic, site-wide "most
 * commented" ranking (homepage-sections/editor-pick.php's $most_read).
 * Editor-requested (2026-09-22): the automatic version showed whatever
 * got the most comments site-wide regardless of section — a marathon
 * date or a startup ranking sitting next to actual news, with no
 * editorial control over it at all. Same "Article URL or ID" repeatable-
 * row pattern as Breaking Ticker's pinned articles
 * (addons/breaking-ticker/addon.php), including its self-healing
 * resolve-at-render-time posture: a pick that's later trashed or
 * unpublished just drops out of the list on its own, no dangling entry
 * to clean up.
 *
 * Falls back to the original automatic "most commented" ranking
 * (core/homepage/data.php's 'most_popular') the moment the list is empty
 * — a fresh/unconfigured install renders exactly what it did before this
 * existed, same safety-net convention Breaking Ticker's own pinned list
 * already established.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BDAY_FOCUS_LIST_OPTION = 'bday_focus_list';

/**
 * Resolves the saved list into real, published posts — same URL-or-
 * numeric-ID resolution as bday_breaking_ticker_resolve_pinned(), kept
 * local rather than shared since add-ons/core files in this theme don't
 * depend on each other's code being loaded.
 *
 * @return WP_Post[]
 */
function bday_focus_list_posts(): array {
	$raw = get_option( BDAY_FOCUS_LIST_OPTION, array() );
	$raw = is_array( $raw ) ? $raw : array();

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

function bday_render_focus_list_tab( array $values ): void {
	$raw = get_option( BDAY_FOCUS_LIST_OPTION, array() );
	$raw = is_array( $raw ) ? array_values( $raw ) : array();
	?>
	<p class="description">
		Shown as the "Focus" list on the homepage, in this order, in place of the automatic "most
		commented" ranking — paste an article's URL or its numeric post ID. Leave this empty to go
		back to the automatic ranking.
	</p>
	<table class="widefat striped bday-focus-list-table" style="max-width:720px;">
		<thead><tr><th>Article URL or ID</th><th style="width:120px;"></th></tr></thead>
		<tbody id="bday-focus-list-rows">
			<?php foreach ( $raw as $i => $entry ) : ?>
				<?php echo bday_focus_list_row_html( $i, (string) $entry ); ?>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p><button type="button" class="button" id="bday-focus-list-add-row">+ Add an article</button></p>
	<template id="bday-focus-list-row-template">
		<?php echo bday_focus_list_row_html( '__INDEX__', '' ); ?>
	</template>

	<script>
	(function () {
		var tbody = document.getElementById( 'bday-focus-list-rows' );
		var addBtn = document.getElementById( 'bday-focus-list-add-row' );
		var template = document.getElementById( 'bday-focus-list-row-template' );
		var nextIndex = <?php echo (int) count( $raw ); ?>;

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

/** @param int|string $index */
function bday_focus_list_row_html( $index, string $entry ): string {
	ob_start();
	?>
	<tr>
		<td>
			<input type="text" name="<?php echo esc_attr( BDAY_FOCUS_LIST_OPTION ); ?>[<?php echo esc_attr( (string) $index ); ?>]" value="<?php echo esc_attr( $entry ); ?>" class="widefat" placeholder="https://businessday.ng/... or a post ID">
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

/** @return string[] */
function bday_sanitize_focus_list( $input ): array {
	$rows = is_array( $input ) ? $input : array();
	$out  = array();

	foreach ( $rows as $entry ) {
		$entry = sanitize_text_field( wp_unslash( (string) $entry ) );
		// A row added via "+ Add an article" but never filled in and never
		// removed shouldn't persist as a dead slot — skip it entirely, same
		// posture as Breaking Ticker's own pinned-article sanitizer.
		if ( '' !== $entry ) {
			$out[] = $entry;
		}
	}

	return $out;
}

add_filter(
	'bday_settings_schema',
	static function ( array $schema ): array {
		$schema['focus_list'] = array(
			'tab_label' => 'Focus List',
				'group'     => 'editorial',
			'option'    => BDAY_FOCUS_LIST_OPTION,
			'render'    => 'bday_render_focus_list_tab',
			'intro'     => 'What shows in the "Focus" list on the homepage (beside Editor\'s Pick) — by default it\'s an automatic "most commented" ranking with no editorial control over which stories qualify. Add articles below to replace that with a manually curated, ordered list instead.',
		);
		return $schema;
	}
);

add_action(
	'admin_init',
	static function (): void {
		register_setting(
			BDAY_FOCUS_LIST_OPTION,
			BDAY_FOCUS_LIST_OPTION,
			array( 'sanitize_callback' => 'bday_sanitize_focus_list' )
		);
	}
);
