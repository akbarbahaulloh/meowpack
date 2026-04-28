<?php
/**
 * Admin class — registers admin menus, enqueues assets, handles settings forms.
 *
 * @package MeowPack
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MeowPack_Admin
 *
 * All WP admin integration: menus, sub-pages, settings, assets.
 */
class MeowPack_Admin {

	/**
	 * Constructor — hook into admin.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_settings_save' ) );
		add_action( 'admin_init', array( $this, 'handle_token_save' ) );
		add_action( 'admin_init', array( $this, 'handle_csv_export' ) );
		// v2.0.0 new form handlers (admin-post.php).
		add_action( 'admin_post_meowpack_save_hotlink',    array( $this, 'handle_hotlink_save' ) );
		add_action( 'admin_post_meowpack_save_captcha',    array( $this, 'handle_captcha_save' ) );
		add_action( 'admin_post_meowpack_save_bot_rules',  array( $this, 'handle_bot_rules_save' ) );
		add_action( 'admin_post_meowpack_add_bot_rule',    array( $this, 'handle_add_bot_rule' ) );
		// Content moderation handlers.
		add_action( 'admin_post_meowpack_save_content_settings',  array( $this, 'handle_content_settings_save' ) );
		add_action( 'admin_post_meowpack_save_content_rules',     array( $this, 'handle_content_rules_save' ) );
		add_action( 'admin_post_meowpack_add_content_rule',       array( $this, 'handle_add_content_rule' ) );
		add_action( 'admin_post_meowpack_bulk_add_content',       array( $this, 'handle_bulk_add_content' ) );
		add_action( 'admin_post_meowpack_import_content_rules',   array( $this, 'handle_import_content_rules' ) );
		add_action( 'admin_post_meowpack_reset_content_category', array( $this, 'handle_reset_content_category' ) );
		// v2.2.0: Settings sync.
		add_action( 'admin_post_meowpack_import_sync',        array( $this, 'handle_sync_import' ) );

		// AJAX test share.
		add_action( 'wp_ajax_meowpack_test_share', array( $this, 'handle_test_share' ) );
	}

	/**
	 * Register admin menu and sub-menus.
	 */
	public function register_menus() {
		add_menu_page(
			__( 'MeowPack', 'meowpack' ),
			__( 'MeowPack', 'meowpack' ),
			'manage_options',
			'meowpack',
			array( $this, 'page_stats' ),
			'data:image/svg+xml;base64,' . base64_encode( '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z" fill="#a7aaad"/></svg>' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			2.2
		);

		add_submenu_page( 'meowpack', __( 'Statistik', 'meowpack' ),           __( 'Statistik', 'meowpack' ),           'manage_options', 'meowpack',                     array( $this, 'page_stats' ) );
		add_submenu_page( 'meowpack', __( 'Pengaturan', 'meowpack' ),          __( 'Pengaturan', 'meowpack' ),          'manage_options', 'meowpack-settings',            array( $this, 'page_settings' ) );
		add_submenu_page( 'meowpack', __( 'Auto Share', 'meowpack' ),          __( 'Auto Share', 'meowpack' ),          'manage_options', 'meowpack-autoshare',           array( $this, 'page_autoshare' ) );
		add_submenu_page( 'meowpack', __( 'AI Bot Manager', 'meowpack' ),      __( 'AI Bot Manager', 'meowpack' ),      'manage_options', 'meowpack-bot-manager',        array( $this, 'page_bot_manager' ) );
		add_submenu_page( 'meowpack', __( 'Anti-Hotlink', 'meowpack' ),        __( 'Anti-Hotlink', 'meowpack' ),        'manage_options', 'meowpack-hotlink',               array( $this, 'page_hotlink' ) );
		add_submenu_page( 'meowpack', __( 'Captcha', 'meowpack' ),             __( 'Captcha', 'meowpack' ),             'manage_options', 'meowpack-captcha',              array( $this, 'page_captcha' ) );
		add_submenu_page( 'meowpack', __( 'Filter Konten', 'meowpack' ),       __( 'Filter Konten', 'meowpack' ),       'manage_options', 'meowpack-content-moderation',  array( $this, 'page_content_moderation' ) );
		add_submenu_page( 'meowpack', __( 'Malware Scanner', 'meowpack' ),     __( 'Malware Scanner', 'meowpack' ),      'manage_options', 'meowpack-malware-scanner',      array( $this, 'page_malware_scanner' ) );
	}

	/**
	 * Enqueue admin CSS and JS only on MeowPack pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		$meowpack_pages = array(
			'toplevel_page_meowpack',
			'meowpack_page_meowpack-settings',
			'meowpack_page_meowpack-autoshare',
			'meowpack_page_meowpack-importer',
			// v2.0.0 new pages.
			'meowpack_page_meowpack-device-stats',
			'meowpack_page_meowpack-location-stats',
			'meowpack_page_meowpack-author-stats',
			'meowpack_page_meowpack-click-tracker',
			'meowpack_page_meowpack-bot-manager',
			'meowpack_page_meowpack-hotlink',
			'meowpack_page_meowpack-captcha',
			'meowpack_page_meowpack-content-moderation',
			'meowpack_page_meowpack-malware-scanner',
		);

		if ( ! in_array( $hook, $meowpack_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'meowpack-admin',
			MEOWPACK_URL . 'admin/assets/admin.css',
			array(),
			MEOWPACK_VERSION
		);

		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js',
			array(),
			'4.4.3',
			true
		);

		wp_enqueue_script(
			'meowpack-admin',
			MEOWPACK_URL . 'admin/assets/admin.js',
			array( 'wp-api-fetch', 'chartjs' ),
			MEOWPACK_VERSION,
			true
		);

		wp_localize_script(
			'meowpack-admin',
			'meowpackAdmin',
			array(
				'apiBase'   => rest_url( 'meowpack/v1/' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'exportUrl' => admin_url( 'admin.php?page=meowpack&meowpack_export=1' ),
				'exportNonce' => wp_create_nonce( 'meowpack_csv_export' ),
				'testNonce'   => wp_create_nonce( 'meowpack_test_share' ),
				'strings' => array(
					'error'      => __( 'Terjadi kesalahan.', 'meowpack' ),
				),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Settings Save Handlers
	// -------------------------------------------------------------------------

	/**
	 * Handle general settings form submission.
	 */
	public function handle_settings_save() {
		if ( ! isset( $_POST['meowpack_settings_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['meowpack_settings_nonce'] ) ), 'meowpack_settings_save' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$fields = array(
			'enable_tracking'       => 'absint',
			'enable_view_counter'   => 'absint',
			'enable_share_buttons'  => 'absint',
			'enable_autoshare'      => 'absint',
			'enable_widget'         => 'absint',
			'share_button_position' => 'sanitize_text_field',
			'share_button_style'    => 'sanitize_text_field',
			'number_format'         => 'sanitize_text_field',
			'data_retention_days'   => 'absint',
			'exclude_admins'        => 'absint',
			'track_post_types'      => 'sanitize_text_field',
			'share_platforms'       => 'sanitize_text_field',
			'autoshare_platforms'   => 'sanitize_text_field',
			'autoshare_delay_hours' => 'absint',
			// Frontend
			'show_post_meta_bar'    => 'sanitize_text_field',
			'show_views_on'         => 'array',
			'show_reading_time_on'  => 'array',
			'show_share_buttons_on' => 'array',
			'show_toc'              => 'sanitize_text_field',
			'enable_related_posts'  => 'absint',
			'views_format_text'        => 'sanitize_textarea_field',
			'reading_time_format_text' => 'sanitize_textarea_field',
		);

		// Handle missing array fields (when all checkboxes are unchecked)
		$array_fields = array( 'track_post_types', 'show_views_on', 'show_reading_time_on', 'show_share_buttons_on' );
		foreach ( $array_fields as $array_key ) {
			if ( ! isset( $_POST[ $array_key ] ) ) {
				MeowPack_Database::update_setting( $array_key, '' );
			}
		}

		foreach ( $fields as $key => $sanitizer ) {
			if ( isset( $_POST[ $key ] ) ) {
				$val_raw = wp_unslash( $_POST[ $key ] );
				if ( is_array( $val_raw ) ) {
					$value = implode( ',', array_map( 'sanitize_text_field', $val_raw ) );
				} else {
					$value = ( 'array' === $sanitizer ) ? '' : call_user_func( $sanitizer, $val_raw );
				}
				MeowPack_Database::update_setting( $key, $value );
			} elseif ( in_array( $sanitizer, array( 'absint' ), true ) ) {
				// Unchecked checkboxes won't be in POST.
				MeowPack_Database::update_setting( $key, '0' );
			}
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'meowpack-settings', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handle social media token form submission.
	 */
	public function handle_token_save() {
		if ( ! isset( $_POST['meowpack_token_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['meowpack_token_nonce'] ) ), 'meowpack_token_save' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$platform = sanitize_key( $_POST['platform'] ?? '' );
		if ( ! in_array( $platform, MeowPack_AutoShare::$platforms, true ) ) {
			return;
		}

		$access_token = sanitize_text_field( wp_unslash( $_POST['access_token'] ?? '' ) );
		$token_data   = array();

		// Platform-specific extra fields.
		$extra_fields = array(
			'telegram'  => array( 'chat_id', 'message_template' ),
			'facebook'  => array( 'page_id', 'message_template' ),
			'instagram' => array( 'ig_user_id', 'message_template' ),
			'twitter'   => array( 'api_key', 'api_secret', 'access_secret', 'message_template' ),
			'linkedin'  => array( 'author', 'message_template' ),
			'bluesky'   => array( 'handle', 'password', 'message_template' ),
			'threads'   => array( 'user_id', 'message_template' ),
			'pinterest' => array( 'board_id', 'message_template' ),
			'line'      => array( 'message_template' ),
			'whatsapp'  => array( 'phone_number_id', 'recipient_number', 'message_template' ),
		);

		foreach ( ( $extra_fields[ $platform ] ?? array() ) as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$token_data[ $field ] = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
			}
		}

		global $wpdb;
		$table = $wpdb->prefix . 'meow_social_tokens';

		$encrypted = $access_token ? MeowPack_AutoShare::encrypt_token( $access_token ) : '';

		$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'platform'     => $platform,
				'access_token' => $encrypted,
				'token_data'   => wp_json_encode( $token_data ),
				'updated_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);

		wp_safe_redirect( add_query_arg( array( 'page' => 'meowpack-autoshare', 'saved' => '1', 'platform' => $platform ), admin_url( 'admin.php' ) ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// CSV Export
	// -------------------------------------------------------------------------

	/**
	 * Handle CSV export download.
	 *
	 * Triggered by GET ?page=meowpack&meowpack_export=1&_nonce=...&date_from=...&date_to=...
	 */
	public function handle_csv_export() {
		if ( ! isset( $_GET['meowpack_export'] ) || '1' !== $_GET['meowpack_export'] ) {
			return;
		}

		// Auth check.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Akses ditolak.', 'meowpack' ) );
		}

		// Nonce check.
		$nonce = isset( $_GET['_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'meowpack_csv_export' ) ) {
			wp_die( esc_html__( 'Nonce tidak valid.', 'meowpack' ) );
		}

		$type     = isset( $_GET['export_type'] ) ? sanitize_key( $_GET['export_type'] ) : 'daily';
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$date_to   = isset( $_GET['date_to'] )   ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) )   : gmdate( 'Y-m-d' );

		// Validate date format.
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
			$date_from = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
			$date_to = gmdate( 'Y-m-d' );
		}

		$filename = 'meowpack-' . $type . '-' . $date_from . '-' . $date_to . '.csv';

		// Clean output buffer.
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		// Set CSV headers.
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// BOM for Excel UTF-8 compatibility.
		echo "\xEF\xBB\xBF"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( 'posts' === $type ) {
			$this->export_posts_csv( $output, $date_from, $date_to );
		} elseif ( 'raw' === $type ) {
			$this->export_raw_visits_csv( $output, $date_from, $date_to );
		} else {
			$this->export_daily_csv( $output, $date_from, $date_to );
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Export sitewide daily stats to CSV.
	 *
	 * @param resource $output    File handle (php://output).
	 * @param string   $date_from Start date Y-m-d.
	 * @param string   $date_to   End date Y-m-d.
	 */
	private function export_daily_csv( $output, $date_from, $date_to ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_daily_stats';

		// Header row.
		fputcsv( $output, array(
			'Tanggal',
			'Pengunjung Unik',
			'Total Pageviews',
			'Langsung',
			'Pencarian',
			'Sosial Media',
			'Referral',
			'Email',
		) );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT stat_date, unique_visitors, total_views,
				        source_direct, source_search, source_social, source_referral, source_email
				 FROM {$table}
				 WHERE post_id = 0
				   AND stat_date BETWEEN %s AND %s
				 ORDER BY stat_date ASC",
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		foreach ( $rows as $row ) {
			fputcsv( $output, array(
				$row['stat_date'],
				$row['unique_visitors'],
				$row['total_views'],
				$row['source_direct'],
				$row['source_search'],
				$row['source_social'],
				$row['source_referral'],
				$row['source_email'],
			) );
		}
	}

	/**
	 * Export per-post stats to CSV.
	 *
	 * @param resource $output    File handle.
	 * @param string   $date_from Start date.
	 * @param string   $date_to   End date.
	 */
	private function export_posts_csv( $output, $date_from, $date_to ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_daily_stats';

		fputcsv( $output, array(
			'Post ID',
			'Judul',
			'URL',
			'Total Pageviews',
			'Pengunjung Unik',
		) );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT post_id,
				        SUM(total_views) AS total_views,
				        SUM(unique_visitors) AS unique_visitors
				 FROM {$table}
				 WHERE post_id > 0
				   AND stat_date BETWEEN %s AND %s
				 GROUP BY post_id
				 ORDER BY total_views DESC",
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		foreach ( $rows as $row ) {
			$post_id = absint( $row['post_id'] );
			fputcsv( $output, array(
				$post_id,
				get_the_title( $post_id ) ?: '(Dihapus)',
				get_permalink( $post_id ) ?: '',
				$row['total_views'],
				$row['unique_visitors'],
			) );
		}
	}

	/**
	 * Export raw visits to CSV.
	 *
	 * @param resource $output    File handle.
	 * @param string   $date_from Start date.
	 * @param string   $date_to   End date.
	 */
	private function export_raw_visits_csv( $output, $date_from, $date_to ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_visits';

		fputcsv( $output, array(
			'ID',
			'Post ID',
			'Tanggal',
			'Jam',
			'Sumber Tipe',
			'Sumber Nama',
			'UTM Source',
			'UTM Medium',
			'UTM Campaign',
			'Negara',
			'Bot',
			'Waktu',
		) );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id, post_id, visit_date, visit_hour, source_type, source_name,
				        utm_source, utm_medium, utm_campaign, country_code, is_bot, created_at
				 FROM {$table}
				 WHERE visit_date BETWEEN %s AND %s
				 ORDER BY visit_date ASC, id ASC
				 LIMIT 50000",
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		foreach ( $rows as $row ) {
			fputcsv( $output, array(
				$row['id'],
				$row['post_id'],
				$row['visit_date'],
				$row['visit_hour'],
				$row['source_type'],
				$row['source_name'],
				$row['utm_source'],
				$row['utm_medium'],
				$row['utm_campaign'],
				$row['country_code'],
				$row['is_bot'] ? 'Ya' : 'Tidak',
				$row['created_at'],
			) );
		}
	}

	// -------------------------------------------------------------------------
	// Page Renderers
	// -------------------------------------------------------------------------

	/**
	 * Consolidated Statistics page (with tabs).
	 */
	public function page_stats() {
		require_once MEOWPACK_DIR . 'admin/views/page-stats.php';
	}

	/**
	 * Dashboard view loader (now loaded via page_stats).
	 */
	public function page_dashboard() {
		require_once MEOWPACK_DIR . 'admin/views/page-dashboard.php';
	}

	/**
	 * Callback for Malware Scanner page.
	 */
	public function page_malware_scanner() {
		include MEOWPACK_PATH . 'admin/views/page-malware-scanner.php';
	}

	/**
	 * Callback for Stats page.
	 */
	public function page_settings() {
		require_once MEOWPACK_DIR . 'admin/views/page-settings.php';
	}

	/**
	 * Auto share settings page.
	 */
	public function page_autoshare() {
		require_once MEOWPACK_DIR . 'admin/views/page-autoshare.php';
	}

	// -------------------------------------------------------------------------
	// v2.0.0 New Page Renderers
	// -------------------------------------------------------------------------

	/** Device & Browser stats page. */
	public function page_device_stats() {
		require_once MEOWPACK_DIR . 'admin/views/page-device-stats.php';
	}

	/** Location stats page. */
	public function page_location_stats() {
		require_once MEOWPACK_DIR . 'admin/views/page-location-stats.php';
	}

	/** Author stats page. */
	public function page_author_stats() {
		require_once MEOWPACK_DIR . 'admin/views/page-author-stats.php';
	}

	/** Outbound click tracker page. */
	public function page_click_tracker() {
		require_once MEOWPACK_DIR . 'admin/views/page-click-tracker.php';
	}

	/** AI Bot Manager page. */
	public function page_bot_manager() {
		require_once MEOWPACK_DIR . 'admin/views/page-bot-manager.php';
	}

	/** Anti-Hotlink settings page. */
	public function page_hotlink() {
		require_once MEOWPACK_DIR . 'admin/views/page-hotlink.php';
	}

	/** Captcha settings page. */
	public function page_captcha() {
		require_once MEOWPACK_DIR . 'admin/views/settings-captcha.php';
	}

	// -------------------------------------------------------------------------
	// v2.0.0 New Form Save Handlers
	// -------------------------------------------------------------------------

	/**
	 * Handle anti-hotlink settings form (admin-post.php action).
	 */
	public function handle_hotlink_save() {
		check_admin_referer( 'meowpack_save_hotlink', 'meowpack_hotlink_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Akses ditolak.', 'meowpack' ) );
		}

		$fields = array(
			'enable_anti_hotlink'   => 'absint',
			'hotlink_extensions'    => 'sanitize_text_field',
			'hotlink_response'      => 'sanitize_key',
			'hotlink_redirect_url'  => 'esc_url_raw',
			'hotlink_whitelist'     => 'sanitize_textarea_field',
		);

		foreach ( $fields as $key => $sanitizer ) {
			if ( isset( $_POST[ $key ] ) ) {
				MeowPack_Database::update_setting( $key, call_user_func( $sanitizer, wp_unslash( $_POST[ $key ] ) ) );
			} elseif ( 'absint' === $sanitizer ) {
				MeowPack_Database::update_setting( $key, '0' );
			}
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'meowpack-hotlink', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handle captcha settings form (admin-post.php action).
	 */
	public function handle_captcha_save() {
		check_admin_referer( 'meowpack_save_captcha', 'meowpack_captcha_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Akses ditolak.', 'meowpack' ) );
		}

		$checkbox_fields = array(
			'enable_captcha', 
			'captcha_on_comments', 
			'captcha_on_login',
			'captcha_on_register', 
			'captcha_on_lostpassword', 
			'captcha_on_woo_checkout',
		);
		foreach ( $checkbox_fields as $key ) {
			$value = isset( $_POST[ $key ] ) ? '1' : '0';
			MeowPack_Database::update_setting( $key, $value );
		}

		if ( isset( $_POST['captcha_type'] ) ) {
			MeowPack_Database::update_setting( 'captcha_type', sanitize_key( wp_unslash( $_POST['captcha_type'] ) ) );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'meowpack-captcha', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handle bulk bot rule save (admin-post.php action).
	 */
	public function handle_bot_rules_save() {
		check_admin_referer( 'meowpack_save_bot_rules', 'meowpack_bot_rules_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Akses ditolak.', 'meowpack' ) );
		}

		// Delete marked rules.
		if ( ! empty( $_POST['bot_delete'] ) && is_array( $_POST['bot_delete'] ) ) {
			foreach ( array_map( 'absint', $_POST['bot_delete'] ) as $rule_id ) {
				MeowPack_AI_Bot_Manager::delete_rule( $rule_id );
			}
		}

		// Update actions.
		if ( ! empty( $_POST['bot_action'] ) && is_array( $_POST['bot_action'] ) ) {
			foreach ( $_POST['bot_action'] as $rule_id => $action ) {
				$is_active = isset( $_POST['bot_active'][ $rule_id ] ) ? 1 : 0;
				MeowPack_AI_Bot_Manager::save_rule(
					absint( $rule_id ),
					sanitize_key( $action ),
					'',
					$is_active
				);
			}
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'meowpack-bot-manager', 'tab' => 'rules', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handle adding a custom bot rule (admin-post.php action).
	 */
	public function handle_add_bot_rule() {
		check_admin_referer( 'meowpack_add_bot_rule', 'meowpack_add_bot_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Akses ditolak.', 'meowpack' ) );
		}

		$bot_name   = sanitize_text_field( wp_unslash( $_POST['bot_name'] ?? '' ) );
		$ua_pattern = sanitize_text_field( wp_unslash( $_POST['ua_pattern'] ?? '' ) );
		$bot_type   = sanitize_key( $_POST['bot_type'] ?? 'ai_bot' );
		$bot_action = sanitize_key( $_POST['bot_action'] ?? 'allow' );

		if ( $bot_name && $ua_pattern ) {
			MeowPack_AI_Bot_Manager::add_rule( $bot_name, $ua_pattern, $bot_type, $bot_action );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'meowpack-bot-manager', 'tab' => 'rules', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// v2.1.0 Content Moderation Form Handlers
	// -------------------------------------------------------------------------

	/** Content moderation page. */
	public function page_content_moderation() {
		require_once MEOWPACK_DIR . 'admin/views/page-content-moderation.php';
	}

	/** Save global moderation settings. */
	public function handle_content_settings_save() {
		check_admin_referer( 'meowpack_save_content_settings', 'meowpack_content_settings_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Akses ditolak.', 'meowpack' ) );
		}

		$checkboxes = array(
			'enable_content_moderation',
			'modscan_comments',
			'modscan_usernames',
			'modscan_posts',
			'moderation_notify_admin',
		);

		foreach ( $checkboxes as $key ) {
			MeowPack_Database::update_setting( $key, isset( $_POST[ $key ] ) ? '1' : '0' );
		}

		// Re-init the moderation instance immediately so rules can catch up if just enabled.
		if ( isset( $_POST['enable_content_moderation'] ) && class_exists( 'MeowPack_Core' ) ) {
			$core = MeowPack_Core::get_instance();
			if ( ! $core->content_moderation ) {
				$core->content_moderation = new MeowPack_Content_Moderation();
			}
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'meowpack-content-moderation', 'tab' => 'settings', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Bulk save dictionary rules. */
	public function handle_content_rules_save() {
		check_admin_referer( 'meowpack_save_content_rules', 'meowpack_content_rules_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Akses ditolak.', 'meowpack' ) );
		}

		$cat_filter = isset( $_POST['category_filter'] ) ? sanitize_key( wp_unslash( $_POST['category_filter'] ) ) : '';

		// Delete
		if ( ! empty( $_POST['delete'] ) && is_array( $_POST['delete'] ) ) {
			foreach ( array_map( 'absint', $_POST['delete'] ) as $id ) {
				MeowPack_Content_Moderation::delete_rule( $id );
			}
		}

		// Update
		if ( ! empty( $_POST['action'] ) && is_array( $_POST['action'] ) ) {
			foreach ( $_POST['action'] as $id => $action ) {
				$mode   = isset( $_POST['match_mode'][ $id ] ) ? sanitize_key( $_POST['match_mode'][ $id ] ) : 'substring';
				$active = isset( $_POST['active'][ $id ] ) ? 1 : 0;
				MeowPack_Content_Moderation::update_rule( absint( $id ), $action, $mode, $active );
			}
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'meowpack-content-moderation', 'tab' => 'dictionary', 'category' => $cat_filter, 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Add single keyword. */
	public function handle_add_content_rule() {
		check_admin_referer( 'meowpack_add_content_rule', 'meowpack_add_content_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Akses ditolak.', 'meowpack' ) );
		}

		$keyword    = sanitize_text_field( wp_unslash( $_POST['keyword'] ?? '' ) );
		$category   = sanitize_key( $_POST['category'] ?? 'custom' );
		$action     = sanitize_key( $_POST['action'] ?? 'hold' );
		$match_mode = sanitize_key( $_POST['match_mode'] ?? 'substring' );

		if ( $keyword ) {
			MeowPack_Content_Moderation::add_rule( $keyword, $category, $action, $match_mode );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'meowpack-content-moderation', 'tab' => 'add', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Bulk add keywords via textarea. */
	public function handle_bulk_add_content() {
		check_admin_referer( 'meowpack_bulk_add_content', 'meowpack_bulk_content_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Akses ditolak.', 'meowpack' ) );
		}

		$category = sanitize_key( $_POST['category'] ?? 'custom' );
		$action   = sanitize_key( $_POST['action'] ?? 'hold' );
		$keywords = preg_split( '/\r?\n/', wp_unslash( $_POST['keywords'] ?? '' ) );

		foreach ( $keywords as $kw ) {
			$kw = trim( sanitize_text_field( $kw ) );
			if ( $kw ) {
				MeowPack_Content_Moderation::add_rule( $kw, $category, $action, 'substring' ); // Default substring for bulk
			}
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'meowpack-content-moderation', 'tab' => 'dictionary', 'category' => $category, 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handle AJAX request to test a social media connection.
	 */
	public function handle_test_share() {
		check_ajax_referer( 'meowpack_test_share', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$platform = sanitize_key( $_POST['platform'] ?? '' );
		if ( ! in_array( $platform, MeowPack_AutoShare::$platforms, true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid platform' ) );
		}

		$vars = array(
			'{id}'             => 0,
			'{title}'          => 'Test Post from MeowPack',
			'{url}'            => home_url(),
			'{excerpt}'        => 'Ini adalah pesan tes untuk memastikan koneksi Auto Share Anda sudah benar.',
			'{tags}'           => '#MeowPack #Test',
			'{sitename}'       => get_bloginfo( 'name' ),
			'{featured_image}' => 'https://via.placeholder.com/800x600.png?text=MeowPack+Test',
		);

		// Use the correct class for testing.
		$autoshare = new MeowPack_AutoShare();
		$result = $autoshare->test_connection( $platform, $vars );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => 'Pesan tes berhasil dikirim ke ' . ucfirst( $platform ) . '!' ) );
		} else {
			wp_send_json_error( array( 
				'message' => 'Gagal mengirim pesan tes ke ' . ucfirst( $platform ) . '.',
				'code'    => $result['code'] ?? 'Unknown Error'
			) );
		}
	}

	/** Import CSV. */
	public function handle_import_content_rules() {
		check_admin_referer( 'meowpack_import_content', 'meowpack_import_content_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Akses ditolak.', 'meowpack' ) );
		}

		$csv_raw = isset( $_POST['csv_content'] ) ? wp_unslash( $_POST['csv_content'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		
		if ( $csv_raw ) {
			MeowPack_Content_Moderation::import_csv( $csv_raw );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'meowpack-content-moderation', 'tab' => 'import', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Export CSV. */
	public function handle_export_content_rules() {
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'meowpack_export_content' ) ) {
			wp_die( esc_html__( 'Tautan kadaluarsa.', 'meowpack' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Akses ditolak.', 'meowpack' ) );
		}

		$csv = MeowPack_Content_Moderation::export_csv();

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=meowpack-content-rules-' . gmdate( 'Ymd' ) . '.csv' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Output BOM for Excel UTF-8 compatibility
		echo "\xEF\xBB\xBF"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/** Reset/flush a whole category. */
	public function handle_reset_content_category() {
		check_admin_referer( 'meowpack_reset_content_category', 'meowpack_reset_category_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Akses ditolak.', 'meowpack' ) );
		}

		$category = sanitize_key( $_POST['category'] ?? '' );
		if ( $category ) {
			MeowPack_Content_Moderation::delete_rules_by_category( $category );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'meowpack-content-moderation', 'tab' => 'import', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handle MeowSync config import.
	 */
	public function handle_sync_import() {
		if ( ! isset( $_POST['meowpack_sync_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['meowpack_sync_nonce'] ) ), 'meowpack_sync_import' ) ) {
			wp_die( 'Keamanan tidak valid.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Akses ditolak.' );
		}

		$config_string = trim( $_POST['meowpack_sync_code'] ?? '' );
		if ( empty( $config_string ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'meowpack-settings', 'sync_error' => 'empty' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		$result = MeowPack_Database::import_sync_data( $config_string );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'meowpack-settings', 'sync_error' => $result->get_error_code() ), admin_url( 'admin.php' ) ) );
		} else {
			wp_safe_redirect( add_query_arg( array( 'page' => 'meowpack-settings', 'sync_saved' => '1' ), admin_url( 'admin.php' ) ) );
		}
		exit;
	}
}

// Instantiate immediately.
new MeowPack_Admin();
