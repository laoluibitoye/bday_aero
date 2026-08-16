<?php
/**
 * Google Ad Manager — consolidates what used to be four separate
 * registration blocks (a viewability-refresh engine plus three static
 * bd_desktop_N / bd_mobile_N blocks, each re-calling enableSingleRequest()
 * and enableServices()) into one registration pass. Slot paths/sizes are
 * preserved exactly as they were, since they map to real ad units already
 * configured in Google Ad Manager — inventing new ones here would silently
 * break ad revenue mapping.
 *
 * Phase 5 of the roadmap ("reconnect the ad system"): this used to define
 * all 13 named slots eagerly at wp_head against fixed div ids that no
 * template ever rendered — ads-sharing-matrix's bday_ad_zone() fired
 * bday_render_ad_zone with nothing listening, so nothing ever displayed.
 * Fixed by splitting into (a) global GPT setup + the 4 out-of-page slots
 * (unconditional — they need no container, unchanged from before) done
 * here in print_head(), and (b) render_zone(), which now actually listens
 * (via addons/vendors/addon.php's new bday_render_ad_zone dispatch) and
 * defines+displays exactly one slot per zone render, in a freshly emitted
 * container. Reuses 5 of the same 13 real ad-unit paths (the businessday_*
 * and bd_desktop_1/bd_mobile_1 ones) rather than inventing new inventory —
 * the other 8 remain available in zone_slots() for whoever confirms the
 * rest of the mapping with ad ops (the roadmap's "needs GAM inventory
 * coordination" note; bday_gam_zone_slots lets that happen without a code
 * change). Div ids are generated fresh per render instance rather than
 * reused from the old fixed list — GPT only requires the *ad unit path* to
 * match real inventory, the div id is an arbitrary, page-local DOM id, so
 * this is what makes a zone (like below_article_recirculation) safe to
 * render more than once on the same page without an id collision.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bday_Vendor_Gam extends Bday_Vendor_Driver {

	private static int $zone_instance = 0;

	public function slug(): string {
		return 'gam';
	}

	public function is_configured(): bool {
		$opt = $this->option();
		if ( isset( $opt['enabled'] ) && ! $opt['enabled'] ) {
			return false;
		}
		return bday_ads_allowed();
	}

	public function settings_fields(): array {
		return array(
			array( 'key' => 'enabled', 'type' => 'checkbox', 'label' => 'Enable Google Ad Manager', 'default' => true, 'description' => 'The main ad server — GAM decides which advertiser fills each ad zone on the page. Zone-by-zone placement rules live on the Ads & Sharing Matrix tab, not here; this only turns the ad server itself on or off.' ),
		);
	}

	/**
	 * zone => [ad unit path, sizes, size-mapping key ('top'|'body'|null)].
	 * Filterable so the final zone→inventory mapping can be confirmed/
	 * adjusted by ad ops without touching code — see the class docblock.
	 */
	private function zone_slots(): array {
		$slots = array(
			'homepage_leaderboard'        => array( '/23043164651,21781351181/businessday_top', array( array( 728, 90 ), array( 300, 50 ), array( 320, 100 ), array( 300, 100 ), array( 468, 60 ), array( 970, 90 ), 'fluid', array( 320, 50 ), array( 300, 250 ) ), 'top' ),
			'in_article_after_p2'         => array( '/23043164651,21781351181/businessday_body1', array( array( 300, 50 ), array( 300, 100 ), array( 200, 200 ), array( 250, 250 ), array( 336, 280 ), array( 300, 250 ), 'fluid', array( 320, 100 ), array( 320, 50 ) ), 'body' ),
			'below_share_buttons'         => array( '/23043164651,21781351181/businessday_body2', array( array( 300, 50 ), array( 728, 90 ), array( 300, 100 ), array( 320, 100 ), array( 320, 50 ), array( 250, 250 ), array( 336, 280 ), array( 300, 250 ), array( 200, 200 ), array( 320, 480 ), 'fluid' ), 'body' ),
			'below_article_recirculation' => array( '/23043164651,21781351181/businessday_body3', array( array( 160, 600 ), array( 120, 600 ), array( 200, 200 ), array( 320, 480 ), array( 300, 600 ), 'fluid', array( 250, 250 ), array( 300, 250 ), array( 336, 280 ) ), 'body' ),
			'sidebar'                     => array( '/21781351181/bd_desktop_1', array( array( 970, 250 ), 'fluid', array( 468, 60 ), array( 970, 90 ), array( 300, 250 ), array( 728, 90 ) ), null ),
		);
		return apply_filters( 'bday_gam_zone_slots', $slots );
	}

	public function print_head(): void {
		?>
		<script async src="https://securepubads.g.doubleclick.net/tag/js/gpt.js" crossorigin="anonymous"></script>
		<script>
		window.googletag = window.googletag || { cmd: [] };
		window.bdayGamSlotState = window.bdayGamSlotState || {};

		googletag.cmd.push(function () {
			var slotState = window.bdayGamSlotState;
			var MAX_REFRESHES = 3;
			var lastActivity = Date.now();

			['mousemove', 'scroll', 'touchstart', 'keydown'].forEach(function (evt) {
				document.addEventListener(evt, function () { lastActivity = Date.now(); }, { passive: true });
			});

			window.bdayGamMappingTop = googletag.sizeMapping()
				.addSize([1024, 0], [[970, 90], [728, 90], 'fluid'])
				.addSize([768, 0], [[728, 90], [300, 250], [300, 100], 'fluid'])
				.addSize([0, 0], [[320, 100], [320, 50], [300, 250], 'fluid'])
				.build();

			window.bdayGamMappingBody = googletag.sizeMapping()
				.addSize([1024, 0], [[336, 280], [300, 280], [300, 250], [250, 250], [320, 480], [480, 320], [320, 100], [320, 50], [300, 50], 'fluid'])
				.addSize([768, 0], [[336, 280], [300, 250], [250, 250], [320, 480], [480, 320], [320, 100], [320, 50], [300, 50], 'fluid'])
				.addSize([0, 0], [[300, 250], [250, 250], [320, 480], [480, 320], [320, 100], [320, 50], [300, 50], 'fluid'])
				.build();

			// Out-of-page formats need no container div, so these stay
			// eager/unconditional exactly as before — untouched by the
			// zone-dispatch rework below.
			var anchorSlot = googletag.defineOutOfPageSlot('/23043164651,21781351181/businessday/businessday_anchor', googletag.enums.OutOfPageFormat.BOTTOM_ANCHOR);
			var interstitialSlot = googletag.defineOutOfPageSlot('/23043164651,21781351181/businessday/businessday_interstitial', googletag.enums.OutOfPageFormat.INTERSTITIAL);
			if (anchorSlot) anchorSlot.addService(googletag.pubads());
			if (interstitialSlot) interstitialSlot.addService(googletag.pubads());
			// Rail formats return null wherever the browser/viewport can't
			// support them (defineOutOfPageSlot's documented behavior) —
			// found live during Phase 5 verification: the original,
			// unguarded `.addService()` chained straight off this call threw
			// and broke the rest of the queued GPT commands whenever that
			// happened, silently killing every zone's registration too.
			var leftRailSlot = googletag.defineOutOfPageSlot('/21781351181/bd_left_rail', googletag.enums.OutOfPageFormat.LEFT_SIDE_RAIL);
			var rightRailSlot = googletag.defineOutOfPageSlot('/21781351181/bd_right_rail', googletag.enums.OutOfPageFormat.RIGHT_SIDE_RAIL);
			if (leftRailSlot) leftRailSlot.addService(googletag.pubads());
			if (rightRailSlot) rightRailSlot.addService(googletag.pubads());

			if (anchorSlot) slotState[anchorSlot.getSlotElementId()] = { refreshCount: 0 };
			if (interstitialSlot) slotState[interstitialSlot.getSlotElementId()] = { refreshCount: 0 };

			// One registration pass, one call each — previously four
			// separate blocks each called these.
			googletag.pubads().enableSingleRequest();
			googletag.pubads().enableLazyLoad({ fetchMarginPercent: 100, renderMarginPercent: 50, mobileScaling: 1.0 });
			googletag.pubads().collapseEmptyDivs(true);
			googletag.pubads().setTargeting('sections', [window.pageCategory || 'all']);
			googletag.enableServices();

			/**
			 * Called by each bday_ad_zone() render (Bday_Vendor_Gam::render_zone()
			 * below) with a freshly emitted, page-unique div id — defining and
			 * displaying slots progressively as the page's own content calls
			 * for them is a supported GPT pattern (defineSlot()/display() can
			 * both run any time after enableServices(), not just before it),
			 * which is what makes this safe to call from arbitrary points in
			 * the body rather than needing every ad zone known up front.
			 */
			window.bdayGamRegisterZoneSlot = function (path, sizes, id, mappingKey) {
				googletag.cmd.push(function () {
					var slot = googletag.defineSlot(path, sizes, id);
					if (!slot) return;
					var mapping = mappingKey === 'top' ? window.bdayGamMappingTop : mappingKey === 'body' ? window.bdayGamMappingBody : null;
					if (mapping) slot.defineSizeMapping(mapping);
					slot.addService(googletag.pubads());
					slotState[id] = { refreshCount: 0, viewable: false, lastRefresh: 0 };
					googletag.display(id);
					var el = document.getElementById(id);
					if (el && 'IntersectionObserver' in window) {
						window.bdayGamZoneObserver.observe(el);
					}
				});
			};

			function canRefresh(id) {
				var s = slotState[id];
				if (!s || s.refreshCount >= MAX_REFRESHES) return false;
				if (document.hidden || !document.hasFocus()) return false;
				if (Date.now() - lastActivity > 180000) return false;
				if (Date.now() - s.lastRefresh < 120000) return false;
				return true;
			}

			googletag.pubads().addEventListener('impressionViewable', function (event) {
				var slot = event.slot;
				var id = slot.getSlotElementId();
				if (!slotState[id]) return;
				slotState[id].viewable = true;
				setTimeout(function () {
					if (!canRefresh(id)) return;
					googletag.pubads().refresh([slot]);
					slotState[id].refreshCount++;
					slotState[id].lastRefresh = Date.now();
				}, 30000);
			});

			googletag.pubads().addEventListener('slotRenderEnded', function (event) {
				var el = document.getElementById(event.slot.getSlotElementId());
				if (!el) return;
				var container = el.closest('.ad-container') || el;
				container.classList.toggle('filled', !event.isEmpty);
			});

			window.bdayGamZoneObserver = new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					if (!entry.isIntersecting || entry.intersectionRatio < 0.7) return;
					var id = entry.target.id;
					if (slotState[id]) slotState[id].eligible = true;
				});
			}, { threshold: [0.7] });
		});
		</script>
		<?php
	}

	public function render_zone( string $zone, ?WP_Post $post ): void {
		$slots = $this->zone_slots();
		if ( empty( $slots[ $zone ] ) ) {
			return;
		}
		list( $path, $sizes, $mapping_key ) = $slots[ $zone ];

		++self::$zone_instance;
		$id = 'div-gpt-ad-zone-' . sanitize_key( $zone ) . '-' . self::$zone_instance;
		?>
		<div class="ad-container" data-bd-ad-zone="<?php echo esc_attr( $zone ); ?>">
			<div id="<?php echo esc_attr( $id ); ?>"></div>
		</div>
		<script>
		// Queued onto googletag.cmd rather than calling
		// window.bdayGamRegisterZoneSlot directly — gpt.js loads async, so a
		// direct call here would run before print_head()'s own cmd.push(setup)
		// has actually executed and defined that function (found live: the
		// helper genuinely didn't exist yet at this point on a normal page
		// load, and a synchronous `if (window.bdayGamRegisterZoneSlot)` guard
		// just silently swallowed the call instead of erroring — every zone
		// slot went permanently undefined). Queuing preserves GPT's FIFO
		// command order: print_head's setup is always queued first.
		window.googletag = window.googletag || { cmd: [] };
		googletag.cmd.push(function () {
			if (window.bdayGamRegisterZoneSlot) {
				window.bdayGamRegisterZoneSlot(
					<?php echo wp_json_encode( $path ); ?>,
					<?php echo wp_json_encode( $sizes ); ?>,
					<?php echo wp_json_encode( $id ); ?>,
					<?php echo wp_json_encode( $mapping_key ); ?>
				);
			}
		});
		</script>
		<?php
	}
}
