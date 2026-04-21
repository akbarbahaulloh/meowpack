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

	/** @var string Database schema version. */
	const SCHEMA_VERSION = '2.4.0';

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

		// -----------------------------------------------------------------------
		// Table: raw visits — retained for N days then aggregated.
		// v2.0.0: added author_id, region, city, device_type, browser, os,
		//         time_on_page, scroll_depth, bot_name.
		// -----------------------------------------------------------------------
		$sql_visits = "CREATE TABLE {$wpdb->prefix}meow_visits (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			author_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			visit_date DATE NOT NULL,
			visit_hour TINYINT UNSIGNED NOT NULL DEFAULT 0,
			ip_hash VARCHAR(64) NOT NULL,
			source_type VARCHAR(20) NOT NULL DEFAULT 'direct',
			source_name VARCHAR(100) DEFAULT NULL,
			utm_source VARCHAR(100) DEFAULT NULL,
			utm_medium VARCHAR(100) DEFAULT NULL,
			utm_campaign VARCHAR(100) DEFAULT NULL,
			country_code CHAR(2) DEFAULT NULL,
			region VARCHAR(100) DEFAULT NULL,
			city VARCHAR(100) DEFAULT NULL,
			device_type VARCHAR(20) DEFAULT NULL,
			browser VARCHAR(50) DEFAULT NULL,
			os VARCHAR(50) DEFAULT NULL,
			time_on_page SMALLINT UNSIGNED DEFAULT NULL,
			scroll_depth TINYINT UNSIGNED DEFAULT NULL,
			is_bot TINYINT UNSIGNED NOT NULL DEFAULT 0,
			bot_name VARCHAR(100) DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			INDEX idx_post_date (post_id, visit_date),
			INDEX idx_date (visit_date),
			INDEX idx_bot (is_bot),
			INDEX idx_author (author_id),
			INDEX idx_device (device_type),
			INDEX idx_country (country_code)
		) $charset;";

		// -----------------------------------------------------------------------
		// Table: daily aggregated stats — kept forever.
		// v2.0.0: added author_id, mobile/tablet/desktop views, avg engagement.
		// -----------------------------------------------------------------------
		$sql_daily = "CREATE TABLE {$wpdb->prefix}meow_daily_stats (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			stat_date DATE NOT NULL,
			post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			author_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			unique_visitors INT UNSIGNED NOT NULL DEFAULT 0,
			total_views INT UNSIGNED NOT NULL DEFAULT 0,
			source_direct INT UNSIGNED NOT NULL DEFAULT 0,
			source_search INT UNSIGNED NOT NULL DEFAULT 0,
			source_social INT UNSIGNED NOT NULL DEFAULT 0,
			source_referral INT UNSIGNED NOT NULL DEFAULT 0,
			source_email INT UNSIGNED NOT NULL DEFAULT 0,
			mobile_views INT UNSIGNED NOT NULL DEFAULT 0,
			tablet_views INT UNSIGNED NOT NULL DEFAULT 0,
			desktop_views INT UNSIGNED NOT NULL DEFAULT 0,
			avg_time_on_page SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			avg_scroll_depth TINYINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY unique_date_post (stat_date, post_id),
			INDEX idx_date (stat_date),
			INDEX idx_post (post_id),
			INDEX idx_author (author_id)
		) $charset;";

		// -----------------------------------------------------------------------
		// Table: auto-share logs.
		// -----------------------------------------------------------------------
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

		// -----------------------------------------------------------------------
		// Table: social media tokens (encrypted).
		// -----------------------------------------------------------------------
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

		// -----------------------------------------------------------------------
		// Table: plugin settings (key-value).
		// -----------------------------------------------------------------------
		$sql_settings = "CREATE TABLE {$wpdb->prefix}meow_settings (
			setting_key VARCHAR(100) NOT NULL,
			setting_value LONGTEXT DEFAULT NULL,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (setting_key)
		) $charset;";

		// -----------------------------------------------------------------------
		// [NEW v2.0.0] Table: outbound click logs.
		// -----------------------------------------------------------------------
		$sql_click_logs = "CREATE TABLE {$wpdb->prefix}meow_click_logs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			url TEXT NOT NULL,
			url_hash VARCHAR(64) NOT NULL,
			anchor_text VARCHAR(255) DEFAULT NULL,
			click_count INT UNSIGNED NOT NULL DEFAULT 1,
			last_clicked DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY unique_url_post (url_hash(64), post_id),
			INDEX idx_post (post_id),
			INDEX idx_click_count (click_count)
		) $charset;";

		// -----------------------------------------------------------------------
		// [NEW v2.0.0] Table: bot blocking rules.
		// -----------------------------------------------------------------------
		$sql_bot_rules = "CREATE TABLE {$wpdb->prefix}meow_bot_rules (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			bot_name VARCHAR(100) NOT NULL,
			user_agent_pattern VARCHAR(200) NOT NULL,
			bot_type VARCHAR(20) NOT NULL DEFAULT 'crawler',
			action VARCHAR(20) NOT NULL DEFAULT 'allow',
			redirect_url VARCHAR(500) DEFAULT NULL,
			is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_pattern (user_agent_pattern)
		) $charset;";

		// -----------------------------------------------------------------------
		// [NEW v2.0.0] Table: aggregated bot visit stats.
		// -----------------------------------------------------------------------
		$sql_bot_stats = "CREATE TABLE {$wpdb->prefix}meow_bot_stats (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			stat_date DATE NOT NULL,
			bot_name VARCHAR(100) NOT NULL,
			bot_type VARCHAR(20) DEFAULT NULL,
			visit_count INT UNSIGNED NOT NULL DEFAULT 0,
			top_pages TEXT DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY unique_date_bot (stat_date, bot_name),
			INDEX idx_date (stat_date),
			INDEX idx_bot_name (bot_name)
		) $charset;";

		// -----------------------------------------------------------------------
		// [NEW v2.0.0] Table: hotlink block log.
		// -----------------------------------------------------------------------
		$sql_hotlink_logs = "CREATE TABLE {$wpdb->prefix}meow_hotlink_logs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			blocked_url VARCHAR(500) NOT NULL DEFAULT '',
			referrer_domain VARCHAR(200) NOT NULL DEFAULT '',
			blocked_date DATE NOT NULL,
			block_count INT UNSIGNED NOT NULL DEFAULT 1,
			last_blocked DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY unique_url_domain (blocked_url(200), referrer_domain),
			INDEX idx_date (blocked_date),
			INDEX idx_domain (referrer_domain)
		) $charset;";

		// -----------------------------------------------------------------------
		// [NEW v2.4.0] Table: post reactions (emojis).
		// -----------------------------------------------------------------------
		$sql_reactions = "CREATE TABLE {$wpdb->prefix}meow_reactions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL,
			reaction_type VARCHAR(20) NOT NULL,
			ip_hash VARCHAR(64) NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			INDEX idx_post_reaction (post_id, reaction_type),
			INDEX idx_ip_post (ip_hash, post_id)
		) $charset;";

		dbDelta( $sql_visits );
		dbDelta( $sql_daily );
		dbDelta( $sql_share_logs );
		dbDelta( $sql_social_tokens );
		dbDelta( $sql_settings );
		dbDelta( $sql_click_logs );
		dbDelta( $sql_bot_rules );
		dbDelta( $sql_bot_stats );
		dbDelta( $sql_hotlink_logs );

		// -----------------------------------------------------------------------
		// [NEW v2.1.0] Table: content moderation keyword dictionary.
		// -----------------------------------------------------------------------
		$sql_content_rules = "CREATE TABLE {$wpdb->prefix}meow_content_rules (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			keyword VARCHAR(200) NOT NULL,
			category VARCHAR(50) NOT NULL DEFAULT 'custom',
			action VARCHAR(20) NOT NULL DEFAULT 'hold',
			match_mode VARCHAR(10) NOT NULL DEFAULT 'substring',
			is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			INDEX idx_category (category),
			INDEX idx_active (is_active)
		) $charset;";

		// -----------------------------------------------------------------------
		// [NEW v2.1.0] Table: content moderation detection log.
		// -----------------------------------------------------------------------
		$sql_content_logs = "CREATE TABLE {$wpdb->prefix}meow_content_logs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			context VARCHAR(30) NOT NULL,
			object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			matched_keyword VARCHAR(200) DEFAULT NULL,
			matched_category VARCHAR(50) DEFAULT NULL,
			action_taken VARCHAR(20) DEFAULT NULL,
			content_excerpt TEXT DEFAULT NULL,
			PRIMARY KEY (id),
			INDEX idx_date (detected_at),
			INDEX idx_context (context),
			INDEX idx_category (matched_category)
		) $charset;";

		// -----------------------------------------------------------------------
		// [NEW v2.2.0] Table: malware & webshell scanner logs.
		// -----------------------------------------------------------------------
		$sql_malware_logs = "CREATE TABLE {$wpdb->prefix}meow_malware_logs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			file_path VARCHAR(500) NOT NULL,
			signature VARCHAR(200) NOT NULL,
			category VARCHAR(50) NOT NULL DEFAULT 'malware',
			status VARCHAR(20) NOT NULL DEFAULT 'detected',
			PRIMARY KEY (id),
			INDEX idx_status (status),
			INDEX idx_category (category)
		) $charset;";

		dbDelta( $sql_content_rules );
		dbDelta( $sql_content_logs );
		dbDelta( $sql_malware_logs );
		dbDelta( $sql_reactions );

		// Seed default settings.
		self::seed_defaults();

		// Seed default AI bot rules.
		self::seed_bot_rules();

		// Seed default content moderation keywords.
		self::seed_content_rules();
	}

	/**
	 * Insert default settings into the settings table.
	 */
	private static function seed_defaults() {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_settings';

		$defaults = array(
			'enable_tracking'          => '1',
			'enable_view_counter'      => '1',
			'enable_share_buttons'     => '1',
			'enable_autoshare'         => '0',
			'enable_widget'            => '1',
			'share_button_position'    => 'after',
			'share_button_style'       => 'icon-text',
			'number_format'            => 'id',
			'data_retention_days'      => '30',
			'exclude_admins'           => '1',
			'track_post_types'         => 'post,page',
			'share_platforms'          => 'facebook,twitter,telegram,whatsapp',
			'autoshare_platforms'      => 'telegram',
			'autoshare_delay_hours'    => '0',
			// v2.0.0 new settings.
			'enable_click_tracker'     => '1',
			'enable_reading_time'      => '1',
			'enable_anti_hotlink'      => '0',
			'hotlink_response'         => 'placeholder',
			'hotlink_extensions'       => 'jpg,jpeg,png,gif,webp',
			'hotlink_whitelist'        => '',
			'enable_captcha'           => '0',
			'captcha_type'             => 'math',
			'captcha_on_comments'      => '1',
			'captcha_on_login'         => '0',
			'captcha_on_register'      => '0',
			'captcha_on_lostpassword'  => '0',
			'ai_bot_default_action'    => 'allow',
			// v2.1.0 content moderation.
			'enable_content_moderation' => '0',
			'modscan_comments'          => '1',
			'modscan_usernames'         => '1',
			'modscan_posts'             => '0',
			'moderation_notify_admin'   => '1',
			// Frontend Enhancers
			'show_post_meta_bar'        => 'top',
			'show_views_on'             => 'post,page',
			'show_reading_time_on'      => 'post,page',
			'show_share_buttons_on'     => 'post,page',
			'show_toc'                  => 'auto',
			'enable_related_posts'      => '1',
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
	 * Seed default AI bot rules if table is empty.
	 */
	private static function seed_bot_rules() {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_bot_rules';

		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		if ( $count > 0 ) {
			return;
		}

		$default_bots = array(
			// AI / LLM bots.
			array( 'GPTBot',              'GPTBot',              'ai_bot', 'allow' ),
			array( 'ChatGPT-User',        'ChatGPT-User',        'ai_bot', 'allow' ),
			array( 'ClaudeBot',           'ClaudeBot',           'ai_bot', 'allow' ),
			array( 'anthropic-ai',        'anthropic-ai',        'ai_bot', 'allow' ),
			array( 'Google-Extended',     'Google-Extended',     'ai_bot', 'allow' ),
			array( 'PerplexityBot',       'PerplexityBot',       'ai_bot', 'allow' ),
			array( 'CCBot',               'CCBot',               'ai_bot', 'allow' ),
			array( 'Amazonbot',           'Amazonbot',           'ai_bot', 'allow' ),
			array( 'FacebookBot',         'FacebookBot',         'ai_bot', 'allow' ),
			array( 'Applebot-Extended',   'Applebot-Extended',   'ai_bot', 'allow' ),
			array( 'Bytespider',          'Bytespider',          'ai_bot', 'allow' ),
			array( 'Diffbot',             'Diffbot',             'ai_bot', 'allow' ),
			array( 'cohere-ai',           'cohere-ai',           'ai_bot', 'allow' ),
			array( 'YouBot',              'YouBot',              'ai_bot', 'allow' ),
			array( 'Timpibot',            'Timpibot',            'ai_bot', 'allow' ),
			array( 'omgilibot',           'omgilibot',           'ai_bot', 'allow' ),
			array( 'DataForSeoBot',       'DataForSeoBot',       'ai_bot', 'allow' ),
			array( 'ImagesiftBot',        'ImagesiftBot',        'ai_bot', 'allow' ),
			array( 'PetalBot',            'PetalBot',            'ai_bot', 'allow' ),
			array( 'Scrapy',              'Scrapy',              'scraper', 'allow' ),
		);

		foreach ( $default_bots as $bot ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'bot_name'           => $bot[0],
					'user_agent_pattern' => $bot[1],
					'bot_type'           => $bot[2],
					'action'             => $bot[3],
					'is_active'          => 1,
				),
				array( '%s', '%s', '%s', '%s', '%d' )
			);
		}
	}

	/**
	 * Seed default content moderation keywords if table is empty.
	 */
	private static function seed_content_rules() {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_content_rules';

		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		if ( $count > 0 ) {
			return;
		}

		// Class may not yet be loaded during bare activation — require it.
		if ( ! class_exists( 'MeowPack_Content_Moderation' ) ) {
			$path = MEOWPACK_DIR . 'includes/class-meowpack-content-moderation.php';
			if ( file_exists( $path ) ) {
				require_once $path;
			} else {
				return;
			}
		}

		$seeds = MeowPack_Content_Moderation::get_seed_keywords();
		foreach ( $seeds as $seed ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'keyword'    => $seed[0],
					'category'   => $seed[1],
					'action'     => $seed[2],
					'match_mode' => $seed[3],
					'is_active'  => 1,
				),
				array( '%s', '%s', '%s', '%s', '%d' )
			);
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
			$wpdb->prefix . 'meow_click_logs',
			$wpdb->prefix . 'meow_bot_rules',
			$wpdb->prefix . 'meow_bot_stats',
			$wpdb->prefix . 'meow_hotlink_logs',
			$wpdb->prefix . 'meow_content_rules',  // v2.1.0
			$wpdb->prefix . 'meow_content_logs',   // v2.1.0
			$wpdb->prefix . 'meow_reactions',      // v2.4.0
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
			'visits'          => $wpdb->prefix . 'meow_visits',
			'daily_stats'     => $wpdb->prefix . 'meow_daily_stats',
			'share_logs'      => $wpdb->prefix . 'meow_share_logs',
			'social_tokens'   => $wpdb->prefix . 'meow_social_tokens',
			'settings'        => $wpdb->prefix . 'meow_settings',
			'click_logs'      => $wpdb->prefix . 'meow_click_logs',
			'bot_rules'       => $wpdb->prefix . 'meow_bot_rules',
			'bot_stats'       => $wpdb->prefix . 'meow_bot_stats',
			'hotlink_logs'    => $wpdb->prefix . 'meow_hotlink_logs',
			'content_rules'   => $wpdb->prefix . 'meow_content_rules',
			'content_logs'    => $wpdb->prefix . 'meow_content_logs',
			'reactions'       => $wpdb->prefix . 'meow_reactions',
		);
	}

	/**
	 * Export core settings and content rules into an encoded string.
	 * Excludes autoshare-specific keys for security.
	 *
	 * @return string
	 */
	public static function export_sync_data() {
		global $wpdb;
		$tables = self::get_tables();

		// 1. Settings (Exclude autoshare keys)
		$settings_raw = $wpdb->get_results( "SELECT setting_key, setting_value FROM {$tables['settings']}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$settings = array();
		foreach ( $settings_raw as $row ) {
			if ( strpos( $row->setting_key, 'autoshare_' ) === 0 ) {
				continue;
			}
			$settings[ $row->setting_key ] = $row->setting_value;
		}

		// 2. Content Rules (Blacklist/Malware)
		$rules = $wpdb->get_results( "SELECT keyword, category, action, match_mode, is_active FROM {$tables['content_rules']}", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$data = array(
			'version'   => self::SCHEMA_VERSION,
			'timestamp' => current_time( 'timestamp' ),
			'settings'  => $settings,
			'rules'     => $rules,
		);

		return 'MEOW_CONFIG_v2::' . base64_encode( wp_json_encode( $data ) );
	}

	/**
	 * Import sync data from an encoded string.
	 *
	 * @param string $encoded_string The encoded MeowConfig string.
	 * @return bool|WP_Error
	 */
	public static function import_sync_data( $encoded_string ) {
		global $wpdb;

		if ( strpos( $encoded_string, 'MEOW_CONFIG_v2::' ) !== 0 ) {
			return new WP_Error( 'invalid_format', 'Format kode MeowConfig tidak valid.' );
		}

		$payload = substr( $encoded_string, strlen( 'MEOW_CONFIG_v2::' ) );
		$decoded = base64_decode( $payload );
		if ( ! $decoded ) {
			return new WP_Error( 'decode_failed', 'Gagal melakukan decode data.' );
		}

		$data = json_decode( $decoded, true );
		if ( ! is_array( $data ) || ! isset( $data['settings'] ) ) {
			return new WP_Error( 'invalid_json', 'Data JSON tidak valid.' );
		}

		$tables = self::get_tables();

		// 1. Import Settings
		foreach ( $data['settings'] as $key => $value ) {
			$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$tables['settings'],
				array( 'setting_key' => $key, 'setting_value' => $value ),
				array( '%s', '%s' )
			);
			wp_cache_delete( 'meowpack_setting_' . $key, 'meowpack' );
		}

		// 2. Import Content Rules
		if ( isset( $data['rules'] ) && is_array( $data['rules'] ) ) {
			foreach ( $data['rules'] as $rule ) {
				$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$tables['content_rules'],
					$rule
				);
			}
		}

		return true;
	}
}
