<?php
/**
 * Click Tracker — records outbound (external) link clicks.
 *
 * @package MeowPack
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MeowPack_Click_Tracker
 *
 * The JavaScript counterpart (in meowpack-tracker.js) detects clicks on
 * external links and POSTs them to the REST endpoint registered here.
 * Data is stored in meow_click_logs with an INSERT … ON DUPLICATE KEY UPDATE
 * so repeated clicks simply increment the counter.
 */
class MeowPack_Click_Tracker {

	/**
	 * Constructor — register hooks.
	 */
	public function __construct() {
		if ( '1' !== MeowPack_Database::get_setting( 'enable_click_tracker', '1' ) ) {
			return;
		}
		// REST endpoint is registered by MeowPack_Core.
	}

	/**
	 * Handle REST POST /wp-json/meowpack/v1/click.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_click_request( WP_REST_Request $request ) {
		// Bypassed for LiteSpeed Cache compatibility
		// $nonce = $request->get_param( 'nonce' );
		// if ( ! wp_verify_nonce( $nonce, 'meowpack_track' ) ) {
		// 	return new WP_REST_Response( array( 'ok' => false ), 200 );
		// }

		$url         = esc_url_raw( $request->get_param( 'url' ) ?? '' );
		$post_id     = absint( $request->get_param( 'post_id' ) );
		$anchor_text = sanitize_text_field( $request->get_param( 'anchor_text' ) ?? '' );

		if ( empty( $url ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'reason' => 'empty_url' ), 200 );
		}

		$url_hash = hash( 'sha256', $url );

		global $wpdb;
		$table = $wpdb->prefix . 'meow_click_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (post_id, url, url_hash, anchor_text, click_count, last_clicked)
				 VALUES (%d, %s, %s, %s, 1, %s)
				 ON DUPLICATE KEY UPDATE
				   click_count = click_count + 1,
				   last_clicked = %s",
				$post_id,
				$url,
				$url_hash,
				$anchor_text,
				current_time( 'mysql' ),
				current_time( 'mysql' )
			)
		);

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Get top outbound links.
	 *
	 * @param int    $limit   Number of rows.
	 * @param int    $post_id Filter by post (0 = all).
	 * @return array
	 */
	public static function get_top_links( $limit = 20, $post_id = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_click_logs';

		$where = $post_id > 0 ? $wpdb->prepare( 'WHERE post_id = %d', $post_id ) : '';

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT url, SUM(click_count) AS total_clicks, MAX(last_clicked) AS last_clicked
				 FROM {$table}
				 {$where}
				 GROUP BY url
				 ORDER BY total_clicks DESC
				 LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			),
			ARRAY_A
		);

		return $rows ?: array();
	}

	/**
	 * Get click stats grouped by post.
	 *
	 * @param int $limit Number of rows.
	 * @return array
	 */
	public static function get_clicks_by_post( $limit = 20 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_click_logs';

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT post_id, SUM(click_count) AS total_clicks
				 FROM {$table}
				 WHERE post_id > 0
				 GROUP BY post_id
				 ORDER BY total_clicks DESC
				 LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return $rows ?: array();
	}
}
