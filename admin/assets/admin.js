/**
 * MeowPack Admin JS
 * Handles dashboard charts (Chart.js via CDN) and dynamic top-posts reload.
 */
(function () {
	'use strict';

	// Load Chart.js from CDN lazily.
	function loadChartJs(callback) {
		if (window.Chart) {
			callback();
			return;
		}
		var s = document.createElement('script');
		s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js';
		s.onload = callback;
		document.head.appendChild(s);
	}

	// =========================================================
	// Dashboard Charts
	// =========================================================

	function initDashboardCharts() {
		var chartCanvas = document.getElementById('meowpack-chart-visitors');
		var sourceCanvas = document.getElementById('meowpack-chart-sources');

		if (!chartCanvas && !sourceCanvas) {
			return; // Not on dashboard page.
		}

		loadChartJs(function () {
			var loadingEl = document.querySelector('.meowpack-chart-loading');
			if (loadingEl) loadingEl.style.display = 'none';

			// Visitor chart (line).
			if (chartCanvas && window.meowpackChartData) {
				var labels = meowpackChartData.map(function (d) {
					return d.date.slice(5); // MM-DD.
				});
				var uvData = meowpackChartData.map(function (d) { return d.unique_visitors; });
				var pvData = meowpackChartData.map(function (d) { return d.total_views; });

				new Chart(chartCanvas, {
					type: 'line',
					data: {
						labels: labels,
						datasets: [
							{
								label: 'Pengunjung Unik',
								data: uvData,
								borderColor: '#6366f1',
								backgroundColor: 'rgba(99,102,241,.1)',
								borderWidth: 2,
								pointRadius: 3,
								tension: 0.4,
								fill: true,
							},
							{
								label: 'Pageviews',
								data: pvData,
								borderColor: '#06b6d4',
								backgroundColor: 'rgba(6,182,212,.06)',
								borderWidth: 2,
								pointRadius: 3,
								tension: 0.4,
								fill: true,
							},
						],
					},
					options: {
						responsive: true,
						interaction: { mode: 'index', intersect: false },
						plugins: {
							legend: { display: false },
							tooltip: {
								callbacks: {
									label: function (ctx) {
										return ctx.dataset.label + ': ' + ctx.parsed.y.toLocaleString('id-ID');
									},
								},
							},
						},
						scales: {
							x: {
								ticks: { font: { size: 11 }, maxTicksLimit: 10 },
								grid: { color: 'rgba(0,0,0,.04)' },
							},
							y: {
								ticks: {
									font: { size: 11 },
									callback: function (v) {
										return v >= 1000 ? (v / 1000).toFixed(1) + 'rb' : v;
									},
								},
								grid: { color: 'rgba(0,0,0,.04)' },
							},
						},
					},
				});
			}

			// Source pie chart.
			if (sourceCanvas && window.meowpackSourceData) {
				var src = window.meowpackSourceData;
				var srcLabels = { direct: 'Langsung', search: 'Pencarian', social: 'Sosial Media', referral: 'Referral', email: 'Email' };
				var srcColors = ['#6366f1', '#06b6d4', '#f59e0b', '#10b981', '#ec4899'];
				var srcKeys   = Object.keys(src);
				var srcVals   = srcKeys.map(function (k) { return src[k]; });
				var srcLbls   = srcKeys.map(function (k) { return srcLabels[k] || k; });

				new Chart(sourceCanvas, {
					type: 'doughnut',
					data: {
						labels: srcLbls,
						datasets: [{
							data: srcVals,
							backgroundColor: srcColors,
							borderWidth: 2,
							borderColor: '#fff',
						}],
					},
					options: {
						responsive: true,
						cutout: '65%',
						plugins: {
							legend: { display: false },
							tooltip: {
								callbacks: {
									label: function (ctx) {
										var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
										var pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
										return ctx.label + ': ' + ctx.parsed.toLocaleString('id-ID') + ' (' + pct + '%)';
									},
								},
							},
						},
					},
				});
			}
		});
	}


	// =========================================================
	// Share Platforms Checkbox → Hidden Input
	// =========================================================

	function initSharePlatformCheckboxes() {
		var checkboxes = document.querySelectorAll('input[name="share_platforms_check[]"]');
		var hidden = document.getElementById('share_platforms_hidden');
		if (!checkboxes.length || !hidden) return;

		function sync() {
			var checked = [];
			checkboxes.forEach(function (cb) {
				if (cb.checked) checked.push(cb.value);
			});
			hidden.value = checked.join(',');
		}
		checkboxes.forEach(function (cb) { cb.addEventListener('change', sync); });
		sync();
	}

	// =========================================================
	// Helpers
	// =========================================================

	function escHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}

	function fmtNum(n) {
		n = parseInt(n, 10);
		if (n >= 1000000) return (n / 1000000).toFixed(1).replace('.', ',') + 'jt';
		if (n >= 1000)    return (n / 1000).toFixed(1).replace('.', ',') + 'rb';
		return n.toLocaleString('id-ID');
	}

	// =========================================================
	// CSV Export
	// =========================================================

	function initExportCsv() {
		var exportBtn  = document.getElementById('meowpack-btn-export');
		var typeEl     = document.getElementById('meowpack-export-type');
		var fromEl     = document.getElementById('meowpack-export-from');
		var toEl       = document.getElementById('meowpack-export-to');
		var presets    = document.querySelectorAll('.meowpack-preset');

		if (!exportBtn || !typeEl || !fromEl || !toEl) return;

		// Preset quick-select buttons.
		presets.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var days = parseInt(this.dataset.days, 10);
				var today = new Date();
				var from  = new Date();
				from.setDate(today.getDate() - days);

				fromEl.value = from.toISOString().slice(0, 10);
				toEl.value   = today.toISOString().slice(0, 10);

				// Highlight active preset.
				presets.forEach(function (b) { b.classList.remove('is-active'); });
				btn.classList.add('is-active');
			});
		});

		// Set default active preset (30 days).
		var defaultPreset = document.querySelector('.meowpack-preset[data-days="30"]');
		if (defaultPreset) defaultPreset.classList.add('is-active');

		// Clear active preset when dates change manually.
		[fromEl, toEl].forEach(function (el) {
			el.addEventListener('change', function () {
				presets.forEach(function (b) { b.classList.remove('is-active'); });
			});
		});

		// Download handler.
		exportBtn.addEventListener('click', function () {
			var type     = typeEl.value;
			var dateFrom = fromEl.value;
			var dateTo   = toEl.value;

			if (!dateFrom || !dateTo) {
				alert('Pilih rentang tanggal terlebih dahulu.');
				return;
			}

			if (dateFrom > dateTo) {
				alert('Tanggal "Dari" tidak boleh lebih besar dari "Sampai".');
				return;
			}

			// Build download URL.
			var url = meowpackAdmin.exportUrl
				+ '&_nonce='     + encodeURIComponent(meowpackAdmin.exportNonce)
				+ '&export_type='+ encodeURIComponent(type)
				+ '&date_from='  + encodeURIComponent(dateFrom)
				+ '&date_to='    + encodeURIComponent(dateTo);

			// Trigger download via hidden link (avoids CORS issues with fetch).
			var link = document.createElement('a');
			link.href = url;
			link.download = 'meowpack-' + type + '-' + dateFrom + '-' + dateTo + '.csv';
			document.body.appendChild(link);

			exportBtn.classList.add('is-loading');
			exportBtn.textContent = '⏳ Menyiapkan...';

			link.click();
			document.body.removeChild(link);

			// Reset button after delay.
			setTimeout(function () {
				exportBtn.classList.remove('is-loading');
				exportBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Download CSV';
			}, 2000);
		});
	}

	// =========================================================
	// Boot
	// =========================================================

	document.addEventListener('DOMContentLoaded', function () {
		initDashboardCharts();
		initSharePlatformCheckboxes();
		initExportCsv();
	});
})();
