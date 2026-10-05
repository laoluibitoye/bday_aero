<?php
/**
 * Matches published bday_popup posts against the current page and emits
 * their markup + a per-popup trigger/device config for bdayInitPopups()
 * (script.js) to act on client-side.
 *
 * Display rules are evaluated here, server-side — safe under this site's
 * page caching (Cloudflare/Redis) because they only depend on *which
 * page* this is, which is the same for every visitor of a cached page.
 * Device target is deliberately NOT evaluated here (see metabox.php's
 * own note) — baking "desktop vs mobile" into cached HTML would serve
 * the wrong popup to whichever device type didn't generate that cache
 * entry, so that check happens client-side instead, per visitor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Whether $post's display rule matches the page currently being rendered. */
function bday_popup_matches_current_page( WP_Post $post ): bool {
	$mode = (string) get_post_meta( $post->ID, '_bday_popup_rule_mode', true ) ?: 'site_wide';

	switch ( $mode ) {
		case 'home_only':
			return is_front_page();

		case 'post_types':
			$types = (array) get_post_meta( $post->ID, '_bday_popup_rule_post_types', true );
			return ! empty( $types ) && is_singular( $types );

		case 'terms':
			$pairs = (array) get_post_meta( $post->ID, '_bday_popup_rule_terms', true );
			foreach ( $pairs as $pair ) {
				$parts = explode( ':', $pair, 2 );
				if ( 2 !== count( $parts ) ) {
					continue;
				}
				list( $taxonomy, $term_id ) = $parts;
				if ( is_singular() && has_term( (int) $term_id, $taxonomy ) ) {
					return true;
				}
				if ( is_tax( $taxonomy, (int) $term_id ) || ( 'category' === $taxonomy && is_category( (int) $term_id ) ) || ( 'post_tag' === $taxonomy && is_tag( (int) $term_id ) ) ) {
					return true;
				}
			}
			return false;

		case 'urls':
			$configured = (string) get_post_meta( $post->ID, '_bday_popup_rule_urls', true );
			$current    = untrailingslashit( (string) wp_parse_url( add_query_arg( array() ), PHP_URL_PATH ) );
			foreach ( preg_split( '/\r\n|\r|\n/', $configured ) as $line ) {
				$line = untrailingslashit( trim( $line ) );
				if ( '' !== $line && $line === $current ) {
					return true;
				}
			}
			return false;

		case 'site_wide':
		default:
			return true;
	}
}

/** @return WP_Post[] published popups whose display rule matches the current page, in menu/date order. */
function bday_popups_get_matching(): array {
	$popups = bday_get_posts(
		array(
			'post_type'       => 'bday_popup',
			'numberposts'     => 20,
			'cache_namespace' => 'popups',
		)
	);

	return array_values( array_filter( $popups, 'bday_popup_matches_current_page' ) );
}

add_action(
	'bday_popup_zone',
	static function (): void {
		if ( is_admin() ) {
			return;
		}

		$popups = bday_popups_get_matching();
		if ( empty( $popups ) ) {
			return;
		}

		$configs = array();
		foreach ( $popups as $popup ) {
			$position = (string) get_post_meta( $popup->ID, '_bday_popup_position', true ) ?: 'center';
			$configs[] = array(
				'id'       => $popup->ID,
				'position' => $position,
				'trigger'  => (string) get_post_meta( $popup->ID, '_bday_popup_trigger', true ) ?: 'immediate',
				'delay'    => max( 0, (int) get_post_meta( $popup->ID, '_bday_popup_trigger_delay', true ) ),
				'scroll'   => max( 0, min( 100, (int) get_post_meta( $popup->ID, '_bday_popup_trigger_scroll', true ) ) ),
				'device'   => (string) get_post_meta( $popup->ID, '_bday_popup_device', true ) ?: 'both',
			);
			?>
			<div
				class="bday-popup bday-popup--<?php echo esc_attr( $position ); ?>"
				id="bday-popup-<?php echo (int) $popup->ID; ?>"
				data-bday-popup
				data-bday-popup-id="<?php echo (int) $popup->ID; ?>"
				role="dialog"
				aria-modal="true"
				hidden
			>
				<div class="bday-popup__card">
					<button type="button" class="bday-popup__close" data-bday-popup-close aria-label="Close">&times;</button>
					<div class="bday-popup__body">
						<?php if ( has_post_thumbnail( $popup ) ) : ?>
							<div class="bday-popup__image"><?php echo get_the_post_thumbnail( $popup, 'large' ); ?></div>
						<?php endif; ?>
						<?php echo apply_filters( 'the_content', $popup->post_content ); // phpcs:ignore -- same rendering path every post's own content goes through ?>
					</div>
				</div>
			</div>
			<?php
		}
		?>
		<script type="application/json" id="bd-popups-data"><?php echo wp_json_encode( $configs ); ?></script>
		<?php
	}
);
