<?php
/**
 * Core class — bootstraps MeowPack and registers global hooks.
 *
 * @package MeowPack
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MeowPack_Core
 *
 * Singleton that wires up all plugin sub-systems.
 */
class MeowPack_Core {

	/** @var MeowPack_Core|null Singleton instance. */
	private static $instance = null;

	/** @var MeowPack_Tracker */
	public $tracker;

	/** @var MeowPack_Stats */
	public $stats;

	/** @var MeowPack_AutoShare */
	public $autoshare;

	/** @var MeowPack_ShareButtons */
	public $share_buttons;

	/** @var MeowPack_ViewCounter */
	public $view_counter;

	/** @var MeowPack_AI_Bot_Manager */
	public $ai_bot_manager;

	/** @var MeowPack_Click_Tracker */
	public $click_tracker;

	/** @var MeowPack_Reading_Time */
	public $reading_time;

	/** @var MeowPack_Anti_Hotlink */
	public $anti_hotlink;

	/** @var MeowPack_Captcha */
	public $captcha;

	/** @var MeowPack_Content_Moderation */
	public $content_moderation;

	/** @var MeowPack_Frontend_Enhancer */
	public $frontend;

	/** @var MeowPack_Reactions */
	public $reactions;

	/**
	 * Get singleton instance.
	 *
	 * @return MeowPack_Core
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — private to enforce singleton.
	 */
	private function __construct() {
		// Ensure DB is up-to-date on every load (lightweight version check).
		MeowPack_Database::install();

		// -----------------------------------------------------------------------
		// Init sub-systems.
		// -----------------------------------------------------------------------

		// AI Bot Manager runs at init priority 1 — before everything else.
		$this->ai_bot_manager = new MeowPack_AI_Bot_Manager();

		$this->tracker       = new MeowPack_Tracker();
		$this->stats         = new MeowPack_Stats();
		$this->autoshare     = new MeowPack_AutoShare();
		$this->share_buttons = new MeowPack_ShareButtons();
		$this->view_counter  = new MeowPack_ViewCounter();
		$this->click_tracker = new MeowPack_Click_Tracker();
		$this->reading_time  = new MeowPack_Reading_Time();
		$this->anti_hotlink  = new MeowPack_Anti_Hotlink();
		$this->captcha       = new MeowPack_Captcha();
		$this->content_moderation = new MeowPack_Content_Moderation();
		
		if ( class_exists( 'MeowPack_Frontend_Enhancer' ) ) {
			$this->frontend = new MeowPack_Frontend_Enhancer();
		}

		if ( class_exists( 'MeowPack_Reactions' ) ) {
			$this->reactions = new MeowPack_Reactions();
		}

		if ( class_exists( 'MeowPack_GitHub_Updater' ) ) {
			new MeowPack_GitHub_Updater();
		}

		if ( class_exists( 'MeowPack_Malware_Scanner' ) ) {
			new MeowPack_Malware_Scanner();
		}

		// Register REST API routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Daily cron handler.
		add_action( 'meowpack_daily_cron', array( $this, 'run_daily_cron' ) );

		// Plugin row links.
		add_filter( 'plugin_action_links_' . MEOWPACK_BASENAME, array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		// --- Tracking endpoint (Disguised as /search to bypass AdBlockers) ---
		register_rest_route(
			'meowpack/v1',
			'/search',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->tracker, 'handle_track_request' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_id'      => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
					'referrer'     => array( 'type' => 'string',  'sanitize_callback' => 'esc_url_raw' ),
					'utm_source'   => array( 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ),
					'utm_medium'   => array( 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ),
					'utm_campaign' => array( 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ),
					'nonce'        => array( 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);

		// --- Engagement (reading time + scroll depth) --------------------------
		register_rest_route(
			'meowpack/v1',
			'/engagement',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->reading_time, 'handle_engagement_request' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_id'      => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
					'time_on_page' => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
					'scroll_depth' => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
					'nonce'        => array( 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);

		// --- Outbound click tracking -------------------------------------------
		register_rest_route(
			'meowpack/v1',
			'/click',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->click_tracker, 'handle_click_request' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_id'     => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
					'url'         => array( 'type' => 'string',  'sanitize_callback' => 'esc_url_raw' ),
					'anchor_text' => array( 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ),
					'nonce'       => array( 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);

		// --- Stats endpoint (admin dashboard charts) ---------------------------
		register_rest_route(
			'meowpack/v1',
			'/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->stats, 'handle_stats_request' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		// --- Share click counter ----------------------------------------------
		register_rest_route(
			'meowpack/v1',
			'/share-click',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->share_buttons, 'handle_share_click' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_id'  => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
					'platform' => array( 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ),
					'nonce'    => array( 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);

		// --- Manual Cron trigger ----------------------------------------------
		register_rest_route(
			'meowpack/v1',
			'/cron',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_manual_cron' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'token' => array( 'type' => 'string', 'required' => true ),
				),
			)
		);

		// --- Reactions API -----------------------------------------------------
		if ( class_exists( 'MeowPack_Reactions' ) ) {
			MeowPack_Reactions::register_routes( $this );
		}
	}

	/**
	 * Run daily cron tasks:
	 *  1. Aggregate yesterday's visits into daily_stats.
	 *  2. Delete raw visits older than retention period.
	 *  3. Retry failed share logs.
	 *  4. Aggregate bot stats.
	 *
	 * @param bool $force Whether to force execution regardless of cron_mode setting.
	 */
	public function run_daily_cron( $force = false ) {
		if ( ! $force && 'manual' === MeowPack_Database::get_setting( 'cron_mode' ) ) {
			return;
		}

		$this->stats->aggregate_yesterday();

		$retention = (int) MeowPack_Database::get_setting( 'data_retention_days', 30 );
		$this->stats->purge_old_visits( $retention );

		$this->autoshare->retry_failed_shares();

		// Record last run time.
		MeowPack_Database::update_setting( 'last_cron_run', current_time( 'mysql' ) );
	}

	/**
	 * Handle manual cron trigger via REST API.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return array|WP_Error
	 */
	public function handle_manual_cron( $request ) {
		$token = $request->get_param( 'token' );
		$saved_token = MeowPack_Database::get_setting( 'cron_secret_token' );

		if ( empty( $saved_token ) || $token !== $saved_token ) {
			return new WP_Error( 'forbidden', 'Invalid cron token.', array( 'status' => 403 ) );
		}

		$this->run_daily_cron( true );

		return array(
			'success' => true,
			'message' => 'MeowPack daily tasks executed.',
			'time'    => current_time( 'mysql' ),
		);
	}

	/**
	 * Add action links on plugin listing page.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=meowpack' ) ) . '">' .
			esc_html__( 'Pengaturan', 'meowpack' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}
