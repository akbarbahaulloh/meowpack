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

	// =========================================================
	// Dashboard Charts (Pro Version)
	// =========================================================

	var vChart = null;

	function initDashboardCharts() {
		var chartCanvas = document.getElementById('meowpack-chart-visitors');
		if (!chartCanvas || !window.meowpackChartData) return;

		loadChartJs(function () {
			var ctx = chartCanvas.getContext('2d');
			
			// Create Gradients.
			var gradPV = ctx.createLinearGradient(0, 0, 0, 300);
			gradPV.addColorStop(0, 'rgba(59, 130, 246, 0.2)');
			gradPV.addColorStop(1, 'rgba(59, 130, 246, 0)');

			var gradUV = ctx.createLinearGradient(0, 0, 0, 300);
			gradUV.addColorStop(0, 'rgba(37, 99, 235, 0.1)');
			gradUV.addColorStop(1, 'rgba(37, 99, 235, 0)');

			var labels = meowpackChartData.map(function (d) { return d.date.slice(5); });
			var pvData = meowpackChartData.map(function (d) { return d.total_views; });
			var uvData = meowpackChartData.map(function (d) { return d.unique_visitors; });

			vChart = new Chart(chartCanvas, {
				type: 'line',
				data: {
					labels: labels,
					datasets: [
						{
							label: 'Pageviews',
							data: pvData,
							borderColor: '#3b82f6',
							backgroundColor: gradPV,
							fill: true,
							tension: 0.3,
							pointRadius: 0,
							pointHoverRadius: 4,
							borderWidth: 2
						},
						{
							label: 'Pengunjung',
							data: uvData,
							borderColor: '#1d4ed8',
							backgroundColor: gradUV,
							fill: true,
							tension: 0.3,
							pointRadius: 0,
							pointHoverRadius: 4,
							borderWidth: 2
						}
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					interaction: { mode: 'index', intersect: false },
					plugins: {
						legend: { display: false },
						tooltip: {
							backgroundColor: '#1e293b',
							padding: 12,
							titleFont: { size: 13, weight: 'bold' },
							bodyFont: { size: 12 },
							cornerRadius: 8,
							callbacks: {
								label: function(ctx) {
									return ctx.dataset.label + ': ' + ctx.parsed.y.toLocaleString('id-ID');
								}
							}
						}
					},
					scales: {
						x: { ticks: { color: '#94a3b8', font: { size: 10 } }, grid: { display: false } },
						y: { beginAtZero: true, ticks: { color: '#94a3b8', font: { size: 10 } }, grid: { color: '#f1f5f9' } }
					}
				}
			});

			initChartControls();
		});
	}

	function initChartControls() {
		var select = document.getElementById('meowpack-chart-days');
		var togglePV = document.getElementById('toggle-pv');
		var toggleUV = document.getElementById('toggle-uv');

		if (!select) return;

		// Period change handler.
		select.addEventListener('change', function() {
			var days = this.value;
			var container = document.querySelector('.meowpack-chart-container');
			container.style.opacity = '0.5';

			fetch(meowpackAdmin.apiBase + 'stats?type=chart&days=' + days, {
				headers: { 'X-WP-Nonce': meowpackAdmin.nonce }
			})
			.then(r => r.json())
			.then(data => {
				container.style.opacity = '1';
				if (!data.chart || !vChart) return;

				// Update Chart.
				vChart.data.labels = data.chart.map(d => d.date.slice(5));
				vChart.data.datasets[0].data = data.chart.map(d => d.total_views);
				vChart.data.datasets[1].data = data.chart.map(d => d.unique_visitors);
				vChart.update();

				// Update Metrics Grid.
				document.getElementById('metric-pv-val').textContent = fmtNum(data.totals.pv);
				document.getElementById('metric-uv-val').textContent = fmtNum(data.totals.uv);
			});
		});

		// Toggle line visibility.
		[togglePV, toggleUV].forEach((el, idx) => {
			if (!el) return;
			el.addEventListener('change', function() {
				vChart.setDatasetVisibility(idx, this.checked);
				vChart.update();
			});
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
