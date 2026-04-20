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
				return new WP_REST_Response( array( 'error' => 'File tidak ditemukan' ), 400 );
			}
			$result = $this->import_from_csv( $file, $offset, $limit );
		} else {
			return new WP_REST_Response( array( 'error' => 'Sumber migrasi tidak didukung. Silakan gunakan impor CSV.' ), 400 );
		}

		return new WP_REST_Response( $result, 200 );
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
