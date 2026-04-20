<?php
/**
 * Stats class — aggregation, queries, and REST data endpoint.
 *
 * @package MeowPack
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MeowPack_Stats
 *
 * Handles daily aggregation of raw visit data and provides
 * query methods for dashboard charts and widgets.
 */
class MeowPack_Stats {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// No frontend hooks needed for this class.
	}

	// -------------------------------------------------------------------------
	// Aggregation & Cleanup
	// -------------------------------------------------------------------------

	/**
	 * Aggregate yesterday's raw visits into meow_daily_stats.
	 * Called by the daily cron.
	 */
	public function aggregate_yesterday() {
		$yesterday = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
		$this->aggregate_for_date( $yesterday );
	}

	/**
	 * Aggregate visits for a specific date.
	 *
	 * @param string $date Date in Y-m-d format.
	 */
	public function aggregate_for_date( $date ) {
		global $wpdb;

		$visits_table = $wpdb->prefix . 'meow_visits';
		$stats_table  = $wpdb->prefix . 'meow_daily_stats';

		// Get all distinct post_ids for that date (including sitewide = 0 aggregate).
		$post_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$visits_table} WHERE visit_date = %s AND is_bot = 0",
				$date
			)
		);

		// Always include sitewide (0).
		$post_ids[] = 0;
		$post_ids   = array_unique( array_map( 'absint', $post_ids ) );

		foreach ( $post_ids as $post_id ) {
			$where_post = ( 0 === $post_id ) ? '' : $wpdb->prepare( ' AND post_id = %d', $post_id );

			$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT
						COUNT(DISTINCT ip_hash) AS unique_visitors,
						COUNT(*) AS total_views,
						SUM(source_type = 'direct') AS source_direct,
						SUM(source_type = 'search') AS source_search,
						SUM(source_type = 'social') AS source_social,
						SUM(source_type = 'referral') AS source_referral,
						SUM(source_type = 'email') AS source_email
					FROM {$visits_table}
					WHERE visit_date = %s AND is_bot = 0" . $where_post,
					$date
				),
				ARRAY_A
			);

			if ( ! $row || 0 === (int) $row['total_views'] ) {
				continue;
			}

			$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$stats_table,
				array(
					'stat_date'       => $date,
					'post_id'         => $post_id,
					'unique_visitors' => (int) $row['unique_visitors'],
					'total_views'     => (int) $row['total_views'],
					'source_direct'   => (int) $row['source_direct'],
					'source_search'   => (int) $row['source_search'],
					'source_social'   => (int) $row['source_social'],
					'source_referral' => (int) $row['source_referral'],
					'source_email'    => (int) $row['source_email'],
				),
				array( '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d' )
			);
		}

		// Bust transient caches after aggregation.
		$this->flush_stat_caches();
	}

	/**
	 * Delete raw visit records older than $days days.
	 *
	 * @param int $days Number of days to retain.
	 */
	public function purge_old_visits( $days = 30 ) {
		global $wpdb;
		$table    = $wpdb->prefix . 'meow_visits';
		$cutoff   = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "DELETE FROM {$table} WHERE visit_date < %s", $cutoff )
		);
	}

	/**
	 * Delete all cached stat transients.
	 */
	public function flush_stat_caches() {
		global $wpdb;
		// Delete all MeowPack transients (wildcard) since they have dynamic names based on periods/post_ids.
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_meowpack\_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_meowpack\_%'" );
	}

	// -------------------------------------------------------------------------
	// Query Methods
	// -------------------------------------------------------------------------

	/**
	 * Get sitewide stats for a specific period.
	 *
	 * @param string $period today|week|month|alltime
	 * @return array{ unique_visitors: int, total_views: int }
	 */
	public function get_sitewide_stats( $period = 'today' ) {
		$cache_key = 'meowpack_stats_' . $period;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$stats_table = $wpdb->prefix . 'meow_daily_stats';

		switch ( $period ) {
			case 'today':
				$where = $wpdb->prepare( 'stat_date = %s', current_time( 'Y-m-d' ) );
				break;
			case 'week':
				// Monday of this week in site's timezone.
				$monday = date( 'Y-m-d', strtotime( 'monday this week', current_time( 'timestamp' ) ) );
				$where = $wpdb->prepare( 'stat_date >= %s', $monday );
				break;
			case 'month':
				$start = current_time( 'Y-m' ) . '-01';
				$where = $wpdb->prepare( 'stat_date >= %s', $start );
				break;
			case 'year':
				$start = current_time( 'Y' ) . '-01-01';
				$where = $wpdb->prepare( 'stat_date >= %s', $start );
				break;
			default: // alltime.
				$where = '1=1';
		}

		// Real-time component (Today's raw visits)
		$visits_table = $wpdb->prefix . 'meow_visits';
		$today        = current_time( 'Y-m-d' );
		$row_today    = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT ip_hash) AS unique_visitors, COUNT(*) AS total_views
				 FROM {$visits_table}
				 WHERE visit_date = %s AND is_bot = 0",
				$today
			),
			ARRAY_A
		);

		// Aggregated component (Historical statistics)
		$row_agg = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT SUM(unique_visitors) AS unique_visitors, SUM(total_views) AS total_views
			 FROM {$stats_table}
			 WHERE post_id = 0 AND {$where}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$result = array(
			'unique_visitors' => (int) ( $row_today['unique_visitors'] ?? 0 ) + (int) ( $row_agg['unique_visitors'] ?? 0 ),
			'total_views'     => (int) ( $row_today['total_views'] ?? 0 ) + (int) ( $row_agg['total_views'] ?? 0 ),
		);

		$ttl = ( 'today' === $period ) ? 60 : 300; // Shorter cache for live stats
		set_transient( $cache_key, $result, $ttl );

		return $result;
	}

	/**
	 * Get last N days of daily visitor data for charting.
	 *
	 * @param int $days Number of days (default 30).
	 * @return array Array of { date, unique_visitors, total_views }
	 */
	public function get_last_n_days( $days = 30 ) {
		$cache_key = 'meowpack_stats_' . $days . 'days';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$stats_table = $wpdb->prefix . 'meow_daily_stats';
		$visits_table = $wpdb->prefix . 'meow_visits';
		$now_ts      = current_time( 'timestamp' );
		$start       = date( 'Y-m-d', strtotime( "-{$days} days", $now_ts ) );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT stat_date AS date, unique_visitors, total_views
				 FROM {$stats_table}
				 WHERE post_id = 0 AND stat_date >= %s
				 ORDER BY stat_date ASC",
				$start
			),
			ARRAY_A
		);

		// Fill missing dates with zeroes.
		$result = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$date = date( 'Y-m-d', strtotime( "-{$i} days", $now_ts ) );
			$result[ $date ] = array(
				'date'             => $date,
				'unique_visitors'  => 0,
				'total_views'      => 0,
			);
		}

		foreach ( $rows as $row ) {
			if ( isset( $result[ $row['date'] ] ) ) {
				$result[ $row['date'] ]['unique_visitors'] = (int) $row['unique_visitors'];
				$result[ $row['date'] ]['total_views']     = (int) $row['total_views'];
			}
		}

		// Inject today's real-time data into the last point
		$today     = current_time( 'Y-m-d' );
		$row_today = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT ip_hash) AS uv, COUNT(*) AS pv 
				 FROM {$visits_table} WHERE visit_date = %s AND is_bot = 0",
				$today
			),
			ARRAY_A
		);

		if ( isset( $result[ $today ] ) ) {
			$result[ $today ]['unique_visitors'] += (int) ( $row_today['uv'] ?? 0 );
			$result[ $today ]['total_views']     += (int) ( $row_today['pv'] ?? 0 );
		}

		$result = array_values( $result );
		set_transient( $cache_key, $result, 300 );

		return $result;
	}

	/**
	 * Get top posts by views.
	 *
	 * @param int    $count  Number of posts to return.
	 * @param string $period this_month|this_week|today|alltime
	 * @return array
	 */
	public function get_top_posts( $count = 10, $period = 'this_month' ) {
		$cache_key = 'meowpack_top_posts_' . $period . '_' . $count;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$stats_table  = $wpdb->prefix . 'meow_daily_stats';
		$visits_table = $wpdb->prefix . 'meow_visits';

		switch ( $period ) {
			case 'today':
				$where_agg   = '1=0';
				$where_today = $wpdb->prepare( 'visit_date = %s', current_time( 'Y-m-d' ) );
				break;
			case 'this_week':
				$now_ts      = current_time( 'timestamp' );
				$monday      = date( 'Y-m-d', strtotime( 'monday this week', $now_ts ) );
				$where_agg   = $wpdb->prepare( 'stat_date >= %s', $monday );
				$where_today = $wpdb->prepare( 'visit_date >= %s', $monday );
				break;
			case 'this_month':
				$start       = current_time( 'Y-m' ) . '-01';
				$where_agg   = $wpdb->prepare( 'stat_date >= %s', $start );
				$where_today = $wpdb->prepare( 'visit_date >= %s', $start );
				break;
			default: // alltime.
				$where_agg   = '1=1';
				$where_today = '1=1';
		}

		// Fetch raw views from today/current range (real-time)
		$raw_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT post_id, COUNT(*) AS views, COUNT(DISTINCT ip_hash) AS uv 
			 FROM {$visits_table} 
			 WHERE is_bot = 0 AND post_id > 0 AND {$where_today} 
			 GROUP BY post_id",
			ARRAY_A
		);

		// Fetch aggregated views
		$agg_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT post_id, SUM(total_views) AS views, SUM(unique_visitors) AS uv 
			 FROM {$stats_table} 
			 WHERE post_id > 0 AND {$where_agg} 
			 GROUP BY post_id",
			ARRAY_A
		);

		// Merge
		$merged = array();
		foreach ( $raw_rows as $r ) {
			$pid = (int) $r['post_id'];
			$merged[ $pid ] = array( 'views' => (int) $r['views'], 'uv' => (int) $r['uv'] );
		}
		foreach ( $agg_rows as $r ) {
			$pid = (int) $r['post_id'];
			if ( isset( $merged[ $pid ] ) ) {
				$merged[ $pid ]['views'] += (int) $r['views'];
				$merged[ $pid ]['uv']    += (int) $r['uv'];
			} else {
				$merged[ $pid ] = array( 'views' => (int) $r['views'], 'uv' => (int) $r['uv'] );
			}
		}

		// Sort and Limit
		uasort( $merged, function($a, $b) { return $b['views'] <=> $a['views']; } );
		$top_pids = array_slice( $merged, 0, $count, true );

		// Enrich with post data.
		$result = array();
		foreach ( $top_pids as $pid => $data ) {
			$post = get_post( $pid );
			if ( ! $post || 'publish' !== $post->post_status ) {
				continue;
			}
			$result[] = array(
				'post_id'          => $pid,
				'title'            => get_the_title( $post ),
				'url'              => get_permalink( $post ),
				'views'            => $data['views'],
				'unique_visitors'  => $data['uv'],
			);
		}

		set_transient( $cache_key, $result, 300 );

		return $result;
	}

	/**
	 * Get traffic source breakdown for a period.
	 *
	 * @param string $period this_month|this_week|today|alltime
	 * @return array { direct, search, social, referral, email }
	 */
	public function get_source_breakdown( $period = 'this_month' ) {
		$cache_key = 'meowpack_source_' . $period;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$stats_table  = $wpdb->prefix . 'meow_daily_stats';
		$visits_table = $wpdb->prefix . 'meow_visits';

		switch ( $period ) {
			case 'today':
				$where_agg   = '1=0';
				$where_today = $wpdb->prepare( 'visit_date = %s', current_time( 'Y-m-d' ) );
				break;
			case 'this_week':
				$now_ts      = current_time( 'timestamp' );
				$monday      = date( 'Y-m-d', strtotime( 'monday this week', $now_ts ) );
				$where_agg   = $wpdb->prepare( 'stat_date >= %s', $monday );
				$where_today = $wpdb->prepare( 'visit_date >= %s', $monday );
				break;
			case 'this_month':
				$start       = current_time( 'Y-m' ) . '-01';
				$where_agg   = $wpdb->prepare( 'stat_date >= %s', $start );
				$where_today = $wpdb->prepare( 'visit_date >= %s', $start );
				break;
			default:
				$where_agg   = '1=1';
				$where_today = '1=1';
		}

		// Raw component
		$raw_row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT
				SUM(source_type = 'direct') AS direct,
				SUM(source_type = 'search') AS search,
				SUM(source_type = 'social') AS social,
				SUM(source_type = 'referral') AS referral,
				SUM(source_type = 'email') AS email
			 FROM {$visits_table}
			 WHERE is_bot = 0 AND {$where_today}",
			ARRAY_A
		);

		// Aggregated component
		$agg_row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT
				SUM(source_direct) AS direct,
				SUM(source_search) AS search,
				SUM(source_social) AS social,
				SUM(source_referral) AS referral,
				SUM(source_email) AS email
			 FROM {$stats_table}
			 WHERE post_id = 0 AND {$where_agg}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$result = array(
			'direct'   => (int) ( $raw_row['direct'] ?? 0 ) + (int) ( $agg_row['direct'] ?? 0 ),
			'search'   => (int) ( $raw_row['search'] ?? 0 ) + (int) ( $agg_row['search'] ?? 0 ),
			'social'   => (int) ( $raw_row['social'] ?? 0 ) + (int) ( $agg_row['social'] ?? 0 ),
			'referral' => (int) ( $raw_row['referral'] ?? 0 ) + (int) ( $agg_row['referral'] ?? 0 ),
			'email'    => (int) ( $raw_row['email'] ?? 0 ) + (int) ( $agg_row['email'] ?? 0 ),
		);

		set_transient( $cache_key, $result, 300 );

		return $result;
	}

	/**
	 * Get view count for a specific post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $period  alltime|this_month|this_week|today
	 * @return int
	 */
	public function get_post_views( $post_id, $period = 'alltime' ) {
		$post_id   = absint( $post_id );
		$cache_key = "meowpack_post_views_{$post_id}_{$period}";
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		global $wpdb;
		$stats_table = $wpdb->prefix . 'meow_daily_stats';

		switch ( $period ) {
			case 'today':
				$where = $wpdb->prepare( ' AND stat_date = %s', current_time( 'Y-m-d' ) );
				break;
			case 'this_week':
				$start = gmdate( 'Y-m-d', strtotime( 'monday this week' ) );
				$where = $wpdb->prepare( ' AND stat_date >= %s', $start );
				break;
			case 'this_month':
				$start = current_time( 'Y-m' ) . '-01';
				$where = $wpdb->prepare( ' AND stat_date >= %s', $start );
				break;
			case 'this_year':
				$start = current_time( 'Y' ) . '-01-01';
				$where = $wpdb->prepare( ' AND stat_date >= %s', $start );
				break;
			default:
				$where = '';
		}

		$visits_table = $wpdb->prefix . 'meow_visits';
		$today        = current_time( 'Y-m-d' );

		$views_today = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$visits_table} WHERE visit_date = %s AND is_bot = 0 AND post_id = %d",
				$today,
				$post_id
			)
		);

		$views_agg = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT SUM(total_views) FROM {$stats_table} WHERE post_id = %d" . $where, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$post_id
			)
		);

		$views = $views_today + $views_agg;
		set_transient( $cache_key, $views, 300 );

		return $views;
	}

	/**
	 * Get total comments count for a period.
	 */
	public function get_total_comments( $period = 'alltime' ) {
		global $wpdb;
		$where = '';
		switch ( $period ) {
			case 'today':
				$where = $wpdb->prepare( "AND comment_date >= %s", current_time( 'Y-m-d' ) . ' 00:00:00' );
				break;
			case 'week':
				$monday = date( 'Y-m-d', strtotime( 'monday this week', current_time( 'timestamp' ) ) );
				$where = $wpdb->prepare( "AND comment_date >= %s", $monday . ' 00:00:00' );
				break;
			case 'month':
				$where = $wpdb->prepare( "AND comment_date >= %s", current_time( 'Y-m' ) . '-01 00:00:00' );
				break;
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = '1' {$where}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Get total reactions count for a period.
	 */
	public function get_total_reactions( $period = 'alltime' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_reactions';
		$where = '1=1';
		switch ( $period ) {
			case 'today':
				$where = $wpdb->prepare( "created_at >= %s", current_time( 'Y-m-d' ) . ' 00:00:00' );
				break;
			case 'week':
				$monday = date( 'Y-m-d', strtotime( 'monday this week', current_time( 'timestamp' ) ) );
				$where = $wpdb->prepare( "created_at >= %s", $monday . ' 00:00:00' );
				break;
			case 'month':
				$where = $wpdb->prepare( "created_at >= %s", current_time( 'Y-m' ) . '-01 00:00:00' );
				break;
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	// -------------------------------------------------------------------------
	// REST Handler
	// -------------------------------------------------------------------------

	/**
	 * Handle REST /stats request for admin dashboard.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_stats_request( WP_REST_Request $request ) {
		$type   = sanitize_text_field( $request->get_param( 'type' ) ?? 'overview' );
		$period = sanitize_text_field( $request->get_param( 'period' ) ?? 'this_month' );
		$days   = absint( $request->get_param( 'days' ) ?? 30 );
		$count  = absint( $request->get_param( 'count' ) ?? 10 );

		$data = array();

		switch ( $type ) {
			case 'overview':
				$data = array(
					'today'   => $this->get_sitewide_stats( 'today' ),
					'week'    => $this->get_sitewide_stats( 'week' ),
					'month'   => $this->get_sitewide_stats( 'month' ),
					'year'    => $this->get_sitewide_stats( 'year' ),
					'alltime' => $this->get_sitewide_stats( 'alltime' ),
				);
				break;
			case 'chart':
				$chart_data = $this->get_last_n_days( $days );
				$data = array(
					'chart'  => $chart_data,
					'totals' => array(
						'pv' => array_sum( array_column( $chart_data, 'total_views' ) ),
						'uv' => array_sum( array_column( $chart_data, 'unique_visitors' ) ),
					),
				);
				break;
			case 'top_posts':
				$data = $this->get_top_posts( $count, $period );
				break;
			case 'sources':
				$data = $this->get_source_breakdown( $period );
				break;
		}

		return new WP_REST_Response( $data, 200 );
	}
}
