<?php
/**
 * Importer class — migrate data from Jetpack or CSV export.
 *
 * @package MeowPack
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MeowPack_Importer
 *
 * Detects and imports Jetpack stats, or imports from a WordPress.com CSV export.
 */
class MeowPack_Importer {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// REST handler registered in Core.
	}

	/**
	 * Handle import REST request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_import_request( WP_REST_Request $request ) {
		$source = sanitize_text_field( $request->get_param( 'source' ) ?? 'jetpack' );
		$offset = absint( $request->get_param( 'offset' ) ?? 0 );
		$limit  = 200;

		if ( 'csv' === $source ) {
			$file = sanitize_text_field( $request->get_param( 'file' ) ?? '' );
			if ( ! $file || ! file_exists( $file ) ) {
				return new WP_REST_Response( array( 'error' => 'File not found' ), 400 );
			}
			$result = $this->import_from_csv( $file, $offset, $limit );
		} else {
			if ( ! $this->has_jetpack_data() ) {
				return new WP_REST_Response( array( 'error' => 'No Jetpack data found' ), 404 );
			}
			$result = $this->import_from_jetpack( $offset, $limit );
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Check if Jetpack stat data exists.
	 *
	 * @return bool
	 */
	public function has_jetpack_data() {
		global $wpdb;

		// Check Jetpack option.
		$jetpack_active = get_option( 'jetpack_options' );
		if ( $jetpack_active ) {
			return true;
		}

		// Check for Jetpack stats table.
		$table = $wpdb->prefix . 'jetpack_sites';
		$exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SHOW TABLES LIKE %s", $table )
		);

		return ! empty( $exists );
	}

	/**
	 * Import from Jetpack database tables.
	 *
	 * @param int $offset Pagination offset.
	 * @param int $limit  Rows per batch.
	 * @return array{ imported: int, skipped: int, done: bool }
	 */
	public function import_from_jetpack( $offset = 0, $limit = 200 ) {
		global $wpdb;

		$imported = 0;
		$skipped  = 0;

		// Try common Jetpack stats table names.
		$jetpack_tables = array(
			$wpdb->prefix . 'jetpack_post_views',
			$wpdb->base_prefix . 'stats_post_views',
		);

		$source_table = null;
		foreach ( $jetpack_tables as $t ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $t ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $exists ) {
				$source_table = $t;
				break;
			}
		}

		// Fallback: try to read from Jetpack option blob.
		if ( ! $source_table ) {
			return $this->import_from_jetpack_options( $offset, $limit );
		}

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT post_id, view_date, views FROM {$source_table} ORDER BY view_date ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit, $offset
			),
			ARRAY_A
		);

		foreach ( $rows as $row ) {
			$result = $this->upsert_daily_stat(
				$row['view_date'],
				absint( $row['post_id'] ),
				absint( $row['views'] )
			);
			$result ? $imported++ : $skipped++;
		}

		$done = count( $rows ) < $limit;

		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
			'offset'   => $offset + count( $rows ),
			'done'     => $done,
		);
	}

	/**
	 * Attempt to read Jetpack stats from serialized option blob.
	 *
	 * @param int $offset Offset.
	 * @param int $limit  Limit.
	 * @return array
	 */
	private function import_from_jetpack_options( $offset, $limit ) {
		$stats = get_option( 'stats_cache' ) ?: get_option( 'jetpack_post_stats' );

		if ( ! $stats || ! is_array( $stats ) ) {
			return array( 'imported' => 0, 'skipped' => 0, 'offset' => 0, 'done' => true, 'message' => 'No Jetpack stats data found in options.' );
		}

		$imported = 0;
		$skipped  = 0;
		$items    = array_slice( $stats, $offset, $limit );

		foreach ( $items as $post_id => $daily_data ) {
			if ( ! is_array( $daily_data ) ) {
				continue;
			}
			foreach ( $daily_data as $date => $views ) {
				$result = $this->upsert_daily_stat( $date, absint( $post_id ), absint( $views ) );
				$result ? $imported++ : $skipped++;
			}
		}

		$done = count( $items ) < $limit;

		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
			'offset'   => $offset + count( $items ),
			'done'     => $done,
		);
	}

	/**
	 * Import from a WordPress.com CSV export file.
	 *
	 * Expected CSV format: date, post_id (or 0 for sitewide), views
	 *
	 * @param string $file_path Absolute path to CSV file.
	 * @param int    $offset    Line offset (skip header).
	 * @param int    $limit     Rows per batch.
	 * @return array{ imported: int, skipped: int, done: bool }
	 */
	public function import_from_csv( $file_path, $offset = 0, $limit = 200 ) {
		$imported = 0;
		$skipped  = 0;

		if ( ! file_exists( $file_path ) ) {
			return array( 'error' => 'File not found', 'imported' => 0, 'skipped' => 0, 'done' => true );
		}

		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return array( 'error' => 'Cannot open file', 'imported' => 0, 'skipped' => 0, 'done' => true );
		}

		$line_num = 0;
		$read     = 0;

		while ( ( $row = fgetcsv( $handle ) ) !== false ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$line_num++;

			// Skip header row.
			if ( 1 === $line_num ) {
				continue;
			}

			// Skip rows before offset.
			if ( $line_num <= $offset + 1 ) {
				continue;
			}

			if ( $read >= $limit ) {
				break;
			}

			if ( count( $row ) < 2 ) {
				$skipped++;
				$read++;
				continue;
			}

			// Support formats: date,views or date,post_id,views.
			if ( count( $row ) >= 3 ) {
				$date    = sanitize_text_field( trim( $row[0] ) );
				$post_id = absint( trim( $row[1] ) );
				$views   = absint( trim( $row[2] ) );
			} else {
				$date    = sanitize_text_field( trim( $row[0] ) );
				$post_id = 0;
				$views   = absint( trim( $row[1] ) );
			}

			// Validate date.
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				$skipped++;
				$read++;
				continue;
			}

			$result = $this->upsert_daily_stat( $date, $post_id, $views );
			$result ? $imported++ : $skipped++;
			$read++;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$done = $read < $limit;

		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
			'offset'   => $offset + $read,
			'done'     => $done,
		);
	}

	/**
	 * Upsert a row in meow_daily_stats without duplicating.
	 *
	 * @param string $date    Y-m-d date string.
	 * @param int    $post_id Post ID (0 = sitewide).
	 * @param int    $views   View count.
	 * @return bool True if inserted/updated, false if skipped.
	 */
	private function upsert_daily_stat( $date, $post_id, $views ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_daily_stats';

		if ( $views <= 0 ) {
			return false;
		}

		// Check existence.
		$existing = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id, total_views FROM {$table} WHERE stat_date = %s AND post_id = %d",
				$date, $post_id
			),
			ARRAY_A
		);

		if ( $existing ) {
			// Only update if imported value is higher (don't overwrite real data).
			if ( (int) $existing['total_views'] < $views ) {
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$table,
					array( 'total_views' => $views ),
					array( 'id' => (int) $existing['id'] ),
					array( '%d' ),
					array( '%d' )
				);
				return true;
			}
			return false; // Skip — already have better data.
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'stat_date'       => $date,
				'post_id'         => $post_id,
				'total_views'     => $views,
				'unique_visitors' => (int) round( $views * 0.75 ), // Jetpack doesn't separate UV.
				'source_direct'   => $views,
			),
			array( '%s', '%d', '%d', '%d', '%d' )
		);

		return true;
	}
}
