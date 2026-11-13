<?php
/**
 * "Why isn't my ad showing?" — plain-English checks the admin screens show next to every unit and
 * placement, computed from the same data the front end uses.
 *
 * Every rule here exists because it was a real, silent failure: an ad that is configured but
 * invisible gives the editor nothing to act on. Notes are {level, text}; level is 'error' (this
 * will not show), 'warn' (it will show, but not where/how you probably expect) or 'info'.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param array<string,mixed> $u Unit.
 * @return array<int, array{level:string,text:string}>
 */
function bday_ads_unit_notes( array $u ): array {
	$notes   = array();
	$add     = static function ( string $level, string $text ) use ( &$notes ): void {
		$notes[] = array( 'level' => $level, 'text' => $text );
	};
	$catalog = bday_ads_placement_catalog();
	$usage   = '' !== (string) $u['id'] ? bday_ads_unit_usage( (string) $u['id'] ) : array();
	$is_oop  = 'gam' === $u['partner'] && 'standard' !== $u['format'];

	if ( '' !== (string) $u['id'] && ! $usage ) {
		$add( 'warn', 'Not assigned to any placement yet — it will not show anywhere until you tick it under Ad Placements.' );
	}
	if ( 'active' !== $u['status'] ) {
		$add( 'info', 'Paused — it is never shown.' );
	}

	switch ( $u['partner'] ) {
		case 'banner':
			$usable = 0;
			foreach ( (array) $u['creatives'] as $c ) {
				if ( ! is_array( $c ) ) {
					continue;
				}
				$id = (int) ( $c['image'] ?? 0 );
				if ( ( $id && wp_get_attachment_url( $id ) ) || ! empty( $c['image_url'] ) ) {
					++$usable;
				}
			}
			if ( 0 === $usable ) {
				$add( 'error', 'No usable image — none was chosen, or it was deleted from the media library. This banner will not show.' );
			}
			if ( '' !== (string) $u['end'] && strtotime( (string) $u['end'] ) && strtotime( (string) $u['end'] ) < time() ) {
				$add( 'warn', 'Its schedule has ended, so it is hidden.' );
			}
			if ( '' !== (string) $u['start'] && strtotime( (string) $u['start'] ) && strtotime( (string) $u['start'] ) > time() ) {
				$add( 'info', 'Its schedule has not started yet, so it is hidden until ' . esc_html( str_replace( 'T', ' ', (string) $u['start'] ) ) . '.' );
			}
			break;

		case 'gam':
			if ( '' === trim( (string) $u['path'] ) ) {
				$add( 'error', 'No valid GAM ad unit path. It must start with "/" and use only letters, numbers and _ - . , / (for example /21781351181/bd_sidebar_1). Nothing will load.' );
			}
			if ( ! $is_oop && '' === trim( (string) $u['sizes_desktop'] ) && '' === trim( (string) $u['sizes_mobile'] ) ) {
				$add( 'error', 'No sizes entered, so no ad can be requested. Add at least one size such as 300x250.' );
			} elseif ( ! $is_oop && 'all' === $u['device'] && '' === trim( (string) $u['sizes_mobile'] ) ) {
				$add( 'info', 'No mobile sizes — on phones only the desktop sizes that fit the screen are requested.' );
			}
			break;

		case 'adsense':
			if ( '' === (string) $u['adsense_slot'] ) {
				$add( 'error', 'No AdSense slot ID. Nothing will load.' );
			}
			if ( '' === (string) Bday_Ads_Store::settings()['adsense_client'] ) {
				$add( 'error', 'No AdSense publisher ID is set. Add it under Ad Manager → General, otherwise AdSense units stay empty and leave a blank gap.' );
			}
			break;

		case 'html':
			if ( '' === trim( (string) $u['body_code'] ) ) {
				$add( 'warn', 'The body code is empty, so there is nothing to show.' );
			}
			break;
	}

	foreach ( $usage as $pid => $def ) {
		if ( false === ( $def['phone'] ?? true ) && 'mobile' === $u['device'] ) {
			$add( 'error', sprintf( '"%s" is hidden on phones, so a phone-only unit placed there never shows.', $def['label'] ) );
		}
		if ( 'home_after_lead' === $pid && 'desktop' === $u['device'] ) {
			$add( 'error', 'The "right under the lead story" spot only exists on phones and tablets, so a desktop-only unit placed there never shows.' );
		}
		if ( ! bday_ads_placement_on_live_layout( $def ) ) {
			$add( 'warn', sprintf( '"%s" does not exist on the live homepage layout (%s), so it will not show there.', $def['label'], bday_ads_active_layout() ) );
		}
	}

	return $notes;
}

/**
 * @return array<int, array{level:string,text:string}>
 */
function bday_ads_placement_notes( string $id ): array {
	$notes   = array();
	$add     = static function ( string $level, string $text ) use ( &$notes ): void {
		$notes[] = array( 'level' => $level, 'text' => $text );
	};
	$catalog = bday_ads_placement_catalog();
	if ( ! isset( $catalog[ $id ] ) ) {
		return array();
	}
	$def  = $catalog[ $id ];
	$conf = Bday_Ads_Store::placement( $id );

	if ( ! bday_ads_placement_on_live_layout( $def ) ) {
		$live = array();
		foreach ( $catalog as $pid => $d ) {
			if ( 'section' !== $d['kind'] && ( ! empty( $d['homepage'] ) || ( ! empty( $d['layouts'] ) && in_array( bday_ads_active_layout(), (array) $d['layouts'], true ) ) ) ) {
				$live[] = $d['label'];
			}
		}
		$add(
			'error',
			sprintf(
				'This spot does not exist on your live homepage layout (%s) — it only exists on: %s. Anything assigned here will never show. On the live layout use: %s, or the "between sections" slots.',
				bday_ads_active_layout() ?: 'unknown',
				implode( ', ', (array) $def['layouts'] ),
				$live ? implode( '; ', $live ) : 'none'
			)
		);
	}

	if ( 'section' === $def['kind'] ) {
		$sections = bday_ads_homepage_sections();
		if ( '' === (string) ( $conf['after_section'] ?? '' ) ) {
			$add( 'warn', 'No section chosen yet — pick the section this ad should follow.' );
		} elseif ( ! isset( $sections[ $conf['after_section'] ] ) ) {
			$add( 'warn', sprintf( 'The chosen section "%s" is switched off or no longer exists on the homepage, so this slot shows nothing.', $conf['after_section'] ) );
		}
	}

	if ( empty( $conf['enabled'] ) ) {
		$add( 'info', 'This placement is switched off.' );
	}
	if ( empty( $conf['units'] ) ) {
		$add( 'info', 'No units assigned — nothing shows here.' );
	}
	if ( ! empty( $conf['post_types'] ) && ! empty( $def['layouts'] ) ) {
		$add( 'info', 'Post-type restrictions do not apply on the homepage, only on articles.' );
	}

	$phone_only = 0;
	foreach ( (array) $conf['units'] as $uid ) {
		$u = Bday_Ads_Store::unit( (string) $uid );
		if ( ! $u ) {
			$add( 'warn', sprintf( 'Unit "%s" no longer exists and is skipped.', $uid ) );
			continue;
		}
		if ( 'mobile' === $u['device'] ) {
			++$phone_only;
		}
		foreach ( bday_ads_unit_notes( $u ) as $n ) {
			// Surface only what stops or changes this unit here; "not assigned" can't apply (it is assigned).
			if ( in_array( $n['level'], array( 'error', 'warn' ), true ) && false === strpos( $n['text'], 'Not assigned' ) && false === strpos( $n['text'], 'does not exist on the live homepage' ) ) {
				$add( $n['level'], sprintf( '%s: %s', $u['label'] ?: $u['id'], $n['text'] ) );
			}
		}
	}
	if ( false === ( $def['phone'] ?? true ) && $phone_only ) {
		$add( 'error', 'This spot is hidden on phones, but it has phone-only units — they will never show.' );
	}

	return $notes;
}

/** @param array<int, array{level:string,text:string}> $notes */
function bday_ads_render_notes( array $notes ): void {
	if ( ! $notes ) {
		return;
	}
	echo '<ul class="bday-ad-notes">';
	foreach ( $notes as $n ) {
		$icon = 'error' === $n['level'] ? '⛔' : ( 'warn' === $n['level'] ? '⚠' : 'ℹ' );
		printf( '<li class="bday-ad-note bday-ad-note--%s"><span aria-hidden="true">%s</span> %s</li>', esc_attr( $n['level'] ), esc_html( $icon ), esc_html( $n['text'] ) );
	}
	echo '</ul>';
}
