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

		if ( 'api' === $source ) {
			return new WP_REST_Response( $this->import_from_api( $offset ), 200 );
		}

		if ( 'api_preview' === $source ) {
			$data = array();
			switch ( $offset ) {
				case 0:
					$data = $this->fetch_jetpack_api( 'stats/visits', array( 'unit' => 'day', 'quantity' => 30 ) );
					break;
				case 1:
					$data = $this->fetch_jetpack_api( 'stats/top-posts', array( 'period' => 'all', 'max' => 50 ) );
					break;
				case 2:
					$data = $this->fetch_jetpack_api( 'stats/referrers', array( 'period' => 'all', 'max' => 50 ) );
					break;
				case 3:
					$data = $this->fetch_jetpack_api( 'stats/country-views', array( 'period' => 'all' ) );
					break;
				case 4:
					$data = $this->fetch_jetpack_api( 'stats/search-terms', array( 'period' => 'all' ) );
					break;
				case 5:
					$data = $this->fetch_jetpack_api( 'stats/clicks', array( 'period' => 'all' ) );
					break;
				case 6:
					$data = $this->fetch_jetpack_api( 'stats/post-views', array( 'period' => 'month' ) );
					break;
			}
			return new WP_REST_Response( array( 'success' => true, 'preview' => wp_json_encode( $data, JSON_PRETTY_PRINT ) ), 200 );
		}

		if ( 'csv' !== $source ) {
			return new WP_REST_Response( array( 'error' => 'Sumber migrasi tidak didukung. Silakan gunakan impor CSV atau Direct API.' ), 400 );
		}

		$upload_dir = wp_upload_dir();
		$temp_file  = $upload_dir['basedir'] . '/meowpack_import_temp.csv';

		if ( $offset === 0 ) {
			$files = $request->get_file_params();
			if ( empty( $files['file'] ) || empty( $files['file']['tmp_name'] ) ) {
				return new WP_REST_Response( array( 'error' => 'File CSV wajib diunggah.' ), 400 );
			}
			$ext = pathinfo( $files['file']['name'], PATHINFO_EXTENSION );
			if ( strtolower( $ext ) !== 'csv' ) {
				return new WP_REST_Response( array( 'error' => 'Gagal: Ekstensi harus .csv' ), 400 );
			}
			move_uploaded_file( $files['file']['tmp_name'], $temp_file );
		}

		if ( ! file_exists( $temp_file ) ) {
			return new WP_REST_Response( array( 'error' => 'File sementara tidak ditemukan. Mohon unggah ulang.' ), 400 );
		}

		$result = $this->import_from_csv( $temp_file, $offset, $limit );

		if ( ! empty( $result['done'] ) || ! empty( $result['error'] ) ) {
			@unlink( $temp_file );
		}

		return new WP_REST_Response( $result, 200 );
	}


	/**
	 * Makes an authenticated request to WordPress.com API using Jetpack's connection.
	 * 
	 * @param string $endpoint API Path.
	 * @param array  $params   Query parameters.
	 * @return array|WP_Error
	 */
	private function fetch_jetpack_api( $endpoint, $params = array() ) {
		if ( ! class_exists( 'Jetpack_Options' ) || ( ! class_exists( 'Automattic\Jetpack\Connection\Client' ) && ! class_exists( 'Jetpack_Client' ) ) ) {
			return new WP_Error( 'no_jetpack', 'Plugin Jetpack harus aktif dan terhubung dengan WordPress.com untuk menyedot data via API.' );
		}

		$site_id = Jetpack_Options::get_option( 'id' );
		if ( ! $site_id ) {
			return new WP_Error( 'no_jetpack_id', 'Koneksi Jetpack terputus. Silakan hubungkan Jetpack kembali sebelum melakukan migrasi.' );
		}

		$path = '/sites/' . $site_id . '/' . ltrim( $endpoint, '/' );
		if ( ! empty( $params ) ) {
			$path = add_query_arg( $params, $path );
		}

		if ( class_exists( 'Automattic\Jetpack\Connection\Client' ) ) {
			$response = Automattic\Jetpack\Connection\Client::wpcom_json_api_request_as_blog( $path, '1.1' );
		} else {
			$response = Jetpack_Client::wpcom_json_api_request_as_blog( $path, '1.1' );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['error'] ) && 'unauthorized' === $data['error'] ) {
			return new WP_Error( 'unauthorized', 'Token Jetpack Anda sudah hangus/ditolak oleh WordPress.com. Silakan gunakan Impor CSV.' );
		}

		if ( isset( $data['error'] ) ) {
			return new WP_Error( 'api_error', $data['message'] ?? $data['error'] );
		}

		return $data;
	}

	/**
	 * Direct API Scraper using Token Hijacking.
	 * 
	 * @param int $step Offset step (0 = visits, 1 = top posts, 2 = referrers).
	 * @return array
	 */
	public function import_from_api( $step = 0 ) {
		$imported = 0;
		$skipped  = 0;
		$done     = false;
		$msg      = '';

		switch ( $step ) {
			case 0:
				// Step 0: Pull last 30 days of daily limits
				$data = $this->fetch_jetpack_api( 'stats/visits', array( 'unit' => 'day', 'quantity' => 30 ) );
				if ( is_wp_error( $data ) ) {
					return array( 'success' => false, 'error' => $data->get_error_message() );
				}

				if ( ! empty( $data['data'] ) && is_array( $data['data'] ) ) {
					foreach ( $data['data'] as $row ) {
						// Format: [ "2024-04-20", 1500, 1000 ] -> date, views, visitors
						if ( count( $row ) < 2 ) continue;
						$date     = date( 'Y-m-d', strtotime( $row[0] ) );
						$views    = absint( $row[1] );
						$visitors = absint( $row[2] ?? 0 );
						$result   = $this->upsert_daily_stat( $date, 0, $views, $visitors, 'source_direct' );
						$result ? $imported++ : $skipped++;
					}
				}
				$msg = "Berhasil menarik data harian 30 hari ke belakang.";
				$next_step = 1;
				break;

			case 1:
				// Step 1: Pull Top Posts
				$data = $this->fetch_jetpack_api( 'stats/top-posts', array( 'max' => 100 ) ); // WP.com API parameter fallbacks
				if ( is_wp_error( $data ) ) {
					return array( 'success' => false, 'error' => $data->get_error_message() );
				}

				// Depending on API response, WP.com wraps the array in 'days' -> 'YYYY-MM-DD' -> 'postviews'
				$days_array = $data['days'] ?? array();
				$day_data   = reset( $days_array ); // dynamically get the first key (which is the date string)
				$posts      = $day_data['postviews'] ?? array();

				foreach ( $posts as $p ) {
					$post_id = absint( $p['id'] ?? 0 );
					$views   = absint( $p['views'] ?? 0 );
					if ( ! $post_id || ! $views ) continue;
					
					// Calculate fallback date based on publish date
					$date = '1970-01-01';
					if ( ! empty( $p['date'] ) ) {
						$date = gmdate( 'Y-m-d', strtotime( $p['date'] ) );
					} else {
						$post = get_post( $post_id );
						if ( $post ) {
							$date = gmdate( 'Y-m-d', strtotime( $post->post_date ) );
						}
					}

					$result = $this->upsert_daily_stat( $date, $post_id, $views, 0 );
					$result ? $imported++ : $skipped++;
				}
				$msg = "Berhasil menarik data total Artikel Terpopuler.";
				$next_step = 2;
				break;

			case 2:
				// Step 2: Pull Top Referrers
				$data = $this->fetch_jetpack_api( 'stats/referrers', array( 'max' => 50 ) );
				if ( is_wp_error( $data ) ) {
					$done = true;
					break;
				}

				$days_array = $data['days'] ?? array();
				$day_data   = reset( $days_array ); // dynamically get the first key (the date string)
				$groups     = $day_data['groups'] ?? array();

				foreach ( $groups as $group ) {
					$name  = strtolower( trim( $group['name'] ?? '' ) );
					$views = absint( $group['total'] ?? 0 );
					if ( ! $name || ! $views ) continue;

					$source_col = 'source_referral';
					if ( strpos( $name, 'search engines' ) !== false || strpos( $name, 'mesin pencari' ) !== false || strpos( $name, 'google' ) !== false ) {
						$source_col = 'source_search';
					} elseif ( strpos( $name, 'facebook' ) !== false || strpos( $name, 'twitter' ) !== false || strpos( $name, 'instagram' ) !== false ) {
						$source_col = 'source_social';
					}
					$result = $this->upsert_daily_stat( '1970-01-01', 0, $views, 0, $source_col );
					$result ? $imported++ : $skipped++;
				}
				$msg  = "Berhasil menarik data total Referrer (Selesai).";
				$done = true;
				$next_step = 3;
				break;

			default:
				$done = true;
				$next_step = $step;
		}

		if ( $imported > 0 ) {
			if ( class_exists( 'MeowPack_Core' ) && isset( MeowPack_Core::get_instance()->stats ) ) {
				MeowPack_Core::get_instance()->stats->flush_stat_caches();
			}
		}

		return array(
			'success'  => true,
			'imported' => $imported,
			'skipped'  => $skipped,
			'offset'   => $next_step,
			'done'     => $done,
			'message'  => $msg,
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
			'title'    => -1,
			'url'      => -1,
		);

		foreach ( $header as $idx => $col ) {
			$col = strtolower( trim( $col ) );
			// Jetpack common headers: Date, Views, Visitors, Post ID, Article ID
			if ( strpos( $col, 'date' ) !== false )      $map['date']     = $idx;
			if ( strpos( $col, 'view' ) !== false )      $map['views']    = $idx;
			if ( strpos( $col, 'visit' ) !== false )     $map['visitors'] = $idx;
			if ( strpos( $col, 'post id' ) !== false )   $map['post_id']  = $idx;
			if ( strpos( $col, 'article id' ) !== false )$map['post_id']  = $idx;
			if ( strpos( $col, 'url' ) !== false )       $map['url']      = $idx;
		}

		$is_headless = false;

		// Fallback for simple date,views format if no headers match.
		if ( $map['date'] === -1 && $map['views'] === -1 && count( $header ) >= 2 ) {
			// Check if it's the "Top Posts" headless CSV: Title, Views, URL
			if ( is_numeric( trim( $header[1] ) ) && filter_var( trim( $header[2] ?? '' ), FILTER_VALIDATE_URL ) ) {
				$map['title'] = 0;
				$map['views'] = 1;
				$map['url']   = 2;
				$is_headless  = true;
			} elseif ( is_numeric( trim( $header[1] ) ) && empty( $header[2] ) ) {
				// Referrer/Search Engine CSV: Source Name, Views
				$map['title']    = 0;
				$map['views']    = 1;
				$map['referrer'] = 0;
				$is_headless     = true;
			} else {
				$map['date']  = 0;
				$map['views'] = 1;
				// If first item is a valid date, it's a headless Date,Views CSV
				if ( strtotime( trim( $header[0] ) ) ) {
					$is_headless = true;
				}
			}
		}

		if ( $map['date'] === -1 && $map['url'] === -1 && ! isset( $map['referrer'] ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return array( 'success' => false, 'error' => 'Format file tidak dikenali.' );
		}

		// Rewind if headless so we don't miss the first row
		if ( $is_headless ) {
			rewind( $handle );
		}

		// 2. Skip to offset.
		for ( $i = 0; $i < $offset; $i++ ) {
			fgetcsv( $handle );
		}

		// 3. Process batch.
		$read = 0;
		while ( $read < $limit && ( $row = fgetcsv( $handle ) ) !== false ) {
			$read++;

			$views    = absint( $row[ $map['views'] ] ?? 0 );
			$visitors = $map['visitors'] !== -1 ? absint( $row[ $map['visitors'] ] ?? 0 ) : 0;
			$post_id  = $map['post_id'] !== -1 ? absint( $row[ $map['post_id'] ] ?? 0 ) : 0;
			$date     = '';

			if ( $views <= 0 ) {
				$skipped++;
				continue;
			}

			// Referrer Format (No Date, No URL, Just String and Views)
			if ( $map['date'] === -1 && isset( $map['referrer'] ) ) {
				$title = strtolower( trim( $row[ $map['title'] ] ?? '' ) );
				if ( ! $title ) {
					$skipped++;
					continue;
				}

				$source_col = 'source_referral';
				if ( strpos( $title, 'mesin pencari' ) !== false || strpos( $title, 'google' ) !== false || strpos( $title, 'bing' ) !== false || strpos( $title, 'yahoo' ) !== false || strpos( $title, 'duckduckgo' ) !== false ) {
					$source_col = 'source_search';
				} elseif ( strpos( $title, 'facebook' ) !== false || strpos( $title, 'x' ) !== false || strpos( $title, 'twitter' ) !== false || strpos( $title, 'instagram' ) !== false ) {
					$source_col = 'source_social';
				}

				// Import into sitewide baseline (1970)
				$result = $this->upsert_daily_stat( '1970-01-01', 0, $views, $visitors, $source_col );
				$result ? $imported++ : $skipped++;
				continue;
			}

			// Top Posts Format (No Date, Has URL)
			if ( $map['date'] === -1 && $map['url'] !== -1 ) {
				$url = rtrim( $row[ $map['url'] ] ?? '', '/' );
				if ( ! $url ) {
					$skipped++;
					continue;
				}

				// Try to find Post ID from URL (Domain mismatch safe)
				if ( $post_id === 0 ) {
					$parsed = wp_parse_url( $url );
					$path   = $parsed['path'] ?? '/';
					$local_url = home_url( $path );

					if ( rtrim( $local_url, '/' ) === rtrim( home_url(), '/' ) ) {
						$post_id = 0; // Homepage / Sitewide
					} else {
						$post_id = url_to_postid( $local_url );
					}
					
					// Fallback to slug search if domain + path mapping failed
					if ( $post_id === 0 && $path !== '/' ) {
						$slug = trim( $path, '/' );
						$slug_parts = explode( '/', $slug );
						$last_slug = end( $slug_parts );
						if ( $last_slug ) {
							global $wpdb;
							$found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_status = 'publish' LIMIT 1", $last_slug ) );
							$post_id = absint( $found );
						}
					}
				}

				// Fallback date: Use post's publish date, or '1970-01-01' for All-Time sum
				$date = '1970-01-01';
				if ( $post_id > 0 ) {
					$post = get_post( $post_id );
					if ( $post ) {
						$date = gmdate( 'Y-m-d', strtotime( $post->post_date ) );
					}
				}
			} else {
				// Standard Jetpack Format with Date
				$date_raw = $row[ $map['date'] ] ?? '';
				if ( empty( $date_raw ) ) {
					$skipped++;
					continue;
				}
				$timestamp = strtotime( $date_raw );
				if ( ! $timestamp ) {
					$skipped++;
					continue;
				}
				$date = date( 'Y-m-d', $timestamp );
			}

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
	 * @param string $source   Traffic source column (default 'source_direct').
	 * @return bool True if inserted/updated, false if skipped.
	 */
	public function upsert_daily_stat( $date, $post_id, $views, $visitors = 0, $source = 'source_direct' ) {
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
				"SELECT id, total_views, unique_visitors, {$source} FROM {$table} WHERE stat_date = %s AND post_id = %d",
				$date, $post_id
			),
			ARRAY_A
		);

		if ( $existing ) {
			$data = array();
			$data['total_views']     = (int) $existing['total_views'] + $views;
			$data['unique_visitors'] = (int) $existing['unique_visitors'] + $visitors;
			$data[ $source ]         = (int) $existing[ $source ] + $views;

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				$data,
				array( 'id' => (int) $existing['id'] ),
				array( '%d', '%d', '%d' ),
				array( '%d' )
			);
			return true;
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'stat_date'       => $date,
				'post_id'         => $post_id,
				'total_views'     => $views,
				'unique_visitors' => $visitors,
				$source           => $views,
			),
			array( '%s', '%d', '%d', '%d', '%d' )
		);

		return true;
	}
}
