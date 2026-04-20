<?php
/**
 * Admin page: Anti-Hotlink Settings.
 *
 * @package MeowPack
 */
defined( 'ABSPATH' ) || exit;

$hotlink_stats = MeowPack_Anti_Hotlink::get_hotlink_stats( 20 );
$site_host     = wp_parse_url( home_url(), PHP_URL_HOST );
$extensions    = explode( ',', MeowPack_Database::get_setting( 'hotlink_extensions', 'jpg,jpeg,png,gif,webp' ) );
$nginx_snippet = MeowPack_Anti_Hotlink::get_nginx_snippet( $site_host, $extensions );
?>
<div class="wrap meowpack-wrap">
	<h1>🛡️ <?php esc_html_e( 'Anti-Hotlink Protection', 'meowpack' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Cegah situs lain mencuri gambar dari server Anda. Pengunjung yang mengakses gambar langsung atau dari domain Anda sendiri tetap diizinkan.', 'meowpack' ); ?>
	</p>

	<!-- Settings Form -->
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'meowpack_save_hotlink', 'meowpack_hotlink_nonce' ); ?>
		<input type="hidden" name="action" value="meowpack_save_hotlink" />

		<div class="meowpack-card" style="margin-bottom:24px;">
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Aktifkan Anti-Hotlink', 'meowpack' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="enable_anti_hotlink" value="1"
								<?php checked( MeowPack_Database::get_setting( 'enable_anti_hotlink', '0' ), '1' ); ?> />
							<?php esc_html_e( 'Aktifkan perlindungan hotlink', 'meowpack' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Ekstensi yang Dilindungi', 'meowpack' ); ?></th>
					<td>
						<input type="text" name="hotlink_extensions" class="regular-text"
							value="<?php echo esc_attr( MeowPack_Database::get_setting( 'hotlink_extensions', 'jpg,jpeg,png,gif,webp' ) ); ?>" />
						<p class="description"><?php esc_html_e( 'Pisahkan dengan koma. Contoh: jpg,jpeg,png,gif,webp', 'meowpack' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Respons saat Hotlink Terdeteksi', 'meowpack' ); ?></th>
					<td>
						<?php
						$response_options = array(
							'placeholder' => __( 'Tampilkan gambar placeholder "No Hotlinking"', 'meowpack' ),
							'403'         => __( 'Kembalikan HTTP 403 Forbidden', 'meowpack' ),
							'redirect'    => __( 'Redirect ke URL tertentu', 'meowpack' ),
						);
						$current_response = MeowPack_Database::get_setting( 'hotlink_response', 'placeholder' );
						foreach ( $response_options as $val => $label ) :
						?>
						<label style="display:block; margin-bottom:6px;">
							<input type="radio" name="hotlink_response" value="<?php echo esc_attr( $val ); ?>"
								<?php checked( $current_response, $val ); ?> />
							<?php echo esc_html( $label ); ?>
						</label>
						<?php endforeach; ?>
						<div style="margin-top:8px;">
							<input type="url" name="hotlink_redirect_url" class="regular-text"
								placeholder="https://example.com/no-hotlink"
								value="<?php echo esc_attr( MeowPack_Database::get_setting( 'hotlink_redirect_url', '' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Diisi jika memilih opsi "Redirect". URL tujuan redirect.', 'meowpack' ); ?></p>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Whitelist Domain Tambahan', 'meowpack' ); ?></th>
					<td>
						<textarea name="hotlink_whitelist" rows="4" class="large-text"><?php echo esc_textarea( MeowPack_Database::get_setting( 'hotlink_whitelist', '' ) ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Satu domain per baris. Domain ini diizinkan menggunakan gambar Anda.', 'meowpack' ); ?><br>
							<?php esc_html_e( 'Catatan: domain Anda sendiri, Google, Facebook, WhatsApp sudah di-whitelist otomatis.', 'meowpack' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Simpan Pengaturan', 'meowpack' ); ?></button></p>
		</div>
	</form>

	<!-- Server Config Snippets -->
	<div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:24px;">
		<div class="meowpack-card">
			<h3><?php esc_html_e( 'Konfigurasi Nginx (server-level)', 'meowpack' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Jika menggunakan Nginx, tambahkan ini ke block server {} di file konfigurasi Nginx Anda. Cara ini lebih efisien dari PHP karena bekerja sebelum PHP dijalankan.', 'meowpack' ); ?></p>
			<textarea class="large-text" rows="8" readonly onclick="this.select()"><?php echo esc_textarea( $nginx_snippet ); ?></textarea>
		</div>
		<div class="meowpack-card">
			<h3><?php esc_html_e( 'Catatan Apache .htaccess', 'meowpack' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Plugin ini mengelola rules .htaccess otomatis saat fitur diaktifkan (hanya di server Apache). Rules ditandai dengan marker MeowPack-Hotlink.', 'meowpack' ); ?></p>
			<p>✅ <?php esc_html_e( 'Apache: Otomatis dikelola plugin', 'meowpack' ); ?></p>
			<p>⚠️ <?php esc_html_e( 'Nginx: Tambahkan snippet di atas secara manual', 'meowpack' ); ?></p>
			<p>⚠️ <?php esc_html_e( 'CDN: Whitelist domain CDN Anda agar gambar tetap bisa diakses', 'meowpack' ); ?></p>
		</div>
	</div>

	<!-- Hotlink Stats -->
	<div class="meowpack-card">
		<h3><?php esc_html_e( 'Statistik Hotlink Diblokir', 'meowpack' ); ?></h3>
		<?php if ( empty( $hotlink_stats ) ) : ?>
			<p><?php esc_html_e( 'Belum ada hotlink yang terdeteksi.', 'meowpack' ); ?></p>
		<?php else : ?>
		<table class="widefat striped meowpack-table">
			<thead><tr>
				<th><?php esc_html_e( 'Domain Pelanggar', 'meowpack' ); ?></th>
				<th><?php esc_html_e( 'Total Blokir', 'meowpack' ); ?></th>
				<th><?php esc_html_e( 'Terakhir', 'meowpack' ); ?></th>
			</tr></thead>
			<tbody>
				<?php foreach ( $hotlink_stats as $row ) : ?>
				<tr>
					<td><?php echo esc_html( $row['referrer_domain'] ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (int) $row['total_blocks'] ) ); ?></td>
					<td style="color:#6c7086; font-size:0.85em;">
						<?php echo esc_html( $row['last_blocked'] ? date_i18n( get_option( 'date_format' ), strtotime( $row['last_blocked'] ) ) : '—' ); ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>
</div>
