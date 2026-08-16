/**
 * BusinessDay Premium — theme JS. Dependency-free vanilla JS by design:
 * the previous theme's script was 100% jQuery syntax while a separate
 * PHP hook deregistered jQuery after enqueueing it, so none of it ran.
 * Nothing here needs a library.
 */

/**
 * Header (header.php / components/_header.scss) — sticky/glass condense on
 * scroll, the mobile full-screen nav overlay, the search overlay, and the
 * dark-mode toggle. Replaces the old Bootstrap navbar/offcanvas/dropdown
 * JS entirely — no data-bs-* attributes read anywhere here.
 */
function bdayInitHeader() {
	var header = document.querySelector('[data-bd-header]');
	if (header) {
		// Two thresholds, not one — found live: a single toggle point
		// made the header visibly shake near scrollY=24px. The condensed
		// state itself changes the header's height (utility bar
		// max-height: 0), and that height change nudges scrollY by a
		// pixel or two (scroll anchoring, trackpad momentum), which
		// crossed the same single threshold back the other way and
		// re-triggered the CSS transition — repeatedly, in both
		// directions, for as long as the scroll gesture continued. A gap
		// between the enter/exit points means a few px of feedback can't
		// re-cross both boundaries in one frame.
		var ENTER_THRESHOLD = 64;
		var EXIT_THRESHOLD = 32;
		var isScrolled = false;
		var onScroll = function () {
			var y = window.scrollY;
			if (!isScrolled && y > ENTER_THRESHOLD) {
				isScrolled = true;
			} else if (isScrolled && y < EXIT_THRESHOLD) {
				isScrolled = false;
			}
			header.classList.toggle('is-scrolled', isScrolled);
		};
		onScroll();
		window.addEventListener('scroll', onScroll, { passive: true });
	}

	var menuToggle = document.querySelector('[data-bd-menu-toggle]');
	var menuOverlay = document.getElementById('bd-menu-overlay');
	var menuClose = document.querySelector('[data-bd-menu-close]');

	// Two instances now (desktop: far right of the nav bar; mobile: inside
	// the hamburger overlay's own top row) — querySelectorAll instead of
	// the single-element lookup this used before the search relocation,
	// same "widen to support >1 trigger" pattern already used for the
	// podcast-carousel facade.
	var searchToggles = document.querySelectorAll('[data-bd-search-toggle]');
	var searchOverlay = document.getElementById('bd-search-overlay');
	var searchClose = document.querySelector('[data-bd-search-close]');
	var searchInput = document.querySelector('[data-bd-search-input]');

	function setSearchTogglesExpanded(expanded) {
		searchToggles.forEach(function (t) {
			t.setAttribute('aria-expanded', String(expanded));
		});
	}

	function closeOverlay(overlay, trigger) {
		if (!overlay || overlay.hidden) return;
		overlay.hidden = true;
		if (overlay === searchOverlay) setSearchTogglesExpanded(false);
		else if (trigger) trigger.setAttribute('aria-expanded', 'false');
		if (!menuOverlay || menuOverlay.hidden) {
			if (!searchOverlay || searchOverlay.hidden) {
				document.body.style.overflow = '';
			}
		}
	}

	function openOverlay(overlay, trigger, focusEl) {
		if (!overlay) return;
		// Mutual exclusivity — opening one closes the other rather than
		// stacking two full-width overlays.
		closeOverlay(overlay === menuOverlay ? searchOverlay : menuOverlay, overlay === menuOverlay ? null : menuToggle);
		overlay.hidden = false;
		if (overlay === searchOverlay) setSearchTogglesExpanded(true);
		else if (trigger) trigger.setAttribute('aria-expanded', 'true');
		document.body.style.overflow = 'hidden';
		if (focusEl) focusEl.focus();
	}

	if (menuToggle && menuOverlay) {
		menuToggle.addEventListener('click', function () {
			if (menuOverlay.hidden) {
				openOverlay(menuOverlay, menuToggle, menuClose);
			} else {
				closeOverlay(menuOverlay, menuToggle);
			}
		});
	}
	if (menuClose) {
		menuClose.addEventListener('click', function () {
			closeOverlay(menuOverlay, menuToggle);
		});
	}

	if (searchToggles.length && searchOverlay) {
		searchToggles.forEach(function (toggle) {
			toggle.addEventListener('click', function () {
				if (searchOverlay.hidden) {
					openOverlay(searchOverlay, toggle, searchInput);
				} else {
					closeOverlay(searchOverlay, toggle);
				}
			});
		});
	}
	if (searchClose) {
		searchClose.addEventListener('click', function () {
			closeOverlay(searchOverlay, null);
		});
	}

	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape') return;
		if (menuOverlay && !menuOverlay.hidden) closeOverlay(menuOverlay, menuToggle);
		if (searchOverlay && !searchOverlay.hidden) closeOverlay(searchOverlay, null);
	});

	// Three-state theme toggle (light -> warm -> dark -> light) — mirrors
	// the inline head script's localStorage key ('bd-theme') so there's
	// one source of truth for the reader's choice. Reading the *effective*
	// current theme (not just the attribute) means the first click from
	// an OS-dark, no-attribute-set state correctly advances from dark,
	// not from an assumed light.
	var themeToggle = document.querySelector('[data-bd-theme-toggle]');
	if (themeToggle) {
		var prefersDarkMq = window.matchMedia('(prefers-color-scheme: dark)');
		var THEME_CYCLE = ['light', 'warm', 'dark'];
		var THEME_LABEL = { light: 'light', warm: 'warm (reduced blue light)', dark: 'dark' };

		function effectiveTheme() {
			var attr = document.documentElement.getAttribute('data-theme');
			if (attr === 'dark' || attr === 'warm' || attr === 'light') return attr;
			return prefersDarkMq.matches ? 'dark' : 'light';
		}

		function syncLabel() {
			var current = effectiveTheme();
			themeToggle.setAttribute('aria-label', 'Color theme: ' + THEME_LABEL[current] + '. Activate to change.');
		}
		syncLabel();

		themeToggle.addEventListener('click', function () {
			var currentIndex = THEME_CYCLE.indexOf(effectiveTheme());
			var next = THEME_CYCLE[(currentIndex + 1) % THEME_CYCLE.length];
			document.documentElement.setAttribute('data-theme', next);
			try {
				localStorage.setItem('bd-theme', next);
			} catch (e) {}
			syncLabel();
		});
	}

	// World-clock utility-bar strip (header.php) — ticks client-side since
	// a server-rendered time would go stale between cache hits; each city
	// carries its own IANA zone via data-bd-clock-tz, formatted with
	// Intl.DateTimeFormat rather than hand-rolled offset math so DST
	// transitions are handled correctly for every zone automatically.
	var clockEls = document.querySelectorAll('[data-bd-clock]');
	if (clockEls.length && window.Intl && Intl.DateTimeFormat) {
		var updateClocks = function () {
			var now = new Date();
			clockEls.forEach(function (el) {
				var tz = el.getAttribute('data-bd-clock-tz');
				var timeEl = el.querySelector('[data-bd-clock-time]');
				if (!tz || !timeEl) return;
				try {
					timeEl.textContent = new Intl.DateTimeFormat('en-GB', {
						timeZone: tz,
						hour: '2-digit',
						minute: '2-digit',
					}).format(now);
				} catch (e) {
					/* unknown zone — leave the placeholder */
				}
			});
		};
		updateClocks();
		setInterval(updateClocks, 30000);
	}
}

/**
 * Featured video cards (addons/featured-video-cards) — swaps a card's
 * static YouTube poster (data-bd-video-facade) for a real, muted,
 * autoplaying iframe once the card scrolls into view, using the YouTube
 * IFrame Player API so the in-card mute/pause buttons can control it
 * without exposing YouTube's own chrome. Respects prefers-reduced-motion
 * and Save-Data — both cases fall back to a static poster + click-to-play,
 * never a forced autoplay (Phase 2 spec, deep-dive §9's non-negotiable).
 */
function bdayInitFeaturedVideoCards() {
	var facades = document.querySelectorAll('[data-bd-video-facade]');
	if (!facades.length) return;

	var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var saveData = !!(navigator.connection && navigator.connection.saveData);
	var autoplayAllowed = !reducedMotion && !saveData;

	function wireControls(facade, getPlayer) {
		var muteBtn = facade.querySelector('[data-bd-video-mute]');
		var pauseBtn = facade.querySelector('[data-bd-video-pause]');
		var controls = facade.querySelector('.bday-video-facade__controls');
		if (controls) controls.hidden = false;

		if (muteBtn) {
			muteBtn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				var player = getPlayer();
				if (!player) return;
				var nowMuted = player.isMuted();
				if (nowMuted) player.unMute();
				else player.mute();
				muteBtn.setAttribute('aria-pressed', String(!nowMuted));
			});
		}
		if (pauseBtn) {
			pauseBtn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				var player = getPlayer();
				if (!player) return;
				var state = player.getPlayerState();
				if (state === 1) {
					player.pauseVideo();
					pauseBtn.setAttribute('aria-pressed', 'true');
				} else {
					player.playVideo();
					pauseBtn.setAttribute('aria-pressed', 'false');
				}
			});
		}
	}

	function activate(facade) {
		if (facade.dataset.bdActivated) return;
		facade.dataset.bdActivated = '1';
		var videoId = facade.dataset.videoId;
		if (!videoId) return;

		var mount = document.createElement('div');
		mount.className = 'bday-video-facade__player';
		facade.appendChild(mount);
		var player = null;

		function buildPlayer() {
			player = new window.YT.Player(mount, {
				videoId: videoId,
				playerVars: {
					autoplay: 1,
					mute: 1,
					controls: 0,
					loop: 1,
					playlist: videoId,
					playsinline: 1,
					modestbranding: 1,
					rel: 0,
				},
				events: {
					onReady: function () {
						facade.classList.add('is-playing');
					},
				},
			});
		}

		if (window.YT && window.YT.Player) {
			buildPlayer();
		} else {
			window.bdayYTReadyQueue = window.bdayYTReadyQueue || [];
			window.bdayYTReadyQueue.push(buildPlayer);
			if (!document.getElementById('bd-youtube-iframe-api')) {
				var tag = document.createElement('script');
				tag.id = 'bd-youtube-iframe-api';
				tag.src = 'https://www.youtube.com/iframe_api';
				document.head.appendChild(tag);
				window.onYouTubeIframeAPIReady = function () {
					window.bdayYTReadyQueue.forEach(function (fn) {
						fn();
					});
					window.bdayYTReadyQueue = [];
				};
			}
		}

		wireControls(facade, function () {
			return player;
		});
	}

	if (!autoplayAllowed) {
		// No forced network/autoplay cost — a tap on the poster opens the
		// video the normal way (the card's own <a> already links to the
		// article, which is judged sufficient rather than adding a second,
		// data-heavier in-place player for readers who asked to avoid this).
		return;
	}

	if ('IntersectionObserver' in window) {
		var observer = new IntersectionObserver(
			function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting) activate(entry.target);
				});
			},
			{ rootMargin: '200px' }
		);
		facades.forEach(function (facade) {
			observer.observe(facade);
		});
	} else {
		facades.forEach(activate);
	}
}

/**
 * Homepage "Latest Episode" facade (template-parts/homepage/bottom-widgets.php)
 * — the Spotify iframe isn't injected into the page until a reader taps
 * play, so the homepage never pays Spotify's embed-script/iframe cost for
 * a card most visitors won't listen to.
 */
function bdayInitPodcastFacade() {
	// One or many cards — the homepage podcast carousel
	// (template-parts/homepage/podcast-carousel.php) renders several,
	// each its own independent facade instance.
	var cards = document.querySelectorAll('[data-bd-podcast-facade]');
	cards.forEach(function (card) {
		var playBtn = card.querySelector('[data-bd-podcast-play]');
		var player = card.querySelector('[data-bd-podcast-player]');
		var embedUrl = card.dataset.podcastEmbed;
		if (!playBtn || !player || !embedUrl) return;

		playBtn.addEventListener('click', function (e) {
			e.preventDefault();
			if (card.classList.contains('is-active')) return;
			var iframe = document.createElement('iframe');
			iframe.src = embedUrl + (embedUrl.indexOf('?') === -1 ? '?' : '&') + 'autoplay=1';
			iframe.allow = 'autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture';
			iframe.loading = 'lazy';
			iframe.title = 'Spotify episode player';
			player.appendChild(iframe);
			player.hidden = false;
			card.classList.add('is-active');
		});
	});
}

/**
 * Reading progress bar (single-default.php's data-bd-reading-progress) —
 * tracks scroll position against the article body specifically, not the
 * whole document, so the bar reaches 100% as the reader finishes the
 * article's own text rather than the unrelated related-content/ad rows
 * below it.
 */
function bdayInitReadingProgress() {
	var track = document.querySelector('[data-bd-reading-progress]');
	var articleBody = document.querySelector('[data-bd-article-body]');
	if (!track || !articleBody) return;
	var bar = track.querySelector('.bday-reading-progress__bar');
	if (!bar) return;

	function update() {
		var rect = articleBody.getBoundingClientRect();
		var total = rect.height - window.innerHeight;
		var scrolled = -rect.top;
		var pct = total > 0 ? Math.min(100, Math.max(0, (scrolled / total) * 100)) : 0;
		bar.style.width = pct + '%';
	}
	update();
	window.addEventListener('scroll', update, { passive: true });
	window.addEventListener('resize', update);
}

/**
 * Header translate panel (addons/translate/ — a no-op if that add-on is
 * disabled, since its trigger markup then simply doesn't exist). Google's
 * widget mounts itself into #bday-google-translate-mount independently
 * (its own async script, addon.php's wp_footer hook) — this only owns
 * showing/hiding the small panel around it, same open/close shape as the
 * theme's other header dropdowns.
 */
function bdayInitTranslate() {
	var root = document.querySelector('[data-bd-translate-root]');
	var toggle = document.querySelector('[data-bd-translate-toggle]');
	var panel = document.querySelector('[data-bd-translate-panel]');
	if (!root || !toggle || !panel) return;

	function close() {
		panel.hidden = true;
		toggle.setAttribute('aria-expanded', 'false');
	}
	function open() {
		// Panel is position:fixed (see _header.scss) to escape
		// .bd-header__utility's overflow:hidden clipping, so it no longer
		// inherits an offset from the relatively-positioned toggle wrapper —
		// anchor it here instead, from the toggle's own live rect.
		var rect = toggle.getBoundingClientRect();
		panel.style.top = (rect.bottom + 10) + 'px';
		panel.style.right = (window.innerWidth - rect.right) + 'px';
		panel.hidden = false;
		toggle.setAttribute('aria-expanded', 'true');
	}

	toggle.addEventListener('click', function (e) {
		e.stopPropagation();
		if (panel.hidden) open();
		else close();
	});
	document.addEventListener('click', function (e) {
		if (!panel.hidden && !root.contains(e.target)) close();
	});
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && !panel.hidden) close();
	});

	/**
	 * Google's own dropdown (what its widget would normally show) opens
	 * inside a cross-origin iframe this theme's CSS can never reach —
	 * that's the actual reason the language picker still looked
	 * unstyled no matter how its container was dressed up. Driving the
	 * widget through its own googtrans cookie contract instead lets the
	 * trigger/panel above stay fully theme-owned; a full reload is
	 * required since the widget reads the cookie on page load.
	 */
	panel.addEventListener('click', function (e) {
		var option = e.target.closest('[data-bd-translate-lang]');
		if (!option) return;
		var lang = option.getAttribute('data-bd-translate-lang');
		var host = window.location.hostname;
		var parts = host.split('.');
		var base = parts.length > 2 ? parts.slice(-2).join('.') : host;
		var expire = 'expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/;';
		document.cookie = 'googtrans=;' + expire;
		document.cookie = 'googtrans=;' + expire + 'domain=.' + base + ';';
		if (lang !== 'en') {
			var value = 'googtrans=/en/' + lang + ';path=/;';
			document.cookie = value;
			document.cookie = value + 'domain=.' + base + ';';
		}
		window.location.reload();
	});
}

/**
 * Text-to-speech (single-default.php's #bday-tts-toggle, next to the
 * bookmark mount) — window.speechSynthesis is a standard browser API,
 * needs no backend/auth, so this stays theme-side rather than in the SDK
 * (matching the reading-progress bar/theme-toggle above, the theme's own
 * convention for pure-frontend features). Reads [data-bd-article-body]'s
 * visible text; one button cycles idle -> speaking -> paused -> idle,
 * same "one control, icon swap" pattern the theme toggle uses.
 */
function bdayInitTextToSpeech() {
	var button = document.getElementById('bday-tts-toggle');
	// .post-content specifically, not the wider [data-bd-article-body]
	// the reading-progress bar tracks — that wrapper also spans the share
	// row, table of contents, and related/YMAL recirculation links, none
	// of which should be read aloud as if they were the article's prose.
	var articleBody = document.querySelector('.post-content');
	if (!button || !articleBody || !('speechSynthesis' in window)) {
		if (button) button.hidden = true; // no TTS support in this browser — hide rather than offer a dead button
		return;
	}

	var synth = window.speechSynthesis;
	var utterance = null;

	/**
	 * Reader-reported: the voice sounds "extremely robotic". That's
	 * speechSynthesis defaulting to whatever's first in the browser's
	 * voice list, typically a low-quality offline/compact voice — not a
	 * bug so much as a bad default. Voices load asynchronously in some
	 * browsers (Chrome fires 'voiceschanged' after the initial empty
	 * getVoices() call), so the preferred one is resolved lazily, once,
	 * on first use rather than at init. Preference order: an explicitly
	 * higher-quality en voice (cloud/"Natural"/"Neural"/"Premium"/Google
	 * voices are consistently better than default offline ones) over any
	 * other English voice, over whatever the browser picks by default.
	 */
	var preferredVoice = null;
	var voicesReady = false;
	function resolvePreferredVoice() {
		var voices = synth.getVoices();
		if (!voices.length) return;
		voicesReady = true;
		var qualityHint = /Google|Natural|Neural|Premium|Enhanced/i;
		var enVoices = voices.filter(function (v) { return /^en/i.test(v.lang); });
		var pool = enVoices.length ? enVoices : voices;
		preferredVoice =
			pool.find(function (v) { return qualityHint.test(v.name) && !v.localService; }) ||
			pool.find(function (v) { return qualityHint.test(v.name); }) ||
			pool.find(function (v) { return !v.localService; }) ||
			pool[0];
	}
	resolvePreferredVoice();
	if (!voicesReady && 'onvoiceschanged' in synth) {
		synth.addEventListener('voiceschanged', resolvePreferredVoice, { once: true });
	}

	var label = button.querySelector('.bday-byline__tts-label');
	function setState(state) {
		button.dataset.state = state; // 'idle' | 'speaking' | 'paused'
		button.setAttribute('aria-label', state === 'speaking' ? 'Pause reading' : state === 'paused' ? 'Resume reading' : 'Listen to this article');
		if (label) label.textContent = state === 'speaking' ? 'Pause' : state === 'paused' ? 'Resume' : 'Listen';
	}
	setState('idle');

	function buildUtterance() {
		var text = articleBody.innerText || articleBody.textContent || '';
		var u = new SpeechSynthesisUtterance(text);
		u.rate = 1;
		if (!voicesReady) resolvePreferredVoice(); // last-chance resolve if 'voiceschanged' never fired
		if (preferredVoice) u.voice = preferredVoice;
		u.onend = function () {
			setState('idle');
		};
		u.onerror = function () {
			setState('idle');
		};
		return u;
	}

	button.addEventListener('click', function () {
		var state = button.dataset.state;
		if (state === 'idle') {
			synth.cancel(); // clears anything stray from a previous article/navigation
			utterance = buildUtterance();
			synth.speak(utterance);
			setState('speaking');
		} else if (state === 'speaking') {
			synth.pause();
			setState('paused');
		} else if (state === 'paused') {
			synth.resume();
			setState('speaking');
		}
	});

	// A reader navigating away mid-read (SPA-like behavior isn't in play
	// here, but a stray utterance surviving a bfcache restore is a real
	// browser quirk) shouldn't keep talking over the next page.
	window.addEventListener('pagehide', function () {
		synth.cancel();
	});
}

document.addEventListener('DOMContentLoaded', function () {
	bdayInitHeader();
	bdayInitFeaturedVideoCards();
	bdayInitPodcastFacade();
	bdayInitReadingProgress();
	bdayInitTranslate();
	bdayInitTextToSpeech();

	// Lazy-loaded images (post_thumbnail_html filter emits data-src)
	var lazyImages = document.querySelectorAll('img.img-lazy-load');
	if ('IntersectionObserver' in window && lazyImages.length) {
		var imageObserver = new IntersectionObserver(function (entries, observer) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) return;
				var img = entry.target;
				if (img.dataset.src) img.src = img.dataset.src;
				if (img.dataset.srcset) img.srcset = img.dataset.srcset;
				img.classList.remove('img-lazy-load');
				observer.unobserve(img);
			});
		});
		lazyImages.forEach(function (img) {
			imageObserver.observe(img);
		});
	}

	// Premium leaderboard rotation (addons/premium-leaderboard)
	document.querySelectorAll('.bday-leaderboard').forEach(function (board) {
		var slides = board.querySelectorAll('.bday-leaderboard__slide');
		if (slides.length < 2) return;
		var speed = parseInt(board.dataset.sliderSpeed, 10) || 20000;
		var current = 0;
		setInterval(function () {
			slides[current].style.display = 'none';
			current = (current + 1) % slides.length;
			slides[current].style.display = '';
		}, speed);
	});

	// News carousel (addons/news-carousel)
	document.querySelectorAll('.bday-news-carousel').forEach(function (carousel) {
		var track = carousel.querySelector('.bday-news-carousel__track');
		var prev = carousel.querySelector('.bday-news-carousel__prev');
		var next = carousel.querySelector('.bday-news-carousel__next');
		var items = Array.prototype.slice.call(carousel.querySelectorAll('.bday-news-carousel__item'));
		var dots = Array.prototype.slice.call(carousel.querySelectorAll('.bday-news-carousel__dot'));
		if (!track) return;

		function step() {
			var item = track.querySelector('.bday-news-carousel__item');
			return item ? item.offsetWidth + 20 : 300;
		}
		function scrollNext() {
			var atEnd = track.scrollLeft + track.offsetWidth >= track.scrollWidth - 10;
			if (atEnd) track.scrollTo({ left: 0, behavior: 'smooth' });
			else track.scrollBy({ left: step(), behavior: 'smooth' });
		}

		if (next) next.addEventListener('click', scrollNext);
		if (prev)
			prev.addEventListener('click', function () {
				if (track.scrollLeft <= 10) track.scrollTo({ left: track.scrollWidth, behavior: 'smooth' });
				else track.scrollBy({ left: -step(), behavior: 'smooth' });
			});

		// Which card/dot is "current" — driven by scroll position, not by
		// whichever button was last clicked, so it stays correct through
		// touch/trackpad scrolling and dragging too, not just the arrows.
		function updateActive() {
			if (!items.length) return;
			var maxScroll = track.scrollWidth - track.offsetWidth;
			var index = maxScroll > 0 ? Math.round((track.scrollLeft / maxScroll) * (items.length - 1)) : 0;
			items.forEach(function (item, i) {
				item.classList.toggle('is-active', i === index);
			});
			dots.forEach(function (dot, i) {
				dot.classList.toggle('is-active', i === index);
			});
		}
		track.addEventListener('scroll', updateActive);

		dots.forEach(function (dot) {
			dot.addEventListener('click', function () {
				var index = parseInt(dot.dataset.index, 10);
				var maxScroll = track.scrollWidth - track.offsetWidth;
				track.scrollTo({ left: (index / (dots.length - 1)) * maxScroll, behavior: 'smooth' });
			});
		});

		// Mouse-drag scrolling — trackpad/touch already scroll the track
		// natively, this just extends the same gesture to a plain mouse.
		var isDragging = false;
		var dragStartX = 0;
		var dragStartScroll = 0;
		track.addEventListener('mousedown', function (e) {
			isDragging = true;
			dragStartX = e.pageX;
			dragStartScroll = track.scrollLeft;
			track.style.scrollSnapType = 'none';
		});
		function endDrag() {
			isDragging = false;
			track.style.scrollSnapType = '';
		}
		track.addEventListener('mouseleave', endDrag);
		track.addEventListener('mouseup', endDrag);
		track.addEventListener('mousemove', function (e) {
			if (!isDragging) return;
			e.preventDefault();
			track.scrollLeft = dragStartScroll - (e.pageX - dragStartX);
		});

		var autoMs = parseInt(carousel.dataset.autoScroll, 10);
		if (autoMs) {
			var timer = setInterval(scrollNext, autoMs);
			carousel.addEventListener('mouseenter', function () {
				clearInterval(timer);
			});
			carousel.addEventListener('mouseleave', function () {
				timer = setInterval(scrollNext, autoMs);
			});
		}
	});

	// Deferred Disqus: only load once the reader scrolls halfway down an article
	var disqusMount = document.getElementById('disqus_thread');
	if (disqusMount) {
		var disqusLoaded = false;
		window.addEventListener(
			'scroll',
			function () {
				if (disqusLoaded) return;
				var max = document.documentElement.scrollHeight - document.documentElement.clientHeight;
				if (max > 0 && window.scrollY / max >= 0.5) {
					disqusLoaded = true;
					var s = document.createElement('script');
					s.id = 'disqus';
					s.src = 'https://businessday-ng.disqus.com/embed.js';
					s.setAttribute('data-timestamp', String(Date.now()));
					(document.head || document.body).appendChild(s);
				}
			},
			{ passive: true }
		);
	}
});
