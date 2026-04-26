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
						<select name="show_post_meta_bar" style="margin-bottom: 10px;">
							<option value="top" <?php selected( $s( 'show_post_meta_bar', 'top' ), 'top' ); ?>><?php esc_html_e( 'Otomatis di Atas Artikel', 'meowpack' ); ?></option>
							<option value="bottom" <?php selected( $s( 'show_post_meta_bar', 'top' ), 'bottom' ); ?>><?php esc_html_e( 'Otomatis di Bawah Artikel', 'meowpack' ); ?></option>
							<option value="hidden" <?php selected( $s( 'show_post_meta_bar', 'top' ), 'hidden' ); ?>><?php esc_html_e( 'Sembunyikan Total', 'meowpack' ); ?></option>
						</select>
						<p class="description" style="margin-bottom: 15px;"><?php esc_html_e( 'Menampilkan: "📈 1.250 Dilihat • ⏱️ Estimasi Baca: 3 Menit".', 'meowpack' ); ?></p>
						
						<?php
						$all_types = array( 'post' => 'Pos', 'page' => 'Halaman', 'attachment' => 'Media' );
						$views_on  = explode( ',', $s( 'show_views_on', 'post,page' ) );
						$read_on   = explode( ',', $s( 'show_reading_time_on', 'post,page' ) );
						?>
						
						<div style="margin-bottom:10px; padding:10px; background:#f8f9fa; border:1px solid #e9ecef; border-radius:4px; max-width:400px;">
							<strong><?php esc_html_e( 'Tampilkan Jumlah Dilihat (Views) di:', 'meowpack' ); ?></strong><br>
							<?php foreach ( $all_types as $type_slug => $type_label ) : ?>
								<label style="margin-right: 15px; display: inline-block;">
									<input type="checkbox" name="show_views_on[]" value="<?php echo esc_attr( $type_slug ); ?>" <?php checked( in_array( $type_slug, $views_on, true ) ); ?>>
									<?php echo esc_html( $type_label ); ?>
								</label>
							<?php endforeach; ?>
						</div>

						<div style="padding:10px; background:#f8f9fa; border:1px solid #e9ecef; border-radius:4px; max-width:400px;">
							<strong><?php esc_html_e( 'Tampilkan Menit Dibaca (Reading Time) di:', 'meowpack' ); ?></strong><br>
							<?php foreach ( $all_types as $type_slug => $type_label ) : ?>
								<label style="margin-right: 15px; display: inline-block;">
									<input type="checkbox" name="show_reading_time_on[]" value="<?php echo esc_attr( $type_slug ); ?>" <?php checked( in_array( $type_slug, $read_on, true ) ); ?>>
									<?php echo esc_html( $type_label ); ?>
								</label>
							<?php endforeach; ?>
						</div>
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
						<?php
						$post_types = get_post_types( array( 'public' => true ), 'objects' );
						$tracked    = explode( ',', $s( 'track_post_types', 'post,page' ) );
						foreach ( $post_types as $type ) :
						?>
							<label style="margin-right: 15px; display: inline-block;">
								<input type="checkbox" name="track_post_types[]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, $tracked, true ) ); ?>>
								<?php echo esc_html( $type->label ); ?>
							</label>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Centang tipe konten yang ingin dihitung statistiknya dan ditampilkan View Counter-nya.', 'meowpack' ); ?></p>
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
						<select name="share_button_position" style="margin-bottom: 15px;">
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

						<?php
						$all_types = array( 'post' => 'Pos', 'page' => 'Halaman', 'attachment' => 'Media' );
						$share_on  = explode( ',', $s( 'show_share_buttons_on', 'post,page' ) );
						?>
						<div style="padding:10px; background:#f8f9fa; border:1px solid #e9ecef; border-radius:4px; max-width:400px;">
							<strong><?php esc_html_e( 'Tampilkan Tombol Share di:', 'meowpack' ); ?></strong><br>
							<?php foreach ( $all_types as $type_slug => $type_label ) : ?>
								<label style="margin-right: 15px; display: inline-block;">
									<input type="checkbox" name="show_share_buttons_on[]" value="<?php echo esc_attr( $type_slug ); ?>" <?php checked( in_array( $type_slug, $share_on, true ) ); ?>>
									<?php echo esc_html( $type_label ); ?>
								</label>
							<?php endforeach; ?>
						</div>
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

	<!-- Shortcode Documentation -->
	<div class="meowpack-settings-section" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 1px solid #dee2e6; padding: 30px; border-radius: 16px; margin-top: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
		<h2 style="margin-top:0; display:flex; align-items:center; gap:10px;">🧩 <?php esc_html_e( 'Shortcodes & Embed Code', 'meowpack' ); ?></h2>
		<p class="description" style="font-size:1.1em; color:#475569; margin-bottom:20px;">
			<?php esc_html_e( 'Gunakan shortcode di bawah ini untuk menampilkan daftar artikel atau statistik di mana saja (Post, Page, atau Modul Code/Text Divi).', 'meowpack' ); ?>
		</p>

		<div class="meowpack-doc-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:20px;">
			<!-- Recent Posts -->
			<div style="background:#fff; padding:20px; border-radius:12px; border:1px solid #e2e8f0;">
				<h3 style="margin-top:0;">✨ <?php esc_html_e( 'Artikel Terbaru', 'meowpack' ); ?></h3>
				<code>[meowpack_recent count="5"]</code>
				<p class="description" style="margin-top:10px;"><?php esc_html_e( 'Menampilkan daftar judul artikel terbaru dengan gaya premium.', 'meowpack' ); ?></p>
			</div>

			<!-- Random Posts -->
			<div style="background:#fff; padding:20px; border-radius:12px; border:1px solid #e2e8f0;">
				<h3 style="margin-top:0;">🎲 <?php esc_html_e( 'Artikel Acak', 'meowpack' ); ?></h3>
				<code>[meowpack_random count="5"]</code>
				<p class="description" style="margin-top:10px;"><?php esc_html_e( 'Menampilkan artikel secara acak untuk meningkatkan dwell time pengunjung.', 'meowpack' ); ?></p>
			</div>

			<!-- Popular Posts -->
			<div style="background:#fff; padding:20px; border-radius:12px; border:1px solid #e2e8f0;">
				<h3 style="margin-top:0;">🔥 <?php esc_html_e( 'Artikel Populer', 'meowpack' ); ?></h3>
				<code>[meowpack_popular count="5"]</code>
				<p class="description" style="margin-top:10px;"><?php esc_html_e( 'Menampilkan artikel yang paling banyak dibaca dalam 7 hari terakhir.', 'meowpack' ); ?></p>
			</div>

			<!-- Visitor Counter -->
			<div style="background:#fff; padding:20px; border-radius:12px; border:1px solid #e2e8f0;">
				<h3 style="margin-top:0;">👁️ <?php esc_html_e( 'Statistik Pengunjung', 'meowpack' ); ?></h3>
				<code>[meowpack_counter type="all"]</code>
				<p class="description" style="margin-top:10px;"><?php esc_html_e( 'Pilihan type: today, month, total, pageviews, atau all.', 'meowpack' ); ?></p>
			</div>

			<!-- Table of Contents -->
			<div style="background:#fff; padding:20px; border-radius:12px; border:1px solid #e2e8f0;">
				<h3 style="margin-top:0;">📋 <?php esc_html_e( 'Daftar Isi (TOC)', 'meowpack' ); ?></h3>
				<code>[meow_toc]</code>
				<p class="description" style="margin-top:10px;"><?php esc_html_e( 'Gunakan ini jika Anda memilih mode "Manual via Shortcode" di pengaturan di atas.', 'meowpack' ); ?></p>
			</div>
		</div>
	</div>

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
