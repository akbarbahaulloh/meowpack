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
			array(),
			MEOWPACK_VERSION,
			true // Load in footer.
		);

		wp_localize_script(
			'meowpack-tracker',
			'meowpack_data',
			array(
				'post_id'             => $post_id,
				'endpoint'            => rest_url( 'meowpack/v1/track' ),
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
	 * Handle incoming tracking REST request.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response
	 */
	public function handle_track_request( WP_REST_Request $request ) {
		// Validate nonce.
		$nonce = $request->get_param( 'nonce' );
		if ( ! wp_verify_nonce( $nonce, 'meowpack_track' ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 200 );
		}

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
	 * Insert a visit record into the database.
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
	 * Uses ip-api.com (free, region + city available).
	 * For production, swap with local MaxMind GeoLite2-City.mmdb.
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

		$response = wp_remote_get(
			'http://ip-api.com/json/' . rawurlencode( $ip ) . '?fields=countryCode,regionName,city',
			array( 'timeout' => 3, 'sslverify' => false )
		);

		$result = $empty;
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
