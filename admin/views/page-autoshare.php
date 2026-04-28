<?php
/**
 * Admin view: Auto Share settings page.
 *
 * @package MeowPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$saved    = isset( $_GET['saved'] ) && '1' === $_GET['saved']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$platform = isset( $_GET['platform'] ) ? sanitize_key( $_GET['platform'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$platform_config = array(
	'telegram' => array(
		'label'  => 'Telegram',
		'icon'   => '✈️',
		'fields' => array(
			'access_token' => array( 'label' => 'Bot Token', 'type' => 'password', 'placeholder' => '123456789:ABCdefGhIJKlmNoPQRstuVWX' ),
			'chat_id'      => array( 'label' => 'Chat ID / Channel', 'type' => 'text', 'placeholder' => '@channelname atau -100xxxxxxx' ),
			'message_template' => array( 'label' => 'Template Pesan', 'type' => 'textarea', 'placeholder' => "{title}\n\n{excerpt}\n\n{url}" ),
		),
	),
	'facebook' => array(
		'label'  => 'Facebook',
		'icon'   => '📘',
		'fields' => array(
			'access_token'     => array( 'label' => 'Page Access Token', 'type' => 'password' ),
			'page_id'          => array( 'label' => 'Page ID', 'type' => 'text' ),
			'message_template' => array( 'label' => 'Template Pesan', 'type' => 'textarea', 'placeholder' => '{title} — {sitename}' ),
		),
	),
	'instagram' => array(
		'label'  => 'Instagram',
		'icon'   => '📸',
		'fields' => array(
			'access_token'     => array( 'label' => 'Page Access Token', 'type' => 'password' ),
			'ig_user_id'       => array( 'label' => 'Instagram Business ID', 'type' => 'text' ),
			'message_template' => array( 'label' => 'Caption Template', 'type' => 'textarea', 'placeholder' => "{title}\n\n{url}" ),
		),
	),
	'twitter' => array(
		'label'  => 'X (Twitter)',
		'icon'   => '🐦',
		'fields' => array(
			'api_key'          => array( 'label' => 'API Key (Consumer Key)', 'type' => 'password' ),
			'api_secret'       => array( 'label' => 'API Secret', 'type' => 'password' ),
			'access_token'     => array( 'label' => 'Access Token', 'type' => 'password' ),
			'access_secret'    => array( 'label' => 'Access Token Secret', 'type' => 'password' ),
			'message_template' => array( 'label' => 'Template Pesan (maks 280 karakter)', 'type' => 'textarea', 'placeholder' => '{title} {url}' ),
		),
	),
	'linkedin' => array(
		'label'  => 'LinkedIn',
		'icon'   => '💼',
		'fields' => array(
			'access_token'     => array( 'label' => 'Access Token', 'type' => 'password' ),
			'author'           => array( 'label' => 'Author URN', 'type' => 'text', 'placeholder' => 'urn:li:person:xxxxx' ),
			'message_template' => array( 'label' => 'Template Pesan', 'type' => 'textarea', 'placeholder' => '{title}\n\n{excerpt}\n\n{url}' ),
		),
	),
	'bluesky' => array(
		'label'  => 'Bluesky',
		'icon'   => '🦋',
		'fields' => array(
			'handle'           => array( 'label' => 'Handle', 'type' => 'text', 'placeholder' => 'username.bsky.social' ),
			'password'         => array( 'label' => 'App Password', 'type' => 'password' ),
			'message_template' => array( 'label' => 'Template Pesan', 'type' => 'textarea', 'placeholder' => '{title}\n\n{excerpt}\n\n{url}' ),
		),
	),
	'threads' => array(
		'label'  => 'Threads',
		'icon'   => '🧵',
		'fields' => array(
			'access_token'     => array( 'label' => 'Access Token', 'type' => 'password' ),
			'user_id'          => array( 'label' => 'User ID', 'type' => 'text' ),
			'message_template' => array( 'label' => 'Template Pesan', 'type' => 'textarea', 'placeholder' => '{title}\n\n{url}' ),
		),
	),
	'pinterest' => array(
		'label'  => 'Pinterest',
		'icon'   => '📌',
		'fields' => array(
			'access_token'     => array( 'label' => 'Access Token', 'type' => 'password' ),
			'board_id'         => array( 'label' => 'Board ID', 'type' => 'text' ),
			'message_template' => array( 'label' => 'Deskripsi Pin', 'type' => 'textarea', 'placeholder' => '{title} — {sitename}' ),
		),
	),
	'line' => array(
		'label'  => 'Line Notify',
		'icon'   => '💬',
		'fields' => array(
			'access_token'     => array( 'label' => 'Notify Token', 'type' => 'password' ),
			'message_template' => array( 'label' => 'Template Pesan', 'type' => 'textarea', 'placeholder' => "\n{title}\n{excerpt}\n{url}" ),
		),
	),
	'whatsapp' => array(
		'label'  => 'WhatsApp',
		'icon'   => '📱',
		'fields' => array(
			'access_token'      => array( 'label' => 'Access Token (Meta)', 'type' => 'password' ),
			'phone_number_id'   => array( 'label' => 'Phone Number ID', 'type' => 'text' ),
			'recipient_number'  => array( 'label' => 'Nomor Penerima (dengan kode negara)', 'type' => 'text', 'placeholder' => '628xxxxxxxxx' ),
			'message_template'  => array( 'label' => 'Template Pesan', 'type' => 'textarea', 'placeholder' => "{title}\n\n{excerpt}\n\n{url}" ),
		),
	),
);

// Get current active tab.
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'telegram'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! array_key_exists( $active_tab, $platform_config ) ) {
	$active_tab = 'telegram';
}
?>
<div class="wrap meowpack-admin" id="meowpack-autoshare">
	<h1><?php esc_html_e( '📡 Auto Share — Konfigurasi Platform', 'meowpack' ); ?></h1>

	<?php if ( $saved ) : ?>
	<div class="notice notice-success is-dismissible">
		<p><?php printf( esc_html__( 'Token %s berhasil disimpan.', 'meowpack' ), '<strong>' . esc_html( ucfirst( $platform ) ) . '</strong>' ); ?></p>
	</div>
	<?php endif; ?>

	<div class="notice notice-info" style="margin: 15px 0; padding: 12px 15px; border-left: 4px solid #2271b1;">
		<p style="margin: 0 0 8px 0;">
			<strong>📖 Butuh bantuan setup?</strong> 
			Baca <a href="<?php echo esc_url( MEOWPACK_URL . 'PANDUAN-AUTOSHARE.md' ); ?>" target="_blank" style="font-weight: 600;">Panduan Lengkap Auto Share</a> 
			untuk tutorial step-by-step setiap platform.
		</p>
		<p style="margin: 0; font-size: 13px;">
			🚀 <a href="<?php echo esc_url( MEOWPACK_URL . 'QUICK-START-AUTOSHARE.md' ); ?>" target="_blank">Quick Start (5 menit)</a> | 
			🔧 <a href="<?php echo esc_url( MEOWPACK_URL . 'TROUBLESHOOTING-AUTOSHARE.md' ); ?>" target="_blank">Troubleshooting</a>
		</p>
	</div>

	<p class="meowpack-intro">
		<?php esc_html_e( 'Konfigurasi token untuk setiap platform. Gunakan variabel {title}, {url}, {excerpt}, {tags}, {sitename} di template pesan.', 'meowpack' ); ?>
	</p>

	<!-- Platform Tabs -->
	<nav class="meowpack-platform-tabs">
		<?php foreach ( $platform_config as $slug => $info ) : ?>
		<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'meowpack-autoshare', 'tab' => $slug ), admin_url( 'admin.php' ) ) ); ?>"
			class="meowpack-platform-tab <?php echo $active_tab === $slug ? 'is-active' : ''; ?>"
			id="tab-<?php echo esc_attr( $slug ); ?>">
			<?php echo esc_html( $info['icon'] ); ?> <?php echo esc_html( $info['label'] ); ?>
		</a>
		<?php endforeach; ?>
	</nav>

	<!-- Active Platform Form -->
	<?php
	$config = $platform_config[ $active_tab ];

	// Load existing token data.
	global $wpdb;
	$token_row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}meow_social_tokens WHERE platform = %s", $active_tab ),
		ARRAY_A
	);
	$token_data = array();
	if ( $token_row && ! empty( $token_row['token_data'] ) ) {
		$token_data = json_decode( $token_row['token_data'], true ) ?? array();
	}
	?>
	<div class="meowpack-platform-form">
		<h2><?php echo esc_html( $config['icon'] . ' ' . $config['label'] ); ?></h2>

		<?php if ( 'instagram' === $active_tab ) : ?>
		<div class="notice notice-warning inline" style="margin-bottom: 20px;">
			<p>⚠️ <strong>Penting:</strong> Instagram Graph API mewajibkan postingan memiliki <strong>Gambar Utama (Featured Image)</strong>. Jika tidak ada gambar, sistem akan melewati (skip) proses sharing untuk platform ini.</p>
		</div>
		<?php endif; ?>

		<form method="post" action="">
			<?php wp_nonce_field( 'meowpack_token_save', 'meowpack_token_nonce' ); ?>
			<input type="hidden" name="platform" value="<?php echo esc_attr( $active_tab ); ?>">

			<table class="form-table meowpack-form-table">
				<?php foreach ( $config['fields'] as $field_key => $field ) :
					$field_value = '';
					if ( 'access_token' === $field_key ) {
						$field_value = $token_row ? '••••••••' : ''; // Mask stored token.
					} else {
						$field_value = $token_data[ $field_key ] ?? '';
					}
				?>
				<tr>
					<th><label for="<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
					<td>
						<?php if ( 'textarea' === $field['type'] ) : ?>
						<textarea name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>"
							class="large-text" rows="3"
							placeholder="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>"><?php echo esc_textarea( $field_value ); ?></textarea>
						<?php else : ?>
						<input type="<?php echo esc_attr( $field['type'] ); ?>"
							name="<?php echo esc_attr( $field_key ); ?>"
							id="<?php echo esc_attr( $field_key ); ?>"
							class="regular-text"
							value="<?php echo 'password' === $field['type'] && $field_value ? '••••••••' : esc_attr( $field_value ); ?>"
							placeholder="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>"
							autocomplete="new-password">
						<?php if ( 'access_token' === $field_key && $token_row ) : ?>
						<p class="description"><?php esc_html_e( 'Token tersimpan. Isi ulang untuk memperbarui.', 'meowpack' ); ?></p>
						<?php endif; ?>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary" id="meowpack-save-token-<?php echo esc_attr( $active_tab ); ?>">
					<?php printf( esc_html__( 'Simpan %s', 'meowpack' ), esc_html( $config['label'] ) ); ?>
				</button>

				<?php if ( $token_row && ! empty( $token_row['access_token'] ) ) : ?>
				<button type="button" class="button meowpack-btn-test-share" data-platform="<?php echo esc_attr( $active_tab ); ?>">
					🚀 <?php esc_html_e( 'Kirim Pesan Tes', 'meowpack' ); ?>
				</button>
				<span class="meowpack-badge meowpack-badge--connected">✅ <?php esc_html_e( 'Terhubung', 'meowpack' ); ?></span>
				<?php else : ?>
				<span class="meowpack-badge meowpack-badge--notset">⚠️ <?php esc_html_e( 'Belum dikonfigurasi', 'meowpack' ); ?></span>
				<?php endif; ?>
			</p>
		</form>
	</div>
</div>
