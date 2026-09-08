<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The "read like a magazine" flip-through PDF viewer — one shared implementation reused by both
 * the website's "Read Edition" button (sdk/src/edition-download.ts) and the mobile app's own
 * E-Editions screens (WebView pointed at this same URL), so a fix/improvement here reaches both
 * surfaces at once rather than maintaining two page-flip implementations.
 *
 * Deliberately NOT a real WP Page + page template (which the app has no way to create for
 * itself, and would need a manual wp-admin step) — this hooks `template_redirect` directly and
 * short-circuits on a query var, the same technique class-bd-universal-links.php (the connector
 * plugin) uses for the Apple App Site Association file. `?bday_reader=1&pdf=<url>` works from any
 * URL on the site, no page/rewrite rule needed.
 *
 * `pdf` must already be a real, short-lived signed URL from subscription-service (this file never
 * itself decides who can read what — that entitlement check already happened when the caller
 * fetched the signed URL in the first place, same posture as the plain download button).
 */
add_action(
	'template_redirect',
	static function (): void {
		if ( ! isset( $_GET['bday_reader'] ) ) {
			return;
		}

		$pdf_url = isset( $_GET['pdf'] ) ? esc_url_raw( wp_unslash( $_GET['pdf'] ) ) : '';
		if ( '' === $pdf_url ) {
			wp_die( 'Missing pdf parameter.', 400 );
		}
		// See bday_edition_reader_pdf_url_is_allowed()'s own docblock
		// (secure-storage.php) — this used to render whatever URL was
		// passed here with no check of its own, making the reader a fully
		// open proxy for arbitrary third-party content under this site's
		// domain.
		if ( ! bday_edition_reader_pdf_url_is_allowed( $pdf_url ) ) {
			wp_die( 'This link is invalid or has expired.', 403 );
		}

		header( 'Content-Type: text/html; charset=utf-8' );
		?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes">
<title>Reading edition</title>
<style>
	html, body { margin: 0; padding: 0; background: #1a1a1a; height: 100%; overscroll-behavior: none; }
	#stage { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; perspective: 2000px; }
	#pageWrap { position: relative; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; transform-style: preserve-3d; }
	.page-canvas {
		position: absolute; max-width: 100%; max-height: 100%; box-shadow: 0 8px 40px rgba(0,0,0,0.5); background: #fff;
		opacity: 0; transform: translateX(0) scale(0.97); transition: opacity 0.32s ease, transform 0.32s ease;
	}
	/* Exactly one of these three is ever applied at a time — see goTo()'s
	   setPos() below. Crossfade + a slight slide-and-scale reads as a real
	   page turning past the previous one, without needing a page-flip
	   library or the false 3D perspective the old single-canvas rotateY
	   wobble attempted (and cut short: it swapped content at 160ms while
	   declaring a 350ms transition, so the tilt never finished before the
	   page underneath it changed). This runs the swap and the transition
	   on the same 320ms clock, both directions. */
	.page-canvas.pos-center { opacity: 1; transform: translateX(0) scale(1); }
	.page-canvas.pos-right  { opacity: 0; transform: translateX(28px) scale(0.97); }
	.page-canvas.pos-left   { opacity: 0; transform: translateX(-28px) scale(0.97); }
	#hud { position: fixed; left: 0; right: 0; bottom: 0; display: flex; align-items: center; justify-content: space-between; padding: 10px 16px; background: linear-gradient(transparent, rgba(0,0,0,0.6)); color: #fff; font: 13px -apple-system, Helvetica, Arial, sans-serif; }
	#hud button { background: rgba(255,255,255,0.12); border: none; color: #fff; padding: 8px 14px; border-radius: 999px; font-size: 13px; }
	#hud a { color: #fff; text-decoration: underline; }
	#tapZones { position: fixed; inset: 0; display: flex; }
	#tapZones div { flex: 1; }
	#loading { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; color: #fff; font: 14px -apple-system, Helvetica, Arial, sans-serif; }
</style>
</head>
<body>
<div id="loading">Loading edition…</div>
<div id="stage">
	<div id="pageWrap">
		<canvas class="page-canvas" id="pageCanvasA"></canvas>
		<canvas class="page-canvas" id="pageCanvasB"></canvas>
	</div>
</div>
<div id="tapZones"><div id="prevZone"></div><div id="nextZone"></div></div>
<div id="hud">
	<button id="prevBtn" aria-label="Previous page">‹ Prev</button>
	<span id="pageIndicator">–</span>
	<a id="downloadLink" href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" rel="noopener">Download PDF</a>
	<button id="nextBtn" aria-label="Next page">Next ›</button>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
(function () {
	var pdfUrl = <?php echo wp_json_encode( $pdf_url ); ?>;
	pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

	var pdfDoc = null;
	var pageNum = 1;
	var rendering = false;
	var TRANSITION_MS = 320; // matches the CSS transition duration below exactly — the old
	                          // version swapped content at 160ms against a declared 350ms
	                          // transition, so it never finished before being cut off.
	var canvases = [document.getElementById('pageCanvasA'), document.getElementById('pageCanvasB')];
	var activeIndex = 0; // which of canvases[] is currently the on-screen page
	var indicator = document.getElementById('pageIndicator');
	var loading = document.getElementById('loading');

	function setPos(el, pos) {
		el.classList.remove('pos-center', 'pos-right', 'pos-left');
		el.classList.add('pos-' + pos);
	}

	// Rasterizes `page` into `canvas` at a viewport-fit scale — the same
	// sizing math as before, just extracted so both the initial render and
	// every subsequent page turn share one implementation.
	function sizeAndRenderInto(canvas, page) {
		var viewport = page.getViewport({ scale: 1 });
		var scale = Math.min(window.innerWidth / viewport.width, window.innerHeight * 0.88 / viewport.height);
		var scaledViewport = page.getViewport({ scale: scale * (window.devicePixelRatio || 1) });
		canvas.width = scaledViewport.width;
		canvas.height = scaledViewport.height;
		canvas.style.width = (scaledViewport.width / (window.devicePixelRatio || 1)) + 'px';
		canvas.style.height = (scaledViewport.height / (window.devicePixelRatio || 1)) + 'px';
		return page.render({ canvasContext: canvas.getContext('2d'), viewport: scaledViewport }).promise;
	}

	// First page on load — no second page to cross from, just fades/scales
	// in from the base (unpositioned) state straight to centered.
	function renderInitial(num) {
		if (!pdfDoc) return;
		rendering = true;
		pdfDoc.getPage(num).then(function (page) {
			return sizeAndRenderInto(canvases[activeIndex], page);
		}).then(function () {
			setPos(canvases[activeIndex], 'center');
			indicator.textContent = num + ' / ' + pdfDoc.numPages;
			rendering = false;
		});
	}

	// A window resize re-renders the currently active page at the new
	// scale, in place — no transition, no swap, the other (inactive,
	// invisible) canvas is left untouched.
	function rerenderActive() {
		if (!pdfDoc || rendering) return;
		pdfDoc.getPage(pageNum).then(function (page) {
			return sizeAndRenderInto(canvases[activeIndex], page);
		});
	}

	// The actual page turn: render the new page into the OTHER canvas
	// while the current one is still fully visible, then crossfade+slide
	// both at once — the previous page is genuinely still on screen while
	// the new one arrives, unlike the old single-canvas version where the
	// outgoing page's pixels were simply overwritten mid-tilt.
	function goTo(num, direction) {
		if (!pdfDoc || num < 1 || num > pdfDoc.numPages || rendering) return;
		rendering = true;

		var outgoing = canvases[activeIndex];
		var incomingIndex = 1 - activeIndex;
		var incoming = canvases[incomingIndex];
		var entrySide = 1 === direction ? 'right' : 'left';
		var exitSide  = 1 === direction ? 'left' : 'right';

		pdfDoc.getPage(num).then(function (page) {
			return sizeAndRenderInto(incoming, page);
		}).then(function () {
			// Place the incoming canvas at its entry position first, with
			// no transition of its own to animate (it's still invisible,
			// wherever it was left after its last turn) — then force the
			// browser to commit that as a real, painted state before
			// asking it to animate away from it. Skipping this reflow
			// would let the browser coalesce "move to entrySide" and
			// "move to center" into a single frame and silently skip the
			// transition entirely.
			setPos(incoming, entrySide);
			void incoming.offsetWidth;

			setPos(incoming, 'center');
			setPos(outgoing, exitSide);

			window.setTimeout(function () {
				pageNum = num;
				activeIndex = incomingIndex;
				indicator.textContent = num + ' / ' + pdfDoc.numPages;
				rendering = false;
			}, TRANSITION_MS);
		});
	}

	document.getElementById('prevBtn').addEventListener('click', function () { goTo(pageNum - 1, -1); });
	document.getElementById('nextBtn').addEventListener('click', function () { goTo(pageNum + 1, 1); });
	document.getElementById('prevZone').addEventListener('click', function () { goTo(pageNum - 1, -1); });
	document.getElementById('nextZone').addEventListener('click', function () { goTo(pageNum + 1, 1); });

	// Horizontal swipe — a page-flip's most natural gesture on a touch device, matching the
	// tap zones above for anyone who prefers tapping the page edges instead.
	var touchStartX = null;
	document.getElementById('stage').addEventListener('touchstart', function (e) {
		touchStartX = e.touches[0].clientX;
	}, { passive: true });
	document.getElementById('stage').addEventListener('touchend', function (e) {
		if (touchStartX === null) return;
		var dx = e.changedTouches[0].clientX - touchStartX;
		if (Math.abs(dx) > 50) {
			dx < 0 ? goTo(pageNum + 1, 1) : goTo(pageNum - 1, -1);
		}
		touchStartX = null;
	}, { passive: true });

	document.addEventListener('keydown', function (e) {
		if (e.key === 'ArrowRight') goTo(pageNum + 1, 1);
		if (e.key === 'ArrowLeft') goTo(pageNum - 1, -1);
	});

	pdfjsLib.getDocument(pdfUrl).promise.then(function (doc) {
		pdfDoc = doc;
		loading.style.display = 'none';
		renderInitial(pageNum);
	}).catch(function () {
		loading.textContent = "Couldn't load this edition. Try the download link below instead.";
	});

	window.addEventListener('resize', rerenderActive);
})();
</script>
</body>
</html>
		<?php
		exit;
	}
);
