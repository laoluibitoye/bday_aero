<?php
/**
 * Addon Name: Typography
 * Addon Slug: typography
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
		$out .= 'h1,h2,h3,h4,h5,h6,.post-title,.post-title a{font-family:"' . esc_html( $settings['header_font'] ) . '",sans-serif !important;}';
	}
	if ( ! empty( $settings['body_font'] ) ) {
		$out .= 'body,p,.article-text,.post-excerpt,article p{font-family:"' . esc_html( $settings['body_font'] ) . '",sans-serif !important;}';
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
			'fields'    => array(
				array( 'key' => 'header_font', 'type' => 'text', 'label' => 'Header font family', 'description' => 'Exact Google Fonts name, e.g. Montserrat.' ),
				array( 'key' => 'header_weights', 'type' => 'text', 'label' => 'Header weights', 'description' => 'Comma-separated, e.g. 400,700.' ),
				array( 'key' => 'body_font', 'type' => 'text', 'label' => 'Body font family' ),
				array( 'key' => 'body_weights', 'type' => 'text', 'label' => 'Body weights' ),
				array( 'key' => 'post_title_size', 'type' => 'text', 'label' => 'Post title size', 'description' => 'e.g. 2rem' ),
				array( 'key' => 'header_line_height', 'type' => 'text', 'label' => 'Header line height' ),
				array( 'key' => 'link_color', 'type' => 'text', 'label' => 'Link color', 'description' => 'e.g. #ba141a' ),
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
