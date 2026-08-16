<?php
/**
 * CRUD + matching over the repeatable restriction rules. A rule row is
 * {id, post_type, taxonomy, term_ids: int[], number_allowed, period_days,
 * require_registration}. Ported behavior-for-behavior from the retired
 * connector-plugin's AeroPaywall_Restriction_Rules — rules are matched
 * top-to-bottom, first match wins, a post is governed by exactly one rule.
 *
 * Repeatable nested rows don't fit register_setting()'s flat POST model
 * cleanly, so this is saved via a dedicated JSON-body AJAX endpoint
 * instead — same nonce-checked pattern as class-admin-ui.php's
 * handle_test_connection(), called directly by the React Restrictions
 * tab (RestrictionsTab.tsx's handleSaveAll()).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Restriction_Rules {

	public function __construct() {
		add_action( 'wp_ajax_aero_paywall_save_restriction_rules', array( $this, 'handle_save_rules' ) );
	}

	public function handle_save_rules(): void {
		check_ajax_referer( 'aero_paywall_restriction_rules', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'bday-aero' ) ), 403 );
			return;
		}

		$raw     = isset( $_POST['rules'] ) ? wp_unslash( (string) $_POST['rules'] ) : '[]';
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid payload.', 'bday-aero' ) ), 400 );
			return;
		}

		$sanitized = self::sanitize_rules( $decoded );
		update_option( Bday_Aero_Settings::RESTRICTION_RULES, $sanitized );

		wp_send_json_success( array( 'rules' => $sanitized ) );
	}

	/** @return array<int, array<string, mixed>> */
	public static function get_rules(): array {
		return Bday_Aero_Settings::restriction_rules();
	}

	/** @return array<string, mixed>|null */
	public static function match_rule_for_post( int $post_id ): ?array {
		$rules = self::get_rules();
		if ( empty( $rules ) ) {
			return null;
		}

		$post_type = get_post_type( $post_id );
		if ( ! $post_type ) {
			return null;
		}

		foreach ( $rules as $rule ) {
			if ( ( $rule['post_type'] ?? '' ) !== $post_type ) {
				continue;
			}

			$taxonomy = (string) ( $rule['taxonomy'] ?? '' );
			$term_ids = $rule['term_ids'] ?? array();

			if ( '' === $taxonomy || empty( $term_ids ) ) {
				return $rule; // blanket rule for this post type
			}

			$post_term_ids = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $post_term_ids ) ) {
				continue;
			}
			if ( count( array_intersect( $term_ids, $post_term_ids ) ) > 0 ) {
				return $rule;
			}
		}

		return null;
	}

	/**
	 * Drops malformed rows rather than rejecting the whole save.
	 *
	 * @param array<int, mixed> $rules
	 * @return array<int, array<string, mixed>>
	 */
	public static function sanitize_rules( array $rules ): array {
		$sanitized = array();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['post_type'] ) ) {
				continue;
			}

			$number_allowed = $rule['number_allowed'] ?? null;
			$period_days    = $rule['period_days'] ?? null;

			$sanitized[] = array(
				'id'                    => ! empty( $rule['id'] ) ? sanitize_key( (string) $rule['id'] ) : wp_generate_uuid4(),
				'post_type'             => sanitize_key( (string) $rule['post_type'] ),
				'taxonomy'              => ! empty( $rule['taxonomy'] ) ? sanitize_key( (string) $rule['taxonomy'] ) : '',
				'term_ids'              => isset( $rule['term_ids'] ) && is_array( $rule['term_ids'] )
					? array_values( array_map( 'intval', $rule['term_ids'] ) )
					: array(),
				'number_allowed'        => ( null !== $number_allowed && '' !== $number_allowed ) ? max( 1, (int) $number_allowed ) : null,
				'period_days'           => ( null !== $period_days && '' !== $period_days ) ? max( 1, (int) $period_days ) : null,
				'require_registration'  => ! empty( $rule['require_registration'] ),
			);
		}
		return $sanitized;
	}
}
