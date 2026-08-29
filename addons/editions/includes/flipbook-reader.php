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
	#pageWrap { position: relative; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; transform-style: preserve-3d; transition: transform 0.35s ease; }
	#pageWrap.turning-next { transform: rotateY(-14deg) scale(0.96); }
	#pageWrap.turning-prev { transform: rotateY(14deg) scale(0.96); }
	canvas { max-width: 100%; max-height: 100%; box-shadow: 0 8px 40px rgba(0,0,0,0.5); background: #fff; }
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
	<div id="pageWrap"><canvas id="pageCanvas"></canvas></div>
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
	var canvas = document.getElementById('pageCanvas');
	var ctx = canvas.getContext('2d');
	var pageWrap = document.getElementById('pageWrap');
	var indicator = document.getElementById('pageIndicator');
	var loading = document.getElementById('loading');

	function renderPage(num) {
		if (rendering || !pdfDoc) return;
		rendering = true;
		pdfDoc.getPage(num).then(function (page) {
			var viewport = page.getViewport({ scale: 1 });
			var scale = Math.min(window.innerWidth / viewport.width, window.innerHeight * 0.88 / viewport.height);
			var scaledViewport = page.getViewport({ scale: scale * (window.devicePixelRatio || 1) });
			canvas.width = scaledViewport.width;
			canvas.height = scaledViewport.height;
			canvas.style.width = (scaledViewport.width / (window.devicePixelRatio || 1)) + 'px';
			canvas.style.height = (scaledViewport.height / (window.devicePixelRatio || 1)) + 'px';
			page.render({ canvasContext: ctx, viewport: scaledViewport }).promise.then(function () {
				rendering = false;
				indicator.textContent = num + ' / ' + pdfDoc.numPages;
			});
		});
	}

	function goTo(num, direction) {
		if (!pdfDoc || num < 1 || num > pdfDoc.numPages || rendering) return;
		pageWrap.classList.add(direction === 1 ? 'turning-next' : 'turning-prev');
		setTimeout(function () {
			pageNum = num;
			renderPage(pageNum);
			pageWrap.classList.remove('turning-next', 'turning-prev');
		}, 160);
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
		renderPage(pageNum);
	}).catch(function () {
		loading.textContent = "Couldn't load this edition. Try the download link below instead.";
	});

	window.addEventListener('resize', function () { renderPage(pageNum); });
})();
</script>
</body>
</html>
		<?php
		exit;
	}
);
