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
</div>
