<?php
/**
 * Admin page: Filter Konten Berbahaya (Content Moderation).
 *
 * @package MeowPack
 */
defined( 'ABSPATH' ) || exit;

// Handle messages.
$saved   = isset( $_GET['saved'] );
$tab     = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dictionary';
$cat_filter = isset( $_GET['category'] ) ? sanitize_key( $_GET['category'] ) : '';

$categories = array(
	''         => __( 'Semua Kategori', 'meowpack' ),
	'gambling' => '🎰 ' . __( 'Judi / Gambling', 'meowpack' ),
	'drugs'    => '💊 ' . __( 'Obat Terlarang', 'meowpack' ),
	'scam'     => '💸 ' . __( 'Penipuan / Scam', 'meowpack' ),
	'violence' => '🩸 ' . __( 'Kekerasan', 'meowpack' ),
	'porn'     => '🔞 ' . __( 'Pornografi', 'meowpack' ),
	'sara'     => '⚠️ '  . __( 'SARA', 'meowpack' ),
	'custom'   => '📦 ' . __( 'Kustom', 'meowpack' ),
);

$action_labels = array(
	'hold'    => __( 'Tahan (Review)', 'meowpack' ),
	'block'   => __( 'Blokir Langsung', 'meowpack' ),
	'flag'    => __( 'Tandai + Notif', 'meowpack' ),
	'replace' => __( 'Sensor (***)', 'meowpack' ),
);
$action_colors = array(
	'hold'    => '#89dceb',
	'block'   => '#f38ba8',
	'flag'    => '#fab387',
	'replace' => '#cba6f7',
);

$rules = MeowPack_Content_Moderation::get_all_rules( $cat_filter );
$logs  = MeowPack_Content_Moderation::get_logs( 50 );
$stats = MeowPack_Content_Moderation::get_stats_by_category();

// Count by category.
$cat_counts = array();
foreach ( MeowPack_Content_Moderation::get_all_rules() as $r ) {
	$cat_counts[ $r['category'] ] = ( $cat_counts[ $r['category'] ] ?? 0 ) + 1;
}
?>
<div class="wrap meowpack-wrap">
	<h1>🚫 <?php esc_html_e( 'Filter Konten Berbahaya', 'meowpack' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Deteksi dan blokir konten berbahaya seperti judi online, obat ilegal, penipuan, dan kekerasan di komentar, postingan, dan username.', 'meowpack' ); ?>
	</p>

	<?php if ( $saved ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '✅ Pengaturan berhasil disimpan.', 'meowpack' ); ?></p></div>
	<?php endif; ?>

	<!-- Global toggle -->
	<div class="meowpack-card" style="margin-bottom:20px; padding:16px 24px;">
		<?php
		$enabled = MeowPack_Database::get_setting( 'enable_content_moderation', '0' );
		?>
		<?php if ( '1' === $enabled ) : ?>
			<span style="display:inline-block;background:#a6e3a1;color:#1e1e2e;border-radius:20px;padding:4px 14px;font-weight:700;">✅ <?php esc_html_e( 'Aktif', 'meowpack' ); ?></span>
		<?php else : ?>
			<span style="display:inline-block;background:#f38ba8;color:#1e1e2e;border-radius:20px;padding:4px 14px;font-weight:700;">❌ <?php esc_html_e( 'Nonaktif', 'meowpack' ); ?></span>
		<?php endif; ?>
		&nbsp;
		<a href="?page=meowpack-content-moderation&tab=settings" class="button button-small">
			<?php esc_html_e( '⚙️ Ubah Pengaturan', 'meowpack' ); ?>
		</a>
		&nbsp;&nbsp;
		<strong><?php echo esc_html( count( $rules ) ); ?></strong> <?php esc_html_e( 'kata kunci aktif', 'meowpack' ); ?>
		&nbsp;|&nbsp;
		<strong><?php echo esc_html( count( $logs ) ); ?></strong> <?php esc_html_e( 'deteksi terakhir', 'meowpack' ); ?>
	</div>

	<!-- TABS -->
	<h2 class="nav-tab-wrapper" style="margin-bottom:20px;">
		<a href="?page=meowpack-content-moderation&tab=dictionary" class="nav-tab <?php echo 'dictionary' === $tab ? 'nav-tab-active' : ''; ?>">
			📖 <?php esc_html_e( 'Kamus Kata Kunci', 'meowpack' ); ?>
		</a>
		<a href="?page=meowpack-content-moderation&tab=add" class="nav-tab <?php echo 'add' === $tab ? 'nav-tab-active' : ''; ?>">
			➕ <?php esc_html_e( 'Tambah Kata Kunci', 'meowpack' ); ?>
		</a>
		<a href="?page=meowpack-content-moderation&tab=import" class="nav-tab <?php echo 'import' === $tab ? 'nav-tab-active' : ''; ?>">
			📥 <?php esc_html_e( 'Import / Export', 'meowpack' ); ?>
		</a>
		<a href="?page=meowpack-content-moderation&tab=logs" class="nav-tab <?php echo 'logs' === $tab ? 'nav-tab-active' : ''; ?>">
			📋 <?php esc_html_e( 'Log Deteksi', 'meowpack' ); ?>
			<?php if ( ! empty( $logs ) ) : ?>
				<span class="meowpack-badge" style="background:#f38ba8;color:#1e1e2e;margin-left:4px;"><?php echo esc_html( count( $logs ) ); ?></span>
			<?php endif; ?>
		</a>
		<a href="?page=meowpack-content-moderation&tab=scanner" class="nav-tab <?php echo 'scanner' === $tab ? 'nav-tab-active' : ''; ?>">
			🕵️ <?php esc_html_e( 'Scanner Manual', 'meowpack' ); ?>
		</a>
		<a href="?page=meowpack-content-moderation&tab=settings" class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>">
			⚙️ <?php esc_html_e( 'Pengaturan', 'meowpack' ); ?>
		</a>
	</h2>

	<?php if ( 'dictionary' === $tab ) : ?>
	<!-- =====================================================================
	     TAB: KAMUS KATA KUNCI
	===================================================================== -->
	<!-- Category filter -->
	<div style="margin-bottom:16px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
		<strong><?php esc_html_e( 'Filter Kategori:', 'meowpack' ); ?></strong>
		<?php foreach ( $categories as $slug => $label ) : ?>
			<a href="?page=meowpack-content-moderation&tab=dictionary&category=<?php echo esc_attr( $slug ); ?>"
			   class="button <?php echo $cat_filter === $slug ? 'button-primary' : ''; ?>" style="font-size:0.85em;">
				<?php echo esc_html( $label ); ?>
				<?php if ( $slug && isset( $cat_counts[ $slug ] ) ) : ?>
					<span style="opacity:0.7">(<?php echo esc_html( $cat_counts[ $slug ] ); ?>)</span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'meowpack_save_content_rules', 'meowpack_content_rules_nonce' ); ?>
		<input type="hidden" name="action" value="meowpack_save_content_rules" />
		<input type="hidden" name="category_filter" value="<?php echo esc_attr( $cat_filter ); ?>" />

		<table class="widefat striped meowpack-table">
			<thead>
				<tr>
					<th style="width:30%"><?php esc_html_e( 'Kata Kunci', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Kategori', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Pencocokan', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Aksi', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Aktif?', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Hapus?', 'meowpack' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rules ) ) : ?>
				<tr><td colspan="6" style="text-align:center; color:#6c7086;">
					<?php esc_html_e( 'Tidak ada kata kunci di kategori ini.', 'meowpack' ); ?>
				</td></tr>
				<?php endif; ?>
				<?php foreach ( $rules as $rule ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $rule['keyword'] ); ?></strong></td>
					<td>
						<span class="meowpack-badge" style="background:#313244;">
							<?php echo esc_html( $categories[ $rule['category'] ] ?? $rule['category'] ); ?>
						</span>
					</td>
					<td>
						<select name="match_mode[<?php echo esc_attr( $rule['id'] ); ?>]" style="font-size:0.85em;">
							<option value="substring" <?php selected( $rule['match_mode'], 'substring' ); ?>><?php esc_html_e( 'Substring', 'meowpack' ); ?></option>
							<option value="word"      <?php selected( $rule['match_mode'], 'word' ); ?>><?php esc_html_e( 'Kata Utuh', 'meowpack' ); ?></option>
						</select>
					</td>
					<td>
						<select name="action[<?php echo esc_attr( $rule['id'] ); ?>]" style="font-size:0.85em; width:100%;">
							<?php foreach ( $action_labels as $act_key => $act_label ) : ?>
							<option value="<?php echo esc_attr( $act_key ); ?>"
								<?php selected( $rule['action'], $act_key ); ?>
								style="background:<?php echo esc_attr( $action_colors[ $act_key ] ); ?>">
								<?php echo esc_html( $act_label ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</td>
					<td style="text-align:center">
						<input type="checkbox" name="active[<?php echo esc_attr( $rule['id'] ); ?>]" value="1"
							<?php checked( $rule['is_active'], 1 ); ?> />
					</td>
					<td style="text-align:center">
						<input type="checkbox" name="delete[]" value="<?php echo esc_attr( $rule['id'] ); ?>" />
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Simpan Perubahan', 'meowpack' ); ?></button></p>
	</form>

	<?php elseif ( 'add' === $tab ) : ?>
	<!-- =====================================================================
	     TAB: TAMBAH KATA KUNCI
	===================================================================== -->
	<div style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">
		<!-- Single add -->
		<div class="meowpack-card">
			<h3><?php esc_html_e( 'Tambah Satu Kata Kunci', 'meowpack' ); ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'meowpack_add_content_rule', 'meowpack_add_content_nonce' ); ?>
				<input type="hidden" name="action" value="meowpack_add_content_rule" />
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Kata Kunci', 'meowpack' ); ?></th>
						<td><input type="text" name="keyword" class="regular-text" required
							placeholder="<?php esc_attr_e( 'contoh: judi online', 'meowpack' ); ?>" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Kategori', 'meowpack' ); ?></th>
						<td>
							<select name="category">
								<?php foreach ( $categories as $slug => $label ) : if ( ! $slug ) continue; ?>
								<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Aksi', 'meowpack' ); ?></th>
						<td>
							<select name="action">
								<?php foreach ( $action_labels as $k => $l ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Mode Pencocokan', 'meowpack' ); ?></th>
						<td>
							<select name="match_mode">
								<option value="substring"><?php esc_html_e( 'Substring (temukan di mana saja dalam teks)', 'meowpack' ); ?></option>
								<option value="word"><?php esc_html_e( 'Kata Utuh (hanya cocok jika kata berdiri sendiri)', 'meowpack' ); ?></option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Contoh: kata "slot" dengan substring akan cocok dengan "slotgacor", tapi word tidak.', 'meowpack' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Tambah Kata Kunci', 'meowpack' ); ?></button></p>
			</form>
		</div>

		<!-- Bulk add textarea -->
		<div class="meowpack-card">
			<h3><?php esc_html_e( 'Tambah Massal (Satu per baris)', 'meowpack' ); ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'meowpack_bulk_add_content', 'meowpack_bulk_content_nonce' ); ?>
				<input type="hidden" name="action" value="meowpack_bulk_add_content" />
				<p>
					<label><strong><?php esc_html_e( 'Kategori untuk semua entri ini:', 'meowpack' ); ?></strong></label><br>
					<select name="category" style="margin-top:4px;">
						<?php foreach ( $categories as $slug => $label ) : if ( ! $slug ) continue; ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label><strong><?php esc_html_e( 'Aksi:', 'meowpack' ); ?></strong></label><br>
					<select name="action" style="margin-top:4px;">
						<?php foreach ( $action_labels as $k => $l ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label><strong><?php esc_html_e( 'Kata kunci (satu per baris):', 'meowpack' ); ?></strong></label><br>
					<textarea name="keywords" rows="10" class="large-text" style="margin-top:4px;"
						placeholder="<?php esc_attr_e( "slot gacor\njudi online\ntogel sgp", 'meowpack' ); ?>"></textarea>
				</p>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Tambah Semua', 'meowpack' ); ?></button></p>
			</form>
		</div>
	</div>

	<?php elseif ( 'import' === $tab ) : ?>
	<!-- =====================================================================
	     TAB: IMPORT / EXPORT
	===================================================================== -->
	<div style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">
		<!-- Import -->
		<div class="meowpack-card">
			<h3><?php esc_html_e( 'Import dari CSV', 'meowpack' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Format: keyword,category,action,match_mode — satu baris per kata kunci. Baris diawali # diabaikan.', 'meowpack' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'meowpack_import_content', 'meowpack_import_content_nonce' ); ?>
				<input type="hidden" name="action" value="meowpack_import_content_rules" />
				<textarea name="csv_content" rows="10" class="large-text"
					placeholder="# keyword,category,action,match_mode&#10;slot gacor,gambling,hold,substring&#10;judi online,gambling,block,substring"></textarea>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Import CSV', 'meowpack' ); ?></button></p>
			</form>
		</div>

		<!-- Export -->
		<div class="meowpack-card">
			<h3><?php esc_html_e( 'Export ke CSV', 'meowpack' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Download semua kata kunci yang ada sebagai file CSV.', 'meowpack' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=meowpack_export_content_rules&_wpnonce=' . wp_create_nonce( 'meowpack_export_content' ) ) ); ?>"
			   class="button button-secondary">
				📥 <?php esc_html_e( 'Download CSV', 'meowpack' ); ?>
			</a>

			<h3 style="margin-top:24px;"><?php esc_html_e( 'Reset Kategori', 'meowpack' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Hapus semua kata kunci dalam kategori tertentu (tidak dapat dibatalkan).', 'meowpack' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			      onsubmit="return confirm('<?php esc_attr_e( 'Yakin? Ini akan menghapus semua kata kunci di kategori ini!', 'meowpack' ); ?>')">
				<?php wp_nonce_field( 'meowpack_reset_content_category', 'meowpack_reset_category_nonce' ); ?>
				<input type="hidden" name="action" value="meowpack_reset_content_category" />
				<select name="category">
					<?php foreach ( $categories as $slug => $label ) : if ( ! $slug ) continue; ?>
					<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button" style="color:#f38ba8;"><?php esc_html_e( 'Reset Kategori', 'meowpack' ); ?></button>
			</form>
		</div>
	</div>

	<?php elseif ( 'logs' === $tab ) : ?>
	<!-- =====================================================================
	     TAB: LOG DETEKSI
	===================================================================== -->

	<!-- Stats by category -->
	<?php if ( ! empty( $stats ) ) : ?>
	<div style="display:flex; flex-wrap:wrap; gap:12px; margin-bottom:24px;">
		<?php foreach ( $stats as $stat ) : ?>
		<div class="meowpack-card" style="min-width:150px; text-align:center; padding:12px 20px;">
			<div style="font-size:2em;"><?php
				$icons = array( 'gambling'=>'🎰','drugs'=>'💊','scam'=>'💸','violence'=>'🩸','porn'=>'🔞','sara'=>'⚠️','custom'=>'📦' );
				echo esc_html( $icons[ $stat['matched_category'] ] ?? '⚠️' );
			?></div>
			<div style="font-size:1.5em; font-weight:700;"><?php echo esc_html( number_format_i18n( (int) $stat['total'] ) ); ?></div>
			<div style="color:#6c7086; font-size:0.8em;"><?php echo esc_html( $categories[ $stat['matched_category'] ] ?? $stat['matched_category'] ); ?></div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<?php if ( empty( $logs ) ) : ?>
		<div class="meowpack-notice"><?php esc_html_e( 'Belum ada konten berbahaya yang terdeteksi.', 'meowpack' ); ?></div>
	<?php else : ?>
	<table class="widefat striped meowpack-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Waktu', 'meowpack' ); ?></th>
				<th><?php esc_html_e( 'Lokasi', 'meowpack' ); ?></th>
				<th><?php esc_html_e( 'Kata Kunci', 'meowpack' ); ?></th>
				<th><?php esc_html_e( 'Kategori', 'meowpack' ); ?></th>
				<th><?php esc_html_e( 'Aksi', 'meowpack' ); ?></th>
				<th><?php esc_html_e( 'Cuplikan Konten', 'meowpack' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $logs as $log ) : ?>
			<tr>
				<td style="white-space:nowrap; color:#6c7086; font-size:0.85em;">
					<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $log['detected_at'] ) ) ); ?>
				</td>
				<td>
					<?php
					$ctx_icons = array( 'comment' => '💬', 'post' => '📄', 'username' => '👤' );
					echo esc_html( ( $ctx_icons[ $log['context'] ] ?? '?' ) . ' ' . ucfirst( $log['context'] ) );
					if ( $log['object_id'] > 0 ) {
						echo ' <small style="color:#6c7086">#' . esc_html( $log['object_id'] ) . '</small>';
					}
					?>
				</td>
				<td>
					<code style="background:#313244; padding:2px 6px; border-radius:4px;">
						<?php echo esc_html( $log['matched_keyword'] ); ?>
					</code>
				</td>
				<td>
					<span class="meowpack-badge" style="background:#313244;">
						<?php echo esc_html( $categories[ $log['matched_category'] ] ?? $log['matched_category'] ); ?>
					</span>
				</td>
				<td>
					<span class="meowpack-badge" style="background:<?php echo esc_attr( $action_colors[ $log['action_taken'] ] ?? '#6c7086' ); ?>;color:#1e1e2e;">
						<?php echo esc_html( $action_labels[ $log['action_taken'] ] ?? $log['action_taken'] ); ?>
					</span>
				</td>
				<td style="max-width:300px; font-size:0.85em; color:#cdd6f4;">
					<?php echo esc_html( mb_substr( $log['content_excerpt'] ?? '', 0, 100 ) ); ?>
					<?php if ( mb_strlen( $log['content_excerpt'] ?? '' ) > 100 ) : ?>&hellip;<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>

	<?php elseif ( 'settings' === $tab ) : ?>
	<!-- =====================================================================
	     TAB: PENGATURAN
	===================================================================== -->
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'meowpack_save_content_settings', 'meowpack_content_settings_nonce' ); ?>
		<input type="hidden" name="action" value="meowpack_save_content_settings" />

		<div class="meowpack-card">
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Aktifkan Filter Konten', 'meowpack' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="enable_content_moderation" value="1"
								<?php checked( MeowPack_Database::get_setting( 'enable_content_moderation', '0' ), '1' ); ?> />
							<?php esc_html_e( 'Aktifkan sistem pendeteksian konten berbahaya', 'meowpack' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Target Pemindaian', 'meowpack' ); ?></th>
					<td>
						<?php
						$scan_targets = array(
							'modscan_comments'  => __( '💬 Komentar baru (default aktif — sangat disarankan)', 'meowpack' ),
							'modscan_usernames' => __( '👤 Username saat registrasi', 'meowpack' ),
							'modscan_posts'     => __( '📄 Konten post saat dipublish (lebih berat, hati-hati)', 'meowpack' ),
						);
						foreach ( $scan_targets as $key => $label ) :
						?>
						<label style="display:block; margin-bottom:8px;">
							<input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1"
								<?php checked( MeowPack_Database::get_setting( $key, $key === 'modscan_comments' ? '1' : '0' ), '1' ); ?> />
							<?php echo esc_html( $label ); ?>
						</label>
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Notifikasi Admin', 'meowpack' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="moderation_notify_admin" value="1"
								<?php checked( MeowPack_Database::get_setting( 'moderation_notify_admin', '1' ), '1' ); ?> />
							<?php esc_html_e( 'Kirim email notifikasi saat konten "flag" terdeteksi', 'meowpack' ); ?>
						</label>
						<p class="description"><?php printf( esc_html__( 'Email akan dikirim ke: %s', 'meowpack' ), '<strong>' . esc_html( get_option( 'admin_email' ) ) . '</strong>' ); ?></p>
					</td>
				</tr>
			</table>
			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Simpan Pengaturan', 'meowpack' ); ?></button></p>
		</div>
	</form>

	<?php elseif ( 'scanner' === $tab ) : ?>
	<!-- =====================================================================
	     TAB: SCANNER MANUAL
	===================================================================== -->
	<div class="meowpack-card" style="margin-bottom:24px;">
		<h3><?php esc_html_e( 'Pemindaian Menyeluruh On-Demand (Full Database)', 'meowpack' ); ?></h3>
		<p class="description" style="max-width:800px; line-height:1.6;">
			<?php esc_html_e( 'Gunakan fitur ini jika Anda curiga website pernah disusupi malware/SEO spam. Scanner ini akan secara dinamis mengambil <strong>seluruh daftar tabel database</strong>, mencari kolom teks (varchar, text, longtext, dll), dan memindainya satu per satu terhadap kamus kata kunci.', 'meowpack' ); ?><br><br>
			⚠️ <?php esc_html_e( 'Karena memindai seluruh basis data, proses ini mungkin membutuhkan waktu cukup lama bergantung pada besarnya size database. Jendela browser jangan ditutup hingga pemindaian selesai.', 'meowpack' ); ?>
		</p>

		<button type="button" id="btn-start-scan" class="button button-primary button-large" style="margin-top:16px;">
			🚀 <?php esc_html_e( 'Mulai Scan Seluruh Database', 'meowpack' ); ?>
		</button>
		
		<div id="scan-progress-wrap" style="display:none; margin-top:24px; padding:20px; background:#1e1e2e; border-radius:8px; border:1px solid #313244;">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
				<div style="font-weight:600; font-size:1.1em;" id="scan-status">⏳ <?php esc_html_e( 'Menyiapkan tabel...', 'meowpack' ); ?></div>
				<div id="scan-table-counter" style="color:#a6e3a1; font-weight:700;"></div>
			</div>
			<div style="width:100%; height:16px; background:#11111b; border-radius:8px; overflow:hidden;">
				<div id="scan-bar" style="width:0; height:100%; background:linear-gradient(90deg, #89b4fa, #cba6f7); transition:width 0.3s ease;"></div>
			</div>
		</div>
	</div>

	<!-- Tabel Hasil -->
	<div class="meowpack-card" id="scan-results-card" style="display:none;">
		<h3>⚠️ <?php esc_html_e( 'Hasil Pemindaian', 'meowpack' ); ?></h3>
		<p class="description" id="scan-results-desc"></p>
		<table class="widefat striped meowpack-table" id="scan-results-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Nama Tabel', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Identitas Baris (Primary Key)', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Kata Kunci', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Aksi / Lacak', 'meowpack' ); ?></th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>
	</div>

	<script>
	jQuery(document).ready(function($) {
		let tablesToScan = [];
		let currentTableIndex = 0;
		let currentOffset = 0;
		let foundItems = [];
		let isRunning = false;

		$('#btn-start-scan').on('click', function() {
			if (isRunning) return;

			if (!confirm('Apakah Anda yakin ingin memulai pemindaian penuh database? Ini mungkin memakan waktu lama.')) {
				return;
			}

			isRunning = true;
			currentTableIndex = 0;
			currentOffset = 0;
			foundItems = [];
			tablesToScan = [];

			$(this).prop('disabled', true);
			$('#scan-progress-wrap').show();
			$('#scan-results-card').hide();
			$('#scan-results-table tbody').empty();
			updateProgress(0, 'Mendapatkan daftar tabel database...');
			
			// Step 1: Get all tables.
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'meowpack_manual_scan',
					nonce: '<?php echo esc_js( wp_create_nonce( 'meowpack_manual_scan' ) ); ?>',
					mode: 'get_tables'
				},
				success: function(res) {
					if (res.success && res.data.tables && res.data.tables.length > 0) {
						tablesToScan = res.data.tables;
						doScanTableBatch();
					} else {
						updateProgress(100, '❌ Gagal mendapatkan daftar tabel.');
						alert('Gagal mengambil struktur tabel.');
						finishScan();
					}
				},
				error: function() {
					updateProgress(100, '❌ Koneksi terputus.');
					finishScan();
				}
			});
		});

		function doScanTableBatch() {
			if (currentTableIndex >= tablesToScan.length) {
				finishScan();
				return;
			}

			let tableName = tablesToScan[currentTableIndex];
			let percent = (currentTableIndex / tablesToScan.length) * 100;
			updateProgress(percent, `Memindai tabel: <strong>${tableName}</strong> (offset: ${currentOffset})...`);
			$('#scan-table-counter').text(`${currentTableIndex + 1} / ${tablesToScan.length} Tabel`);

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'meowpack_manual_scan',
					nonce: '<?php echo esc_js( wp_create_nonce( 'meowpack_manual_scan' ) ); ?>',
					mode: 'scan_table',
					table: tableName,
					offset: currentOffset
				},
				success: function(res) {
					if (res.success && res.data) {
						if (res.data.found && res.data.found.length > 0) {
							foundItems = foundItems.concat(res.data.found);
							renderFoundItems(res.data.found);
						}

						if (res.data.next_offset !== null) {
							// Continue same table at next offset
							currentOffset = res.data.next_offset;
							doScanTableBatch();
						} else {
							// Done with this table, move to next
							currentTableIndex++;
							currentOffset = 0;
							doScanTableBatch();
						}
					} else {
						// Error in response, skip table to prevent infinite blockage
						console.error("Gagal memindai tabel: " + tableName, res);
						currentTableIndex++;
						currentOffset = 0;
						doScanTableBatch();
					}
				},
				error: function() {
					// Network error, skip table and wait briefly
					console.error("Koneksi terputus pada tabel: " + tableName);
					currentTableIndex++;
					currentOffset = 0;
					setTimeout(doScanTableBatch, 2000); // retry next in 2s
				}
			});
		}

		function renderFoundItems(items) {
			$('#scan-results-card').show();
			let tbody = $('#scan-results-table tbody');
			
			items.forEach(function(item) {
				let actionLink = '#';
				if (item.link !== '#') {
					actionLink = `<a href="${item.link}" target="_blank" class="button button-small">${item.linkLabel}</a>`;
				} else {
					actionLink = `<span style="color:#6c7086;font-size:0.85em;">Cek manual via phpMyAdmin</span>`;
				}

				let html = `<tr>
					<td><span class="meowpack-badge" style="background:#313244;">${item.type}</span></td>
					<td style="font-family:monospace; color:#cdd6f4;">${item.title}</td>
					<td><code style="background:#f38ba8;color:#1e1e2e;padding:2px 6px;border-radius:4px;">${item.keyword}</code> <small style="color:#6c7086">(${item.category})</small></td>
					<td>${actionLink}</td>
				</tr>`;
				tbody.append(html);
			});
		}

		function finishScan() {
			isRunning = false;
			$('#btn-start-scan').prop('disabled', false);
			
			if (tablesToScan.length > 0) {
				$('#scan-table-counter').text(`${tablesToScan.length} / ${tablesToScan.length} Selesai`);
				updateProgress(100, `✅ Pemindaian database selesai! Menemukan ${foundItems.length} potensi ancaman.`);
				
				if (foundItems.length === 0) {
					$('#scan-results-card').show();
					$('#scan-results-desc').html('<span style="color:#a6e3a1; font-weight:600;">🎉 Sempurna! Seluruh database WordPress terbebas dari kata kunci berbahaya.</span>');
				} else {
					$('#scan-results-desc').html(`Ditemukan pada lokasi anomali. Pertimbangkan untuk mengeceknya melalui dashboard atau Database Manager / phpMyAdmin.`);
				}
			}
		}

		function updateProgress(percent, text) {
			$('#scan-bar').css('width', percent + '%');
			$('#scan-status').html(text);
		}
	});
	</script>
	<?php endif; ?>
</div>
