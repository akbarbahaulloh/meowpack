<?php
/**
 * Tracker class — injects JS tracker and handles REST tracking endpoint.
 *
 * @package MeowPack
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MeowPack_Tracker
 *
 * Injects the non-blocking JavaScript tracker on the frontend and
 * processes tracking data received via the REST API endpoint.
 *
 * v2.0.0: Collects device_type, browser, os, author_id, region, city, bot_name.
 */
class MeowPack_Tracker {

	/**
	 * Constructor — registers hooks.
	 */
	public function __construct() {
		if ( '1' !== MeowPack_Database::get_setting( 'enable_tracking', '1' ) ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracker' ) );
		
		// Register AJAX handlers for tracking (both logged in and not logged in).
		add_action( 'wp_ajax_nopriv_meowpack_track', array( $this, 'handle_track_ajax' ) );
		add_action( 'wp_ajax_meowpack_track', array( $this, 'handle_track_ajax' ) );
	}

	/**
	 * Enqueue the tracker script only on singular posts/pages.
	 */
	public function enqueue_tracker() {
		// Don't track admins if setting is on.
		if ( '1' === MeowPack_Database::get_setting( 'exclude_admins', '1' ) && current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		// Check post type is tracked.
		$tracked_types = explode( ',', MeowPack_Database::get_setting( 'track_post_types', 'post,page' ) );
		$tracked_types = array_map( 'trim', $tracked_types );
		if ( ! in_array( get_post_type( $post_id ), $tracked_types, true ) ) {
			return;
		}

		wp_enqueue_script(
			'meowpack-tracker',
			MEOWPACK_URL . 'public/assets/meowpack-tracker.js',
			array( 'jquery' ),
			filemtime( MEOWPACK_DIR . 'public/assets/meowpack-tracker.js' ),
			true // Load in footer.
		);

		wp_localize_script(
			'meowpack-tracker',
			'meowpack_data',
			array(
				'post_id'             => $post_id,
				'ajax_url'            => admin_url( 'admin-ajax.php' ),
				'engagement_endpoint' => rest_url( 'meowpack/v1/engagement' ),
				'click_endpoint'      => rest_url( 'meowpack/v1/click' ),
				'nonce'               => wp_create_nonce( 'meowpack_track' ),
				'site_host'           => wp_parse_url( home_url(), PHP_URL_HOST ),
				'enable_clicks'       => MeowPack_Database::get_setting( 'enable_click_tracker', '1' ),
				'enable_engagement'   => MeowPack_Database::get_setting( 'enable_reading_time', '1' ),
			)
		);
	}

	/**
	 * Handle incoming tracking AJAX request.
	 * This is the new approach: direct UPDATE to wp_meow_post_views table.
	 *
	 * @return void
	 */
	public function handle_track_ajax() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! get_post( $post_id ) ) {
			wp_die( 'invalid_post' );
		}

		// Get tracking data.
		$referrer     = isset( $_POST['referrer'] ) ? esc_url_raw( wp_unslash( $_POST['referrer'] ) ) : '';
		$utm_source   = isset( $_POST['utm_source'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_source'] ) ) : '';
		$utm_medium   = isset( $_POST['utm_medium'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_medium'] ) ) : '';
		$utm_campaign = isset( $_POST['utm_campaign'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_campaign'] ) ) : '';

		$ua     = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$ip     = MeowPack_Bot_Filter::get_client_ip();
		$is_bot = MeowPack_Bot_Filter::is_bot( $ua, $ip ) ? 1 : 0;

		// Skip bot tracking if setting is on.
		if ( '1' === MeowPack_Database::get_setting( 'exclude_bots', '1' ) && $is_bot ) {
			wp_die( 'ok' );
		}

		// Detect AI bot name for flagged visits.
		$bot_info = MeowPack_AI_Bot_Manager::detect_from_ua( $ua );
		$bot_name = $bot_info['bot_name'];

		// Parse traffic source.
		$source = MeowPack_Bot_Filter::parse_source( $referrer, $utm_source, $utm_medium );

		// Hash IP for privacy.
		$ip_hash = MeowPack_Bot_Filter::hash_ip( $ip );

		// Detect device / browser / OS.
		$device_info = MeowPack_Device_Detector::parse( $ua );

		// Geo-location (country + region + city).
		$geo = $this->get_geo_data( $ip );

		// Author of the post.
		$post      = get_post( $post_id );
		$author_id = $post ? (int) $post->post_author : 0;

		// Step 1: Direct UPDATE to wp_meow_post_views (simple counter).
		$this->update_post_views( $post_id );

		// Step 2: Record detailed visit to wp_meow_visits (for analytics).
		$this->record_visit( array(
			'post_id'      => $post_id,
			'author_id'    => $author_id,
			'visit_date'   => current_time( 'Y-m-d' ),
			'visit_hour'   => (int) current_time( 'G' ),
			'ip_hash'      => $ip_hash,
			'source_type'  => $source['source_type'],
			'source_name'  => $source['source_name'],
			'utm_source'   => $utm_source,
			'utm_medium'   => $utm_medium,
			'utm_campaign' => $utm_campaign,
			'country_code' => $geo['country_code'],
			'region'       => $geo['region'],
			'city'         => $geo['city'],
			'device_type'  => $device_info['device'],
			'browser'      => $device_info['browser'],
			'os'           => $device_info['os'],
			'is_bot'       => $is_bot,
			'bot_name'     => $bot_name,
		) );

		wp_die( 'ok' );
	}

	/**
	 * Handle incoming tracking REST request (kept for backward compatibility).
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response
	 */
	public function handle_track_request( WP_REST_Request $request ) {
		// Validate nonce.
		// Bypassed for LiteSpeed Cache compatibility
		// $nonce = $request->get_param( 'nonce' );
		// if ( ! wp_verify_nonce( $nonce, 'meowpack_track' ) ) {
		// 	return new WP_REST_Response( array( 'ok' => false ), 200 );
		// }

		$post_id      = absint( $request->get_param( 'post_id' ) );
		$referrer     = esc_url_raw( $request->get_param( 'referrer' ) ?? '' );
		$utm_source   = sanitize_text_field( $request->get_param( 'utm_source' ) ?? '' );
		$utm_medium   = sanitize_text_field( $request->get_param( 'utm_medium' ) ?? '' );
		$utm_campaign = sanitize_text_field( $request->get_param( 'utm_campaign' ) ?? '' );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'reason' => 'invalid_post' ), 200 );
		}

		$ua     = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$ip     = MeowPack_Bot_Filter::get_client_ip();
		$is_bot = MeowPack_Bot_Filter::is_bot( $ua, $ip ) ? 1 : 0;

		// Detect AI bot name for flagged visits.
		$bot_info = MeowPack_AI_Bot_Manager::detect_from_ua( $ua );
		$bot_name = $bot_info['bot_name'];

		// Parse traffic source.
		$source = MeowPack_Bot_Filter::parse_source( $referrer, $utm_source, $utm_medium );

		// Hash IP for privacy.
		$ip_hash = MeowPack_Bot_Filter::hash_ip( $ip );

		// Detect device / browser / OS.
		$device_info = MeowPack_Device_Detector::parse( $ua );

		// Geo-location (country + region + city).
		$geo = $this->get_geo_data( $ip );

		// Author of the post.
		$post      = get_post( $post_id );
		$author_id = $post ? (int) $post->post_author : 0;

		// Step 1: Direct UPDATE to wp_meow_post_views (simple counter).
		$this->update_post_views( $post_id );

		// Step 2: Record detailed visit to wp_meow_visits (for analytics).
		$this->record_visit( array(
			'post_id'      => $post_id,
			'author_id'    => $author_id,
			'visit_date'   => current_time( 'Y-m-d' ),
			'visit_hour'   => (int) current_time( 'G' ),
			'ip_hash'      => $ip_hash,
			'source_type'  => $source['source_type'],
			'source_name'  => $source['source_name'],
			'utm_source'   => $utm_source,
			'utm_medium'   => $utm_medium,
			'utm_campaign' => $utm_campaign,
			'country_code' => $geo['country_code'],
			'region'       => $geo['region'],
			'city'         => $geo['city'],
			'device_type'  => $device_info['device'],
			'browser'      => $device_info['browser'],
			'os'           => $device_info['os'],
			'is_bot'       => $is_bot,
			'bot_name'     => $bot_name,
		) );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Direct UPDATE to wp_meow_post_views table (simple counter).
	 * This is the key to real-time display without timing issues.
	 *
	 * @param int $post_id Post ID.
	 */
	private function update_post_views( $post_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_post_views';
		$today = current_time( 'Y-m-d' );

		// Try to update existing row for today.
		$updated = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE {$table} SET total_views = total_views + 1, daily_views = daily_views + 1 WHERE post_id = %d AND view_date = %s",
				$post_id,
				$today
			)
		);

		// If no row was updated, insert a new one.
		if ( 0 === $updated ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'post_id'     => $post_id,
					'total_views' => 1,
					'daily_views' => 1,
					'view_date'   => $today,
				),
				array( '%d', '%d', '%d', '%s' )
			);
		}
	}

	/**
	 * Insert a visit record into the database (for detailed analytics).
	 *
	 * @param array $data Visit data.
	 */
	private function record_visit( array $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_visits';

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'post_id'      => $data['post_id'],
				'author_id'    => $data['author_id'],
				'visit_date'   => $data['visit_date'],
				'visit_hour'   => $data['visit_hour'],
				'ip_hash'      => $data['ip_hash'],
				'source_type'  => $data['source_type'],
				'source_name'  => $data['source_name'],
				'utm_source'   => $data['utm_source'],
				'utm_medium'   => $data['utm_medium'],
				'utm_campaign' => $data['utm_campaign'],
				'country_code' => $data['country_code'],
				'region'       => $data['region'],
				'city'         => $data['city'],
				'device_type'  => $data['device_type'],
				'browser'      => $data['browser'],
				'os'           => $data['os'],
				'is_bot'       => $data['is_bot'],
				'bot_name'     => $data['bot_name'],
				'created_at'   => current_time( 'mysql' ),
			),
			array(
				'%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s',
				'%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s',
			)
		);
	}

	/**
	 * Geo-locate an IP address.
	 * 1. Checks for local MaxMind GeoLite2-City.mmdb in includes/data/geoip/
	 * 2. Falls back to ip-api.com (free API).
	 *
	 * @param string $ip IP address.
	 * @return array{ country_code: string, region: string, city: string }
	 */
	private function get_geo_data( $ip ) {
		$empty = array( 'country_code' => '', 'region' => '', 'city' => '' );

		// Skip private/local IPs.
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return $empty;
		}

		$cache_key = 'meowpack_geo_' . md5( $ip );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$result = $empty;

		// --- 1. Attempt Local MaxMind MMDB (if file exists) ---
		$mmdb_path = MEOWPACK_PATH . 'includes/data/geoip/GeoLite2-City.mmdb';
		if ( file_exists( $mmdb_path ) && class_exists( 'MeowPack_MMDB_Reader' ) ) {
			try {
				$reader = new MeowPack_MMDB_Reader( $mmdb_path );
				$record = $reader->get( $ip );
				if ( $record ) {
					$result['country_code'] = $record['country']['iso_code'] ?? '';
					$result['region']       = $record['subdivisions'][0]['names']['en'] ?? '';
					$result['city']         = $record['city']['names']['en'] ?? '';
					
					set_transient( $cache_key, $result, MONTH_IN_SECONDS );
					return $result;
				}
			} catch ( Exception $e ) {
				// Fallback to API on error.
			}
		}

		// --- 2. Fallback to external API (ip-api.com) ---
		$response = wp_remote_get(
			'http://ip-api.com/json/' . rawurlencode( $ip ) . '?fields=countryCode,regionName,city',
			array( 'timeout' => 3, 'sslverify' => false )
		);

		if ( ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! empty( $body['countryCode'] ) ) {
				$result['country_code'] = sanitize_text_field( $body['countryCode'] );
			}
			if ( ! empty( $body['regionName'] ) ) {
				$result['region'] = sanitize_text_field( $body['regionName'] );
			}
			if ( ! empty( $body['city'] ) ) {
				$result['city'] = sanitize_text_field( $body['city'] );
			}
		}

		set_transient( $cache_key, $result, DAY_IN_SECONDS );
		return $result;
	}
}
