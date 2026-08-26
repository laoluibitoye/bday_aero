<?php
/**
 * Premium-flag resolution (category mapping + per-post metabox override)
 * and sync of the resolved premium-post set to subscription-service.
 * Ported from the retired connector-plugin's AeroPaywall_Premium_Map.
 *
 * is_premium() is a cheap per-post check (one meta read, one term read)
 * used at render time by the content gate. sync_to_system_b() rebuilds the
 * complete premium-post-id list and pushes it to subscription-service's
 * /connector/premium-map, debounced through Bday_Query_Cache::remember()
 * (the same caching primitive the rest of the theme uses for DB queries)
 * so repeated triggers within the TTL window don't re-run the site-wide
 * query or the HTTP call.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Premium_Map {

	private const META_KEY   = '_aero_paywall_premium_override';
	private const CACHE_TTL  = 12 * HOUR_IN_SECONDS;

	/** Retry hooks, and the transient that drives the "sync failed" admin notice — see sync_to_system_b()/sync_restriction_rules_to_system_b(). */
	private const RETRY_HOOK_PREMIUM_MAP       = 'bday_aero_premium_map_retry_sync';
	private const RETRY_HOOK_RESTRICTION_RULES = 'bday_aero_restriction_rules_retry_sync';
	private const SYNC_FAILED_TRANSIENT        = 'bday_aero_premium_sync_failed';

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
		add_action( 'save_post', array( $this, 'handle_save_post' ) );
		add_action( 'update_option_' . Bday_Aero_Settings::PREMIUM_TERMS, array( $this, 'invalidate_and_sync' ) );
		add_action( 'update_option_' . Bday_Aero_Settings::RESTRICTION_RULES, array( $this, 'invalidate_and_sync' ) );
		add_action( self::RETRY_HOOK_PREMIUM_MAP, array( $this, 'retry_premium_map_sync' ) );
		add_action( self::RETRY_HOOK_RESTRICTION_RULES, array( $this, 'retry_restriction_rules_sync' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_sync_failed_notice' ) );
	}

	/**
	 * Both sync_to_system_b() and sync_restriction_rules_to_system_b() fire
	 * wp_remote_post() and, previously, never looked at the response — a
	 * failed sync (network blip, subscription-service deploy, bad API key)
	 * meant the premium-map/restriction-rules subscription-service had on
	 * file silently went stale with nothing logged and no way to know short
	 * of manually diffing the two systems. This now logs, schedules exactly
	 * one retry 5 minutes out (not a retry loop — if that single retry also
	 * fails, it's logged too and the next natural sync trigger picks it up),
	 * and surfaces a wp-admin notice until a sync next succeeds.
	 */
	private function handle_sync_response( $response, string $context, string $retry_hook ): bool {
		$failed = is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response );
		if ( ! $failed ) {
			delete_transient( self::SYNC_FAILED_TRANSIENT );
			return true;
		}

		$error_detail = is_wp_error( $response )
			? $response->get_error_message()
			: 'HTTP ' . wp_remote_retrieve_response_code( $response );
		error_log( sprintf( '[AeroPaywall] %s failed: %s', $context, $error_detail ) );

		set_transient(
			self::SYNC_FAILED_TRANSIENT,
			array(
				'context' => $context,
				'time'    => time(),
			),
			DAY_IN_SECONDS
		);

		if ( ! wp_next_scheduled( $retry_hook ) ) {
			wp_schedule_single_event( time() + 300, $retry_hook );
		}

		return false;
	}

	/**
	 * The single scheduled retry (see handle_sync_response()) must actually
	 * re-run the HTTP call, not just re-hit the cache: Bday_Query_Cache::
	 * remember() caches whatever the producer returns — including the
	 * `true` these producers always return regardless of whether the
	 * wp_remote_post() inside them succeeded — for the full CACHE_TTL, so
	 * without forgetting the key first a retry against a still-cached
	 * "success" would be a silent no-op.
	 */
	public function retry_premium_map_sync(): void {
		Bday_Query_Cache::forget( 'aero_paywall', 'premium_map_synced' );
		$this->sync_to_system_b();
	}

	public function retry_restriction_rules_sync(): void {
		Bday_Query_Cache::forget( 'aero_paywall', 'restriction_rules_synced' );
		$this->sync_restriction_rules_to_system_b();
	}

	/** Renders the "sync to subscription-service failed" notice set by handle_sync_response(), cleared automatically the next time a sync succeeds. */
	public function maybe_render_sync_failed_notice(): void {
		$failure = get_transient( self::SYNC_FAILED_TRANSIENT );
		if ( ! is_array( $failure ) || empty( $failure['time'] ) ) {
			return;
		}

		$when = wp_date( 'Y-m-d H:i:s', (int) $failure['time'] );
		echo '<div class="notice notice-warning"><p>'
			. esc_html__( 'Premium classification sync to subscription-service failed as of ', 'bday-aero' ) . esc_html( $when ) . '.'
			. '</p></div>';
	}

	public function register_metabox(): void {
		foreach ( Bday_Aero_Settings::restricted_post_types() as $post_type ) {
			add_meta_box(
				'bday_aero_premium_override',
				'AeroPaywall Premium',
				array( $this, 'render_metabox' ),
				$post_type,
				'side'
			);
		}
	}

	public function render_metabox( WP_Post $post ): void {
		$value = get_post_meta( $post->ID, self::META_KEY, true ) ?: 'inherit';
		wp_nonce_field( 'bday_aero_premium_override', 'bday_aero_premium_override_nonce' );

		$options = array(
			'inherit' => 'Inherit from category/rules',
			'premium' => 'Force premium',
			'free'    => 'Force free',
		);

		echo '<p class="description">Overrides the Restrictions tab\'s rules for this specific post.</p>';
		foreach ( $options as $key => $label ) {
			printf(
				'<label style="display:block;margin-bottom:6px;"><input type="radio" name="bday_aero_premium_override" value="%1$s" %2$s /> %3$s</label>',
				esc_attr( $key ),
				checked( $value, $key, false ),
				esc_html( $label )
			);
		}
	}

	public function handle_save_post( int $post_id ): void {
		if ( ! isset( $_POST['bday_aero_premium_override_nonce'] )
			|| ! wp_verify_nonce( $_POST['bday_aero_premium_override_nonce'], 'bday_aero_premium_override' )
		) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$value = isset( $_POST['bday_aero_premium_override'] )
			? sanitize_text_field( wp_unslash( $_POST['bday_aero_premium_override'] ) )
			: 'inherit';
		if ( ! in_array( $value, array( 'inherit', 'premium', 'free' ), true ) ) {
			$value = 'inherit';
		}

		update_post_meta( $post_id, self::META_KEY, $value );
		unset( self::$resolved_cache[ $post_id ] );
		$this->invalidate_and_sync();
	}

	/**
	 * Per-request memoization — is_premium() is called once per row by
	 * class-post-list-badge.php on every wp-admin list-table screen
	 * (including search results, the reported spike source), so without
	 * this a page of N rows costs up to 3N wp_get_post_terms() lookups for
	 * what's always the same answer within a single request.
	 *
	 * @var array<int, bool>
	 */
	private static array $resolved_cache = array();

	/** Cheap per-post check: one meta read, plus (only when 'inherit') the restriction rules/legacy-terms check. */
	public function is_premium( int $post_id ): bool {
		if ( array_key_exists( $post_id, self::$resolved_cache ) ) {
			return self::$resolved_cache[ $post_id ];
		}

		$override = get_post_meta( $post_id, self::META_KEY, true ) ?: 'inherit';

		if ( 'premium' === $override ) {
			return self::$resolved_cache[ $post_id ] = true;
		}
		if ( 'free' === $override ) {
			return self::$resolved_cache[ $post_id ] = false;
		}

		if ( self::terms_match( $post_id, Bday_Aero_Settings::restriction_exceptions() ) ) {
			return self::$resolved_cache[ $post_id ] = false;
		}

		// Bug found live: a matching restriction rule used to short-circuit
		// this check entirely, which meant the moment *any* restriction rule
		// existed anywhere on the site, the simple "Premium Categories"
		// picker stopped doing anything for every post that didn't happen to
		// match one of those rules — an admin picking a new premium category
		// would see it silently ignored. Restriction rules and premium
		// categories are two independent ways a post can be premium, not one
		// overriding the other — either being true is enough.
		$rules = Bday_Aero_Settings::restriction_rules();
		if ( ! empty( $rules ) && null !== Bday_Aero_Restriction_Rules::match_rule_for_post( $post_id ) ) {
			return self::$resolved_cache[ $post_id ] = true;
		}

		return self::$resolved_cache[ $post_id ] = self::terms_match( $post_id, Bday_Aero_Settings::premium_terms() );
	}

	/** @param array<string, int[]> $taxonomy_term_map */
	private static function terms_match( int $post_id, array $taxonomy_term_map ): bool {
		if ( empty( $taxonomy_term_map ) ) {
			return false;
		}

		$post_type            = get_post_type( $post_id );
		$applicable_taxonomies = $post_type ? get_object_taxonomies( $post_type ) : array();

		foreach ( $taxonomy_term_map as $taxonomy => $term_ids ) {
			if ( empty( $term_ids ) || ! in_array( $taxonomy, $applicable_taxonomies, true ) ) {
				continue;
			}
			$post_term_ids = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $post_term_ids ) ) {
				continue;
			}
			if ( count( array_intersect( $term_ids, $post_term_ids ) ) > 0 ) {
				return true;
			}
		}

		return false;
	}

	public function invalidate_and_sync(): void {
		Bday_Query_Cache::forget( 'aero_paywall', 'premium_map_synced' );
		Bday_Query_Cache::forget( 'aero_paywall', 'restriction_rules_synced' );
		$this->sync_to_system_b();
		$this->sync_restriction_rules_to_system_b();
	}

	public function sync_to_system_b(): void {
		Bday_Query_Cache::remember(
			'aero_paywall',
			'premium_map_synced',
			function () {
				$premium_post_ids = $this->resolve_all_premium_post_ids();
				$premium_terms    = Bday_Aero_Settings::premium_terms();
				$premium_category_ids = array_map( 'strval', $premium_terms['category'] ?? array() );

				$base_url = Bday_Aero_Settings::api_base_url();
				$api_key  = Bday_Aero_Settings::api_key();
				if ( '' === $base_url || '' === $api_key ) {
					return true;
				}

				$response = wp_remote_post(
					$base_url . '/connector/premium-map',
					array(
						'timeout' => 5,
						'headers' => array(
							'Content-Type' => 'application/json',
							'X-Api-Key'    => $api_key,
						),
						'body'    => wp_json_encode(
							array(
								'premiumPostIds'     => array_map( 'strval', $premium_post_ids ),
								'premiumCategoryIds' => $premium_category_ids,
							)
						),
					)
				);

				$this->handle_sync_response(
					$response,
					sprintf( 'premium-map sync (%d post IDs)', count( $premium_post_ids ) ),
					self::RETRY_HOOK_PREMIUM_MAP
				);

				return true;
			},
			self::CACHE_TTL
		);
	}

	/** @return int[] */
	private function resolve_all_premium_post_ids(): array {
		$post_types    = Bday_Aero_Settings::restricted_post_types();
		$premium_terms = Bday_Aero_Settings::premium_terms();

		$tax_query = array();
		foreach ( $premium_terms as $taxonomy => $term_ids ) {
			if ( empty( $term_ids ) ) {
				continue;
			}
			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => $term_ids,
			);
		}
		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'OR';
		}

		$term_query = empty( $tax_query ) ? array() : ( new WP_Query(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'tax_query'      => $tax_query,
				/**
				 * Bug found live while testing the meter/funnel end to
				 * end: a bare `'compare' => '!='` against a meta key that
				 * doesn't exist on a post never matches that post at all
				 * (SQL NULL != 'free' is unknown, not true) — since no
				 * post gets this override meta unless an editor
				 * explicitly sets one, this excluded literally every
				 * category-premium post from the synced set, every time.
				 * subscription-service's own meter/funnel only ever
				 * counts/gates posts it believes are premium, so the
				 * practical effect was the paywall's free-article quota
				 * never engaging for anyone, on any post, regardless of
				 * how many they read — the OR'd NOT EXISTS clause below
				 * is the standard WP fix for "compare against an
				 * optional meta key."
				 */
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => self::META_KEY,
						'value'   => 'free',
						'compare' => '!=',
					),
					array(
						'key'     => self::META_KEY,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		) )->posts;

		$override_query = ( new WP_Query(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'meta_key'       => self::META_KEY,
				'meta_value'     => 'premium',
			)
		) )->posts;

		return array_values( array_unique( array_merge( $term_query, $override_query ) ) );
	}

	public function sync_restriction_rules_to_system_b(): void {
		$rules = Bday_Aero_Settings::restriction_rules();
		if ( empty( $rules ) ) {
			return;
		}

		Bday_Query_Cache::remember(
			'aero_paywall',
			'restriction_rules_synced',
			function () use ( $rules ) {
				$base_url = Bday_Aero_Settings::api_base_url();
				$api_key  = Bday_Aero_Settings::api_key();
				if ( '' === $base_url || '' === $api_key ) {
					return true;
				}

				$payload_rules = array_map(
					static function ( array $rule ): array {
						return array(
							'id'                   => $rule['id'],
							'postType'             => $rule['post_type'],
							'taxonomy'             => '' !== $rule['taxonomy'] ? $rule['taxonomy'] : null,
							'termIds'              => array_map( 'strval', $rule['term_ids'] ),
							'numberAllowed'        => $rule['number_allowed'],
							'periodDays'           => $rule['period_days'],
							'requireRegistration'  => $rule['require_registration'],
							'sortOrder'            => 0,
						);
					},
					$rules
				);

				$response = wp_remote_post(
					$base_url . '/connector/restriction-rules',
					array(
						'timeout' => 5,
						'headers' => array(
							'Content-Type' => 'application/json',
							'X-Api-Key'    => $api_key,
						),
						'body'    => wp_json_encode(
							array(
								'rules'       => array_values( $payload_rules ),
								'assignments' => $this->resolve_all_rule_assignments( $rules ),
							)
						),
					)
				);

				$this->handle_sync_response(
					$response,
					sprintf( 'restriction-rules sync (%d rules)', count( $rules ) ),
					self::RETRY_HOOK_RESTRICTION_RULES
				);

				return true;
			},
			self::CACHE_TTL
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $rules
	 * @return array<int, array{postId: string, ruleId: string}>
	 */
	private function resolve_all_rule_assignments( array $rules ): array {
		$assigned    = array();
		$assignments = array();

		foreach ( $rules as $rule ) {
			$tax_query = array();
			if ( '' !== $rule['taxonomy'] && ! empty( $rule['term_ids'] ) ) {
				$tax_query[] = array(
					'taxonomy' => $rule['taxonomy'],
					'field'    => 'term_id',
					'terms'    => $rule['term_ids'],
				);
			}

			$post_ids = ( new WP_Query(
				array(
					'post_type'      => $rule['post_type'],
					'post_status'    => 'publish',
					'fields'         => 'ids',
					'posts_per_page' => -1,
					'tax_query'      => $tax_query,
				)
			) )->posts;

			foreach ( $post_ids as $post_id ) {
				if ( isset( $assigned[ $post_id ] ) ) {
					continue;
				}
				$assigned[ $post_id ] = true;
				$assignments[]        = array(
					'postId' => (string) $post_id,
					'ruleId' => $rule['id'],
				);
			}
		}

		return $assignments;
	}
}
