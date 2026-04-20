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
			array( $this, 'page_dashboard' ),
			'data:image/svg+xml;base64,' . base64_encode( '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z" fill="#a7aaad"/></svg>' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			25
		);

		add_submenu_page( 'meowpack', __( 'Dashboard', 'meowpack' ),       __( 'Dashboard', 'meowpack' ),       'manage_options', 'meowpack',              array( $this, 'page_dashboard' ) );
		add_submenu_page( 'meowpack', __( 'Pengaturan', 'meowpack' ),      __( 'Pengaturan', 'meowpack' ),      'manage_options', 'meowpack-settings',     array( $this, 'page_settings' ) );
		add_submenu_page( 'meowpack', __( 'Auto Share', 'meowpack' ),      __( 'Auto Share', 'meowpack' ),      'manage_options', 'meowpack-autoshare',    array( $this, 'page_autoshare' ) );
		add_submenu_page( 'meowpack', __( 'Import Jetpack', 'meowpack' ),  __( 'Import Jetpack', 'meowpack' ),  'manage_options', 'meowpack-importer',     array( $this, 'page_importer' ) );
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
			'meowpack-admin',
			MEOWPACK_URL . 'admin/assets/admin.js',
			array( 'wp-api-fetch' ),
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
				'strings' => array(
					'importing'  => __( 'Mengimpor...', 'meowpack' ),
					'done'       => __( 'Selesai!', 'meowpack' ),
					'error'      => __( 'Terjadi kesalahan.', 'meowpack' ),
					'noData'     => __( 'Tidak ada data Jetpack yang ditemukan.', 'meowpack' ),
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
		);

		foreach ( $fields as $key => $sanitizer ) {
			if ( isset( $_POST[ $key ] ) ) {
				$value = call_user_func( $sanitizer, wp_unslash( $_POST[ $key ] ) );
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
			'twitter'   => array( 'api_key', 'api_secret', 'access_token_key', 'access_secret', 'message_template' ),
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
	 * Dashboard page.
	 */
	public function page_dashboard() {
		require_once MEOWPACK_DIR . 'admin/views/page-dashboard.php';
	}

	/**
	 * General settings page.
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

	/**
	 * Importer page.
	 */
	public function page_importer() {
		require_once MEOWPACK_DIR . 'admin/views/page-importer.php';
	}
}

// Instantiate on admin_menu.
add_action( 'admin_menu', function () {
	new MeowPack_Admin();
}, 5 );
