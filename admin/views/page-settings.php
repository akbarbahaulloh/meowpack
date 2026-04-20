<?php
/**
 * Admin view: General Settings page.
 *
 * @package MeowPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$saved = isset( $_GET['saved'] ) && '1' === $_GET['saved']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$s = function( $key, $default = '' ) {
	return MeowPack_Database::get_setting( $key, $default );
};
?>
<div class="wrap meowpack-admin" id="meowpack-settings">
	<h1><?php esc_html_e( '⚙️ Pengaturan MeowPack', 'meowpack' ); ?></h1>

	<?php if ( $saved ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Pengaturan berhasil disimpan.', 'meowpack' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="">
		<?php wp_nonce_field( 'meowpack_settings_save', 'meowpack_settings_nonce' ); ?>

		<!-- Module Toggles -->
		<div class="meowpack-settings-section">
			<h2><?php esc_html_e( 'Modul Aktif', 'meowpack' ); ?></h2>
			<table class="form-table meowpack-form-table">
				<tr>
					<th><?php esc_html_e( 'Pelacakan Pengunjung', 'meowpack' ); ?></th>
					<td><label><input type="checkbox" name="enable_tracking" value="1" <?php checked( '1', $s( 'enable_tracking', '1' ) ); ?>> <?php esc_html_e( 'Aktifkan pelacakan kunjungan', 'meowpack' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'View Counter', 'meowpack' ); ?></th>
					<td><label><input type="checkbox" name="enable_view_counter" value="1" <?php checked( '1', $s( 'enable_view_counter', '1' ) ); ?>> <?php esc_html_e( 'Tampilkan jumlah tampilan di artikel', 'meowpack' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Tombol Share', 'meowpack' ); ?></th>
					<td><label><input type="checkbox" name="enable_share_buttons" value="1" <?php checked( '1', $s( 'enable_share_buttons', '1' ) ); ?>> <?php esc_html_e( 'Tampilkan tombol share di artikel', 'meowpack' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Auto Share', 'meowpack' ); ?></th>
					<td><label><input type="checkbox" name="enable_autoshare" value="1" <?php checked( '1', $s( 'enable_autoshare', '0' ) ); ?>> <?php esc_html_e( 'Share otomatis saat post dipublish', 'meowpack' ); ?></label></td>
				</tr>
			</table>
		</div>

		<!-- Frontend Enhancements -->
		<div class="meowpack-settings-section">
			<h2><?php esc_html_e( 'Tampilan Frontend', 'meowpack' ); ?></h2>
			<table class="form-table meowpack-form-table">
				<tr>
					<th><?php esc_html_e( 'Info Artikel (Views & Estimasi Baca)', 'meowpack' ); ?></th>
					<td>
						<select name="show_post_meta_bar">
							<option value="top" <?php selected( $s( 'show_post_meta_bar', 'top' ), 'top' ); ?>><?php esc_html_e( 'Otomatis di Atas Artikel', 'meowpack' ); ?></option>
							<option value="bottom" <?php selected( $s( 'show_post_meta_bar', 'top' ), 'bottom' ); ?>><?php esc_html_e( 'Otomatis di Bawah Artikel', 'meowpack' ); ?></option>
							<option value="hidden" <?php selected( $s( 'show_post_meta_bar', 'top' ), 'hidden' ); ?>><?php esc_html_e( 'Sembunyikan', 'meowpack' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Menampilkan: "📈 1.250 Dilihat • ⏱️ Estimasi Baca: 3 Menit".', 'meowpack' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Table of Contents (Daftar Isi)', 'meowpack' ); ?></th>
					<td>
						<select name="show_toc">
							<option value="auto" <?php selected( $s( 'show_toc', 'auto' ), 'auto' ); ?>><?php esc_html_e( 'Otomatis sebelum Heading (H2) pertama', 'meowpack' ); ?></option>
							<option value="manual" <?php selected( $s( 'show_toc', 'auto' ), 'manual' ); ?>><?php esc_html_e( 'Manual via Shortcode [meow_toc]', 'meowpack' ); ?></option>
							<option value="hidden" <?php selected( $s( 'show_toc', 'auto' ), 'hidden' ); ?>><?php esc_html_e( 'Sembunyikan', 'meowpack' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Akan di-generate otomatis dari struktur heading artikel Anda.', 'meowpack' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Related Posts', 'meowpack' ); ?></th>
					<td>
						<label><input type="checkbox" name="enable_related_posts" value="1" <?php checked( '1', $s( 'enable_related_posts', '1' ) ); ?>> <?php esc_html_e( 'Otomatis tampilkan 3 artikel terkait di akhir paragraf', 'meowpack' ); ?></label>
					</td>
				</tr>
			</table>
		</div>

		<!-- Tracking Settings -->
		<div class="meowpack-settings-section">
			<h2><?php esc_html_e( 'Pengaturan Pelacakan', 'meowpack' ); ?></h2>
			<table class="form-table meowpack-form-table">
				<tr>
					<th><?php esc_html_e( 'Kecualikan Admin', 'meowpack' ); ?></th>
					<td><label><input type="checkbox" name="exclude_admins" value="1" <?php checked( '1', $s( 'exclude_admins', '1' ) ); ?>> <?php esc_html_e( 'Jangan hitung kunjungan administrator', 'meowpack' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Jenis Konten Dilacak', 'meowpack' ); ?></th>
					<td>
						<input type="text" name="track_post_types" class="regular-text" value="<?php echo esc_attr( $s( 'track_post_types', 'post,page' ) ); ?>">
						<p class="description"><?php esc_html_e( 'Pisahkan dengan koma. Contoh: post,page,custom_type', 'meowpack' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Retensi Data Mentah', 'meowpack' ); ?></th>
					<td>
						<input type="number" name="data_retention_days" min="7" max="365" value="<?php echo esc_attr( $s( 'data_retention_days', '30' ) ); ?>">
						<span><?php esc_html_e( 'hari', 'meowpack' ); ?></span>
						<p class="description"><?php esc_html_e( 'Data mentah dihapus setelah N hari. Data agregasi dipertahankan selamanya.', 'meowpack' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Share Button Settings -->
		<div class="meowpack-settings-section">
			<h2><?php esc_html_e( 'Tombol Share', 'meowpack' ); ?></h2>
			<table class="form-table meowpack-form-table">
				<tr>
					<th><?php esc_html_e( 'Posisi Tombol', 'meowpack' ); ?></th>
					<td>
						<select name="share_button_position">
							<?php
							$pos_options = array(
								'after'  => __( 'Setelah konten', 'meowpack' ),
								'before' => __( 'Sebelum konten', 'meowpack' ),
								'both'   => __( 'Sebelum dan sesudah', 'meowpack' ),
								'none'   => __( 'Nonaktif (gunakan shortcode)', 'meowpack' ),
							);
							$current_pos = $s( 'share_button_position', 'after' );
							foreach ( $pos_options as $val => $label ) :
							?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current_pos, $val ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Gaya Tombol', 'meowpack' ); ?></th>
					<td>
						<select name="share_button_style">
							<?php
							$style_options = array(
								'icon-text'    => __( 'Ikon + Teks', 'meowpack' ),
								'icon-only'    => __( 'Ikon Saja', 'meowpack' ),
								'pill-button'  => __( 'Pill Button', 'meowpack' ),
							);
							$current_style = $s( 'share_button_style', 'icon-text' );
							foreach ( $style_options as $val => $label ) :
							?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current_style, $val ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Platform Ditampilkan', 'meowpack' ); ?></th>
					<td>
						<?php
						$all_platforms  = array( 'facebook', 'twitter', 'whatsapp', 'telegram', 'linkedin', 'bluesky', 'threads', 'pinterest', 'line' );
						$platform_labels = array(
							'facebook'  => 'Facebook',
							'twitter'   => 'X (Twitter)',
							'whatsapp'  => 'WhatsApp',
							'telegram'  => 'Telegram',
							'linkedin'  => 'LinkedIn',
							'bluesky'   => 'Bluesky',
							'threads'   => 'Threads',
							'pinterest' => 'Pinterest',
							'line'      => 'Line',
						);
						$active_platforms = array_map( 'trim', explode( ',', $s( 'share_platforms', 'facebook,twitter,telegram,whatsapp' ) ) );
						foreach ( $all_platforms as $platform ) :
						?>
						<label style="display:inline-block;margin-right:12px;margin-bottom:6px;">
							<input type="checkbox" name="share_platforms_check[]" value="<?php echo esc_attr( $platform ); ?>" <?php checked( in_array( $platform, $active_platforms, true ) ); ?>>
							<?php echo esc_html( $platform_labels[ $platform ] ); ?>
						</label>
						<?php endforeach; ?>
						<input type="hidden" name="share_platforms" id="share_platforms_hidden" value="<?php echo esc_attr( $s( 'share_platforms', 'facebook,twitter,telegram,whatsapp' ) ); ?>">
						<p class="description"><?php esc_html_e( 'Centang platform yang ingin ditampilkan.', 'meowpack' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Number Format -->
		<div class="meowpack-settings-section">
			<h2><?php esc_html_e( 'Format Tampilan', 'meowpack' ); ?></h2>
			<table class="form-table meowpack-form-table">
				<tr>
					<th><?php esc_html_e( 'Format Angka', 'meowpack' ); ?></th>
					<td>
						<select name="number_format">
							<option value="id" <?php selected( $s( 'number_format', 'id' ), 'id' ); ?>><?php esc_html_e( 'Indonesia (1,2rb / 1,5jt)', 'meowpack' ); ?></option>
							<option value="en" <?php selected( $s( 'number_format', 'id' ), 'en' ); ?>><?php esc_html_e( 'Inggris (1.2K / 1.5M)', 'meowpack' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<!-- Auto Share Global Settings -->
		<div class="meowpack-settings-section">
			<h2><?php esc_html_e( 'Auto Share — Pengaturan Global', 'meowpack' ); ?></h2>
			<table class="form-table meowpack-form-table">
				<tr>
					<th><?php esc_html_e( 'Platform Default', 'meowpack' ); ?></th>
					<td>
						<input type="text" name="autoshare_platforms" class="regular-text" value="<?php echo esc_attr( $s( 'autoshare_platforms', 'telegram' ) ); ?>">
						<p class="description"><?php esc_html_e( 'Pisahkan dengan koma. Bisa di-override per-post.', 'meowpack' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Delay Share', 'meowpack' ); ?></th>
					<td>
						<input type="number" name="autoshare_delay_hours" min="0" max="72" value="<?php echo esc_attr( $s( 'autoshare_delay_hours', '0' ) ); ?>">
						<span><?php esc_html_e( 'jam setelah publish (0 = langsung)', 'meowpack' ); ?></span>
					</td>
				</tr>
			</table>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary button-hero" id="meowpack-save-settings">
				<?php esc_html_e( 'Simpan Pengaturan', 'meowpack' ); ?>
			</button>
		</p>
	</form>

	<hr style="margin: 40px 0; border: 0; border-top: 1px solid #ddd;">

	<!-- Data Migration section (Relocated from sidebar) -->
	<div class="meowpack-settings-section" id="meowpack-migration">
		<h2><?php esc_html_e( '📦 Migrasi Data', 'meowpack' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Pindahkan statistik Anda dari Jetpack atau import dari file CSV ekspor WordPress.com.', 'meowpack' ); ?>
		</p>

		<div class="meowpack-row" style="display:flex; gap:20px; margin-top:20px;">
			<!-- Jetpack API Block -->
			<div style="flex:1; padding:20px; background:#fff; border:1px solid #ddd; border-radius:8px;">
				<h3>🔌 <?php esc_html_e( 'Direct API Scraper', 'meowpack' ); ?></h3>
				<?php 
				$jp_active = class_exists( 'Jetpack_Options' ) && Jetpack_Options::get_option('id');
				if ( $jp_active ) : 
				?>
					<div style="color:#22c55e; font-weight:600; margin-bottom:10px;">✅ <?php esc_html_e( 'Koneksi Jetpack Aktif!', 'meowpack' ); ?></div>
					<p class="description" style="margin-bottom:15px; font-size:12px;">Mode Pratinjau: Cek Data Mentah sebelum menyimpannya.</p>

					<div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:15px;">
						<button type="button" class="button btn-api-preview" data-step="0"><?php esc_html_e( '👁️ P1. Kunjungan Harian', 'meowpack' ); ?></button>
						<button type="button" class="button btn-api-preview" data-step="1"><?php esc_html_e( '👁️ P2. Artikel (top-posts)', 'meowpack' ); ?></button>
						<button type="button" class="button btn-api-preview" data-step="2"><?php esc_html_e( '👁️ P3. Sumber Referrer', 'meowpack' ); ?></button>
						<button type="button" class="button btn-api-preview" data-step="3"><?php esc_html_e( '👁️ P4. Negara', 'meowpack' ); ?></button>
						<button type="button" class="button btn-api-preview" data-step="4"><?php esc_html_e( '👁️ P5. Kata Kunci', 'meowpack' ); ?></button>
						<button type="button" class="button btn-api-preview" data-step="5"><?php esc_html_e( '👁️ P6. Klik Keluar', 'meowpack' ); ?></button>
						<button type="button" class="button btn-api-preview" data-step="6"><?php esc_html_e( '👁️ P7. Artikel (post-views)', 'meowpack' ); ?></button>
					</div>

					<div id="meowpack-api-preview-box" style="display:none; margin-bottom:15px;">
						<pre id="meowpack-api-preview-content" style="background:#1e293b; color:#e2e8f0; padding:15px; border-radius:6px; max-height:250px; overflow-y:auto; font-size:11px;"></pre>
						<button type="button" id="meowpack-import-api-confirm" class="button button-primary" style="margin-top:10px;">
							<?php esc_html_e( '💾 Konfirmasi & Impor Data Ini', 'meowpack' ); ?>
						</button>
					</div>
					
				<?php else : ?>
					<div style="color:#ef4444; margin-bottom:10px;">❌ <?php esc_html_e( 'Koneksi Jetpack Terputus.', 'meowpack' ); ?></div>
					<p class="description" style="font-size:12px;"><?php esc_html_e( 'Plugin Jetpack harus aktif untuk dapat meretas jalur API-nya. Gunakan Impor CSV jika Anda sudah menghapus Jetpack.', 'meowpack' ); ?></p>
				<?php endif; ?>
			</div>

			<!-- CSV Block -->
			<div style="flex:1; padding:20px; background:#fff; border:1px solid #ddd; border-radius:8px;">
				<h3>📄 <?php esc_html_e( 'Import via CSV', 'meowpack' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Sistem otomatis mendeteksi kolom Date, Views, Visitors, dan Post ID.', 'meowpack' ); ?></p>
				<input type="file" id="meowpack-csv-file" accept=".csv" style="display:none;">
				<button type="button" class="button" onclick="document.getElementById('meowpack-csv-file').click()"><?php esc_html_e( '📂 Pilih File CSV', 'meowpack' ); ?></button>
				<span id="meowpack-csv-filename" style="margin-left:8px; font-size:12px; color:#64748b;"></span>
				<br><br>
				<button type="button" id="meowpack-import-csv" class="button button-secondary" disabled>
					<?php esc_html_e( '📤 Proses CSV', 'meowpack' ); ?>
				</button>
			</div>
		</div>

		<!-- Progress / Logs -->
		<div id="meowpack-import-status" style="display:none; margin-top:30px; padding:20px; background:#fff; border:1px solid #ddd; border-radius:8px;">
			<div style="font-weight:600; margin-bottom:10px;" id="import-msg"></div>
			<div style="height:8px; background:#e2e8f0; border-radius:4px; overflow:hidden;">
				<div id="import-progress-bar" style="width:0; height:100%; background:#3b82f6; transition: width 0.3s ease;"></div>
			</div>
			<ul id="import-log" style="max-height:150px; overflow-y:auto; font-family:monospace; font-size:12px; margin-top:20px; color:#475569; border-top:1px solid #eee; padding-top:10px;"></ul>
		</div>
	</div>

	<script>
	jQuery(document).ready(function($) {
		const csvFileEl = $('#meowpack-csv-file');
		const csvNameEl = $('#meowpack-csv-filename');
		const csvBtnEl  = $('#meowpack-import-csv');
		const prevBtns  = $('.btn-api-preview');
		const confirmBtn= $('#meowpack-import-api-confirm');
		const prevBox   = $('#meowpack-api-preview-box');
		const prevCont  = $('#meowpack-api-preview-content');
		const statusEl  = $('#meowpack-import-status');
		const logEl     = $('#import-log');
		const msgEl     = $('#import-msg');
		const barEl     = $('#import-progress-bar');

		csvFileEl.on('change', function() {
			if (this.files.length) {
				csvNameEl.text(this.files[0].name);
				csvBtnEl.prop('disabled', false);
			}
		});

		function runImport(source, offset, total_imported) {
			statusEl.fadeIn();
			msgEl.text(`Memproses... (Offset: ${offset})`);

			let formData = new FormData();
			formData.append('source', source);
			formData.append('offset', offset);
			
			// Attach file only on the first request to save bandwidth
			if (offset === 0 && csvFileEl[0].files.length) {
				formData.append('file', csvFileEl[0].files[0]);
			}

			$.ajax({
				url: meowpackAdmin.apiBase + 'import',
				method: 'POST',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', meowpackAdmin.nonce);
				},
				data: formData,
				processData: false,
				contentType: false,
				success: function(res) {
					if (res.error) {
						msgEl.html('<span style="color:#ef4444;">❌ Error: ' + res.error + '</span>');
						return;
					}

					let imported = res.imported || 0;
					total_imported += imported;
					logEl.prepend(`<li>Batch Selesai: +${imported} baris (${res.skipped} dilewati)</li>`);
					
					if (res.done || res.success === false) {
						barEl.css('width', '100%');
						msgEl.html(`<span style="color:#22c55e;">✅ Migrasi Selesai! Total ${total_imported} data berhasil diimpor/diupdate.</span>`);
						csvBtnEl.prop('disabled', false).text('📤 Proses CSV');
						if (confirmBtn.length) confirmBtn.prop('disabled', false).text('💾 Konfirmasi & Impor Data Ini');
					} else {
						let pct = Math.min(95, Math.round((res.offset) / (res.offset + 1000) * 100)); 
						barEl.css('width', pct + '%');
						runImport(source, res.offset, total_imported);
					}
				},
				error: function(xhr) {
					msgEl.html('<span style="color:#ef4444;">❌ Server Error. Silakan hubungi admin.</span>');
				}
			});
		}

		csvBtnEl.on('click', function() {
			const file = csvFileEl[0].files[0];
			if (!file) return;

			$(this).prop('disabled', true).text('⏳ Mengunggah...');
			logEl.empty();
			
			// Run import, file will be pulled directly from the input element inside the function
			runImport('csv', 0, 0);
		});

		if (prevBtns.length) {
			let currentPreviewStep = 0;

			prevBtns.on('click', function() {
				const btn = $(this);
				currentPreviewStep = btn.data('step');
				
				prevBtns.prop('disabled', true);
				btn.text('⏳ Mengambil Data...');
				prevBox.hide();
				
				$.ajax({
					url: meowpackAdmin.apiBase + 'import',
					type: 'POST',
					beforeSend: function(xhr) {
						xhr.setRequestHeader('X-WP-Nonce', meowpackAdmin.nonce);
					},
					data: { source: 'api_preview', offset: currentPreviewStep },
					success: function(res) {
						if (res.preview) {
							prevCont.text(res.preview);
							prevBox.fadeIn();
						}
						btn.text('👁️ P' + (currentPreviewStep + 1) + ' Selesai');
						prevBtns.prop('disabled', false);
					},
					error: function() {
						alert('Gagal mengambil data pratinjau API.');
						prevBtns.prop('disabled', false);
					}
				});
			});

			confirmBtn.on('click', function() {
				$(this).prop('disabled', true).text('⏳ Menyimpan ke DB...');
				logEl.empty();
				statusEl.fadeIn();
				
				// Run import for ONLY this specific step instead of loop
				$.ajax({
					url: meowpackAdmin.apiBase + 'import',
					type: 'POST',
					beforeSend: function(xhr) {
						xhr.setRequestHeader('X-WP-Nonce', meowpackAdmin.nonce);
					},
					data: { source: 'api', offset: currentPreviewStep },
					success: function(res) {
						confirmBtn.text('✅ Tersimpan!');
						let time = new Date().toLocaleTimeString();
						logEl.prepend(`<li>[${time}] ${res.message} (+${res.imported} baris)</li>`);
					},
					error: function() {
						confirmBtn.text('❌ Gagal Menyimpan');
						confirmBtn.prop('disabled', false);
					}
				});
			});
		}
	});
	</script>

	<hr style="margin: 40px 0; border: 0; border-top: 1px solid #ddd;">

	<!-- MeowSync Engine -->
	<div class="meowpack-settings-section" id="meowpack-sync">
		<h2>⚡ MeowSync Engine — Secure Copy/Paste</h2>
		<p class="description">
			<?php esc_html_e( 'Sinkronisasi pengaturan dan blacklist malware antar situs dengan cepat tanpa file. (Kunci Auto-Share tidak akan disertakan demi keamanan).', 'meowpack' ); ?>
		</p>

		<?php
		if ( isset( $_GET['sync_saved'] ) ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Sinkronisasi berhasil! Pengaturan dan Blacklist telah diperbarui.', 'meowpack' ) . '</p></div>';
		}
		if ( isset( $_GET['sync_error'] ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Gagal sinkronisasi: ' . sanitize_text_field( $_GET['sync_error'] ), 'meowpack' ) . '</p></div>';
		}
		?>

		<div class="meowpack-row" style="display:flex; gap:20px; margin-top:20px;">
			<!-- Export -->
			<div style="flex:1;">
				<h3>📤 Salin Config (Situs Ini)</h3>
				<textarea readonly style="width:100%; height:120px; font-family:monospace; font-size:11px; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:4px; padding:10px;" id="meow-sync-export-code" onclick="this.select()"><?php echo esc_textarea( MeowPack_Database::export_sync_data() ); ?></textarea>
				<button type="button" class="button" onclick="const c = document.getElementById('meow-sync-export-code'); c.select(); document.execCommand('copy'); alert('Kode Config Tersalin!');" style="margin-top:8px;">📋 Copy Config Code</button>
			</div>

			<!-- Import -->
			<div style="flex:1;">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="meowpack_import_sync">
					<?php wp_nonce_field( 'meowpack_sync_import', 'meowpack_sync_nonce' ); ?>
					<h3>📥 Tempel Config (Situs Lain)</h3>
					<textarea name="meowpack_sync_code" placeholder="Tempel kode MEOW_CONFIG di sini..." style="width:100%; height:120px; font-family:monospace; font-size:11px; background:#fff; border:1px solid #cbd5e1; border-radius:4px; padding:10px;"></textarea>
					<button type="submit" class="button button-primary" style="margin-top:8px;" onclick="return confirm('⚠️ Ini akan menimpa pengaturan dan blacklist di situs ini. Lanjutkan?')">⚡ Import & Sinkronkan</button>
				</form>
			</div>
		</div>
	</div>
