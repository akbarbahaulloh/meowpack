<?php
/**
 * Admin page: Captcha Settings.
 *
 * @package MeowPack
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap meowpack-wrap">
	<h1>🔐 <?php esc_html_e( 'Pengaturan Captcha', 'meowpack' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Lindungi formulir dari spam bot. Pilih kombinasi Math Captcha + Honeypot untuk perlindungan berlapis tanpa mengorbankan kenyamanan pengguna.', 'meowpack' ); ?>
	</p>

	<!-- Comparison table -->
	<div class="meowpack-card" style="margin-bottom:24px;">
		<h3><?php esc_html_e( 'Perbandingan Metode Captcha', 'meowpack' ); ?></h3>
		<table class="widefat meowpack-table">
			<thead><tr>
				<th><?php esc_html_e( 'Metode', 'meowpack' ); ?></th>
				<th><?php esc_html_e( 'Mudah Digunakan', 'meowpack' ); ?></th>
				<th><?php esc_html_e( 'Keamanan', 'meowpack' ); ?></th>
				<th><?php esc_html_e( 'Privasi', 'meowpack' ); ?></th>
				<th><?php esc_html_e( 'Layanan Eksternal?', 'meowpack' ); ?></th>
			</tr></thead>
			<tbody>
				<tr style="background:#1e1e2e;">
					<td><strong>Math Captcha</strong></td>
					<td>⭐⭐⭐⭐⭐</td>
					<td>⭐⭐ <small><?php esc_html_e( '(mudah dilewati bot AI)', 'meowpack' ); ?></small></td>
					<td>✅ <?php esc_html_e( 'Lokal', 'meowpack' ); ?></td>
					<td>❌ <?php esc_html_e( 'Tidak perlu', 'meowpack' ); ?></td>
				</tr>
				<tr>
					<td><strong>Honeypot</strong></td>
					<td>⭐⭐⭐⭐⭐ <small><?php esc_html_e( '(tak terlihat)', 'meowpack' ); ?></small></td>
					<td>⭐⭐⭐</td>
					<td>✅ <?php esc_html_e( 'Lokal', 'meowpack' ); ?></td>
					<td>❌ <?php esc_html_e( 'Tidak perlu', 'meowpack' ); ?></td>
				</tr>
				<tr style="background:#1e1e2e;">
					<td><strong>Math + Honeypot ✨ Rekomendasi</strong></td>
					<td>⭐⭐⭐⭐⭐</td>
					<td>⭐⭐⭐</td>
					<td>✅ <?php esc_html_e( 'Lokal', 'meowpack' ); ?></td>
					<td>❌ <?php esc_html_e( 'Tidak perlu', 'meowpack' ); ?></td>
				</tr>
				<tr>
					<td><strong>Cloudflare Turnstile</strong></td>
					<td>⭐⭐⭐⭐</td>
					<td>⭐⭐⭐⭐⭐</td>
					<td>✅ <?php esc_html_e( 'Cloudflare', 'meowpack' ); ?></td>
					<td>✅ <?php esc_html_e( 'Cloudflare (gratis)', 'meowpack' ); ?></td>
				</tr>
				<tr style="background:#1e1e2e;">
					<td><strong>reCAPTCHA v3</strong></td>
					<td>⭐⭐⭐⭐⭐ <small>(invisible)</small></td>
					<td>⭐⭐⭐⭐</td>
					<td>❌ <?php esc_html_e( 'Data ke Google', 'meowpack' ); ?></td>
					<td>✅ <?php esc_html_e( 'Google', 'meowpack' ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- Settings Form -->
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'meowpack_save_captcha', 'meowpack_captcha_nonce' ); ?>
		<input type="hidden" name="action" value="meowpack_save_captcha" />

		<div class="meowpack-card">
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Aktifkan Captcha', 'meowpack' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="enable_captcha" value="1"
								<?php checked( MeowPack_Database::get_setting( 'enable_captcha', '0' ), '1' ); ?> />
							<?php esc_html_e( 'Aktifkan sistem captcha MeowPack', 'meowpack' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Metode Captcha', 'meowpack' ); ?></th>
					<td>
						<?php
						$captcha_types = array(
							'math'     => __( 'Math Captcha saja (pertanyaan penjumlahan/pengurangan)', 'meowpack' ),
							'honeypot' => __( 'Honeypot saja (field tersembunyi, tidak terlihat user)', 'meowpack' ),
							'both'     => __( 'Math + Honeypot (⭐ Rekomendasi — perlindungan berlapis)', 'meowpack' ),
						);
						$current_type = MeowPack_Database::get_setting( 'captcha_type', 'math' );
						foreach ( $captcha_types as $val => $label ) :
						?>
						<label style="display:block; margin-bottom:8px;">
							<input type="radio" name="captcha_type" value="<?php echo esc_attr( $val ); ?>"
								<?php checked( $current_type, $val ); ?> />
							<?php echo esc_html( $label ); ?>
						</label>
						<?php endforeach; ?>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Aktifkan di Formulir', 'meowpack' ); ?></th>
					<td>
						<?php
						$form_options = array(
							'captcha_on_comments'     => __( 'Form Komentar WordPress', 'meowpack' ),
							'captcha_on_login'        => __( 'Form Login WordPress', 'meowpack' ),
							'captcha_on_register'     => __( 'Form Registrasi WordPress', 'meowpack' ),
							'captcha_on_lostpassword' => __( 'Form Lupa Password', 'meowpack' ),
						);
						foreach ( $form_options as $key => $label ) :
						?>
						<label style="display:block; margin-bottom:6px;">
							<input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1"
								<?php checked( MeowPack_Database::get_setting( $key, '0' ), '1' ); ?> />
							<?php echo esc_html( $label ); ?>
						</label>
						<?php endforeach; ?>
						<?php if ( class_exists( 'WooCommerce' ) ) : ?>
						<label style="display:block; margin-bottom:6px; margin-top:8px;">
							<input type="checkbox" name="captcha_on_woo_checkout" value="1"
								<?php checked( MeowPack_Database::get_setting( 'captcha_on_woo_checkout', '0' ), '1' ); ?> />
							<?php esc_html_e( 'WooCommerce Checkout', 'meowpack' ); ?>
						</label>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Simpan Pengaturan', 'meowpack' ); ?></button></p>
		</div>
	</form>

	<!-- Preview -->
	<div class="meowpack-card" style="margin-top:24px; max-width:400px;">
		<h3><?php esc_html_e( 'Preview Captcha', 'meowpack' ); ?></h3>
		<?php
		$preview = MeowPack_Captcha::generate();
		echo '<p><label style="font-weight:600">' . esc_html( $preview['question'] ) . ' <span style="color:#f38ba8">*</span></label></p>';
		echo '<p><input type="text" inputmode="numeric" style="max-width:80px;border:2px solid #8aadf4;border-radius:6px;padding:6px 10px;" placeholder="?" /></p>';
		echo '<p style="color:#6c7086; font-size:0.85em;">' . esc_html__( '(Ini hanya preview. Token tidak valid.)', 'meowpack' ) . '</p>';
		?>
	</div>
</div>
