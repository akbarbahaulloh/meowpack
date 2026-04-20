<?php
/**
 * Database class — mengelola instalasi dan upgrade tabel MeowPack.
 *
 * @package MeowPack
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MeowPack_Database
 *
 * Handles database table creation and upgrades.
 */
class MeowPack_Database {

	/** @var string Current DB schema version. */
	const SCHEMA_VERSION = '1.0.0';

	/** @var string Option key for stored schema version. */
	const SCHEMA_OPTION = 'meowpack_db_version';

	/**
	 * Run install/upgrade if needed.
	 */
	public static function install() {
		$installed = get_option( self::SCHEMA_OPTION, '0' );

		if ( version_compare( $installed, self::SCHEMA_VERSION, '<' ) ) {
			self::create_tables();
			update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION );
		}
	}

	/**
	 * Create or upgrade all MeowPack tables using dbDelta.
	 */
	public static function create_tables() {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Table: raw visits (retained for 30 days, then aggregated).
		$sql_visits = "CREATE TABLE {$wpdb->prefix}meow_visits (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			visit_date DATE NOT NULL,
			visit_hour TINYINT UNSIGNED NOT NULL DEFAULT 0,
			ip_hash VARCHAR(64) NOT NULL,
			source_type VARCHAR(20) NOT NULL DEFAULT 'direct',
			source_name VARCHAR(100) DEFAULT NULL,
			utm_source VARCHAR(100) DEFAULT NULL,
			utm_medium VARCHAR(100) DEFAULT NULL,
			utm_campaign VARCHAR(100) DEFAULT NULL,
			country_code CHAR(2) DEFAULT NULL,
			is_bot TINYINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			INDEX idx_post_date (post_id, visit_date),
			INDEX idx_date (visit_date),
			INDEX idx_bot (is_bot)
		) $charset;";

		// Table: daily aggregated stats (kept forever).
		$sql_daily = "CREATE TABLE {$wpdb->prefix}meow_daily_stats (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			stat_date DATE NOT NULL,
			post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			unique_visitors INT UNSIGNED NOT NULL DEFAULT 0,
			total_views INT UNSIGNED NOT NULL DEFAULT 0,
			source_direct INT UNSIGNED NOT NULL DEFAULT 0,
			source_search INT UNSIGNED NOT NULL DEFAULT 0,
			source_social INT UNSIGNED NOT NULL DEFAULT 0,
			source_referral INT UNSIGNED NOT NULL DEFAULT 0,
			source_email INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY unique_date_post (stat_date, post_id),
			INDEX idx_date (stat_date),
			INDEX idx_post (post_id)
		) $charset;";

		// Table: auto-share logs.
		$sql_share_logs = "CREATE TABLE {$wpdb->prefix}meow_share_logs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL,
			platform VARCHAR(30) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			response_code SMALLINT UNSIGNED DEFAULT NULL,
			retry_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
			shared_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			INDEX idx_post_platform (post_id, platform),
			INDEX idx_status (status),
			INDEX idx_created (created_at)
		) $charset;";

		// Table: social media tokens (encrypted).
		$sql_social_tokens = "CREATE TABLE {$wpdb->prefix}meow_social_tokens (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			platform VARCHAR(30) NOT NULL,
			access_token TEXT DEFAULT NULL,
			token_data LONGTEXT DEFAULT NULL,
			expires_at DATETIME DEFAULT NULL,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_platform (platform)
		) $charset;";

		// Table: plugin settings (key-value).
		$sql_settings = "CREATE TABLE {$wpdb->prefix}meow_settings (
			setting_key VARCHAR(100) NOT NULL,
			setting_value LONGTEXT DEFAULT NULL,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (setting_key)
		) $charset;";

		dbDelta( $sql_visits );
		dbDelta( $sql_daily );
		dbDelta( $sql_share_logs );
		dbDelta( $sql_social_tokens );
		dbDelta( $sql_settings );

		// Seed default settings if not already present.
		self::seed_defaults();
	}

	/**
	 * Insert default settings into the settings table.
	 */
	private static function seed_defaults() {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_settings';

		$defaults = array(
			'enable_tracking'        => '1',
			'enable_view_counter'    => '1',
			'enable_share_buttons'   => '1',
			'enable_autoshare'       => '0',
			'enable_widget'          => '1',
			'share_button_position'  => 'after',
			'share_button_style'     => 'icon-text',
			'number_format'          => 'id',
			'data_retention_days'    => '30',
			'exclude_admins'         => '1',
			'track_post_types'       => 'post,page',
			'share_platforms'        => 'facebook,twitter,telegram,whatsapp',
			'autoshare_platforms'    => 'telegram',
			'autoshare_delay_hours'  => '0',
		);

		foreach ( $defaults as $key => $value ) {
			$existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare( "SELECT setting_key FROM {$table} WHERE setting_key = %s", $key )
			);
			if ( null === $existing ) {
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$table,
					array(
						'setting_key'   => $key,
						'setting_value' => $value,
					),
					array( '%s', '%s' )
				);
			}
		}
	}

	/**
	 * Get a setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if not found.
	 * @return mixed
	 */
	public static function get_setting( $key, $default = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_settings';

		$value = wp_cache_get( 'meowpack_setting_' . $key, 'meowpack' );
		if ( false !== $value ) {
			return $value;
		}

		$value = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT setting_value FROM {$table} WHERE setting_key = %s", $key )
		);

		$value = ( null !== $value ) ? $value : $default;
		wp_cache_set( 'meowpack_setting_' . $key, $value, 'meowpack', 300 );

		return $value;
	}

	/**
	 * Update a setting value.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 * @return bool
	 */
	public static function update_setting( $key, $value ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_settings';

		wp_cache_delete( 'meowpack_setting_' . $key, 'meowpack' );

		$result = $wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'setting_key'   => sanitize_key( $key ),
				'setting_value' => $value,
			),
			array( '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Drop all MeowPack tables (used on uninstall).
	 */
	public static function drop_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'meow_visits',
			$wpdb->prefix . 'meow_daily_stats',
			$wpdb->prefix . 'meow_share_logs',
			$wpdb->prefix . 'meow_social_tokens',
			$wpdb->prefix . 'meow_settings',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	/**
	 * Get all table names keyed by slug.
	 *
	 * @return array<string,string>
	 */
	public static function get_tables() {
		global $wpdb;
		return array(
			'visits'       => $wpdb->prefix . 'meow_visits',
			'daily_stats'  => $wpdb->prefix . 'meow_daily_stats',
			'share_logs'   => $wpdb->prefix . 'meow_share_logs',
			'social_tokens'=> $wpdb->prefix . 'meow_social_tokens',
			'settings'     => $wpdb->prefix . 'meow_settings',
		);
	}
}
