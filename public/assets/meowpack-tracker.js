/**
 * MeowPack Tracker — non-blocking, < 3KB, injected in footer.
 * Sends visit data to the REST API endpoint without blocking page render.
 */
(function () {
	if (!window.meowpack_data) return;

	var d   = document;
	var pid = meowpack_data.post_id;

	if (!pid) return;

	// Session-based deduplication: don't track the same post twice per session.
	var sessionKey = 'mp_' + pid;
	if (sessionStorage && sessionStorage.getItem(sessionKey)) return;
	if (sessionStorage) sessionStorage.setItem(sessionKey, '1');

	// Grab UTM params from current URL.
	var url = new URL(location.href);
	var payload = {
		post_id:      parseInt(pid, 10),
		referrer:     d.referrer || '',
		utm_source:   url.searchParams.get('utm_source')   || '',
		utm_medium:   url.searchParams.get('utm_medium')   || '',
		utm_campaign: url.searchParams.get('utm_campaign') || '',
		nonce:        meowpack_data.nonce,
	};

	// Fire-and-forget: use sendBeacon if available (pagehide-safe), else fetch.
	if (navigator.sendBeacon) {
		var blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
		navigator.sendBeacon(meowpack_data.endpoint, blob);
	} else if (window.fetch) {
		fetch(meowpack_data.endpoint, {
			method:  'POST',
			headers: { 'Content-Type': 'application/json' },
			body:    JSON.stringify(payload),
			keepalive: true,
		}).catch(function () { /* silent fail */ });
	}
})();
