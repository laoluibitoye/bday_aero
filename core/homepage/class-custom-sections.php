<?php
/**
 * Admin-defined homepage sections — the "Add Section" counterpart to the
 * eight built-in single-source sections (section-sources.php). A
 * Technical Team member adds one from the Homepage Sections tab with a
 * Title, a source tag/category, and a Style, and it renders on the
 * "Redesign 2026" homepage immediately: no homepage-sections/*.php file,
 * no code deploy. Reuses the exact same three shared layouts every
 * built-in single-source section can already opt into
 * (bday_render_section_by_style() in core/helpers.php) — see
 * bday_render_custom_homepage_section(), which is what actually renders
 * one of these on the frontend.
 *
 * Sits beside Bday_Section_Content the same way that class sits beside
 * Bday_Section_Registry: registry state (order + on/off, including these
 * rows once Bday_Section_Registry::discover() merges them in) is separate
 * from what a section actually says and pulls from, kept in its own
 * option so reordering never risks clobbering a definition or vice versa.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Custom_Sections {

	private const OPTION = 'bday_custom_homepage_sections';

	/** @return array<string, array{title: string, source_taxonomy: string, source_term: string, style: string}> keyed by generated section id */
	public static function all(): array {
		$saved = get_option( self::OPTION, array() );
		return is_array( $saved ) ? $saved : array();
	}

	/** @return array{title: string, source_taxonomy: string, source_term: string, style: string}|null null when $id isn't a saved custom section. */
	public static function get( string $id ): ?array {
		$row = self::all()[ $id ] ?? null;
		return is_array( $row ) ? $row : null;
	}

	/** @return array<string, array{title: string, source_taxonomy: string, source_term: string, style: string}> */
	public static function sanitize( $input ): array {
		$rows = is_array( $input ) ? $input : array();
		$out  = array();

		foreach ( $rows as $id => $row ) {
			$id = sanitize_key( (string) $id );
			if ( '' === $id || ! is_array( $row ) ) {
				continue;
			}

			$title    = isset( $row['title'] ) ? sanitize_text_field( wp_unslash( $row['title'] ) ) : '';
			$taxonomy = isset( $row['source_taxonomy'] ) && 'post_tag' === $row['source_taxonomy'] ? 'post_tag' : 'category';
			$term     = isset( $row['source_term'] ) ? sanitize_title( wp_unslash( $row['source_term'] ) ) : '';
			$style    = isset( $row['style'] ) ? sanitize_key( wp_unslash( $row['style'] ) ) : '';
			if ( ! in_array( $style, Bday_Section_Content::styles(), true ) ) {
				$style = 'grid';
			}

			// A row with no title or no source isn't a usable section yet —
			// dropped rather than saved half-filled, same posture as every
			// other repeatable-row sanitizer in this theme (Bday_Section_
			// Content::sanitize(), addons/sections's bday_sanitize_sections()).
			// This is also what makes an abandoned "Add Section" click (added,
			// then never filled in, then the form saved anyway) a no-op.
			if ( '' === $title || '' === $term ) {
				continue;
			}

			$out[ $id ] = array(
				'title'           => $title,
				'source_taxonomy' => $taxonomy,
				'source_term'     => $term,
				'style'           => $style,
			);
		}

		return $out;
	}
}
