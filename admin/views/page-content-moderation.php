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
		<a href="?page=meowpack-content-moderation&tab=cloud" class="nav-tab <?php echo 'cloud' === $tab ? 'nav-tab-active' : ''; ?>">
			🌍 <?php esc_html_e( 'Cloud Sync', 'meowpack' ); ?>
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

		<?php
		// Fallback PHP-only scanner
		$scan_results = array();
		$scan_completed = false;

		if ( isset( $_POST['meowpack_run_full_scan'] ) && check_admin_referer( 'meowpack_full_scan_action' ) ) {
			// Increase time limit for local scan
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 300 );
			}

			global $wpdb;
			
			$target_tables = array(
				array(
					'table' => $wpdb->posts,
					'where' => "post_status NOT IN ('trash', 'auto-draft') AND post_type != 'revision'",
				),
				array(
					'table' => $wpdb->comments,
					'where' => "comment_approved NOT IN ('trash', 'spam')",
				),
				array(
					'table' => $wpdb->terms,
					'where' => "1=1",
				),
				array(
					'table' => $wpdb->term_taxonomy,
					'where' => "1=1",
				),
			);
			
			$moderator = new MeowPack_Content_Moderation();

			foreach ( $target_tables as $target ) {
				$table = $target['table'];
				$where = $target['where'];
				// Find text-based columns
				$columns = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$text_columns = array();
				$primary_key  = '';

				foreach ( $columns as $col ) {
					if ( 'PRI' === $col->Key && ! $primary_key ) {
						$primary_key = $col->Field;
					}
					$type = strtolower( $col->Type );
					if ( strpos( $type, 'char' ) !== false || strpos( $type, 'text' ) !== false ) {
						$text_columns[] = $col->Field;
					}
				}

				if ( empty( $text_columns ) ) {
					continue;
				}

				// Get all rows
				$select_cols = $text_columns;
				if ( $primary_key && ! in_array( $primary_key, $select_cols, true ) ) {
					$select_cols[] = $primary_key;
				}
				
				$cols_str = '`' . implode( '`, `', $select_cols ) . '`';
				
				// Process in chunks to prevent memory exhaustion and "Commands out of sync" DB errors.
				$limit  = 500;
				$offset = 0;

				while ( true ) {
					$query = "SELECT {$cols_str} FROM `{$table}` WHERE {$where} LIMIT {$limit} OFFSET {$offset}";
					$rows  = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

					if ( empty( $rows ) ) {
						break; // No more rows in this table.
					}

					foreach ( $rows as $row ) {
						$row_text = '';
						foreach ( $text_columns as $col ) {
							$row_text .= $row->$col . ' ';
						}

						$match = $moderator->scan_text( $row_text );
						if ( $match ) {
							$pk_val = $primary_key ? $row->$primary_key : 'N/A';
							
							$link = '#';
							$link_label = __( 'Cek Manual', 'meowpack' );
							if ( $table === $wpdb->posts ) {
								$link = get_edit_post_link( $row->$primary_key, 'raw' );
								$link_label = __( 'Edit Post', 'meowpack' );
							} elseif ( $table === $wpdb->comments ) {
								$link = get_edit_comment_link( $row->$primary_key );
								$link_label = __( 'Edit Komentar', 'meowpack' );
							} elseif ( $table === $wpdb->terms || $table === $wpdb->term_taxonomy ) {
								$term_id = $row->$primary_key;
								$term = get_term( $term_id );
								if ( ! is_wp_error( $term ) && $term ) {
									$link = get_edit_term_link( $term->term_id, $term->taxonomy );
									$link_label = __( 'Edit Kategori/Tag', 'meowpack' );
								}
							}

							$scan_results[] = array(
								'type'      => $table,
								'title'     => $primary_key . ': ' . $pk_val,
								'keyword'   => $match['keyword'],
								'category'  => $match['category'],
								'link'      => $link ?: '#',
								'linkLabel' => $link_label,
							);
						}
					}
					
					$offset += $limit;
					
					// Free memory aggressively.
					unset( $rows );
					
					// Safety limit to avoid infinite loops or extreme timeouts on massive tables
					if ( $offset > 500000 ) {
						break;
					}
				}
			}
			$scan_completed = true;
		}
		?>

		<form method="post" action="">
			<?php wp_nonce_field( 'meowpack_full_scan_action' ); ?>
			<input type="hidden" name="meowpack_run_full_scan" value="1" />
			<button type="submit" class="button button-primary button-large" style="margin-top:16px;" onclick="return confirm('<?php esc_attr_e( 'Mulai pemindaian? Proses ini akan memakan waktu bergantung pada ukuran database.', 'meowpack' ); ?>');">
				🚀 <?php esc_html_e( 'Mulai Scan Seluruh Database', 'meowpack' ); ?>
			</button>
		</form>

		<?php if ( $scan_completed ) : ?>
			<div class="meowpack-card" style="margin-top:24px;">
				<h3 style="color:#a6e3a1;">✅ <?php esc_html_e( 'Pemindaian Selesai', 'meowpack' ); ?></h3>
				<?php if ( empty( $scan_results ) ) : ?>
					<p style="color:#22c55e; font-weight:600;">🎉 Sempurna! Seluruh database WordPress terbebas dari kata kunci berbahaya.</p>
				<?php else : ?>
					<p>⚠️ Ditemukan <strong><?php echo count( $scan_results ); ?></strong> ancaman.</p>
					<table class="widefat striped meowpack-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Nama Tabel', 'meowpack' ); ?></th>
								<th><?php esc_html_e( 'Identitas Baris', 'meowpack' ); ?></th>
								<th><?php esc_html_e( 'Kata Kunci', 'meowpack' ); ?></th>
								<th><?php esc_html_e( 'Aksi / Lacak', 'meowpack' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $scan_results as $item ) : ?>
							<tr>
								<td><span class="meowpack-badge" style="background:#f1f5f9; color:#475569;"><?php echo esc_html( $item['type'] ); ?></span></td>
								<td style="font-family:monospace; color:#334155;"><?php echo esc_html( $item['title'] ); ?></td>
								<td><code style="background:#fee2e2;color:#b91c1c;padding:2px 6px;border-radius:4px;"><?php echo esc_html( $item['keyword'] ); ?></code> <small style="color:#6c7086">(<?php echo esc_html( $item['category'] ); ?>)</small></td>
								<td>
									<?php if ( $item['link'] !== '#' ) : ?>
										<a href="<?php echo esc_url( $item['link'] ); ?>" target="_blank" class="button button-small"><?php echo esc_html( $item['linkLabel'] ); ?></a>
									<?php else : ?>
										<span style="color:#6c7086;font-size:0.85em;">Cek di menu Kategori / Tag</span>
									<?php endif; ?>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php elseif ( 'cloud' === $tab ) : ?>
	<!-- =====================================================================
	     TAB: CLOUD SYNC
	===================================================================== -->
	<div class="meowpack-card" style="text-align:center; padding:40px;">
		<div style="font-size:4em; margin-bottom:20px;">🌍</div>
		<h2><?php esc_html_e( 'Sinkronisasi Kamus Cloud', 'meowpack' ); ?></h2>
		<p class="description" style="max-width:600px; margin:0 auto 30px;">
			<?php esc_html_e( 'Ambil daftar kata kunci malware, webshell, judi online, dan blacklist domain terbaru langsung dari repositori GitHub komunitas MeowPack.', 'meowpack' ); ?>
		</p>

		<div id="sync-status" style="margin-bottom:20px; font-weight:600; display:none;"></div>

		<button type="button" id="btn-sync-cloud" class="button button-primary button-large">
			🔄 <?php esc_html_e( 'Sinkronisasi Sekarang', 'meowpack' ); ?>
		</button>

		<div style="margin-top:40px; color:#6c7086; font-size:0.9em;">
			<p><strong>Sumber Data:</strong><br>
			GitHub: <code>akbarbahaulloh/meowpack</code></p>
		</div>
	</div>

	<script>
	jQuery(document).ready(function($) {
		$('#btn-sync-cloud').on('click', function() {
			const btn = $(this);
			const status = $('#sync-status');

			btn.prop('disabled', true).text('⌛ Sinkronisasi...');
			status.show().html('⏳ Menghubungi GitHub...').css('color', '#cdd6f4');

			$.post(ajaxurl, {
				action: 'meowpack_sync_cloud',
				nonce: '<?php echo esc_js( wp_create_nonce( "meowpack_sync_cloud" ) ); ?>'
			}, function(res) {
				btn.prop('disabled', false).text('🔄 Sinkronisasi Sekarang');
				if (res.success) {
					status.html(`✅ Berhasil! Mengimpor ${res.data.keywords} kata kunci dan ${res.data.domains} domain blacklist.`).css('color', '#a6e3a1');
					if (res.data.errors.length > 0) {
						console.error(res.data.errors);
					}
				} else {
					status.html('❌ Gagal sinkronisasi: ' + res.data).css('color', '#f38ba8');
				}
			});
		});
	});
	</script>
	<?php endif; ?>
</div>
