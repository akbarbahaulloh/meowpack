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
		$yesterday = date( 'Y-m-d', strtotime( '-1 day', current_time( 'timestamp' ) ) );
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
						MAX(author_id) AS author_id,
						SUM(source_type = 'direct') AS source_direct,
						SUM(source_type = 'search') AS source_search,
						SUM(source_type = 'social') AS source_social,
						SUM(source_type = 'referral') AS source_referral,
						SUM(source_type = 'email') AS source_email,
						SUM(device_type = 'mobile') AS mobile_views,
						SUM(device_type = 'tablet') AS tablet_views,
						SUM(device_type = 'desktop') AS desktop_views,
						ROUND(AVG(time_on_page)) AS avg_time_on_page,
						ROUND(AVG(scroll_depth)) AS avg_scroll_depth
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
					'stat_date'        => $date,
					'post_id'          => $post_id,
					'author_id'        => (int) $row['author_id'],
					'unique_visitors'  => (int) $row['unique_visitors'],
					'total_views'      => (int) $row['total_views'],
					'source_direct'    => (int) $row['source_direct'],
					'source_search'    => (int) $row['source_search'],
					'source_social'    => (int) $row['source_social'],
					'source_referral'  => (int) $row['source_referral'],
					'source_email'     => (int) $row['source_email'],
					'mobile_views'     => (int) $row['mobile_views'],
					'tablet_views'     => (int) $row['tablet_views'],
					'desktop_views'    => (int) $row['desktop_views'],
					'avg_time_on_page' => (int) $row['avg_time_on_page'],
					'avg_scroll_depth' => (int) $row['avg_scroll_depth'],
				),
				array( '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d' )
			);
		}

		// Bust transient caches after aggregation.

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
		$cutoff   = date( 'Y-m-d', strtotime( "-{$days} days", current_time( 'timestamp' ) ) );

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
		$stats_table  = $wpdb->prefix . 'meow_daily_stats';
		$visits_table = $wpdb->prefix . 'meow_visits';

		// 1. Determine the range for historical aggregated data.
		switch ( $period ) {
			case 'today':
				$where_agg = '1=0'; // No aggregate for today yet.
				break;
			case 'week':
				$monday    = date( 'Y-m-d', strtotime( 'monday this week', current_time( 'timestamp' ) ) );
				$where_agg = $wpdb->prepare( 'stat_date >= %s', $monday );
				break;
			case 'month':
				$start     = current_time( 'Y-m' ) . '-01';
				$where_agg = $wpdb->prepare( 'stat_date >= %s', $start );
				break;
			case 'year':
				$start     = current_time( 'Y' ) . '-01-01';
				$where_agg = $wpdb->prepare( 'stat_date >= %s', $start );
				break;
			default: // alltime.
				$where_agg = '1=1';
		}

		// 2. Fetch the LATEST aggregated date to know where history ends.
		$last_agg_date = $wpdb->get_var( "SELECT MAX(stat_date) FROM {$stats_table}" );
		if ( ! $last_agg_date ) {
			$last_agg_date = '0000-00-00';
		}

		// 3. Historical data from stats table.
		$row_agg = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT SUM(unique_visitors) AS unique_visitors, SUM(total_views) AS total_views
			 FROM {$stats_table}
			 WHERE post_id = 0 AND {$where_agg}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		// 4. Determine range for raw data (Anything NOT in aggregated data).
		// We fetch raw data for the requested period but ONLY for dates > last_agg_date.
		$where_raw_period = '1=1';
		switch ( $period ) {
			case 'today':
				$where_raw_period = $wpdb->prepare( 'visit_date = %s', current_time( 'Y-m-d' ) );
				break;
			case 'week':
				$where_raw_period = $wpdb->prepare( 'visit_date >= %s', $monday );
				break;
			case 'month':
				$where_raw_period = $wpdb->prepare( 'visit_date >= %s', $start );
				break;
			case 'year':
				$where_raw_period = $wpdb->prepare( 'visit_date >= %s', $start );
				break;
		}

		$date_filter = ( 'today' === $period ) ? '' : $wpdb->prepare( ' AND visit_date > %s', $last_agg_date );
		
		// Combined where: date in period AND date > last_agg_date (except for 'today').
		$row_raw = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COUNT(DISTINCT ip_hash) AS unique_visitors, COUNT(*) AS total_views
			 FROM {$visits_table}
			 WHERE is_bot = 0 AND {$where_raw_period}{$date_filter}",
			ARRAY_A
		);

		$result = array(
			'unique_visitors' => (int) ( $row_agg['unique_visitors'] ?? 0 ) + (int) ( $row_raw['unique_visitors'] ?? 0 ),
			'total_views'     => (int) ( $row_agg['total_views'] ?? 0 ) + (int) ( $row_raw['total_views'] ?? 0 ),
		);

		$ttl = ( 'today' === $period ) ? 1 : 300;
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
		$stats_table  = $wpdb->prefix . 'meow_daily_stats';
		$visits_table = $wpdb->prefix . 'meow_visits';
		
		// Use a very reliable "today" definition.
		$today_date   = current_time( 'Y-m-d' );
		$now_ts       = strtotime( $today_date ); // Mid-night start of today.
		$start_date   = date( 'Y-m-d', strtotime( "-{$days} days", $now_ts + 43200 ) ); // +12h for safety in math.

		// 1. Fetch Aggregated Data
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT stat_date AS date, unique_visitors, total_views
				 FROM {$stats_table}
				 WHERE post_id = 0 AND stat_date >= %s
				 ORDER BY stat_date ASC",
				$start_date
			),
			ARRAY_A
		);

		// 2. Fetch Raw Data for non-aggregated days (the last gap).
		$last_agg_date = $wpdb->get_var( "SELECT MAX(stat_date) FROM {$stats_table}" );
		if ( ! $last_agg_date ) {
			$last_agg_date = '0000-00-00';
		}

		$raw_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT visit_date AS date, COUNT(DISTINCT ip_hash) AS uv, COUNT(*) AS pv
				 FROM {$visits_table}
				 WHERE is_bot = 0 AND visit_date > %s AND visit_date >= %s
				 GROUP BY visit_date",
				$last_agg_date,
				$start_date
			),
			ARRAY_A
		);

		// Initialize results with 0s for the entire range.
		$result = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$date = date( 'Y-m-d', strtotime( "-{$i} days", $now_ts + 43200 ) );
			$result[ $date ] = array(
				'date'             => $date,
				'unique_visitors'  => 0,
				'total_views'      => 0,
			);
		}

		// Fill in Aggregated
		foreach ( $rows as $row ) {
			if ( isset( $result[ $row['date'] ] ) ) {
				$result[ $row['date'] ]['unique_visitors'] = (int) $row['unique_visitors'];
				$result[ $row['date'] ]['total_views']     = (int) $row['total_views'];
			}
		}

		// Fill in Raw (The bridge)
		foreach ( $raw_rows as $row ) {
			if ( isset( $result[ $row['date'] ] ) ) {
				$result[ $row['date'] ]['unique_visitors'] += (int) $row['uv'];
				$result[ $row['date'] ]['total_views']     += (int) $row['pv'];
			}
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
			case 'this_year':
				$start       = current_time( 'Y' ) . '-01-01';
				$where_agg   = $wpdb->prepare( 'stat_date >= %s', $start );
				$where_today = $wpdb->prepare( 'visit_date >= %s', $start );
				break;
			default: // alltime.
				$where_agg   = '1=1';
				$where_today = '1=1';
		}

		// Smart-Merge: Avoid double counting based on last aggregation date.
		$last_agg_date = $wpdb->get_var( "SELECT MAX(stat_date) FROM {$stats_table}" ) ?: '0000-00-00';

		$date_filter = ( 'today' === $period ) ? '' : $wpdb->prepare( ' AND visit_date > %s', $last_agg_date );
		
		// Fetch raw views from today/current range (real-time - only for days NOT aggregated)
		$raw_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT post_id, COUNT(*) AS views, COUNT(DISTINCT ip_hash) AS uv 
			 FROM {$visits_table} 
			 WHERE is_bot = 0 AND post_id > 0 AND {$where_today}{$date_filter}
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
			case 'this_year':
				$start       = current_time( 'Y' ) . '-01-01';
				$where_agg   = $wpdb->prepare( 'stat_date >= %s', $start );
				$where_today = $wpdb->prepare( 'visit_date >= %s', $start );
				break;
			default:
				$where_agg   = '1=1';
				$where_today = '1=1';
		}

		// Smart-Merge Logic: Find the gap between aggregated and raw data.
		$last_agg_date = $wpdb->get_var( "SELECT MAX(stat_date) FROM {$stats_table}" );
		if ( ! $last_agg_date ) {
			$last_agg_date = '0000-00-00';
		}

		// Raw component (Only for dates NOT in aggregated data)
		$raw_row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT
					SUM(source_type = 'direct') AS direct,
					SUM(source_type = 'search') AS search,
					SUM(source_type = 'social') AS social,
					SUM(source_type = 'referral') AS referral,
					SUM(source_type = 'email') AS email
				 FROM {$visits_table}
				 WHERE is_bot = 0 AND {$where_today} AND visit_date > %s",
				$last_agg_date
			),
			ARRAY_A
		);

		// Aggregated component (Historical)
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
				$now_ts = current_time( 'timestamp' );
				$monday = date( 'Y-m-d', strtotime( 'monday this week', $now_ts ) );
				$where = $wpdb->prepare( ' AND stat_date >= %s', $monday );
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
	 * Get view count for a specific post (REAL-TIME, NO CACHE).
	 * Used for frontend display to show accurate count immediately.
	 * v2.2.1: Added for real-time view counter display
	 *
	 * @param int    $post_id Post ID.
	 * @param string $period  alltime|this_month|this_week|today
	 * @return int
	 */
	public function get_post_views_realtime( $post_id, $period = 'alltime' ) {
		$post_id = absint( $post_id );

		global $wpdb;
		$stats_table = $wpdb->prefix . 'meow_daily_stats';

		switch ( $period ) {
			case 'today':
				$where = $wpdb->prepare( ' AND stat_date = %s', current_time( 'Y-m-d' ) );
				break;
			case 'this_week':
				$now_ts = current_time( 'timestamp' );
				$monday = date( 'Y-m-d', strtotime( 'monday this week', $now_ts ) );
				$where = $wpdb->prepare( ' AND stat_date >= %s', $monday );
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

		// NO CACHE - returns fresh count every time for real-time display
		return $views_today + $views_agg;
	}

	/**
	 * Get detailed view count for a specific post (total and daily).
	 * Used for Top-10 style formatted text.
	 *
	 * @param int $post_id Post ID.
	 * @return array{ total: int, daily: int }
	 */
	public function get_post_views_detailed( $post_id ) {
		$post_id = absint( $post_id );
		global $wpdb;
		$table = $wpdb->prefix . 'meow_post_views';

		// We can query the extremely fast flat table directly.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT total_views, daily_views, view_date FROM {$table} WHERE post_id = %d", $post_id ) );
		
		$total = 0;
		$daily = 0;

		if ( $row ) {
			$total = (int) $row->total_views;
			if ( $row->view_date === current_time( 'Y-m-d' ) ) {
				$daily = (int) $row->daily_views;
			}
		}

		return array( 'total' => $total, 'daily' => $daily );
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

	/**
	 * Get device type breakdown.
	 */
	public function get_device_stats( $period = 'today' ) {
		return $this->get_generic_breakdown( $period, 'device_type', array( 'mobile', 'tablet', 'desktop' ) );
	}

	/**
	 * Get browser breakdown.
	 */
	public function get_browser_stats( $period = 'today', $limit = 10 ) {
		return $this->get_generic_breakdown( $period, 'browser', array(), $limit );
	}

	/**
	 * Get OS breakdown.
	 */
	public function get_os_stats( $period = 'today', $limit = 10 ) {
		return $this->get_generic_breakdown( $period, 'os', array(), $limit );
	}

	/**
	 * Helper for generic breakdowns.
	 */
	private function get_generic_breakdown( $period, $field, $predefined_keys = array(), $limit = 15 ) {
		$cache_key = 'meowpack_breakdown_' . $field . '_' . $period;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) return $cached;

		global $wpdb;
		$stats_table  = $wpdb->prefix . 'meow_daily_stats';
		$visits_table = $wpdb->prefix . 'meow_visits';

		$monday = date( 'Y-m-d', strtotime( 'monday this week', current_time( 'timestamp' ) ) );
		$month_start = current_time( 'Y-m' ) . '-01';
		$year_start  = current_time( 'Y' ) . '-01-01';

		switch ( $period ) {
			case 'today':      $w_agg = '1=0'; $w_raw = $wpdb->prepare( 'visit_date = %s', current_time( 'Y-m-d' ) ); break;
			case 'this_week':  $w_agg = $wpdb->prepare( 'stat_date >= %s', $monday ); $w_raw = $wpdb->prepare( 'visit_date >= %s', $monday ); break;
			case 'this_month': $w_agg = $wpdb->prepare( 'stat_date >= %s', $month_start ); $w_raw = $wpdb->prepare( 'visit_date >= %s', $month_start ); break;
			case 'this_year':  $w_agg = $wpdb->prepare( 'stat_date >= %s', $year_start ); $w_raw = $wpdb->prepare( 'visit_date >= %s', $year_start ); break;
			default:           $w_agg = '1=1'; $w_raw = '1=1';
		}

		$last_agg_date = $wpdb->get_var( "SELECT MAX(stat_date) FROM {$stats_table}" ) ?: '0000-00-00';

		// Query Raw (for missing days)
		$raw_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT {$field} AS k, COUNT(*) AS v FROM {$visits_table} 
			 WHERE is_bot = 0 AND {$w_raw} AND visit_date > %s AND {$field} IS NOT NULL 
			 GROUP BY {$field} ORDER BY v DESC LIMIT %d",
			$last_agg_date, $limit
		), ARRAY_A );

		$merged = array();
		foreach($raw_rows as $r) { $merged[$r['k']] = (int)$r['v']; }

		set_transient( $cache_key, $merged, 300 );
		return $merged;
	}

	/**
	 * Get top countries.
	 */
	public function get_country_stats( $period = 'today' ) {
		$cache_key = 'meowpack_countries_' . $period;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) return $cached;

		global $wpdb;
		$visits_table = $wpdb->prefix . 'meow_visits';

		$monday = date( 'Y-m-d', strtotime( 'monday this week', current_time( 'timestamp' ) ) );
		$month_start = current_time( 'Y-m' ) . '-01';
		$year_start  = current_time( 'Y' ) . '-01-01';

		switch ( $period ) {
			case 'today':      $w_raw = $wpdb->prepare( 'visit_date = %s', current_time( 'Y-m-d' ) ); break;
			case 'this_week':  $w_raw = $wpdb->prepare( 'visit_date >= %s', $monday ); break;
			case 'this_month': $w_raw = $wpdb->prepare( 'visit_date >= %s', $month_start ); break;
			case 'this_year':  $w_raw = $wpdb->prepare( 'visit_date >= %s', $year_start ); break;
			default:           $w_raw = '1=1';
		}

		$results = $wpdb->get_results(
			"SELECT country_code, COUNT(*) AS views, COUNT(DISTINCT ip_hash) AS unique_visitors
			 FROM {$visits_table} WHERE is_bot = 0 AND {$w_raw} AND country_code IS NOT NULL AND country_code != ''
			 GROUP BY country_code ORDER BY views DESC LIMIT 30",
			ARRAY_A
		);

		set_transient( $cache_key, $results, 300 );
		return $results;
	}

	/**
	 * Get top regions (provinces/states).
	 */
	public function get_region_stats( $period = 'today', $limit = 15 ) {
		return $this->get_generic_breakdown( $period, 'region', array(), $limit );
	}

	/**
	 * Get top cities.
	 */
	public function get_city_stats( $period = 'today', $limit = 15 ) {
		return $this->get_generic_breakdown( $period, 'city', array(), $limit );
	}

	/**
	 * Get top authors.
	 */
	public function get_author_stats( $period = 'today' ) {
		$cache_key = 'meowpack_authors_' . $period;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) return $cached;

		global $wpdb;
		$visits_table = $wpdb->prefix . 'meow_visits';

		$monday = date( 'Y-m-d', strtotime( 'monday this week', current_time( 'timestamp' ) ) );
		$month_start = current_time( 'Y-m' ) . '-01';
		$year_start  = current_time( 'Y' ) . '-01-01';

		switch ( $period ) {
			case 'today':      $w_raw = $wpdb->prepare( 'visit_date = %s', current_time( 'Y-m-d' ) ); break;
			case 'this_week':  $w_raw = $wpdb->prepare( 'visit_date >= %s', $monday ); break;
			case 'this_month': $w_raw = $wpdb->prepare( 'visit_date >= %s', $month_start ); break;
			case 'this_year':  $w_raw = $wpdb->prepare( 'visit_date >= %s', $year_start ); break;
			default:           $w_raw = '1=1';
		}

		$results = $wpdb->get_results(
			"SELECT author_id, COUNT(*) AS views, COUNT(DISTINCT ip_hash) AS unique_visitors, COUNT(DISTINCT post_id) AS post_count
			 FROM {$visits_table} WHERE is_bot = 0 AND {$w_raw} AND author_id > 0
			 GROUP BY author_id ORDER BY views DESC LIMIT 20",
			ARRAY_A
		);

		set_transient( $cache_key, $results, 300 );
		return $results;
	}

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

	/**
	 * Get top post for a specific author.
	 */
	public function get_top_post_for_author( $author_id, $period = 'today' ) {
		global $wpdb;
		$visits_table = $wpdb->prefix . 'meow_visits';

		$monday = date( 'Y-m-d', strtotime( 'monday this week', current_time( 'timestamp' ) ) );
		$month_start = current_time( 'Y-m' ) . '-01';
		$year_start  = current_time( 'Y' ) . '-01-01';

		switch ( $period ) {
			case 'today':      $w_raw = $wpdb->prepare( 'visit_date = %s', current_time( 'Y-m-d' ) ); break;
			case 'this_week':  $w_raw = $wpdb->prepare( 'visit_date >= %s', $monday ); break;
			case 'this_month': $w_raw = $wpdb->prepare( 'visit_date >= %s', $month_start ); break;
			case 'this_year':  $w_raw = $wpdb->prepare( 'visit_date >= %s', $year_start ); break;
			default:           $w_raw = '1=1';
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT post_id, COUNT(*) AS views
				 FROM {$visits_table} WHERE is_bot = 0 AND author_id = %d AND {$w_raw}
				 GROUP BY post_id ORDER BY views DESC LIMIT 1",
				$author_id
			),
			ARRAY_A
		);
	}

	/**
	 * Get post views from the simple wp_meow_post_views table (real-time, no cache).
	 * This is the new approach for instant display without timing issues.
	 *
	 * @param int $post_id Post ID.
	 * @return int Total views.
	 */
	public function get_post_views_simple( $post_id ) {
		$post_id = absint( $post_id );

		global $wpdb;
		$table = $wpdb->prefix . 'meow_post_views';

		// Query directly from simple counter table (no cache, real-time).
		$total = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT SUM(total_views) FROM {$table} WHERE post_id = %d",
				$post_id
			)
		);

		return $total;
	}
}
