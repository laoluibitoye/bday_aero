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
			self::maybe_count_ungated_view( $is_premium, $post_id );
			return $content;
		}

		/**
		 * Task D fix: previously this render never checked the reader's own
		 * JWT at all — it always emitted the preview/placeholder markup and
		 * relied entirely on the SDK's later client-side fetch (against
		 * class-mobile-api.php's REST route) to reveal the real content to
		 * an entitled reader. Any reader whose browser couldn't complete
		 * that fetch (JS disabled, an ad-blocker filter matching a URL
		 * containing "paywall", a restrictive corporate CSP) saw the locked
		 * placeholder forever, even as an actively paying subscriber. Reads
		 * the SDK's own access-token cookie (see
		 * Bday_Aero_Entitlement_Resolver) and, for a verified, actively-
		 * subscribed reader, renders the full article directly here —
		 * skipping the preview/placeholder/SDK-mount-points path entirely.
		 * Anonymous/guest metering is untouched: no cookie (or an
		 * unauthenticated/non-subscribed one) falls straight through to the
		 * unchanged placeholder path below, which still needs the
		 * client-side device-id flow.
		 */
		$entitlement = Bday_Aero_Entitlement_Resolver::resolve_for_current_request();
		if ( null !== $entitlement && ! empty( $entitlement['isSubscriber'] ) ) {
			return $content;
		}

		$structured_data = Bday_Aero_Settings::jsonld_enabled() ? self::structured_data() : '';

		if ( 'hard' === Bday_Aero_Settings::paywall_mode() ) {
			return self::render_hard_wall( $post_id ) . $structured_data;
		}

		$preview = self::build_preview( $content, Bday_Aero_Settings::preview_word_count() );

		return sprintf(
			'<div class="aero-paywall-preview">%1$s</div>'
				. '<div class="aero-paywall-locked" data-aero-post-id="%2$d">'
				. '<p class="aero-paywall-locked-placeholder">%3$s</p>'
				. '%4$s'
				. '</div>%5$s',
			esc_html( $preview ),
			$post_id,
			esc_html( self::placeholder_text() ),
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

	/**
	 * Reader-reported live: with "Paywall scope" set to Global lock on the
	 * admin console, a reader could still read past their funnel
	 * threshold. Root cause: meter_scope_mode (subscription-service's
	 * `GET /public/paywall-config`, fetched here via
	 * Bday_Aero_Paywall_Config_Client) was already being read for other
	 * purposes but never actually consulted by the gating decision — this
	 * method only ever engaged the gate for a post explicitly marked
	 * premium (or when the separate WordPress-only "hard mode" toggle is
	 * on), with no awareness of the scope setting at all. Under
	 * restricted_only/hybrid, that's already the intended behavior
	 * ("only premium articles ever get gated" — admin-web's own copy for
	 * both those modes) — global_lock is the one mode that means every
	 * article, not just premium ones, should go through the same
	 * entitlement/meter check once a reader's free views run out.
	 */
	private static function is_gated_by_mode( bool $is_premium ): bool {
		if ( 'hard' === Bday_Aero_Settings::paywall_mode() || $is_premium ) {
			return true;
		}
		return 'global_lock' === ( Bday_Aero_Paywall_Config_Client::get()['meter_scope_mode'] ?? 'hybrid' );
	}

	/**
	 * Reader-asked: "if reading a free article records nothing, how do we
	 * know when a reader has read the number of allowed free articles?"
	 * Found live while answering that question: under Hybrid scope mode
	 * (subscription-service's funnel.service.ts's own shouldCount() is
	 * fully able to count every article view, gated or not — that's the
	 * whole point of Hybrid, per admin-web's own copy: "every article
	 * counts, only premium ones gate") the meter was never even called
	 * for a non-premium post, because is_gated_by_mode() above answers a
	 * *different* question ("should this specific view be locked") that
	 * this codebase had collapsed into the same boolean. This is the
	 * counting question, kept separate on purpose — restricted_only only
	 * ever counts premium reads (matches its own "free content is never
	 * touched" description); hybrid and global_lock count every read
	 * (global_lock's own gating already implies counting, but this stays
	 * independent so a future scope mode can't silently fall through
	 * either check).
	 */
	private static function should_count_by_mode( bool $is_premium ): bool {
		if ( $is_premium ) {
			return true;
		}
		$scope_mode = Bday_Aero_Paywall_Config_Client::get()['meter_scope_mode'] ?? 'hybrid';
		return in_array( $scope_mode, array( 'hybrid', 'global_lock' ), true );
	}

	/**
	 * Called only from the "not gated" branch of gate_content() — a gated
	 * view already counts via class-mobile-api.php's resolve_entitlement()
	 * (its own Bday_Aero_Meter_Client::check() call, made when the SDK's
	 * client-side entitlement fetch hits the REST route), so this exists
	 * purely to cover the gap that leaves: a free view that Hybrid mode
	 * should still count toward the reader's limit. Fire-and-forget
	 * (Bday_Aero_Meter_Client::record_async()) since nothing about this
	 * response depends on the answer — see that method's own docblock for
	 * why this must never become a blocking call in this hot a path.
	 */
	private static function maybe_count_ungated_view( bool $is_premium, int $post_id ): void {
		if ( ! self::should_count_by_mode( $is_premium ) ) {
			return;
		}
		$device_id = Bday_Aero_Device_Cookie::get();
		if ( null === $device_id ) {
			return;
		}
		Bday_Aero_Meter_Client::record_async( $device_id, $post_id );
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

	private static function render_hard_wall( int $post_id ): string {
		return sprintf(
			'<div class="aero-paywall-locked aero-paywall-hard-wall" data-aero-post-id="%1$d">'
				. '<p class="aero-paywall-locked-placeholder">%2$s</p>'
				. '%3$s'
				. '</div>',
			$post_id,
			esc_html( self::placeholder_text() ),
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
	 * as if it were the real, final gate.
	 *
	 * Task J1/J3: this used to branch on $initial_stage, a same-paint
	 * *hint* from a blocking server-to-server meter call
	 * (resolve_initial_stage(), now removed — confirmed nothing in
	 * sdk/src ever read the data-aero-initial-stage attribute it existed
	 * only to stamp, so it was pure latency/reliability risk for no
	 * functional benefit). Without that hint, this can no longer guess a
	 * per-reader stage — doing so risked showing "Create a free account"
	 * to a reader who is actually already signed in but just not
	 * subscribed, which reads as broken. Falls back to neutral,
	 * admin-configured copy (Bday_Aero_Settings::prompt_copy()) that
	 * doesn't presume anonymous-guest status; the SDK still replaces this
	 * with the reader's real, verified stage within one round trip.
	 */
	private static function placeholder_text(): string {
		$copy = Bday_Aero_Settings::prompt_copy();
		return $copy['paid_lock']['headline'] ?? __( 'Subscribe to keep reading this article.', 'bday-premium' );
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
}

// bday_aero_gate_content() / bday_aero_is_post_gated() — the two global
// helpers templates actually call — live in
// core/boundary/aero-paywall-bridge.php, not here. They must be defined
// unconditionally at theme boot regardless of whether this add-on is
// enabled (its own "Default: off" is deliberate — see addon.php), since
// core templates (template-parts/single-default.php) call them
// unconditionally. Defining them only here would fatal with "call to
// undefined function" on every install that hasn't turned this add-on on.
