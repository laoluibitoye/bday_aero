<?php
/**
 * Render-time content gating. The full article body is never present in
 * this render's HTML — only a cleartext preview (soft mode) or nothing at
 * all (hard mode); `.aero-paywall-locked` is an empty placeholder the SDK
 * fills in only after a genuine per-reader entitlement check against the
 * mobile-api REST route.
 *
 * Unlike the retired connector-plugin's version, this does NOT hook
 * `the_content` as a blind filter. The plugin's own history includes a
 * real bug from exactly that: an unrelated `the_content` filter (related-
 * content injection) landing inside the gated/locked teaser because of a
 * priority-ordering accident neither side controlled. Instead,
 * template-parts/single-default.php calls bday_aero_gate_content()
 * explicitly at its own the_content() call site, so gating's position
 * relative to ads/related-content is a decision the template makes on
 * purpose, not filter-priority roulette.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Content_Gate {

	public const BREAK_SHORTCODE = '[aeropaywall_break]';

	private Bday_Aero_Premium_Map $premium_map;

	public function __construct( Bday_Aero_Premium_Map $premium_map ) {
		$this->premium_map = $premium_map;
		add_shortcode( 'aeropaywall_break', '__return_empty_string' );
	}

	/**
	 * Called explicitly by the single-post template in place of
	 * the_content() — returns the same markup the_content() would, or the
	 * gated preview/lockout markup if this post is gated for the current
	 * request.
	 */
	public function gate_content( int $post_id, string $content ): string {
		if ( self::current_user_has_bypass_role() ) {
			return $content;
		}

		$is_premium = $this->premium_map->is_premium( $post_id );
		if ( ! self::is_gated_by_mode( $is_premium ) ) {
			return $content;
		}

		$initial_stage  = $this->resolve_initial_stage( $post_id );
		$structured_data = Bday_Aero_Settings::jsonld_enabled() ? self::structured_data() : '';

		if ( 'hard' === Bday_Aero_Settings::paywall_mode() ) {
			return self::render_hard_wall( $post_id, $initial_stage ) . $structured_data;
		}

		$preview = self::build_preview( $content, Bday_Aero_Settings::preview_word_count() );

		return sprintf(
			'<div class="aero-paywall-preview">%1$s</div>'
				. '<div class="aero-paywall-locked" data-aero-post-id="%2$d" data-aero-initial-stage="%3$s">'
				. '<p class="aero-paywall-locked-placeholder">%4$s</p>'
				. '%5$s'
				. '</div>%6$s',
			esc_html( $preview ),
			$post_id,
			esc_attr( $initial_stage ),
			esc_html( self::placeholder_text( $initial_stage ) ),
			self::mount_points_markup(),
			$structured_data
		);
	}

	/** Whether $post_id would render gated for the current request — usable by templates to skip content that only belongs in the full unlocked article (in-article ads, related reading). */
	public function is_post_gated( int $post_id ): bool {
		if ( self::current_user_has_bypass_role() ) {
			return false;
		}
		return self::is_gated_by_mode( $this->premium_map->is_premium( $post_id ) );
	}

	private static function is_gated_by_mode( bool $is_premium ): bool {
		return 'hard' === Bday_Aero_Settings::paywall_mode() || $is_premium;
	}

	/**
	 * Splits at the author-placed [aeropaywall_break] marker if present
	 * (must run before do_shortcode() expands it), else falls back to a
	 * fixed word count.
	 */
	private static function build_preview( string $content, int $word_count ): string {
		if ( false !== strpos( $content, self::BREAK_SHORTCODE ) ) {
			return trim( strstr( $content, self::BREAK_SHORTCODE, true ) );
		}

		$text  = wp_strip_all_tags( $content );
		$words = preg_split( '/\s+/', trim( $text ) );
		return implode( ' ', array_slice( $words, 0, max( 0, $word_count ) ) );
	}

	private static function render_hard_wall( int $post_id, string $initial_stage ): string {
		return sprintf(
			'<div class="aero-paywall-locked aero-paywall-hard-wall" data-aero-post-id="%1$d" data-aero-initial-stage="%2$s">'
				. '<p class="aero-paywall-locked-placeholder">%3$s</p>'
				. '%4$s'
				. '</div>',
			$post_id,
			esc_attr( $initial_stage ),
			esc_html( self::placeholder_text( $initial_stage ) ),
			self::mount_points_markup()
		);
	}

	/**
	 * Reader-reported: this placeholder used to be a single hardcoded
	 * "Subscribe to keep reading" string regardless of $initial_stage —
	 * harmless for a reader who never sees it (the SDK replaces it within
	 * one round trip to the entitlement endpoint), but on a slow load
	 * (shared hosting under load, a cold cache, a slow connection) a
	 * reader can land on the page during that window and see this text
	 * as if it were the real, final gate — including an anonymous,
	 * never-registered reader seeing "Subscribe" instead of "Create a
	 * free account," which reads as broken and undoes the exact register-
	 * vs-subscribe distinction the funnel is built around. Reuses the
	 * same admin-configured copy (Bday_Aero_Settings::prompt_copy()) the
	 * SDK's own context passes to the client, so whichever stage briefly
	 * shows here is never wrong, only ever less interactive, than what
	 * follows once JS hydrates. $initial_stage is only ever a same-paint
	 * *hint* (Bday_Aero_Meter_Client::check(), not a verified per-reader
	 * entitlement) — deliberately never anything stronger than that, and
	 * never a substitute for the SDK's own real check.
	 */
	private static function placeholder_text( string $initial_stage ): string {
		$copy = Bday_Aero_Settings::prompt_copy();
		// 'unknown' (no device cookie yet — the very first request from a
		// browser that's never visited before) and 'open' (would mean this
		// post isn't actually gated, unreachable here since gate_content()
		// already returned early in that case) both fall back to the
		// friendliest, most likely-correct stage for a genuinely new
		// reader: register.
		$stage = isset( $copy[ $initial_stage ] ) ? $initial_stage : 'register_prompt';
		return $copy[ $stage ]['headline'] ?? __( 'Subscribe to keep reading this article.', 'bday-premium' );
	}

	private static function mount_points_markup(): string {
		return '<div class="aero-paywall-mount aero-paywall-mount-register" hidden></div>'
			. '<div class="aero-paywall-mount aero-paywall-mount-profile" hidden></div>'
			. '<div class="aero-paywall-mount aero-paywall-mount-checkout" hidden></div>';
	}

	private static function current_user_has_bypass_role(): bool {
		$bypass_roles = Bday_Aero_Settings::bypass_roles();
		if ( empty( $bypass_roles ) ) {
			return false;
		}
		$user = wp_get_current_user();
		if ( ! $user || empty( $user->roles ) ) {
			return false;
		}
		return count( array_intersect( $bypass_roles, $user->roles ) ) > 0;
	}

	private static function structured_data(): string {
		return sprintf(
			'<script type="application/ld+json">%s</script>',
			wp_json_encode(
				array(
					'@context'  => 'https://schema.org',
					'@type'     => 'NewsArticle',
					'isAccessibleForFree' => 'False',
					'hasPart'   => array(
						'@type' => 'WebPageElement',
						'isAccessibleForFree' => 'False',
						'cssSelector' => '.aero-paywall-locked',
					),
				)
			)
		);
	}

	/** Advisory only — a same-paint hint for the SDK; never changes what markup is emitted above. */
	private function resolve_initial_stage( int $post_id ): string {
		$device_id = Bday_Aero_Device_Cookie::get();
		if ( null === $device_id ) {
			return 'unknown';
		}
		$meter = Bday_Aero_Meter_Client::check( $device_id, $post_id );
		return $meter['stage'] ?? 'unknown';
	}
}

// bday_aero_gate_content() / bday_aero_is_post_gated() — the two global
// helpers templates actually call — live in
// core/boundary/aero-paywall-bridge.php, not here. They must be defined
// unconditionally at theme boot regardless of whether this add-on is
// enabled (its own "Default: off" is deliberate — see addon.php), since
// core templates (template-parts/single-default.php) call them
// unconditionally. Defining them only here would fatal with "call to
// undefined function" on every install that hasn't turned this add-on on.
