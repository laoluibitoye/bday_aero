<?php
/**
 * Google Ad Manager — consolidates what used to be four separate
 * registration blocks (a viewability-refresh engine plus three static
 * bd_desktop_N / bd_mobile_N blocks, each re-calling enableSingleRequest()
 * and enableServices()) into one registration pass. Slot paths/div-ids/sizes
 * are preserved exactly as they were, since they map to real ad units
 * already configured in Google Ad Manager — inventing new ones here would
 * silently break ad revenue mapping.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bday_Vendor_Gam extends Bday_Vendor_Driver {

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
			array( 'key' => 'enabled', 'type' => 'checkbox', 'label' => 'Enable Google Ad Manager', 'default' => true ),
		);
	}

	public function print_head(): void {
		?>
		<script async src="https://securepubads.g.doubleclick.net/tag/js/gpt.js" crossorigin="anonymous"></script>
		<script>
		window.googletag = window.googletag || { cmd: [] };
		googletag.cmd.push(function () {
			var slots = [];
			var slotState = {};
			var MAX_REFRESHES = 3;
			var lastActivity = Date.now();

			['mousemove', 'scroll', 'touchstart', 'keydown'].forEach(function (evt) {
				document.addEventListener(evt, function () { lastActivity = Date.now(); }, { passive: true });
			});

			var mappingTop = googletag.sizeMapping()
				.addSize([1024, 0], [[970, 90], [728, 90], 'fluid'])
				.addSize([768, 0], [[728, 90], [300, 250], [300, 100], 'fluid'])
				.addSize([0, 0], [[320, 100], [320, 50], [300, 250], 'fluid'])
				.build();

			var mappingBody = googletag.sizeMapping()
				.addSize([1024, 0], [[336, 280], [300, 280], [300, 250], [250, 250], [320, 480], [480, 320], [320, 100], [320, 50], [300, 50], 'fluid'])
				.addSize([768, 0], [[336, 280], [300, 250], [250, 250], [320, 480], [480, 320], [320, 100], [320, 50], [300, 50], 'fluid'])
				.addSize([0, 0], [[300, 250], [250, 250], [320, 480], [480, 320], [320, 100], [320, 50], [300, 50], 'fluid'])
				.build();

			function registerSlot(path, sizes, id, mapping) {
				var slot = googletag.defineSlot(path, sizes, id);
				if (!slot) return null;
				if (mapping) slot.defineSizeMapping(mapping);
				slot.addService(googletag.pubads());
				slots.push(slot);
				slotState[id] = { refreshCount: 0, viewable: false, lastRefresh: 0 };
				return slot;
			}

			registerSlot('/23043164651,21781351181/businessday_top', [[728,90],[300,50],[320,100],[300,100],[468,60],[970,90],'fluid',[320,50],[300,250]], 'div-gpt-ad-1783084250687-0', mappingTop);
			registerSlot('/23043164651,21781351181/businessday_top2', [[300,50],[300,280],[320,50],[300,250],[728,90],[468,60],[970,90],[320,100],'fluid',[300,100]], 'div-gpt-ad-1783084673395-0', mappingTop);
			registerSlot('/23043164651,21781351181/businessday_body1', [[300,50],[300,100],[200,200],[250,250],[336,280],[300,250],'fluid',[320,100],[320,50]], 'div-gpt-ad-1783096747143-0', mappingBody);
			registerSlot('/23043164651,21781351181/businessday_body2', [[300,50],[728,90],[300,100],[320,100],[320,50],[250,250],[336,280],[300,250],[200,200],[320,480],'fluid'], 'div-gpt-ad-1783097109737-0', mappingBody);
			registerSlot('/23043164651,21781351181/businessday_body3', [[160,600],[120,600],[200,200],[320,480],[300,600],'fluid',[250,250],[300,250],[336,280]], 'div-gpt-ad-1783098103568-0', mappingBody);

			registerSlot('/21781351181/bd_desktop_1', [[970,250],'fluid',[468,60],[970,90],[300,250],[728,90]], 'div-gpt-ad-1731136144280-0', null);
			registerSlot('/21781351181/bd_desktop_2', [[970,250],'fluid',[300,250],[728,90],[468,60],[970,90]], 'div-gpt-ad-1731238739615-0', null);
			registerSlot('/21781351181/bd_desktop_3', [[300,50],[300,100],'fluid',[728,90]], 'div-gpt-ad-1731238848673-0', null);
			registerSlot('/21781351181/bd_desktop_4', ['fluid',[300,100],[300,250],[728,90]], 'div-gpt-ad-1731239152173-0', null);
			registerSlot('/21781351181/bd_mobile_1', [[300,50],[300,100],[320,100],[320,50],[300,250],[336,280],'fluid'], 'div-gpt-ad-1731239615531-0', null);
			registerSlot('/21781351181/bd_mobile_2', ['fluid',[300,100],[300,250],[300,50],[320,50],[320,100],[336,280]], 'div-gpt-ad-1731239712211-0', null);
			registerSlot('/21781351181/bd_mobile_3', [[300,100],[336,280],[300,250],[300,50],[320,100]], 'div-gpt-ad-1731239786872-0', null);
			registerSlot('/21781351181/bd_mobile_4', [[336,280],[300,100],[300,50],[320,100],[300,250]], 'div-gpt-ad-1731239857708-0', null);

			var anchorSlot = googletag.defineOutOfPageSlot('/23043164651,21781351181/businessday/businessday_anchor', googletag.enums.OutOfPageFormat.BOTTOM_ANCHOR);
			var interstitialSlot = googletag.defineOutOfPageSlot('/23043164651,21781351181/businessday/businessday_interstitial', googletag.enums.OutOfPageFormat.INTERSTITIAL);
			if (anchorSlot) anchorSlot.addService(googletag.pubads());
			if (interstitialSlot) interstitialSlot.addService(googletag.pubads());

			googletag.defineOutOfPageSlot('/21781351181/bd_left_rail', googletag.enums.OutOfPageFormat.LEFT_SIDE_RAIL).addService(googletag.pubads());
			googletag.defineOutOfPageSlot('/21781351181/bd_right_rail', googletag.enums.OutOfPageFormat.RIGHT_SIDE_RAIL).addService(googletag.pubads());

			// One registration pass, one call each — previously four
			// separate blocks each called these.
			googletag.pubads().enableSingleRequest();
			googletag.pubads().enableLazyLoad({ fetchMarginPercent: 100, renderMarginPercent: 50, mobileScaling: 1.0 });
			googletag.pubads().collapseEmptyDivs(true);
			googletag.pubads().setTargeting('sections', [window.pageCategory || 'all']);
			googletag.enableServices();

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

			var observer = new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					if (!entry.isIntersecting || entry.intersectionRatio < 0.7) return;
					var id = entry.target.id;
					if (slotState[id]) slotState[id].eligible = true;
				});
			}, { threshold: [0.7] });

			googletag.cmd.push(function () {
				slots.forEach(function (slot) {
					var el = document.getElementById(slot.getSlotElementId());
					if (el) observer.observe(el);
				});
			});
		});
		</script>
		<?php
	}
}
