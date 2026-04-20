<?php
/**
 * Admin view: Jetpack Importer page.
 *
 * @package MeowPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$importer     = MeowPack_Core::get_instance()->importer;
$has_jetpack  = $importer->has_jetpack_data();
?>
<div class="wrap meowpack-admin" id="meowpack-importer">
	<h1><?php esc_html_e( '📥 Import Data Jetpack', 'meowpack' ); ?></h1>

	<p class="meowpack-intro">
		<?php esc_html_e( 'Import statistik dari Jetpack atau file CSV ekspor WordPress.com. Data yang sudah ada tidak akan ditimpa.', 'meowpack' ); ?>
	</p>

	<div class="meowpack-row">

		<!-- Jetpack Import -->
		<div class="meowpack-section meowpack-section--half">
			<h2>🔌 <?php esc_html_e( 'Import dari Jetpack', 'meowpack' ); ?></h2>

			<?php if ( $has_jetpack ) : ?>
			<div class="meowpack-status meowpack-status--ok">
				✅ <?php esc_html_e( 'Data Jetpack terdeteksi di situs ini.', 'meowpack' ); ?>
			</div>
			<p><?php esc_html_e( 'Klik tombol di bawah untuk mulai mengimpor. Proses dilakukan per batch 200 baris.', 'meowpack' ); ?></p>
			<button id="meowpack-import-jetpack" class="button button-primary button-large">
				<?php esc_html_e( '🚀 Mulai Import Jetpack', 'meowpack' ); ?>
			</button>
			<?php else : ?>
			<div class="meowpack-status meowpack-status--warn">
				⚠️ <?php esc_html_e( 'Tidak ada data Jetpack yang terdeteksi.', 'meowpack' ); ?>
			</div>
			<p><?php esc_html_e( 'Jetpack mungkin belum pernah aktif, atau data sudah dihapus. Gunakan import CSV sebagai alternatif.', 'meowpack' ); ?></p>
			<?php endif; ?>
		</div>

		<!-- CSV Import -->
		<div class="meowpack-section meowpack-section--half">
			<h2>📄 <?php esc_html_e( 'Import dari CSV', 'meowpack' ); ?></h2>
			<p>
				<?php esc_html_e( 'Upload file CSV dengan format:', 'meowpack' ); ?>
				<code>date, post_id, views</code>
				<?php esc_html_e( 'atau', 'meowpack' ); ?>
				<code>date, views</code>
			</p>
			<p class="description"><?php esc_html_e( 'Baris pertama (header) akan dilewati.', 'meowpack' ); ?></p>

			<label for="meowpack-csv-file" class="meowpack-upload-label">
				<input type="file" id="meowpack-csv-file" accept=".csv" style="display:none;">
				<span class="button"><?php esc_html_e( '📂 Pilih File CSV', 'meowpack' ); ?></span>
				<span id="meowpack-csv-filename" style="margin-left:8px;color:#666;"><?php esc_html_e( 'Belum ada file dipilih', 'meowpack' ); ?></span>
			</label>

			<br><br>
			<button id="meowpack-import-csv" class="button button-secondary button-large" disabled>
				<?php esc_html_e( '📤 Mulai Import CSV', 'meowpack' ); ?>
			</button>
		</div>
	</div>

	<!-- Progress Bar -->
	<div id="meowpack-import-progress" style="display:none;">
		<div class="meowpack-progress-wrap">
			<div class="meowpack-progress-bar" id="meowpack-progress-bar"></div>
		</div>
		<div id="meowpack-progress-text" class="meowpack-progress-text">
			<?php esc_html_e( 'Memulai...', 'meowpack' ); ?>
		</div>
		<ul id="meowpack-import-log" class="meowpack-import-log"></ul>
	</div>

	<!-- Result -->
	<div id="meowpack-import-result" style="display:none;" class="meowpack-section"></div>

</div>

<script>
/* Import logic — runs after admin.js is loaded */
document.addEventListener('DOMContentLoaded', function () {
	var csvFileEl  = document.getElementById('meowpack-csv-file');
	var csvNameEl  = document.getElementById('meowpack-csv-filename');
	var csvBtnEl   = document.getElementById('meowpack-import-csv');
	var jpBtnEl    = document.getElementById('meowpack-import-jetpack');
	var progressEl = document.getElementById('meowpack-import-progress');
	var barEl      = document.getElementById('meowpack-progress-bar');
	var textEl     = document.getElementById('meowpack-progress-text');
	var logEl      = document.getElementById('meowpack-import-log');
	var resultEl   = document.getElementById('meowpack-import-result');

	if (csvFileEl) {
		csvFileEl.addEventListener('change', function () {
			if (this.files.length) {
				csvNameEl.textContent = this.files[0].name;
				csvBtnEl.disabled = false;
			}
		});
		document.querySelector('label[for="meowpack-csv-file"]').addEventListener('click', function(e) {
			if (e.target.tagName !== 'SPAN') return;
			csvFileEl.click();
		});
	}

	function runImport(source, offset, total, file) {
		progressEl.style.display = 'block';
		textEl.textContent = meowpackAdmin.strings.importing + ' (' + offset + ' baris diproses)';

		var body = JSON.stringify({ source: source, offset: offset, file: file || '' });

		fetch(meowpackAdmin.apiBase + 'import', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': meowpackAdmin.nonce
			},
			body: body
		})
		.then(function(r) { return r.json(); })
		.then(function(data) {
			if (data.error) {
				resultEl.innerHTML = '<div class="notice notice-error"><p>' + data.error + '</p></div>';
				resultEl.style.display = 'block';
				return;
			}

			var li = document.createElement('li');
			li.textContent = 'Batch: +' + data.imported + ' diimpor, ' + data.skipped + ' dilewati (total offset: ' + data.offset + ')';
			logEl.appendChild(li);

			if (data.done) {
				barEl.style.width = '100%';
				textEl.textContent = meowpackAdmin.strings.done;
				resultEl.innerHTML = '<div class="notice notice-success"><p><strong>Import selesai!</strong> Total diimpor: ' + (total + data.imported) + ' baris.</p></div>';
				resultEl.style.display = 'block';
			} else {
				var newOffset = data.offset;
				var pct = Math.min(95, Math.round(newOffset / (newOffset + 200) * 100));
				barEl.style.width = pct + '%';
				runImport(source, newOffset, total + data.imported, file);
			}
		})
		.catch(function(e) {
			textEl.textContent = meowpackAdmin.strings.error + ' ' + e.message;
		});
	}

	if (jpBtnEl) {
		jpBtnEl.addEventListener('click', function() {
			logEl.innerHTML = '';
			resultEl.style.display = 'none';
			barEl.style.width = '0%';
			runImport('jetpack', 0, 0, '');
		});
	}

	if (csvBtnEl) {
		csvBtnEl.addEventListener('click', function() {
			if (!csvFileEl.files.length) return;
			var formData = new FormData();
			formData.append('csv', csvFileEl.files[0]);
			// Upload file first, then import.
			var reader = new FileReader();
			reader.onload = function(e) {
				// For demo: use inline content — in production, upload via media or tmp.
				alert('Import CSV via file upload memerlukan server PHP untuk menyimpan file sementara. Fitur ini tersedia melalui REST endpoint /import dengan parameter file/path.');
			};
			reader.readAsText(csvFileEl.files[0]);
		});
	}
});
</script>
