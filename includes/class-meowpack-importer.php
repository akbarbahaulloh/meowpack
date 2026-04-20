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
	 * Check if Jetpack stat data exists (even if plugin is inactive).
	 *
	 * @return bool
	 */
	public function has_jetpack_data() {
		global $wpdb;

		// 1. Check if Jetpack plugin is active
		if ( class_exists( 'Jetpack' ) ) {
			return true;
		}

		// 2. Check for Jetpack options containing stats cache
		if ( get_option( 'stats_cache' ) || get_option( 'jetpack_post_stats' ) || get_option( 'jetpack_options' ) ) {
			return true;
		}

		// 3. Check for Jetpack stats tables (fallback for older versions or multisite)
		$jetpack_tables = array(
			$wpdb->prefix . 'jetpack_post_views',
			$wpdb->prefix . 'jetpack_sites',
			$wpdb->base_prefix . 'stats_post_views',
		);

		foreach ( $jetpack_tables as $table ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $exists ) {
				return true;
			}
		}

		return false;
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
	 * Enhanced to auto-detect columns and handle flexible date formats.
	 *
	 * @param string $file_path Absolute path to CSV file.
	 * @param int    $offset    Line offset (skipped after header).
	 * @param int    $limit     Rows per batch.
	 * @return array
	 */
	public function import_from_csv( $file_path, $offset = 0, $limit = 200 ) {
		$imported = 0;
		$skipped  = 0;

		if ( ! file_exists( $file_path ) ) {
			return array( 'success' => false, 'error' => 'File tidak ditemukan.' );
		}

		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return array( 'success' => false, 'error' => 'Gagal membuka file CSV.' );
		}

		// 1. Read header to map columns.
		$header = fgetcsv( $handle );
		if ( ! $header ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return array( 'success' => false, 'error' => 'File CSV kosong atau tidak valid.' );
		}

		$map = array(
			'date'     => -1,
			'views'    => -1,
			'visitors' => -1,
			'post_id'  => -1,
		);

		foreach ( $header as $idx => $col ) {
			$col = strtolower( trim( $col ) );
			// Jetpack common headers: Date, Views, Visitors, Post ID, Article ID
			if ( strpos( $col, 'date' ) !== false )      $map['date']     = $idx;
			if ( strpos( $col, 'view' ) !== false )      $map['views']    = $idx;
			if ( strpos( $col, 'visit' ) !== false )     $map['visitors'] = $idx;
			if ( strpos( $col, 'post id' ) !== false )   $map['post_id']  = $idx;
			if ( strpos( $col, 'article id' ) !== false )$map['post_id']  = $idx;
		}

		// Fallback for simple date,views format if no headers match.
		if ( $map['date'] === -1 && count( $header ) >= 2 ) {
			$map['date']  = 0;
			$map['views'] = 1;
		}

		if ( $map['date'] === -1 || $map['views'] === -1 ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return array( 'success' => false, 'error' => 'Kolom "Date" atau "Views" tidak ditemukan di CSV.' );
		}

		// 2. Skip to offset.
		for ( $i = 0; $i < $offset; $i++ ) {
			fgetcsv( $handle );
		}

		// 3. Process batch.
		$read = 0;
		while ( $read < $limit && ( $row = fgetcsv( $handle ) ) !== false ) {
			$read++;

			$date_raw = $row[ $map['date'] ] ?? '';
			$views    = absint( $row[ $map['views'] ] ?? 0 );
			$post_id  = $map['post_id'] !== -1 ? absint( $row[ $map['post_id'] ] ?? 0 ) : 0;
			$visitors = $map['visitors'] !== -1 ? absint( $row[ $map['visitors'] ] ?? 0 ) : 0;

			if ( empty( $date_raw ) || $views <= 0 ) {
				$skipped++;
				continue;
			}

			// Flexible date parsing.
			$timestamp = strtotime( $date_raw );
			if ( ! $timestamp ) {
				$skipped++;
				continue;
			}
			$date = date( 'Y-m-d', $timestamp );

			$result = $this->upsert_daily_stat( $date, $post_id, $views, $visitors );
			$result ? $imported++ : $skipped++;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return array(
			'success'  => true,
			'imported' => $imported,
			'skipped'  => $skipped,
			'offset'   => $offset + $read,
			'done'     => $read < $limit,
		);
	}

	/**
	 * Upsert a row in meow_daily_stats without duplicating.
	 *
	 * @param string $date     Y-m-d date string.
	 * @param int    $post_id  Post ID (0 = sitewide).
	 * @param int    $views    View count.
	 * @param int    $visitors Unique visitor count (0 = estimate 75% of views).
	 * @return bool True if inserted/updated, false if skipped.
	 */
	public function upsert_daily_stat( $date, $post_id, $views, $visitors = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_daily_stats';

		if ( $views <= 0 ) {
			return false;
		}

		if ( $visitors <= 0 ) {
			$visitors = (int) round( $views * 0.75 );
		}

		// Check existence.
		$existing = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id, total_views, unique_visitors FROM {$table} WHERE stat_date = %s AND post_id = %d",
				$date, $post_id
			),
			ARRAY_A
		);

		if ( $existing ) {
			$data = array();
			if ( (int) $existing['total_views'] < $views )       $data['total_views'] = $views;
			if ( (int) $existing['unique_visitors'] < $visitors ) $data['unique_visitors'] = $visitors;

			if ( ! empty( $data ) ) {
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$table,
					$data,
					array( 'id' => (int) $existing['id'] ),
					array( '%d' ),
					array( '%d' )
				);
				return true;
			}
			return false;
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'stat_date'       => $date,
				'post_id'         => $post_id,
				'total_views'     => $views,
				'unique_visitors' => $visitors,
				'source_direct'   => $views,
			),
			array( '%s', '%d', '%d', '%d', '%d' )
		);

		return true;
	}
}
