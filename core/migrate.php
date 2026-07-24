<?php
/**
 * One-time settings migration from the previous theme's option names to
 * the new add-on options, so a site upgrading in place keeps its
 * configured typography, carousel columns, leaderboard slides, live
 * settings, and newsletter credentials without re-entering anything.
 * Guarded by a flag option; runs once, then never again.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'after_setup_theme', 'bday_maybe_migrate_legacy_options', 5 );

function bday_maybe_migrate_legacy_options(): void {
	if ( get_option( 'bday_premium_migrated' ) ) {
		return;
	}

	// Typography: bday_typography_meta -> bday_addon_typography
	$old = get_option( 'bday_typography_meta' );
	if ( is_array( $old ) && ! get_option( 'bday_addon_typography' ) ) {
		update_option(
			'bday_addon_typography',
			array(
				'header_font'        => $old['header_font_family'] ?? '',
				'header_weights'     => $old['header_font_weights'] ?? '',
				'body_font'          => $old['body_font_family'] ?? '',
				'body_weights'       => $old['body_font_weights'] ?? '',
				'post_title_size'    => $old['post_title_size'] ?? '',
				'header_line_height' => $old['header_line_height'] ?? '',
				'link_color'         => $old['link_color'] ?? '',
			)
		);
	}

	// News carousel: bd_news_carousel_meta (col_title_N/col_type_N/col_slug_N) -> columns array
	$old = get_option( 'bd_news_carousel_meta' );
	if ( is_array( $old ) && ! get_option( 'bday_addon_news_carousel' ) ) {
		$columns = array();
		$count   = max( 1, (int) ( $old['column_count'] ?? 4 ) );
		for ( $i = 1; $i <= $count; $i++ ) {
			if ( empty( $old[ 'col_slug_' . $i ] ) ) {
				continue;
			}
			$columns[] = array(
				'title' => $old[ 'col_title_' . $i ] ?? '',
				'type'  => $old[ 'col_type_' . $i ] ?? 'category',
				'slug'  => $old[ 'col_slug_' . $i ],
			);
		}
		update_option(
			'bday_addon_news_carousel',
			array(
				'auto_scroll'  => ! empty( $old['auto_scroll'] ),
				'scroll_speed' => (int) ( $old['scroll_speed'] ?? 5000 ),
				'columns'      => $columns,
			)
		);
	}

	// Premium leaderboard: premium_leaderboard (imageN/urlN) -> slides array
	$old = get_option( 'premium_leaderboard' );
	if ( is_array( $old ) && ! get_option( 'bday_addon_premium_leaderboard' ) ) {
		$slides = array();
		$count  = max( 1, (int) ( $old['leaderboard_count'] ?? 4 ) );
		for ( $i = 1; $i <= $count; $i++ ) {
			if ( empty( $old[ 'image' . $i ] ) ) {
				continue;
			}
			$slides[] = array(
				'image' => $old[ 'image' . $i ],
				'url'   => $old[ 'url' . $i ] ?? '',
			);
		}
		update_option(
			'bday_addon_premium_leaderboard',
			array(
				'slider_speed' => (int) ( $old['slider_speed'] ?? 20000 ),
				'slides'       => $slides,
			)
		);
	}

	// BDay Live: bday_live_meta -> bday_addon_bday_live (+ enable the add-on if it was on)
	$old          = get_option( 'bday_live_meta' );
	$addon_states = get_option( 'bday_addon_states', array() );
	$addon_states = is_array( $addon_states ) ? $addon_states : array();
	if ( is_array( $old ) && ! get_option( 'bday_addon_bday_live' ) ) {
		$was_on = ( $old['bday_live_verify'] ?? 'off' ) === 'on';
		update_option(
			'bday_addon_bday_live',
			array(
				'enabled'    => $was_on,
				'youtube_id' => $old['bday_live_ID'] ?? '',
				'title'      => $old['bday_live_title'] ?? '',
			)
		);
		if ( $was_on ) {
			$addon_states['bday-live'] = true;
		}
	}

	// Live Match: live_match ('yes'/'no') -> bday_addon_live_match.enabled (+ add-on state)
	if ( 'yes' === get_option( 'live_match' ) && ! get_option( 'bday_addon_live_match' ) ) {
		update_option(
			'bday_addon_live_match',
			array(
				'enabled'     => true,
				'cache_ttl'   => 60,
				'max_matches' => 5,
			)
		);
		$addon_states['live-match'] = true;
	}

	// Newsletter: fc_remote_popup_settings -> bday_addon_newsletter
	$old = get_option( 'fc_remote_popup_settings' );
	if ( is_array( $old ) && ! get_option( 'bday_addon_newsletter' ) ) {
		update_option(
			'bday_addon_newsletter',
			array(
				'remote_url'        => $old['remote_url'] ?? '',
				'api_username'      => $old['api_username'] ?? '',
				'api_password'      => $old['api_password'] ?? '',
				'visible_lists'     => array_map( 'intval', (array) ( $old['visible_lists'] ?? array() ) ),
				'category_mappings' => array_map( 'intval', (array) ( $old['category_mappings'] ?? array() ) ),
			)
		);
	}

	// Homepage variant override: bd_homepage_variant_override -> bday_homepage_variant_settings.override
	$old = get_option( 'bd_homepage_variant_override' );
	if ( $old && ! get_option( 'bday_homepage_variant_settings' ) ) {
		update_option( 'bday_homepage_variant_settings', array( 'override' => (string) $old ) );
	}

	if ( ! empty( $addon_states ) ) {
		update_option( 'bday_addon_states', $addon_states );
	}

	update_option( 'bday_premium_migrated', 1 );
}
