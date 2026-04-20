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
				'post_id'  => $post_id,
				'endpoint' => rest_url( 'meowpack/v1/track' ),
				'nonce'    => wp_create_nonce( 'meowpack_track' ),
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
		// Validate nonce (optional layer — REST is already protected by origin).
		$nonce = $request->get_param( 'nonce' );
		if ( ! wp_verify_nonce( $nonce, 'meowpack_track' ) ) {
			// Soft fail — don't expose nonce errors to bots.
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

		$source = MeowPack_Bot_Filter::parse_source( $referrer, $utm_source, $utm_medium );

		$ip_hash = MeowPack_Bot_Filter::hash_ip( $ip );

		$this->record_visit( array(
			'post_id'      => $post_id,
			'visit_date'   => current_time( 'Y-m-d' ),
			'visit_hour'   => (int) current_time( 'G' ),
			'ip_hash'      => $ip_hash,
			'source_type'  => $source['source_type'],
			'source_name'  => $source['source_name'],
			'utm_source'   => $utm_source,
			'utm_medium'   => $utm_medium,
			'utm_campaign' => $utm_campaign,
			'country_code' => $this->get_country_code( $ip ),
			'is_bot'       => $is_bot,
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
				'visit_date'   => $data['visit_date'],
				'visit_hour'   => $data['visit_hour'],
				'ip_hash'      => $data['ip_hash'],
				'source_type'  => $data['source_type'],
				'source_name'  => $data['source_name'],
				'utm_source'   => $data['utm_source'],
				'utm_medium'   => $data['utm_medium'],
				'utm_campaign' => $data['utm_campaign'],
				'country_code' => $data['country_code'],
				'is_bot'       => $data['is_bot'],
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Get the 2-letter country code for an IP.
	 * Uses IP-API (free, no key required) — falls back gracefully.
	 *
	 * For production, replace with local MaxMind GeoIP2 Lite database.
	 *
	 * @param string $ip IP address.
	 * @return string 2-letter country code or empty string.
	 */
	private function get_country_code( $ip ) {
		// Skip for private/local IPs.
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return '';
		}

		$cache_key = 'meowpack_geo_' . md5( $ip );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get(
			'http://ip-api.com/json/' . rawurlencode( $ip ) . '?fields=countryCode',
			array( 'timeout' => 3, 'sslverify' => false )
		);

		$country = '';
		if ( ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! empty( $body['countryCode'] ) ) {
				$country = sanitize_text_field( $body['countryCode'] );
			}
		}

		set_transient( $cache_key, $country, DAY_IN_SECONDS );
		return $country;
	}
}
