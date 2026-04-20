<?php
/**
 * AI Bot Manager — detect, record, and optionally block AI scrapers.
 *
 * @package MeowPack
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MeowPack_AI_Bot_Manager
 *
 * Runs on `init` (early) to detect AI/scraper bots from the bot_rules table.
 * Depending on the configured action per-bot, it will:
 *   - allow   : pass through, record in bot_stats only.
 *   - block   : return HTTP 403.
 *   - block_redirect : redirect to a custom URL.
 */
class MeowPack_AI_Bot_Manager {

	/** @var array<string,array> Loaded rules keyed by user_agent_pattern. */
	private $rules = array();

	/** @var string|null Matched bot name from current request. */
	public $matched_bot = null;

	/** @var string|null Bot type of matched bot. */
	public $matched_bot_type = null;

	/**
	 * Constructor — load rules and hook into init.
	 */
	public function __construct() {
		$this->load_rules();
		add_action( 'init', array( $this, 'check_and_enforce' ), 1 );
	}

	/**
	 * Load bot rules from DB (cached for 5 minutes).
	 */
	private function load_rules() {
		$cached = get_transient( 'meowpack_bot_rules' );
		if ( false !== $cached ) {
			$this->rules = $cached;
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'meow_bot_rules';
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT bot_name, user_agent_pattern, bot_type, action, redirect_url
			 FROM {$table}
			 WHERE is_active = 1",
			ARRAY_A
		);

		$rules = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$rules[ strtolower( $row['user_agent_pattern'] ) ] = $row;
			}
		}

		$this->rules = $rules;
		set_transient( 'meowpack_bot_rules', $rules, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Flush the bot rules cache (call after saving rules in admin).
	 */
	public static function flush_cache() {
		delete_transient( 'meowpack_bot_rules' );
	}

	/**
	 * Match the current request's User-Agent against known AI bot patterns.
	 * Called on `init` at priority 1 — before most plugins run.
	 */
	public function check_and_enforce() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( empty( $ua ) ) {
			return;
		}

		$ua_lower = strtolower( $ua );
		$matched  = null;

		foreach ( $this->rules as $pattern => $rule ) {
			if ( false !== strpos( $ua_lower, $pattern ) ) {
				$matched = $rule;
				break;
			}
		}

		if ( ! $matched ) {
			return;
		}

		$this->matched_bot      = $matched['bot_name'];
		$this->matched_bot_type = $matched['bot_type'];

		// Record the visit (fire-and-forget, non-blocking via shutdown hook).
		add_action( 'shutdown', array( $this, 'record_bot_visit' ) );

		// Enforce action.
		$action = $matched['action'] ?? 'allow';

		if ( 'block' === $action ) {
			// Update robots.txt disallow via filter is passive; this actively blocks.
			status_header( 403 );
			header( 'X-Robots-Tag: noindex, nofollow' );
			wp_die(
				esc_html__( 'Access denied. AI scrapers are not permitted on this site.', 'meowpack' ),
				esc_html__( '403 Forbidden', 'meowpack' ),
				array( 'response' => 403 )
			);
		}

		if ( 'block_redirect' === $action && ! empty( $matched['redirect_url'] ) ) {
			status_header( 302 );
			header( 'Location: ' . esc_url_raw( $matched['redirect_url'] ) );
			exit;
		}
	}

	/**
	 * Called on `shutdown` — write one bot visit to aggregated bot_stats.
	 * Uses INSERT … ON DUPLICATE KEY UPDATE to keep a running counter.
	 */
	public function record_bot_visit() {
		if ( ! $this->matched_bot ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'meow_bot_stats';
		$today = gmdate( 'Y-m-d' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (stat_date, bot_name, bot_type, visit_count)
				 VALUES (%s, %s, %s, 1)
				 ON DUPLICATE KEY UPDATE visit_count = visit_count + 1",
				$today,
				$this->matched_bot,
				$this->matched_bot_type ?? 'ai_bot'
			)
		);
	}

	/**
	 * Get paginated bot stats for admin dashboard.
	 *
	 * @param string $period today|week|month|alltime
	 * @param int    $limit  Row limit.
	 * @return array
	 */
	public static function get_bot_stats( $period = 'month', $limit = 25 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_bot_stats';

		switch ( $period ) {
			case 'today':
				$where = $wpdb->prepare( 'stat_date = %s', gmdate( 'Y-m-d' ) );
				break;
			case 'week':
				$start = gmdate( 'Y-m-d', strtotime( 'monday this week' ) );
				$where = $wpdb->prepare( 'stat_date >= %s', $start );
				break;
			case 'month':
				$start = gmdate( 'Y-m' ) . '-01';
				$where = $wpdb->prepare( 'stat_date >= %s', $start );
				break;
			default: // alltime.
				$where = '1=1';
		}

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT bot_name, bot_type, SUM(visit_count) AS total_visits
				 FROM {$table}
				 WHERE {$where}
				 GROUP BY bot_name, bot_type
				 ORDER BY total_visits DESC
				 LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			),
			ARRAY_A
		);

		return $rows ?: array();
	}

	/**
	 * Get all configured bot rules from the database.
	 *
	 * @return array
	 */
	public static function get_all_rules() {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_bot_rules';
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$table} ORDER BY bot_type ASC, bot_name ASC",
			ARRAY_A
		);
		return $rows ?: array();
	}

	/**
	 * Save a single bot rule action (called from admin form).
	 *
	 * @param int    $rule_id    Rule ID.
	 * @param string $action     allow|block|block_redirect.
	 * @param string $redirect_url Optional redirect URL.
	 * @param int    $is_active  1 or 0.
	 * @return bool
	 */
	public static function save_rule( $rule_id, $action, $redirect_url = '', $is_active = 1 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_bot_rules';

		$allowed_actions = array( 'allow', 'stats_only', 'block', 'block_redirect' );
		if ( ! in_array( $action, $allowed_actions, true ) ) {
			return false;
		}

		$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'action'       => $action,
				'redirect_url' => esc_url_raw( $redirect_url ),
				'is_active'    => absint( $is_active ),
			),
			array( 'id' => absint( $rule_id ) ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);

		self::flush_cache();

		return false !== $result;
	}

	/**
	 * Add a custom bot rule.
	 *
	 * @param string $bot_name  Display name.
	 * @param string $pattern   UA pattern to match.
	 * @param string $bot_type  ai_bot|crawler|scraper|spam.
	 * @param string $action    allow|block|block_redirect.
	 * @return bool
	 */
	public static function add_rule( $bot_name, $pattern, $bot_type = 'ai_bot', $action = 'allow' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_bot_rules';

		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'bot_name'           => sanitize_text_field( $bot_name ),
				'user_agent_pattern' => sanitize_text_field( $pattern ),
				'bot_type'           => sanitize_key( $bot_type ),
				'action'             => sanitize_key( $action ),
				'is_active'          => 1,
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);

		self::flush_cache();

		return false !== $result;
	}

	/**
	 * Delete a custom bot rule by ID.
	 *
	 * @param int $rule_id Rule ID.
	 * @return bool
	 */
	public static function delete_rule( $rule_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_bot_rules';

		$result = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array( 'id' => absint( $rule_id ) ),
			array( '%d' )
		);

		self::flush_cache();

		return false !== $result;
	}

	/**
	 * Detect the matched bot for the current request UA without enforcing.
	 * Used by the tracker to store bot_name on visit records.
	 *
	 * @param string $ua User-Agent string.
	 * @return array{ bot_name: string|null, bot_type: string|null }
	 */
	public static function detect_from_ua( $ua ) {
		if ( empty( $ua ) ) {
			return array( 'bot_name' => null, 'bot_type' => null );
		}

		$cached = get_transient( 'meowpack_bot_rules' );
		$rules  = is_array( $cached ) ? $cached : array();

		if ( empty( $rules ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'meow_bot_rules';
			$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT bot_name, user_agent_pattern, bot_type FROM {$table} WHERE is_active = 1",
				ARRAY_A
			);
			if ( $rows ) {
				foreach ( $rows as $row ) {
					$rules[ strtolower( $row['user_agent_pattern'] ) ] = $row;
				}
			}
		}

		$ua_lower = strtolower( $ua );
		foreach ( $rules as $pattern => $rule ) {
			if ( false !== strpos( $ua_lower, $pattern ) ) {
				return array(
					'bot_name' => $rule['bot_name'],
					'bot_type' => $rule['bot_type'],
				);
			}
		}

		return array( 'bot_name' => null, 'bot_type' => null );
	}
}
