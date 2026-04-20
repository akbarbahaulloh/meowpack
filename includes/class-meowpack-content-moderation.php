<?php
/**
 * Content Moderation — deteksi kata kunci berbahaya di komentar, post, dan username.
 *
 * @package MeowPack
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MeowPack_Content_Moderation
 *
 * Scans user-generated content against a configurable keyword dictionary.
 * Supports per-keyword actions: hold, block, flag, or replace (censor).
 */
class MeowPack_Content_Moderation {

	/** @var array Loaded rules: [ [keyword, category, action, match_mode], ... ] */
	private $rules = array();

	/** @var string Transient key for cached rules. */
	const CACHE_KEY = 'meowpack_content_rules';

	/**
	 * Constructor — load rules and hook into WordPress.
	 */
	public function __construct() {
		if ( '1' !== MeowPack_Database::get_setting( 'enable_content_moderation', '0' ) ) {
			return;
		}

		$this->load_rules();

		// Scan new comments (before saved).
		if ( '1' === MeowPack_Database::get_setting( 'modscan_comments', '1' ) ) {
			add_filter( 'preprocess_comment', array( $this, 'scan_comment' ) );
		}

		// Scan username on registration.
		if ( '1' === MeowPack_Database::get_setting( 'modscan_usernames', '1' ) ) {
			add_filter( 'registration_errors', array( $this, 'scan_username' ), 10, 2 );
		}

		// Scan post content / title on publish.
		if ( '1' === MeowPack_Database::get_setting( 'modscan_posts', '0' ) ) {
			add_action( 'publish_post', array( $this, 'scan_post' ), 10, 1 );
		}

		// AJAX handler for manual scanner.
		add_action( 'wp_ajax_meowpack_manual_scan', array( $this, 'ajax_manual_scan' ) );
	}

	// -----------------------------------------------------------------------
	// Rule Loading
	// -----------------------------------------------------------------------

	/**
	 * Load keyword rules from DB, with transient caching.
	 */
	private function load_rules() {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			$this->rules = $cached;
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'meow_content_rules';
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT keyword, category, action, match_mode FROM {$table} WHERE is_active = 1 ORDER BY LENGTH(keyword) DESC",
			ARRAY_A
		);

		$this->rules = $rows ?: array();
		set_transient( self::CACHE_KEY, $this->rules, 10 * MINUTE_IN_SECONDS );
	}

	/**
	 * Flush rules cache (call after saving rules in admin).
	 */
	public static function flush_cache() {
		delete_transient( self::CACHE_KEY );
	}

	// -----------------------------------------------------------------------
	// Scanning Engine
	// -----------------------------------------------------------------------

	/**
	 * Scan a text string against all active rules.
	 *
	 * @param string $text Text to scan.
	 * @return array|null Match info array or null if clean.
	 *   { keyword, category, action, match_mode }
	 */
	public function scan_text( $text ) {
		if ( empty( $text ) || empty( $this->rules ) ) {
			return null;
		}

		$text_lower = mb_strtolower( $text );

		foreach ( $this->rules as $rule ) {
			$keyword    = mb_strtolower( $rule['keyword'] );
			$match_mode = $rule['match_mode'] ?? 'substring';

			$found = false;
			if ( 'word' === $match_mode ) {
				// Whole-word match using word boundary (Unicode-aware).
				$pattern = '/\b' . preg_quote( $keyword, '/' ) . '\b/iu';
				$found   = (bool) preg_match( $pattern, $text );
			} else {
				// Default: substring match.
				$found = ( false !== mb_strpos( $text_lower, $keyword ) );
			}

			if ( $found ) {
				return $rule;
			}
		}

		return null;
	}

	/**
	 * Apply a censor to text: replace matched keywords with ***.
	 *
	 * @param string $text Original text.
	 * @return string Censored text.
	 */
	public function censor_text( $text ) {
		foreach ( $this->rules as $rule ) {
			if ( 'replace' !== $rule['action'] ) {
				continue;
			}

			$keyword    = $rule['keyword'];
			$match_mode = $rule['match_mode'] ?? 'substring';
			$stars      = str_repeat( '*', mb_strlen( $keyword ) );

			if ( 'word' === $match_mode ) {
				$pattern = '/\b' . preg_quote( $keyword, '/' ) . '\b/iu';
				$text    = preg_replace( $pattern, $stars, $text );
			} else {
				$text = str_ireplace( $keyword, $stars, $text );
			}
		}

		return $text;
	}

	// -----------------------------------------------------------------------
	// Comment Scanner
	// -----------------------------------------------------------------------

	/**
	 * Scan a comment before it is saved.
	 * Hook: preprocess_comment (filter on comment data array).
	 *
	 * @param array $commentdata Comment data.
	 * @return array Modified comment data.
	 */
	public function scan_comment( $commentdata ) {
		$text = ( $commentdata['comment_content'] ?? '' ) . ' ' . ( $commentdata['comment_author'] ?? '' );
		$match = $this->scan_text( $text );

		if ( ! $match ) {
			return $commentdata;
		}

		$action = $match['action'];
		$this->log_detection( 'comment', 0, $match['keyword'], $match['category'], $action, $text );

		switch ( $action ) {
			case 'block':
				wp_die(
					esc_html__( 'Komentar Anda mengandung kata yang tidak diizinkan. Silakan revisi dan coba lagi.', 'meowpack' ),
					esc_html__( 'Komentar Ditolak', 'meowpack' ),
					array( 'back_link' => true, 'response' => 200 )
				);
				break;

			case 'hold':
				$commentdata['comment_approved'] = 0;
				break;

			case 'replace':
				$commentdata['comment_content'] = $this->censor_text( $commentdata['comment_content'] );
				break;

			case 'flag':
				// Let the comment through but notify admin.
				$commentdata['comment_approved'] = 0; // Still hold so admin can review.
				$this->notify_admin( $match['keyword'], $match['category'], $text, 'comment' );
				break;
		}

		return $commentdata;
	}

	// -----------------------------------------------------------------------
	// Username Scanner
	// -----------------------------------------------------------------------

	/**
	 * Scan a username during registration.
	 * Hook: registration_errors (filter on WP_Error).
	 *
	 * @param WP_Error $errors   Registration errors.
	 * @param string   $username Submitted username.
	 * @return WP_Error
	 */
	public function scan_username( $errors, $username ) {
		$match = $this->scan_text( $username );

		if ( $match ) {
			$this->log_detection( 'username', 0, $match['keyword'], $match['category'], 'block', $username );
			$errors->add(
				'meowpack_username_blocked',
				esc_html__( 'Username mengandung kata yang tidak diizinkan. Silakan gunakan nama lain.', 'meowpack' )
			);
		}

		return $errors;
	}

	// -----------------------------------------------------------------------
	// Post Scanner
	// -----------------------------------------------------------------------

	/**
	 * Scan post content when a post is published.
	 * Hook: publish_post action.
	 *
	 * @param int $post_id Post ID.
	 */
	public function scan_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$text  = $post->post_title . ' ' . wp_strip_all_tags( $post->post_content ) . ' ' . $post->post_excerpt;
		$match = $this->scan_text( $text );

		if ( ! $match ) {
			return;
		}

		$this->log_detection( 'post', $post_id, $match['keyword'], $match['category'], 'flag', $text );

		// Move post back to pending if action = block.
		if ( 'block' === $match['action'] ) {
			wp_update_post( array(
				'ID'          => $post_id,
				'post_status' => 'pending',
			) );
			$this->notify_admin( $match['keyword'], $match['category'], $post->post_title, 'post' );
		} elseif ( in_array( $match['action'], array( 'flag', 'hold' ), true ) ) {
			$this->notify_admin( $match['keyword'], $match['category'], $post->post_title, 'post' );
		}
	}

	// -----------------------------------------------------------------------
	// Logging
	// -----------------------------------------------------------------------

	/**
	 * Write a detection event to meow_content_logs.
	 *
	 * @param string $context         comment|post|username
	 * @param int    $object_id       Post ID or comment ID.
	 * @param string $matched_keyword Keyword that triggered.
	 * @param string $matched_category Category of the keyword.
	 * @param string $action_taken    Action applied.
	 * @param string $raw_content     First 200 chars of content.
	 */
	private function log_detection( $context, $object_id, $matched_keyword, $matched_category, $action_taken, $raw_content ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_content_logs';

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'detected_at'      => current_time( 'mysql' ),
				'context'          => sanitize_key( $context ),
				'object_id'        => absint( $object_id ),
				'matched_keyword'  => sanitize_text_field( $matched_keyword ),
				'matched_category' => sanitize_text_field( $matched_category ),
				'action_taken'     => sanitize_key( $action_taken ),
				'content_excerpt'  => mb_substr( wp_strip_all_tags( $raw_content ), 0, 200 ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Email admin when flagged content is found.
	 *
	 * @param string $keyword  Matched keyword.
	 * @param string $category Category.
	 * @param string $excerpt  Content excerpt.
	 * @param string $context  Where it was found.
	 */
	private function notify_admin( $keyword, $category, $excerpt, $context ) {
		$admin_email = get_option( 'admin_email' );
		$site_name   = get_bloginfo( 'name' );

		$subject = sprintf(
			/* translators: 1: site name 2: category */
			__( '[%1$s] Konten Mencurigakan Terdeteksi — Kategori: %2$s', 'meowpack' ),
			$site_name,
			$category
		);

		$message  = sprintf( __( 'MeowPack mendeteksi konten berbahaya di situs Anda.', 'meowpack' ) ) . "\n\n";
		$message .= sprintf( __( 'Lokasi  : %s', 'meowpack' ), $context ) . "\n";
		$message .= sprintf( __( 'Kategori: %s', 'meowpack' ), $category ) . "\n";
		$message .= sprintf( __( 'Keyword : %s', 'meowpack' ), $keyword ) . "\n\n";
		$message .= sprintf( __( 'Cuplikan konten:', 'meowpack' ) ) . "\n";
		$message .= mb_substr( wp_strip_all_tags( $excerpt ), 0, 200 ) . "\n\n";
		$message .= admin_url( 'admin.php?page=meowpack-content-moderation&tab=logs' );

		wp_mail( $admin_email, $subject, $message );
	}

	// -----------------------------------------------------------------------
	// Admin Query Helpers
	// -----------------------------------------------------------------------

	/**
	 * Get all keyword rules, optionally filtered by category.
	 *
	 * @param string $category Filter by category (empty = all).
	 * @return array
	 */
	public static function get_all_rules( $category = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_content_rules';

		if ( $category ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE category = %s ORDER BY category, keyword ASC",
					$category
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT * FROM {$table} ORDER BY category ASC, keyword ASC",
				ARRAY_A
			);
		}

		return $rows ?: array();
	}

	/**
	 * Add a keyword rule.
	 *
	 * @param string $keyword    Keyword.
	 * @param string $category   Category slug.
	 * @param string $action     hold|block|flag|replace.
	 * @param string $match_mode substring|word.
	 * @return bool
	 */
	public static function add_rule( $keyword, $category = 'custom', $action = 'hold', $match_mode = 'substring' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_content_rules';

		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'keyword'    => sanitize_text_field( $keyword ),
				'category'   => sanitize_key( $category ),
				'action'     => sanitize_key( $action ),
				'match_mode' => sanitize_key( $match_mode ),
				'is_active'  => 1,
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);

		self::flush_cache();
		return false !== $result;
	}

	/**
	 * Update a rule.
	 *
	 * @param int    $rule_id    Rule ID.
	 * @param string $action     New action.
	 * @param string $match_mode New match mode.
	 * @param int    $is_active  Active flag.
	 * @return bool
	 */
	public static function update_rule( $rule_id, $action, $match_mode = 'substring', $is_active = 1 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_content_rules';

		$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'action'     => sanitize_key( $action ),
				'match_mode' => sanitize_key( $match_mode ),
				'is_active'  => absint( $is_active ),
			),
			array( 'id' => absint( $rule_id ) ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);

		self::flush_cache();
		return false !== $result;
	}

	/**
	 * Delete a rule by ID.
	 *
	 * @param int $rule_id Rule ID.
	 * @return bool
	 */
	public static function delete_rule( $rule_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_content_rules';

		$result = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array( 'id' => absint( $rule_id ) ),
			array( '%d' )
		);

		self::flush_cache();
		return false !== $result;
	}

	/**
	 * Delete all rules in a category (for re-import or reset).
	 *
	 * @param string $category Category slug.
	 * @return bool
	 */
	public static function delete_rules_by_category( $category ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_content_rules';

		$result = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array( 'category' => sanitize_key( $category ) ),
			array( '%s' )
		);

		self::flush_cache();
		return false !== $result;
	}

	/**
	 * Import keywords from a CSV string.
	 * Expected format: keyword,category,action,match_mode  (one per line).
	 *
	 * @param string $csv Raw CSV content.
	 * @return array{ imported: int, skipped: int }
	 */
	public static function import_csv( $csv ) {
		$lines    = preg_split( '/\r?\n/', trim( $csv ) );
		$imported = 0;
		$skipped  = 0;

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) || '#' === $line[0] ) {
				continue; // Skip empty lines and comments.
			}

			$parts = array_map( 'trim', str_getcsv( $line ) );
			$keyword    = $parts[0] ?? '';
			$category   = $parts[1] ?? 'custom';
			$action     = $parts[2] ?? 'hold';
			$match_mode = $parts[3] ?? 'substring';

			if ( empty( $keyword ) ) {
				$skipped++;
				continue;
			}

			$ok = self::add_rule( $keyword, $category, $action, $match_mode );
			if ( $ok ) {
				$imported++;
			} else {
				$skipped++;
			}
		}

		return array( 'imported' => $imported, 'skipped' => $skipped );
	}

	/**
	 * Export all rules to CSV string.
	 *
	 * @return string CSV content.
	 */
	public static function export_csv() {
		$rules = self::get_all_rules();
		$out   = "# MeowPack Content Moderation Rules Export\n";
		$out  .= "# Format: keyword,category,action,match_mode\n";

		foreach ( $rules as $rule ) {
			$out .= implode( ',', array(
				'"' . str_replace( '"', '""', $rule['keyword'] ) . '"',
				$rule['category'],
				$rule['action'],
				$rule['match_mode'],
			) ) . "\n";
		}

		return $out;
	}

	/**
	 * Get recent detection logs.
	 *
	 * @param int    $limit  Max rows.
	 * @param string $context Filter: comment|post|username|'' (all).
	 * @return array
	 */
	public static function get_logs( $limit = 50, $context = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_content_logs';

		if ( $context ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE context = %s ORDER BY detected_at DESC LIMIT %d",
					$context,
					$limit
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT * FROM {$table} ORDER BY detected_at DESC LIMIT %d",
					$limit
				),
				ARRAY_A
			);
		}

		return $rows ?: array();
	}

	/**
	 * Get detection stats grouped by category.
	 *
	 * @return array
	 */
	public static function get_stats_by_category() {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_content_logs';

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT matched_category, COUNT(*) AS total, MAX(detected_at) AS last_detected
			 FROM {$table}
			 GROUP BY matched_category
			 ORDER BY total DESC",
			ARRAY_A
		);

		return $rows ?: array();
	}

	/**
	 * Get seed keywords for initial database population.
	 * Called from MeowPack_Database::seed_content_rules().
	 *
	 * @return array Array of [keyword, category, action, match_mode].
	 */
	public static function get_seed_keywords() {
		return array(
			// ----------------------------------------------------------------
			// Judi / Gambling
			// ----------------------------------------------------------------
			array( 'slot gacor',         'gambling', 'hold',  'substring' ),
			array( 'slot online',         'gambling', 'hold',  'substring' ),
			array( 'judi online',         'gambling', 'hold',  'substring' ),
			array( 'judi slot',           'gambling', 'hold',  'substring' ),
			array( 'togel online',        'gambling', 'hold',  'substring' ),
			array( 'togel sgp',           'gambling', 'hold',  'substring' ),
			array( 'togel hk',            'gambling', 'hold',  'substring' ),
			array( 'bandar togel',        'gambling', 'hold',  'substring' ),
			array( 'bandar slot',         'gambling', 'hold',  'substring' ),
			array( 'taruhan bola',        'gambling', 'hold',  'substring' ),
			array( 'situs judi',          'gambling', 'hold',  'substring' ),
			array( 'casino online',       'gambling', 'hold',  'substring' ),
			array( 'agen judi',           'gambling', 'hold',  'substring' ),
			array( 'poker online',        'gambling', 'hold',  'substring' ),
			array( 'link alternatif slot','gambling', 'hold',  'substring' ),
			array( 'maxwin',              'gambling', 'hold',  'word'      ),
			array( 'rtp slot',            'gambling', 'hold',  'substring' ),
			array( 'pragmatic play',      'gambling', 'hold',  'substring' ),
			array( 'daftar slot',         'gambling', 'hold',  'substring' ),
			array( 'scatter hitam',       'gambling', 'hold',  'substring' ),

			// ----------------------------------------------------------------
			// Obat Terlarang / Berbahaya
			// ----------------------------------------------------------------
			array( 'obat penggugur kandungan', 'drugs', 'block', 'substring' ),
			array( 'obat aborsi',              'drugs', 'block', 'substring' ),
			array( 'obat gugur kandungan',     'drugs', 'block', 'substring' ),
			array( 'cytotec',                  'drugs', 'block', 'substring' ),
			array( 'misoprostol',              'drugs', 'block', 'substring' ),
			array( 'sabu sabu',                'drugs', 'block', 'substring' ),
			array( 'narkoba',                  'drugs', 'block', 'word'      ),
			array( 'ganja kering',             'drugs', 'block', 'substring' ),
			array( 'beli tramadol',            'drugs', 'block', 'substring' ),
			array( 'jual pil koplo',           'drugs', 'block', 'substring' ),
			array( 'jual pil bius',            'drugs', 'block', 'substring' ),

			// ----------------------------------------------------------------
			// Penipuan / Scam / Pinjol Ilegal
			// ----------------------------------------------------------------
			array( 'pinjol ilegal',        'scam', 'hold', 'substring' ),
			array( 'pinjaman online ilegal','scam', 'hold', 'substring' ),
			array( 'investasi bodong',      'scam', 'hold', 'substring' ),
			array( 'money game',            'scam', 'hold', 'substring' ),
			array( 'arisan online',         'scam', 'hold', 'substring' ),
			array( 'mlm ilegal',            'scam', 'hold', 'substring' ),
			array( 'transfer dulu baru',    'scam', 'hold', 'substring' ),
			array( 'klik link ini menang',  'scam', 'hold', 'substring' ),
			array( 'undian berhadiah',      'scam', 'hold', 'substring' ),
			array( 'anda terpilih',         'scam', 'hold', 'substring' ),
			array( 'jual followers',        'scam', 'hold', 'substring' ),
			array( 'jual akun',             'scam', 'hold', 'substring' ),

			// ----------------------------------------------------------------
			// Konten Berbahaya / Kekerasan
			// ----------------------------------------------------------------
			array( 'cara membuat bom',    'violence', 'block', 'substring' ),
			array( 'cara merakit bom',    'violence', 'block', 'substring' ),
			array( 'cara membunuh',       'violence', 'block', 'substring' ),
			array( 'ancaman pembunuhan',  'violence', 'block', 'substring' ),
			array( 'peledak rakitan',     'violence', 'block', 'substring' ),
		);
	}

	// -----------------------------------------------------------------------
	// Manual Scanner (AJAX Batch Processing)
	// -----------------------------------------------------------------------

	/**
	 * AJAX endpoint for manual scanning.
	 */
	public function ajax_manual_scan() {
		check_ajax_referer( 'meowpack_manual_scan', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$step   = isset( $_POST['step'] ) ? sanitize_key( $_POST['step'] ) : ''; // posts | comments | widgets
		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$limit  = 50;

		$result = array(
			'found'       => array(),
			'next_offset' => null, // null means done for this step.
		);

		switch ( $step ) {
			case 'posts':
				// Scans: post, page, attachment, nav_menu_item
				$result = $this->scan_posts_batch( $offset, $limit );
				break;
			case 'comments':
				$result = $this->scan_comments_batch( $offset, $limit );
				break;
			case 'widgets':
				$result = $this->scan_widgets_batch();
				break;
			default:
				wp_send_json_error( 'Invalid step' );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Batch scan wp_posts.
	 *
	 * @param int $offset Offset.
	 * @param int $limit  Limit.
	 * @return array
	 */
	private function scan_posts_batch( $offset, $limit ) {
		global $wpdb;
		$found = array();

		$posts = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT ID, post_title, post_content, post_excerpt, post_type 
			 FROM {$wpdb->posts} 
			 WHERE post_status IN ('publish','inherit') 
			 AND post_type IN ('post','page','attachment','nav_menu_item') 
			 ORDER BY ID ASC LIMIT %d OFFSET %d",
			$limit, $offset
		) );

		if ( empty( $posts ) ) {
			return array( 'found' => $found, 'next_offset' => null );
		}

		foreach ( $posts as $p ) {
			$text = $p->post_title . ' ' . wp_strip_all_tags( $p->post_content ) . ' ' . $p->post_excerpt;
			$match = $this->scan_text( $text );

			if ( $match ) {
				// Get edit link.
				$edit_link = get_edit_post_link( $p->ID, 'raw' );
				// For nav menus, direct to Appearance -> Menus.
				if ( 'nav_menu_item' === $p->post_type ) {
					$edit_link = admin_url( 'nav-menus.php' );
				}

				$found[] = array(
					'id'       => $p->ID,
					'type'     => ucfirst( $p->post_type ),
					'title'    => esc_html( $p->post_title ?: 'Tanpa Judul' ),
					'keyword'  => $match['keyword'],
					'category' => $match['category'],
					'link'     => $edit_link,
				);
			}
		}

		return array( 'found' => $found, 'next_offset' => $offset + $limit );
	}

	/**
	 * Batch scan wp_comments.
	 *
	 * @param int $offset Offset.
	 * @param int $limit  Limit.
	 * @return array
	 */
	private function scan_comments_batch( $offset, $limit ) {
		global $wpdb;
		$found = array();

		$comments = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT comment_ID, comment_author, comment_content 
			 FROM {$wpdb->comments} 
			 WHERE comment_approved = '1' 
			 ORDER BY comment_ID ASC LIMIT %d OFFSET %d",
			$limit, $offset
		) );

		if ( empty( $comments ) ) {
			return array( 'found' => $found, 'next_offset' => null );
		}

		foreach ( $comments as $c ) {
			$text = $c->comment_author . ' ' . wp_strip_all_tags( $c->comment_content );
			$match = $this->scan_text( $text );

			if ( $match ) {
				$found[] = array(
					'id'       => $c->comment_ID,
					'type'     => 'Komentar',
					'title'    => esc_html( 'Oleh: ' . $c->comment_author ),
					'keyword'  => $match['keyword'],
					'category' => $match['category'],
					'link'     => get_edit_comment_link( $c->comment_ID ),
				);
			}
		}

		return array( 'found' => $found, 'next_offset' => $offset + $limit );
	}

	/**
	 * Scan widgets (single batch since options are small).
	 *
	 * @return array
	 */
	private function scan_widgets_batch() {
		global $wpdb;
		$found = array();

		$options = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT option_name, option_value 
			 FROM {$wpdb->options} 
			 WHERE option_name LIKE 'widget_%'"
		);

		foreach ( $options as $opt ) {
			$val = maybe_unserialize( $opt->option_value );
			if ( ! is_array( $val ) ) {
				continue;
			}

			// Widget usually stores instances as arrays with numbered keys.
			foreach ( $val as $widget_id => $instance ) {
				if ( ! is_array( $instance ) ) {
					continue;
				}

				// Look for text, content, title keys.
				$text = '';
				if ( isset( $instance['title'] ) ) $text .= $instance['title'] . ' ';
				if ( isset( $instance['text'] ) ) $text .= wp_strip_all_tags( $instance['text'] ) . ' ';
				if ( isset( $instance['content'] ) ) $text .= wp_strip_all_tags( $instance['content'] ) . ' ';

				if ( ! trim( $text ) ) {
					continue;
				}

				$match = $this->scan_text( $text );
				if ( $match ) {
					$found[] = array(
						'id'       => $opt->option_name . '-' . $widget_id,
						'type'     => 'Widget',
						'title'    => esc_html( str_replace( 'widget_', '', $opt->option_name ) . " [$widget_id]" ),
						'keyword'  => $match['keyword'],
						'category' => $match['category'],
						'link'     => admin_url( 'widgets.php' ),
					);
				}
			}
		}

		// Done in one go.
		return array( 'found' => $found, 'next_offset' => null );
	}
}
