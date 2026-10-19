/**
 * BDay Ad Manager — front-end loader.
 *
 * One script for every ad partner. The server renders inert containers
 * (.bday-ad) identically for every reader, which is what keeps the page
 * cacheable behind Redis/Cloudflare; this script then:
 *
 *   1. works out who the reader is (anonymous / registered / subscriber,
 *      and the plan's own ad policy) from the paywall SDK's access-token
 *      cookie,
 *   2. removes every container that reader's policy doesn't allow, BEFORE
 *      any ad network script is requested (a fully ad-free subscriber never
 *      loads GPT or AdSense at all),
 *   3. brings up the partners that are left — GAM (GPT), AdSense, pasted
 *      third-party tags, and local banners — lazily, as they near the
 *      viewport.
 *
 * Page config comes from <script id="bday-ads-config"> (render.php).
 */
(function () {
  'use strict';

  var cfgEl = document.getElementById('bday-ads-config');
  if (!cfgEl) { return; }

  var C;
  try { C = JSON.parse(cfgEl.textContent); } catch (e) { return; }

  var DEBUG = !!C.debug;
  function log() {
    if (DEBUG && window.console) { console.log.apply(console, ['[bday-ads]'].concat([].slice.call(arguments))); }
  }

  var TOKEN_COOKIE = 'ap_access_token';
  var SESSION_HINT = 'ap_session';
  var policy = null;
  var allowedCount = 0;
  var gamState = 'idle'; // idle | loading | ready
  var gamQueue = [];
  var slotInfo = {}; // elementId -> { unit, refreshes, timer }
  var lastActivity = Date.now();
  var headInjected = {};
  var adsenseLoaded = false;

  /* ── Reader state ────────────────────────────────────────────────── */

  function readCookie(name) {
    var m = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.$?*|{}()\[\]\\\/+^]/g, '\\$&') + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : null;
  }

  // Presentation-only decode: the claims decide which ads to hide, never what
  // content is revealed (the paywall verifies tokens server-side for that).
  function decodeClaims(token) {
    if (!token) { return null; }
    try {
      var part = token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/');
      while (part.length % 4) { part += '='; }
      var json = decodeURIComponent(escape(atob(part)));
      var claims = JSON.parse(json);
      if (claims.exp && claims.exp * 1000 < Date.now()) { return null; }
      return claims;
    } catch (e) { return null; }
  }

  function currentClaims() { return decodeClaims(readCookie(TOKEN_COOKIE)); }

  /**
   * The access token lives 15 minutes. A returning subscriber usually has
   * none yet — the paywall SDK silently refreshes it on page load and
   * announces 'aero:session-ready'. Wait for that (briefly), but only when
   * a session hint says there's something to wait for, so anonymous
   * readers never pay a delay.
   */
  function whenReaderKnown(done) {
    var claims = currentClaims();
    if (claims || !readCookie(SESSION_HINT)) { done(claims); return; }

    var finished = false;
    function finish() {
      if (finished) { return; }
      finished = true;
      window.removeEventListener('aero:session-ready', finish);
      done(currentClaims());
    }
    window.addEventListener('aero:session-ready', finish);
    setTimeout(finish, 2500);
  }

  function cleanPolicy(p) {
    p = p || {};
    return {
      mode: p.mode === 'none' || p.mode === 'limited' ? p.mode : 'all',
      hide: Array.isArray(p.hide) ? p.hide : [],
      max: parseInt(p.max, 10) > 0 ? parseInt(p.max, 10) : 0
    };
  }

  function policyFor(claims) {
    if (!claims) { return cleanPolicy(C.policies.anonymous); }
    if (claims.subscriptionStatus === 'active') {
      return claims.adPolicy ? cleanPolicy(claims.adPolicy) : cleanPolicy(C.policies.subscriber);
    }
    return cleanPolicy(C.policies.registered);
  }

  function readerLabel(claims) {
    if (!claims) { return 'anonymous'; }
    return claims.subscriptionStatus === 'active' ? 'subscriber' : 'registered';
  }

  function categoryAllowed(category) {
    if (policy.mode === 'none') { return false; }
    return policy.hide.indexOf(category) === -1;
  }

  /* ── Helpers ─────────────────────────────────────────────────────── */

  ['mousemove', 'scroll', 'touchstart', 'keydown'].forEach(function (evt) {
    document.addEventListener(evt, function () { lastActivity = Date.now(); }, { passive: true });
  });

  /**
   * Viewport width the ad decisions use. innerWidth alone is unsafe on phones: one element wider
   * than the screen makes mobile browsers lay the page out wider (a 390px phone reported 978px on
   * the live site), which silently turned every phone into "desktop". screen.width is the device's
   * own width, so the smaller of the two is the real viewport in every case — a genuinely narrow
   * desktop window still has innerWidth < screen.width and counts as mobile.
   */
  function adViewportWidth() {
    var w = window.innerWidth;
    var s = window.screen && window.screen.width;
    return s && s < w ? s : w;
  }

  function isMobileViewport() { return adViewportWidth() < C.bp; }

  // Mirror the decision onto <html> so CSS (device-only containers, reserved heights) follows the
  // same signal instead of its own media queries, which see the inflated layout width.
  function markDevice() {
    document.documentElement.setAttribute('data-bday-ad-device', isMobileViewport() ? 'mobile' : 'desktop');
  }

  function deviceMatches(device) {
    if (device === 'desktop') { return !isMobileViewport(); }
    if (device === 'mobile') { return isMobileViewport(); }
    return true;
  }

  function loadScript(src, attrs) {
    var s = document.createElement('script');
    s.async = true;
    s.src = src;
    Object.keys(attrs || {}).forEach(function (k) { s.setAttribute(k, attrs[k]); });
    document.head.appendChild(s);
    return s;
  }

  // innerHTML never runs <script>; rebuild each one so third-party tags execute.
  function runHtml(html, container) {
    var host = document.createElement('div');
    host.innerHTML = html;
    Array.prototype.slice.call(host.childNodes).forEach(function (node) {
      if (node.nodeName !== 'SCRIPT') { container.appendChild(node); }
    });
    Array.prototype.slice.call(host.querySelectorAll('script')).forEach(function (old) {
      var s = document.createElement('script');
      Array.prototype.slice.call(old.attributes).forEach(function (a) { s.setAttribute(a.name, a.value); });
      if (!old.src) { s.text = old.textContent; }
      container.appendChild(s);
    });
  }

  function whenNearViewport(el, fn) {
    if (!('IntersectionObserver' in window)) { fn(); return; }
    var io = new IntersectionObserver(function (entries) {
      if (entries.some(function (e) { return e.isIntersecting; })) { io.disconnect(); fn(); }
    }, { rootMargin: '300px 0px', threshold: 0 });
    io.observe(el);
  }

  function track(name, el) {
    var detail = { unit: el.getAttribute('data-unit'), placement: el.getAttribute('data-placement') };
    try { window.dispatchEvent(new CustomEvent('bday:ad-' + name, { detail: detail })); } catch (e) { /* old browser */ }
    if (typeof window.gtag === 'function') {
      window.gtag('event', 'banner_' + name, { unit_id: detail.unit, placement: detail.placement });
    }
  }

  /* ── GAM / GPT ───────────────────────────────────────────────────── */

  function ensureGpt() {
    if (gamState !== 'idle') { return; }
    gamState = 'loading';
    window.googletag = window.googletag || { cmd: [] };
    loadScript('https://securepubads.g.doubleclick.net/tag/js/gpt.js', { crossorigin: 'anonymous' });

    googletag.cmd.push(function () {
      var pubads = googletag.pubads();

      var targeting = { reader: readerState };
      if (C.targeting) {
        Object.keys(C.targeting).forEach(function (k) { targeting[k] = String(C.targeting[k]); });
      }

      // singleRequest and lazyLoad only take effect before enableServices(). If other code on the
      // page (e.g. a snippet pasted into Custom Code) already enabled services, they would be
      // ignored — say so in debug mode instead of silently running without them.
      var servicesAlreadyOn = !!googletag.pubadsReady;
      if (servicesAlreadyOn) {
        log('GPT services were already enabled by other page code — lazy loading / single request cannot be configured. Move that code into Ad Units.');
      }

      var lazy = C.lazy && C.lazy.fetch > 0
        ? { fetchMarginPercent: C.lazy.fetch, renderMarginPercent: C.lazy.render, mobileScaling: 2.0 }
        : null;

      // googletag.setConfig is GPT's current API; the pubads() setters it replaces are deprecated
      // and log a warning on every page view. Fall back for any build that predates it.
      if (typeof googletag.setConfig === 'function') {
        var cfg = { singleRequest: true, targeting: targeting };
        if (lazy) { cfg.lazyLoad = lazy; }
        googletag.setConfig(cfg);
      } else {
        pubads.enableSingleRequest();
        Object.keys(targeting).forEach(function (k) { pubads.setTargeting(k, [targeting[k]]); });
        if (lazy) { pubads.enableLazyLoad(lazy); }
      }

      pubads.addEventListener('slotRenderEnded', onSlotRendered);
      pubads.addEventListener('impressionViewable', onImpressionViewable);

      if (!servicesAlreadyOn) { googletag.enableServices(); }
      gamState = 'ready';
      log('GPT ready');
      gamQueue.splice(0).forEach(function (fn) { fn(); });
    });
  }

  function gamRun(fn) {
    if (gamState === 'ready') { fn(); } else { gamQueue.push(fn); ensureGpt(); }
  }

  function sizeList(a, b, deviceOnly, wanted) {
    if (deviceOnly && deviceOnly !== wanted && deviceOnly !== 'all') { return []; }
    return (a && a.length) ? a : (b || []);
  }

  function defineGamSlot(el, unit) {
    var slotEl = el.querySelector('.bday-ad__slot');
    if (!slotEl) { return; }
    var desktop = sizeList(unit.sizesD, unit.sizesM, unit.device, 'desktop');
    var mobile = sizeList(unit.sizesM, unit.sizesD, unit.device, 'mobile');
    var all = desktop.concat(mobile);
    if (!all.length) { log('no sizes for', unit.path); return; }

    var slot = googletag.defineSlot(unit.path, all, slotEl.id);
    if (!slot) { log('defineSlot failed', unit.path); return; }
    slot.defineSizeMapping(
      googletag.sizeMapping().addSize([C.bp, 0], desktop).addSize([0, 0], mobile).build()
    );
    if (unit.refresh) {
      if (typeof slot.setConfig === 'function') { slot.setConfig({ targeting: { refresh: 'true' } }); } else { slot.setTargeting('refresh', 'true'); }
    }
    slot.addService(googletag.pubads());
    slotInfo[slotEl.id] = { unit: unit, refreshes: 0, timer: null, el: el };
    googletag.display(slotEl.id);
    log('slot defined', slotEl.id, unit.path);
  }

  var OOP_FORMATS = {
    interstitial: 'INTERSTITIAL',
    top_anchor: 'TOP_ANCHOR',
    bottom_anchor: 'BOTTOM_ANCHOR',
    left_rail: 'LEFT_SIDE_RAIL',
    right_rail: 'RIGHT_SIDE_RAIL'
  };

  function defineOutOfPage(unit) {
    var fmt = OOP_FORMATS[unit.format];
    if (!fmt) { return; }
    // Returns null where the browser/viewport can't support the format — that's normal, not an error.
    var slot = googletag.defineOutOfPageSlot(unit.path, googletag.enums.OutOfPageFormat[fmt]);
    if (slot) {
      slot.addService(googletag.pubads());
      googletag.display(slot);
      log('out-of-page defined', unit.format, unit.path);
    }
  }

  function onSlotRendered(event) {
    var id = event.slot.getSlotElementId();
    var info = slotInfo[id];
    if (!info) { return; }
    var el = info.el;
    el.classList.toggle('is-filled', !event.isEmpty);
    el.classList.toggle('is-empty', !!event.isEmpty);
    if (event.isEmpty) {
      var fb = el.querySelector('template.bday-ad__fallback');
      if (fb) {
        el.classList.remove('is-empty');
        el.classList.add('is-filled', 'is-fallback');
        var slotEl = el.querySelector('.bday-ad__slot');
        if (slotEl) { slotEl.style.display = 'none'; }
        el.appendChild(fb.content.cloneNode(true));
        initBanner(el);
        log('fallback shown for', id);
      } else if (C.collapse) {
        el.classList.add('is-collapsed');
      }
    }
  }

  function onImpressionViewable(event) {
    var id = event.slot.getSlotElementId();
    var info = slotInfo[id];
    if (!info || !info.unit.refresh || !C.refresh || !(C.refresh.sec > 0)) { return; }
    if (info.refreshes >= C.refresh.max) { return; }
    clearTimeout(info.timer);
    info.timer = setTimeout(function () { tryRefresh(event.slot, info); }, C.refresh.sec * 1000);
  }

  // Only refresh an ad a real person is still looking at — refreshing in a
  // background tab or after the reader walked away is invalid-traffic risk.
  function tryRefresh(slot, info) {
    if (document.hidden || Date.now() - lastActivity > 120000) {
      info.timer = setTimeout(function () { tryRefresh(slot, info); }, 15000);
      return;
    }
    info.refreshes++;
    googletag.pubads().refresh([slot]);
    log('refreshed', slot.getSlotElementId(), info.refreshes);
  }

  /* ── AdSense ─────────────────────────────────────────────────────── */

  function startAdsense(el) {
    if (!C.adsense) { return; }
    if (!adsenseLoaded) {
      adsenseLoaded = true;
      loadScript('https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' + encodeURIComponent(C.adsense), { crossorigin: 'anonymous' });
    }
    whenNearViewport(el, function () {
      try { (window.adsbygoogle = window.adsbygoogle || []).push({}); } catch (e) { log('adsense push failed', e); }
    });
  }

  /**
   * Hand-pasted AdSense tags already sitting in article bodies (<ins class="adsbygoogle"> from
   * the editor). On their own they never worked here: the page loads no AdSense library, and the
   * accompanying inline script calls jQuery's $(), which the theme doesn't ship. Adopt them: they
   * follow the same reader policy as any 'display' ad, and the library is loaded from the tag's
   * own publisher ID the first time one is allowed.
   */
  function adoptStrayAdsense(root) {
    var stray = Array.prototype.filter.call(
      (root || document).querySelectorAll('ins.adsbygoogle'),
      function (el) { return !el.closest('[data-bday-ad]') && !el.getAttribute('data-bday-adopted'); }
    );
    stray.forEach(function (el) {
      el.setAttribute('data-bday-adopted', '1');
      var allowed = categoryAllowed('display') && !(policy.max && allowedCount >= policy.max);
      if (!allowed) { el.parentNode && el.parentNode.removeChild(el); log('stray AdSense removed by policy'); return; }
      allowedCount++;
      var client = el.getAttribute('data-ad-client') || C.adsense;
      if (!client) { return; }
      if (!adsenseLoaded && !document.querySelector('script[src*="adsbygoogle.js"]')) {
        adsenseLoaded = true;
        loadScript('https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' + encodeURIComponent(client), { crossorigin: 'anonymous' });
      }
      whenNearViewport(el, function () {
        if (el.getAttribute('data-adsbygoogle-status')) { return; }
        try { (window.adsbygoogle = window.adsbygoogle || []).push({}); } catch (e) { log('stray AdSense push failed', e); }
      });
    });
  }

  /* ── Third-party (pasted) tags ───────────────────────────────────── */

  function startHtml(el, unitId, unit) {
    if (unit.head && !headInjected[unitId]) {
      headInjected[unitId] = true;
      runHtml(unit.head, document.head);
    }
    var tpl = el.querySelector('template.bday-ad__html');
    var slot = el.querySelector('[data-bday-html]');
    if (!tpl || !slot) { return; }
    whenNearViewport(el, function () {
      runHtml(tpl.innerHTML, slot);
      el.classList.add('is-filled');
    });
  }

  /* ── Local banners ───────────────────────────────────────────────── */

  function initBanner(el) {
    var wrap = el.querySelector('.bday-ad__banners');
    if (!wrap || wrap.getAttribute('data-ready')) { return; }
    wrap.setAttribute('data-ready', '1');
    var items = Array.prototype.slice.call(wrap.querySelectorAll('.bday-ad__creative'));
    if (!items.length) { return; }

    // <source media="(max-width: …)"> follows the layout width; on a phone whose page was laid out
    // wider than the screen it would never match, so the mobile creative would never show.
    if (isMobileViewport()) {
      Array.prototype.forEach.call(wrap.querySelectorAll('source[media]'), function (s) { s.setAttribute('media', 'all'); });
    }

    var idx = items.length > 1 ? Math.floor(Math.random() * items.length) : 0;
    function show(i) { items.forEach(function (n, k) { n.hidden = k !== i; }); }
    show(idx);

    var every = parseInt(el.getAttribute('data-rotate'), 10);
    if (items.length > 1 && every > 0) {
      setInterval(function () {
        if (document.hidden) { return; }
        idx = (idx + 1) % items.length;
        show(idx);
      }, every * 1000);
    }
    el.classList.add('is-filled');

    if ('IntersectionObserver' in window) {
      var seen = new IntersectionObserver(function (entries) {
        if (entries.some(function (e) { return e.isIntersecting; })) { seen.disconnect(); track('view', el); }
      }, { threshold: 0.5 });
      seen.observe(el);
    }
  }

  document.addEventListener('click', function (e) {
    var a = e.target && e.target.closest ? e.target.closest('[data-bday-ad-click]') : null;
    var el = a && a.closest('[data-bday-ad]');
    if (el) { track('click', el); }
  });

  /* ── Pipeline ────────────────────────────────────────────────────── */

  var readerState = 'anonymous';

  function outsideSchedule(el) {
    var now = Math.floor(Date.now() / 1000);
    var start = parseInt(el.getAttribute('data-start'), 10);
    var end = parseInt(el.getAttribute('data-end'), 10);
    return (start && start > now) || (end && end <= now);
  }

  function dropEl(el, why) {
    log('removed', el.getAttribute('data-unit'), why);
    if (el.parentNode) { el.parentNode.removeChild(el); }
  }

  function processContainer(el) {
    if (el.getAttribute('data-bday-ad-done')) { return; }
    el.setAttribute('data-bday-ad-done', '1');

    var unitId = el.getAttribute('data-unit');
    var unit = C.units[unitId];
    if (!unit) { dropEl(el, 'unknown unit'); return; }

    if (!deviceMatches(unit.device)) { dropEl(el, 'device'); return; }

    // Local banners are editor-placed house/direct-sold creative and are shown to EVERYONE:
    // anonymous, registered and every subscriber plan. Reader policy (ad-free, hidden
    // categories, the per-page cap) applies only to third-party ad partners. A banner's own
    // schedule is the only thing that can hide it.
    if (unit.partner === 'banner') {
      if (outsideSchedule(el)) { dropEl(el, 'schedule'); return; }
      initBanner(el);
      return;
    }

    if (!categoryAllowed(unit.category)) { dropEl(el, 'policy:category'); return; }
    if (policy.max && allowedCount >= policy.max) { dropEl(el, 'policy:max'); return; }

    allowedCount++;
    switch (unit.partner) {
      case 'gam': gamRun(function () { defineGamSlot(el, unit); }); break;
      case 'adsense': startAdsense(el); break;
      case 'html': startHtml(el, unitId, unit); break;
    }
  }

  function scan(root) {
    if (!policy) { return; }
    var list = (root || document).querySelectorAll('[data-bday-ad]');
    Array.prototype.forEach.call(list, processContainer);
    adoptStrayAdsense(root || document);
  }

  function startOutOfPage() {
    Object.keys(C.units).forEach(function (id) {
      var u = C.units[id];
      if (u.partner !== 'gam' || !u.format || u.format === 'standard') { return; }
      if (!categoryAllowed(u.category) || !deviceMatches(u.device)) { return; }
      gamRun(function () { defineOutOfPage(u); });
    });
  }

  function start(claims) {
    markDevice();
    policy = policyFor(claims);
    readerState = readerLabel(claims);
    log('reader', readerState, 'policy', policy);

    if (policy.mode === 'none') {
      scan(document); // removes every container (and stray AdSense tags); nothing is loaded
      document.documentElement.classList.add('bday-ads-off');
      return;
    }
    scan(document);
    startOutOfPage();
  }

  window.bdayAds = { scan: scan };

  if (document.getElementById('no-ads')) {
    Array.prototype.forEach.call(document.querySelectorAll('[data-bday-ad]'), function (el) { dropEl(el, 'no-ads'); });
    return;
  }

  whenReaderKnown(start);
})();
