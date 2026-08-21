<?php
/**
 * Addon Name: Typography
 * Addon Slug: typography
 * Description: Site-wide font pairing and type-scale controls.
 * Cache Namespace: typography
 * Settings Tab: Typography
 * Default: on
 *
 * Admin-configurable Google Fonts + type-scale overrides. Previously
 * rebuilt as an inline <style> block from scratch on every single
 * request; the generated CSS string is now cached (object cache, keyed by
 * a hash of the settings) and only rebuilt when the settings actually
 * change, instead of on every pageview.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_head', 'bday_typography_print', 5 );

function bday_typography_print(): void {
	$settings = get_option( 'bday_addon_typography', array() );
	if ( empty( array_filter( $settings ) ) ) {
		return;
	}

	$cache_key = md5( wp_json_encode( $settings ) );
	$markup    = Bday_Query_Cache::remember(
		'typography',
		$cache_key,
		static function () use ( $settings ) {
			return bday_typography_build_markup( $settings );
		},
		DAY_IN_SECONDS
	);

	echo $markup; // phpcs:ignore -- pre-built, escaped at construction time
}

function bday_typography_build_markup( array $settings ): string {
	$fonts = array();
	if ( ! empty( $settings['header_font'] ) ) {
		$fonts[] = $settings['header_font'] . ( ! empty( $settings['header_weights'] ) ? ':' . $settings['header_weights'] : '' );
	}
	if ( ! empty( $settings['body_font'] ) ) {
		$fonts[] = $settings['body_font'] . ( ! empty( $settings['body_weights'] ) ? ':' . $settings['body_weights'] : '' );
	}

	$out = '';
	if ( ! empty( $fonts ) ) {
		$url  = 'https://fonts.googleapis.com/css?family=' . implode( '|', array_map( 'rawurlencode', $fonts ) ) . '&display=swap';
		$out .= "<link href='" . esc_url( $url ) . "' rel='stylesheet'>\n";
	}

	$out .= '<style>';
	if ( ! empty( $settings['header_font'] ) ) {
		// :not(.bday-rd *) — the "Redesign 2026" homepage (homepage-
		// variants/redesign.php's .bday-rd wrapper) has its own deliberate,
		// self-contained font system (--rd-font-display/--rd-font-body,
		// _homepage-redesign.scss) that every .bday-rd-* component reads
		// from. This blanket h1-h6 !important override predates that
		// system and was clobbering it wherever a redesign title happens
		// to sit on an actual heading tag rather than a span/div (found
		// live: the hero's lead-story <h1> rendered in this admin-picked
		// font while every sibling title in the same hero, all <span>s,
		// correctly kept the redesign's own font) — excluded so this
		// setting stays scoped to the classic templates it was built for.
		$out .= 'h1:not(.bday-rd *),h2:not(.bday-rd *),h3:not(.bday-rd *),h4:not(.bday-rd *),h5:not(.bday-rd *),h6:not(.bday-rd *),.post-title,.post-title a{font-family:"' . esc_html( $settings['header_font'] ) . '",sans-serif !important;}';
	}
	if ( ! empty( $settings['body_font'] ) ) {
		// Same .bday-rd exclusion as header_font above, for `p`/`article p`
		// specifically — those select paragraph tags directly, including
		// ones inside the redesign homepage with no font-family rule of
		// their own to out-cascade this. (The plain `body` selector below
		// doesn't need the same guard: it only ever matches the single
		// <body> element itself, and .bday-rd's own font-family rule
		// already out-prioritizes whatever <body> inherits down to it —
		// direct rules always beat inherited values regardless of
		// !important, which only arbitrates between rules matching the
		// *same* element.)
		$out .= 'body,p:not(.bday-rd *),.article-text,.post-excerpt,article p:not(.bday-rd *){font-family:"' . esc_html( $settings['body_font'] ) . '",sans-serif !important;}';
	}
	if ( ! empty( $settings['post_title_size'] ) || ! empty( $settings['header_line_height'] ) ) {
		$out .= '.post-title,.post-title a{';
		if ( ! empty( $settings['post_title_size'] ) ) {
			$out .= 'font-size:' . esc_html( $settings['post_title_size'] ) . ' !important;';
		}
		if ( ! empty( $settings['header_line_height'] ) ) {
			$out .= 'line-height:' . esc_html( $settings['header_line_height'] ) . ' !important;';
		}
		$out .= '}';
	}
	if ( ! empty( $settings['link_color'] ) ) {
		$out .= 'a{color:' . esc_html( $settings['link_color'] ) . ' !important;}';
	}
	$out .= '</style>';

	return $out;
}

add_filter(
	'bday_settings_schema',
	static function ( array $schema ): array {
		$schema['typography'] = array(
			'tab_label' => 'Typography',
			'option'    => 'bday_addon_typography',
			'intro'     => 'Overrides the theme\'s built-in type system with a different font pairing/scale, without editing any CSS. Leave a field blank to keep the theme\'s own default for it — these fields don\'t all need to be filled in together, and an empty option here changes nothing.',
			'about'     => '<p>Fonts are loaded from Google Fonts by the exact family name typed below — the same name you\'d search for at fonts.google.com. A misspelled name silently falls back to the browser\'s default font rather than erroring, so double-check against the Google Fonts listing if a change doesn\'t seem to take.</p>',
			'fields'    => array(
				array( 'key' => 'header_font', 'type' => 'text', 'label' => 'Header font family', 'description' => 'Exact Google Fonts name, e.g. Montserrat. Used for headlines, nav, and section headings.' ),
				array( 'key' => 'header_weights', 'type' => 'text', 'label' => 'Header weights', 'description' => 'Comma-separated font weights to load for the header font, e.g. 400,700 — only load the weights actually used elsewhere in the design to keep page load light.' ),
				array( 'key' => 'body_font', 'type' => 'text', 'label' => 'Body font family', 'description' => 'Exact Google Fonts name for article body text and general paragraph copy.' ),
				array( 'key' => 'body_weights', 'type' => 'text', 'label' => 'Body weights', 'description' => 'Comma-separated font weights to load for the body font.' ),
				array( 'key' => 'post_title_size', 'type' => 'text', 'label' => 'Post title size', 'description' => 'A CSS size value, e.g. 2rem or 32px — controls the article headline size on the single-post page specifically.' ),
				array( 'key' => 'header_line_height', 'type' => 'text', 'label' => 'Header line height', 'description' => 'A unitless CSS line-height value, e.g. 1.2 — tighter for large display headlines, looser for smaller heading sizes.' ),
				array( 'key' => 'link_color', 'type' => 'text', 'label' => 'Link color', 'description' => 'A hex color, e.g. #ba141a — overrides the brand accent color used for in-content links specifically.' ),
			),
		);
		return $schema;
	}
);

// Invalidate the cached markup the moment the setting changes.
add_action(
	'update_option_bday_addon_typography',
	static function (): void {
		wp_cache_flush_group( 'bday_typography' );
	}
);
