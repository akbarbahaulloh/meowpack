/**
 * MeowPack Tracker v2.0.0 — non-blocking, < 5KB, injected in footer.
 *
 * Features:
 *  1. Visit tracking (fire-and-forget, session-deduplicated).
 *  2. Engagement: actual time-on-page (30s heartbeat) + scroll depth.
 *  3. Outbound click tracking (links to external domains).
 */
(function () {
	if (!window.meowpack_data) return;

	var d    = document;
	var data = window.meowpack_data;
	var pid  = data.post_id;

	if (!pid) return;

	/* -----------------------------------------------------------------------
	 * 1. VISIT TRACKING
	 * Deduplicated per post per session via sessionStorage.
	 ----------------------------------------------------------------------- */
	var sessionKey = 'mp_' + pid;
	var alreadyTracked = sessionStorage && sessionStorage.getItem(sessionKey);

	if (!alreadyTracked) {
		if (sessionStorage) sessionStorage.setItem(sessionKey, '1');

		var url     = new URL(location.href);
		var payload = {
			post_id:      parseInt(pid, 10),
			referrer:     d.referrer || '',
			utm_source:   url.searchParams.get('utm_source')   || '',
			utm_medium:   url.searchParams.get('utm_medium')   || '',
			utm_campaign: url.searchParams.get('utm_campaign') || '',
			nonce:        data.nonce,
		};

		// Use sendBeacon if available (survives page navigation), else fetch.
		if (navigator.sendBeacon) {
			navigator.sendBeacon(
				data.endpoint,
				new Blob([JSON.stringify(payload)], { type: 'application/json' })
			);
		} else if (window.fetch) {
			fetch(data.endpoint, {
				method:    'POST',
				headers:   { 'Content-Type': 'application/json' },
				body:      JSON.stringify(payload),
				keepalive: true,
			}).catch(function () { /* silent fail */ });
		}
	}

	/* -----------------------------------------------------------------------
	 * 2. ENGAGEMENT: Time-on-page + Scroll Depth
	 * Only runs if enable_engagement is '1'.
	 ----------------------------------------------------------------------- */
	if (data.enable_engagement === '1' && data.engagement_endpoint) {
		var startTime    = Date.now();
		var activeTime   = 0;          // seconds user was active
		var maxScroll    = 0;          // highest scroll % reached
		var lastActivity = Date.now();
		var isActive     = true;
		var engagementSent = false;

		/* Scroll depth tracker */
		function updateScrollDepth() {
			var el      = d.documentElement;
			var scrolled = el.scrollTop || d.body.scrollTop;
			var total   = el.scrollHeight - el.clientHeight;
			if (total > 0) {
				var pct = Math.round((scrolled / total) * 100);
				if (pct > maxScroll) maxScroll = pct;
			}
		}

		/* Activity detection — increment activeTime every second if user is active  */
		function activityTick() {
			if (isActive && (Date.now() - lastActivity) < 30000) {
				activeTime++;
			}
		}

		function markActive() {
			lastActivity = Date.now();
			isActive = true;
		}

		d.addEventListener('scroll',    updateScrollDepth, { passive: true });
		d.addEventListener('mousemove', markActive,        { passive: true });
		d.addEventListener('keydown',   markActive,        { passive: true });
		d.addEventListener('touchstart',markActive,        { passive: true });
		updateScrollDepth();

		// Tick every second.
		var ticker = setInterval(activityTick, 1000);

		/* Send engagement data on page hide (tab close, navigate away, etc.) */
		function sendEngagement() {
			if (engagementSent) return;
			engagementSent = true;
			clearInterval(ticker);

			var engPayload = {
				post_id:      parseInt(pid, 10),
				time_on_page: Math.min(activeTime, 7200), // cap at 2 hours
				scroll_depth: Math.min(maxScroll, 100),
				nonce:        data.nonce,
			};

			// Use sendBeacon — only API that works reliably on pagehide.
			if (navigator.sendBeacon) {
				navigator.sendBeacon(
					data.engagement_endpoint,
					new Blob([JSON.stringify(engPayload)], { type: 'application/json' })
				);
			}
		}

		d.addEventListener('visibilitychange', function () {
			if (d.visibilityState === 'hidden') sendEngagement();
		});
		window.addEventListener('pagehide', sendEngagement);
	}

	/* -----------------------------------------------------------------------
	 * 3. OUTBOUND CLICK TRACKING
	 * Only runs if enable_clicks is '1'.
	 ----------------------------------------------------------------------- */
	if (data.enable_clicks === '1' && data.click_endpoint && data.site_host) {
		var siteHost = data.site_host.toLowerCase();

		d.addEventListener('click', function (e) {
			var target = e.target.closest('a[href]');
			if (!target) return;

			var href = target.getAttribute('href') || '';
			if (!href || href.charAt(0) === '#' || href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0) return;

			var linkUrl;
			try {
				linkUrl = new URL(href, location.href);
			} catch (err) {
				return;
			}

			// Only track external links.
			if (linkUrl.hostname.toLowerCase() === siteHost) return;
			if (linkUrl.hostname.toLowerCase().endsWith('.' + siteHost)) return;

			var clickPayload = {
				post_id:     parseInt(pid, 10),
				url:         linkUrl.href,
				anchor_text: (target.textContent || '').trim().substring(0, 120),
				nonce:       data.nonce,
			};

			// Non-blocking — use sendBeacon to survive link navigation.
			if (navigator.sendBeacon) {
				navigator.sendBeacon(
					data.click_endpoint,
					new Blob([JSON.stringify(clickPayload)], { type: 'application/json' })
				);
			} else if (window.fetch) {
				fetch(data.click_endpoint, {
					method:    'POST',
					headers:   { 'Content-Type': 'application/json' },
					body:      JSON.stringify(clickPayload),
					keepalive: true,
				}).catch(function () { /* silent fail */ });
			}
		}, { passive: true });
	}
})();
