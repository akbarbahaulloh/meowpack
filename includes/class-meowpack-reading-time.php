<?php
/**
 * Reading Time — estimated read time display + actual engagement tracking.
 *
 * @package MeowPack
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MeowPack_Reading_Time
 *
 * Two-part feature:
 * 1. Calculated estimated reading time shown before/after content.
 * 2. Actual time-on-page and scroll depth recorded by a JS beacon,
 *    stored on the visit row via the /engagement REST endpoint.
 */
class MeowPack_Reading_Time {

	/** @var int Average words per minute for Indonesian readers. */
	const WPM = 200;

	/**
	 * Constructor — register hooks if feature is enabled.
	 */
	public function __construct() {
		if ( '1' !== MeowPack_Database::get_setting( 'enable_reading_time', '1' ) ) {
			return;
		}

		add_filter( 'the_content', array( $this, 'prepend_reading_time' ) );
		add_shortcode( 'meowpack_reading_time', array( $this, 'shortcode' ) );
	}

	// -----------------------------------------------------------------------
	// Estimated Reading Time
	// -----------------------------------------------------------------------

	/**
	 * Estimate reading time in minutes for a given text.
	 *
	 * @param string $content Post content (HTML or plain).
	 * @return int Minutes (minimum 1).
	 */
	public static function estimate_minutes( $content ) {
		$plain     = wp_strip_all_tags( $content );
		$word_count = (int) count( preg_split( '/\s+/u', trim( $plain ), -1, PREG_SPLIT_NO_EMPTY ) );
		$minutes   = (int) ceil( $word_count / self::WPM );
		return max( 1, $minutes );
	}

	/**
	 * Prepend an estimated reading time badge to post content.
	 *
	 * @param string $content Original post content.
	 * @return string Modified content.
	 */
	public function prepend_reading_time( $content ) {
		if ( ! is_singular() ) {
			return $content;
		}

		$minutes = self::estimate_minutes( $content );

		/* translators: %d = number of minutes */
		$label = sprintf( _n( '%d menit baca', '%d menit baca', $minutes, 'meowpack' ), $minutes );

		$badge = '<p class="meowpack-reading-time" aria-label="' . esc_attr__( 'Estimasi waktu baca', 'meowpack' ) . '">'
			. '<span class="meowpack-rt-icon" aria-hidden="true">⏱</span> '
			. '<span class="meowpack-rt-label">' . esc_html( $label ) . '</span>'
			. '</p>';

		return $badge . $content;
	}

	/**
	 * Shortcode [meowpack_reading_time post_id="123"].
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML.
	 */
	public function shortcode( $atts ) {
		$atts    = shortcode_atts( array( 'post_id' => get_the_ID() ), $atts );
		$post_id = absint( $atts['post_id'] );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return '';
		}

		$minutes = self::estimate_minutes( $post->post_content );
		/* translators: %d = number of minutes */
		$label = sprintf( _n( '%d menit baca', '%d menit baca', $minutes, 'meowpack' ), $minutes );

		return '<span class="meowpack-reading-time">⏱ ' . esc_html( $label ) . '</span>';
	}

	// -----------------------------------------------------------------------
	// Actual Engagement: REST endpoint handler
	// -----------------------------------------------------------------------

	/**
	 * Handle REST POST /wp-json/meowpack/v1/engagement.
	 * Updates time_on_page and scroll_depth on the most recent visit row
	 * for the given post_id + ip_hash from today.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_engagement_request( WP_REST_Request $request ) {
		$nonce = $request->get_param( 'nonce' );
		if ( ! wp_verify_nonce( $nonce, 'meowpack_track' ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 200 );
		}

		$post_id      = absint( $request->get_param( 'post_id' ) );
		$time_on_page = absint( $request->get_param( 'time_on_page' ) ); // seconds
		$scroll_depth = min( 100, absint( $request->get_param( 'scroll_depth' ) ) ); // 0-100

		if ( ! $post_id ) {
			return new WP_REST_Response( array( 'ok' => false ), 200 );
		}

		$ip      = MeowPack_Bot_Filter::get_client_ip();
		$ip_hash = MeowPack_Bot_Filter::hash_ip( $ip );
		$today   = current_time( 'Y-m-d' );

		global $wpdb;
		$table = $wpdb->prefix . 'meow_visits';

		// Update the most recent visit row for this post + visitor + today.
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE {$table}
				 SET time_on_page = %d, scroll_depth = %d
				 WHERE post_id = %d AND ip_hash = %s AND visit_date = %s
				   AND is_bot = 0
				 ORDER BY id DESC
				 LIMIT 1",
				$time_on_page,
				$scroll_depth,
				$post_id,
				$ip_hash,
				$today
			)
		);

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	// -----------------------------------------------------------------------
	// Query helpers for admin dashboard
	// -----------------------------------------------------------------------

	/**
	 * Get average time-on-page and scroll depth per post.
	 *
	 * @param int    $limit  Max rows.
	 * @param string $period today|week|month|alltime.
	 * @return array
	 */
	public static function get_engagement_stats( $limit = 20, $period = 'month' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_visits';

		switch ( $period ) {
			case 'today':
				$where = $wpdb->prepare( 'AND visit_date = %s', gmdate( 'Y-m-d' ) );
				break;
			case 'week':
				$start = gmdate( 'Y-m-d', strtotime( 'monday this week' ) );
				$where = $wpdb->prepare( 'AND visit_date >= %s', $start );
				break;
			case 'month':
				$start = gmdate( 'Y-m' ) . '-01';
				$where = $wpdb->prepare( 'AND visit_date >= %s', $start );
				break;
			default:
				$where = '';
		}

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT post_id,
				        ROUND(AVG(time_on_page)) AS avg_time_on_page,
				        ROUND(AVG(scroll_depth)) AS avg_scroll_depth,
				        COUNT(*) AS total_visits
				 FROM {$table}
				 WHERE is_bot = 0
				   AND time_on_page IS NOT NULL
				   AND post_id > 0
				   {$where}
				 GROUP BY post_id
				 ORDER BY avg_scroll_depth DESC
				 LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			),
			ARRAY_A
		);

		return $rows ?: array();
	}
}
