<?php
/**
 * Per-section content overrides — title text and, for the sections that
 * are driven by exactly one tag/category, the tag/category itself. Sits
 * beside Bday_Section_Registry (which only handles order + on/off) rather
 * than inside it: registry state is "which sections and in what order",
 * this is "what does each one say and where does its content come from",
 * and the two are saved to separate options so reordering sections never
 * risks clobbering a content edit or vice versa.
 *
 * Every homepage-sections/*.php file that prints a heading calls
 * bday_section_title( $slug ) instead of hardcoding the text, and the
 * handful of single-source "post content type" sections (Columnists,
 * Opinion, Premium, BD Investigates, The Interview, Partner Content,
 * YSoT, Latest Stories — see section-sources.php) are fetched through
 * bday_section_source_posts( $slug ) instead of a literal tag/category in
 * data.php/redesign-data.php. Both fall back to the shipped default the
 * moment nothing's been saved, so an unconfigured install renders
 * byte-for-byte what it did before this existed.
 *
 * Those same single-source sections can also have their *layout* swapped
 * — style() below — to one of the three reusable renderers in
 * core/helpers.php (bday_render_editorial_grid_section() / _investigative_
 * / _premium_style_, dispatched via bday_render_section_by_style()).
 * Unset (empty string) means "keep this section's own bespoke default
 * markup" — every one of those 8 section files still contains its
 * original hand-rolled rendering as that fallback path, only reached when
 * no style override is saved, so this too changes nothing until a
 * Technical Team member explicitly picks a style.
 *
 * Deliberately scoped to the "Redesign 2026" homepage variant's section
 * files, same boundary Bday_Section_Registry and its own admin tab
 * already draw — the classic Default/Weekend homepage variants are fixed
 * template files, not this reorderable/configurable layer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Section_Content {

	private const OPTION = 'bday_homepage_section_content';

	/** @return array<string, array{title: string, source_taxonomy: string, source_term: string}> */
	public static function all(): array {
		$saved = get_option( self::OPTION, array() );
		return is_array( $saved ) ? $saved : array();
	}

	/** @return array{title?: string, source_taxonomy?: string, source_term?: string} */
	public static function row( string $slug ): array {
		$all = self::all();
		return is_array( $all[ $slug ] ?? null ) ? $all[ $slug ] : array();
	}

	/** Every default this theme ships with a section heading — the fallback bday_section_title() returns until a Technical Team member overrides it. */
	public static function title_defaults(): array {
		return array(
			'browse-desks'        => 'Browse the Desks',
			'columnists'          => 'Columnists',
			'editions'            => 'E-editions',
			'editor-pick'         => "Editor's Pick",
			'events'              => 'Upcoming events',
			'headlines'           => 'Headlines',
			'in-pictures'         => 'In Pictures',
			'interview'           => 'The Interview',
			'investigates'        => 'BD Investigates',
			'latest-stories'      => 'Latest Stories',
			'newsletter'          => 'Subscribe to BusinessDay',
			'opinion'             => 'Opinion',
			'partner-content'     => 'Partnered & Sponsored Content',
			'premium'             => 'BusinessDay Pro — intelligence for decision makers',
			'todays-paper-teaser' => 'The full print edition, online',
			'toon'                => 'Toon of the Day',
			'weekender'           => 'Off the Clock',
			'your-news'           => 'Your News',
			'ysot'                => 'YSoT',
		);
	}

	/** Saved override if one's been set, otherwise this section's shipped default (or the slug itself, if it's not a known section — never fatal). */
	public static function title( string $slug ): string {
		$default = self::title_defaults()[ $slug ] ?? $slug;
		$title   = trim( (string) ( self::row( $slug )['title'] ?? '' ) );
		return '' !== $title ? $title : $default;
	}

	/** @return array{taxonomy: string, term: string}|null null when no override is saved (or the section isn't a single-source one) — caller falls back to its own default. */
	public static function source( string $slug ): ?array {
		$row  = self::row( $slug );
		$term = trim( (string) ( $row['source_term'] ?? '' ) );
		if ( '' === $term ) {
			return null;
		}
		return array(
			'taxonomy' => 'post_tag' === ( $row['source_taxonomy'] ?? '' ) ? 'post_tag' : 'category',
			'term'     => $term,
		);
	}

	/** @return string[] the layouts bday_render_section_by_style() knows how to dispatch to. */
	public static function styles(): array {
		return array( 'grid', 'investigative', 'premium' );
	}

	/** '' when no override is saved — caller falls back to that section's own bespoke default markup. */
	public static function style( string $slug ): string {
		$style = (string) ( self::row( $slug )['style'] ?? '' );
		return in_array( $style, self::styles(), true ) ? $style : '';
	}

	/** @return array<string, array{title: string, source_taxonomy: string, source_term: string, style: string}> */
	public static function sanitize( $input ): array {
		$rows = is_array( $input ) ? $input : array();
		$out  = array();

		foreach ( $rows as $slug => $row ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' === $slug || ! is_array( $row ) ) {
				continue;
			}

			$title    = isset( $row['title'] ) ? sanitize_text_field( wp_unslash( $row['title'] ) ) : '';
			$taxonomy = isset( $row['source_taxonomy'] ) && 'post_tag' === $row['source_taxonomy'] ? 'post_tag' : 'category';
			$term     = isset( $row['source_term'] ) ? sanitize_title( wp_unslash( $row['source_term'] ) ) : '';
			$style    = isset( $row['style'] ) ? sanitize_key( wp_unslash( $row['style'] ) ) : '';
			if ( ! in_array( $style, self::styles(), true ) ) {
				$style = '';
			}

			// An all-blank row (a section nobody has touched) isn't worth
			// saving — same "don't persist a no-op" posture as the Sections
			// addon's own sanitizer.
			if ( '' === $title && '' === $term && '' === $style ) {
				continue;
			}

			$out[ $slug ] = array(
				'title'           => $title,
				'source_taxonomy' => $taxonomy,
				'source_term'     => $term,
				'style'           => $style,
			);
		}

		return $out;
	}
}

function bday_section_title( string $slug ): string {
	return Bday_Section_Content::title( $slug );
}
