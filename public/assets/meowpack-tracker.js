/**
 * MeowPack Tracker v2.2.1 — non-blocking, < 5KB, injected in footer.
 *
 * Features:
 *  1. Visit tracking via AJAX (fire-and-forget, session-deduplicated).
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
	 * 1. VISIT TRACKING (AJAX)
	 * Always tracks on page load (mimics Top-10 plugin behavior)
	 ----------------------------------------------------------------------- */
	var alreadyTracked = false; // Disabled session deduplication per user request

	if (!alreadyTracked) {
		var url     = new URL(location.href);
		var payload = {
			action:        'meowpack_track',
			post_id:       parseInt(pid, 10),
			referrer:      d.referrer || '',
			utm_source:    url.searchParams.get('utm_source')   || '',
			utm_medium:    url.searchParams.get('utm_medium')   || '',
			utm_campaign:  url.searchParams.get('utm_campaign') || '',
		};

		// Use jQuery.post if jQuery is available, else fetch.
		if (window.jQuery) {
			jQuery.post(data.ajax_url, payload).fail(function () { /* silent fail */ });
		} else if (window.fetch) {
			// Fallback to fetch if jQuery not available.
			var formData = new FormData();
			for (var key in payload) {
				if (payload.hasOwnProperty(key)) {
					formData.append(key, payload[key]);
				}
			}
			fetch(data.ajax_url, {
				method:    'POST',
				body:      formData,
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
