<?php
/**
 * Admin page: AI Bot Manager.
 *
 * @package MeowPack
 */
defined( 'ABSPATH' ) || exit;

$period = isset( $_GET['period'] ) ? sanitize_key( $_GET['period'] ) : 'month';
$bot_stats = MeowPack_AI_Bot_Manager::get_bot_stats( $period, 50 );
$all_rules = MeowPack_AI_Bot_Manager::get_all_rules();

$period_labels = array(
	'today'   => __( 'Hari Ini', 'meowpack' ),
	'week'    => __( 'Minggu Ini', 'meowpack' ),
	'month'   => __( 'Bulan Ini', 'meowpack' ),
	'alltime' => __( 'Semua Waktu', 'meowpack' ),
);

$action_labels = array(
	'allow'          => __( 'Izinkan', 'meowpack' ),
	'stats_only'     => __( 'Statistik Saja', 'meowpack' ),
	'block'          => __( 'Blokir (403)', 'meowpack' ),
	'block_redirect' => __( 'Blokir + Redirect', 'meowpack' ),
);

$action_colors = array(
	'allow'          => '#a6e3a1',
	'stats_only'     => '#89dceb',
	'block'          => '#f38ba8',
	'block_redirect' => '#fab387',
);
?>
<div class="wrap meowpack-wrap">
	<h1>🤖 <?php esc_html_e( 'AI Bot Manager', 'meowpack' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Pantau dan kontrol bot AI yang mengunjungi situs Anda. Blokir scraper AI atau biarkan mereka lewat — pilihan ada di tangan Anda.', 'meowpack' ); ?></p>

	<!-- =====================================================================
	     TAB: STATISTIK BOT
	===================================================================== -->
	<h2 class="nav-tab-wrapper" style="margin-bottom:20px;">
		<a href="?page=meowpack-bot-manager&tab=stats" class="nav-tab <?php echo ( ! isset( $_GET['tab'] ) || 'stats' === $_GET['tab'] ) ? 'nav-tab-active' : ''; ?>">
			📊 <?php esc_html_e( 'Statistik Kunjungan Bot', 'meowpack' ); ?>
		</a>
		<a href="?page=meowpack-bot-manager&tab=rules" class="nav-tab <?php echo ( isset( $_GET['tab'] ) && 'rules' === $_GET['tab'] ) ? 'nav-tab-active' : ''; ?>">
			⚙️ <?php esc_html_e( 'Aturan Bot', 'meowpack' ); ?>
		</a>
		<a href="?page=meowpack-bot-manager&tab=add" class="nav-tab <?php echo ( isset( $_GET['tab'] ) && 'add' === $_GET['tab'] ) ? 'nav-tab-active' : ''; ?>">
			➕ <?php esc_html_e( 'Tambah Bot Kustom', 'meowpack' ); ?>
		</a>
	</h2>

	<?php $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'stats'; ?>

	<?php if ( 'stats' === $tab ) : ?>
	<!-- === TAB: STATS === -->
	<div style="margin-bottom:16px;">
		<strong><?php esc_html_e( 'Periode:', 'meowpack' ); ?></strong>
		<?php foreach ( $period_labels as $key => $label ) : ?>
			<a href="?page=meowpack-bot-manager&tab=stats&period=<?php echo esc_attr( $key ); ?>"
			   style="margin-left:8px; font-weight:<?php echo $period === $key ? '700' : '400'; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</div>

	<?php if ( empty( $bot_stats ) ) : ?>
		<div class="meowpack-notice"><?php esc_html_e( 'Belum ada data kunjungan bot untuk periode ini.', 'meowpack' ); ?></div>
	<?php else : ?>
	<table class="widefat striped meowpack-table">
		<thead>
			<tr>
				<th>#</th>
				<th><?php esc_html_e( 'Bot Name', 'meowpack' ); ?></th>
				<th><?php esc_html_e( 'Tipe', 'meowpack' ); ?></th>
				<th><?php esc_html_e( 'Total Kunjungan', 'meowpack' ); ?></th>
				<th><?php esc_html_e( 'Aksi Cepat', 'meowpack' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $bot_stats as $i => $row ) : ?>
			<tr>
				<td><?php echo esc_html( $i + 1 ); ?></td>
				<td><strong><?php echo esc_html( $row['bot_name'] ); ?></strong></td>
				<td>
					<span class="meowpack-badge" style="background:<?php echo 'ai_bot' === $row['bot_type'] ? '#cba6f7' : '#89b4fa'; ?>">
						<?php echo esc_html( $row['bot_type'] ?? 'unknown' ); ?>
					</span>
				</td>
				<td><strong><?php echo esc_html( number_format_i18n( (int) $row['total_visits'] ) ); ?></strong></td>
				<td>
					<a href="?page=meowpack-bot-manager&tab=rules&highlight=<?php echo urlencode( $row['bot_name'] ); ?>"
					   class="button button-small"><?php esc_html_e( 'Kelola Aturan', 'meowpack' ); ?></a>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>

	<?php elseif ( 'rules' === $tab ) : ?>
	<!-- === TAB: RULES === -->
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'meowpack_save_bot_rules', 'meowpack_bot_rules_nonce' ); ?>
		<input type="hidden" name="action" value="meowpack_save_bot_rules" />

		<table class="widefat striped meowpack-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Bot Name', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'UA Pattern', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Tipe', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Aksi', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Aktif?', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Hapus?', 'meowpack' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $all_rules as $rule ) : ?>
				<tr id="bot-rule-<?php echo esc_attr( $rule['id'] ); ?>">
					<td><strong><?php echo esc_html( $rule['bot_name'] ); ?></strong></td>
					<td><code><?php echo esc_html( $rule['user_agent_pattern'] ); ?></code></td>
					<td>
						<span class="meowpack-badge" style="background:<?php echo 'ai_bot' === $rule['bot_type'] ? '#cba6f7' : '#89b4fa'; ?>">
							<?php echo esc_html( $rule['bot_type'] ); ?>
						</span>
					</td>
					<td>
						<select name="bot_action[<?php echo esc_attr( $rule['id'] ); ?>]" style="width:100%">
							<?php foreach ( $action_labels as $act_key => $act_label ) : ?>
								<option value="<?php echo esc_attr( $act_key ); ?>"
									<?php selected( $rule['action'], $act_key ); ?>
									style="background:<?php echo esc_attr( $action_colors[ $act_key ] ?? '#cdd6f4' ); ?>">
									<?php echo esc_html( $act_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
					<td style="text-align:center">
						<input type="checkbox" name="bot_active[<?php echo esc_attr( $rule['id'] ); ?>]" value="1"
							<?php checked( $rule['is_active'], 1 ); ?> />
					</td>
					<td style="text-align:center">
						<input type="checkbox" name="bot_delete[]" value="<?php echo esc_attr( $rule['id'] ); ?>" />
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Simpan Aturan', 'meowpack' ); ?></button></p>
	</form>

	<?php elseif ( 'add' === $tab ) : ?>
	<!-- === TAB: ADD CUSTOM BOT === -->
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'meowpack_add_bot_rule', 'meowpack_add_bot_nonce' ); ?>
		<input type="hidden" name="action" value="meowpack_add_bot_rule" />

		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Nama Bot', 'meowpack' ); ?></th>
				<td><input type="text" name="bot_name" class="regular-text" required placeholder="e.g. MyCustomBot" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Pola User-Agent', 'meowpack' ); ?></th>
				<td>
					<input type="text" name="ua_pattern" class="regular-text" required placeholder="e.g. MyCustomBot" />
					<p class="description"><?php esc_html_e( 'String yang ada di dalam User-Agent bot tersebut (case-insensitive).', 'meowpack' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Tipe Bot', 'meowpack' ); ?></th>
				<td>
					<select name="bot_type">
						<option value="ai_bot">AI Bot</option>
						<option value="crawler">Crawler</option>
						<option value="scraper">Scraper</option>
						<option value="spam">Spam Bot</option>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Aksi', 'meowpack' ); ?></th>
				<td>
					<select name="bot_action">
						<?php foreach ( $action_labels as $act_key => $act_label ) : ?>
							<option value="<?php echo esc_attr( $act_key ); ?>"><?php echo esc_html( $act_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>

		<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Tambah Bot', 'meowpack' ); ?></button></p>
	</form>
	<?php endif; ?>
</div>
