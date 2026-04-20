<?php
/**
 * Anti-Hotlink Protection — blocks image theft from external domains.
 *
 * @package MeowPack
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MeowPack_Anti_Hotlink
 *
 * Strategy:
 * - Registers a rewrite rule to intercept requests to /wp-content/uploads/*.
 * - On the WordPress side, checks the HTTP Referer.
 * - For Apache servers, also writes .htaccess rules via insert_with_markers().
 * - Admin page shows Nginx config snippet as a reference.
 *
 * Settings keys used:
 *   enable_anti_hotlink   : '0'|'1'
 *   hotlink_response      : 'placeholder'|'403'|'redirect'
 *   hotlink_redirect_url  : URL to redirect to
 *   hotlink_extensions    : comma-separated (jpg,jpeg,png,gif,webp)
 *   hotlink_whitelist     : comma-separated domains to always allow
 */
class MeowPack_Anti_Hotlink {

	/** @var array Extensions to protect. */
	private $extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' );

	/** @var array Domains always allowed (crawlers, CDNs, social previews). */
	private $auto_whitelist = array(
		'googleusercontent.com',
		'googleapis.com',
		'fbcdn.net',
		'facebook.com',
		'whatsapp.net',
		'twimg.com',
		'pbs.twimg.com',
		'bing.com',
		'duckduckgo.com',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( '1' !== MeowPack_Database::get_setting( 'enable_anti_hotlink', '0' ) ) {
			return;
		}

		$ext_setting     = MeowPack_Database::get_setting( 'hotlink_extensions', 'jpg,jpeg,png,gif,webp' );
		$this->extensions = array_map( 'trim', explode( ',', $ext_setting ) );

		add_action( 'init', array( $this, 'check_hotlink' ), 5 );
	}

	/**
	 * Inspect the current request — if it's an image and the referer is
	 * an external domain, block or serve placeholder.
	 */
	public function check_hotlink() {
		// Only act on GET requests for static-looking URLs.
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$ext         = strtolower( pathinfo( $request_uri, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, $this->extensions, true ) ) {
			return;
		}

		// Only protect uploads directory.
		if ( false === strpos( $request_uri, '/wp-content/uploads/' ) ) {
			return;
		}

		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';

		// Empty referer = direct access / bookmark — allow.
		if ( empty( $referer ) ) {
			return;
		}

		$referer_host = strtolower( wp_parse_url( $referer, PHP_URL_HOST ) ?? '' );
		$site_host    = strtolower( wp_parse_url( home_url(), PHP_URL_HOST ) ?? '' );

		// Same site — allow.
		if ( $referer_host === $site_host || str_ends_with( $referer_host, '.' . $site_host ) ) {
			return;
		}

		// Auto-whitelist (social crawlers, Google, etc.).
		foreach ( $this->auto_whitelist as $wl_domain ) {
			if ( false !== strpos( $referer_host, $wl_domain ) ) {
				return;
			}
		}

		// User-defined whitelist.
		$user_wl = MeowPack_Database::get_setting( 'hotlink_whitelist', '' );
		if ( ! empty( $user_wl ) ) {
			$wl_domains = array_map( 'trim', explode( ',', strtolower( $user_wl ) ) );
			foreach ( $wl_domains as $wl ) {
				if ( ! empty( $wl ) && false !== strpos( $referer_host, $wl ) ) {
					return;
				}
			}
		}

		// -----------------------------------------------------------------------
		// Hotlink detected — log and respond.
		// -----------------------------------------------------------------------
		$this->log_hotlink( $request_uri, $referer_host );

		$response_type = MeowPack_Database::get_setting( 'hotlink_response', 'placeholder' );

		if ( '403' === $response_type ) {
			status_header( 403 );
			header( 'Content-Type: text/plain' );
			exit( 'Hotlinking not permitted.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		if ( 'redirect' === $response_type ) {
			$redirect_url = MeowPack_Database::get_setting( 'hotlink_redirect_url', home_url() );
			wp_safe_redirect( $redirect_url, 302 );
			exit;
		}

		// Default: serve a "No Hotlinking" placeholder image.
		$this->serve_placeholder();
	}

	/**
	 * Output an inline 1×60 px SVG "No Hotlinking" placeholder and exit.
	 */
	private function serve_placeholder() {
		$custom = MeowPack_Database::get_setting( 'hotlink_placeholder_url', '' );
		if ( $custom && filter_var( $custom, FILTER_VALIDATE_URL ) ) {
			// Serve custom placeholder image.
			header( 'Content-Type: image/svg+xml' );
			$img_data = wp_remote_get( esc_url_raw( $custom ), array( 'timeout' => 3 ) );
			if ( ! is_wp_error( $img_data ) && 200 === wp_remote_retrieve_response_code( $img_data ) ) {
				echo wp_remote_retrieve_body( $img_data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				exit;
			}
		}

		// Built-in SVG placeholder — branded with MeowPack colours.
		header( 'Content-Type: image/svg+xml' );
		header( 'Cache-Control: public, max-age=86400' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="100" viewBox="0 0 400 100">
			<rect width="400" height="100" fill="#1e1e2e"/>
			<text x="200" y="45" font-family="sans-serif" font-size="14" fill="#cdd6f4" text-anchor="middle">⛔ Hotlinking tidak diizinkan</text>
			<text x="200" y="70" font-family="sans-serif" font-size="11" fill="#6c7086" text-anchor="middle">No hotlinking permitted — ' . esc_url( home_url() ) . '</text>
		</svg>';
		exit;
	}

	/**
	 * Record a blocked hotlink attempt into meow_hotlink_logs.
	 *
	 * @param string $blocked_url    The image URL being hotlinked.
	 * @param string $referer_domain The external domain.
	 */
	private function log_hotlink( $blocked_url, $referer_domain ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_hotlink_logs';
		$today = gmdate( 'Y-m-d' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (blocked_url, referrer_domain, blocked_date, block_count, last_blocked)
				 VALUES (%s, %s, %s, 1, %s)
				 ON DUPLICATE KEY UPDATE
				   block_count = block_count + 1,
				   last_blocked = %s",
				substr( $blocked_url, 0, 499 ),
				substr( $referer_domain, 0, 199 ),
				$today,
				current_time( 'mysql' ),
				current_time( 'mysql' )
			)
		);
	}

	/**
	 * Get hotlink stats for admin dashboard.
	 *
	 * @param int $limit Max rows.
	 * @return array
	 */
	public static function get_hotlink_stats( $limit = 20 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_hotlink_logs';

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT referrer_domain, SUM(block_count) AS total_blocks, MAX(last_blocked) AS last_blocked
				 FROM {$table}
				 GROUP BY referrer_domain
				 ORDER BY total_blocks DESC
				 LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return $rows ?: array();
	}

	/**
	 * Write Apache .htaccess hotlink protection rules.
	 * Called from admin when the feature is toggled on.
	 *
	 * @param bool   $enable    True to add rules, false to remove.
	 * @param string $site_host Site hostname (e.g. example.com).
	 * @param array  $extensions File extensions to protect.
	 * @return bool
	 */
	public static function write_htaccess_rules( $enable, $site_host, $extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ) ) {
		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		$htaccess = get_home_path() . '.htaccess';

		if ( ! $enable ) {
			insert_with_markers( $htaccess, 'MeowPack-Hotlink', array() );
			return true;
		}

		$ext_pattern = implode( '|', array_map( 'preg_quote', $extensions ) );
		$rules = array(
			'RewriteEngine On',
			'RewriteCond %{HTTP_REFERER} !^$',
			'RewriteCond %{HTTP_REFERER} !^https?://(www\.)?' . preg_quote( $site_host, '/' ) . '[/]?.*$ [NC]',
			'RewriteRule \.(' . $ext_pattern . ')$ - [F,L]',
		);

		return (bool) insert_with_markers( $htaccess, 'MeowPack-Hotlink', $rules );
	}

	/**
	 * Return the Nginx config snippet for hotlink protection.
	 *
	 * @param string $site_host Site hostname.
	 * @param array  $extensions File extensions.
	 * @return string
	 */
	public static function get_nginx_snippet( $site_host, $extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ) ) {
		$ext_str = implode( '|', $extensions );
		return "location ~* \\.({$ext_str})$ {\n" .
			"    valid_referers none blocked {$site_host} www.{$site_host};\n" .
			"    if (\$invalid_referer) {\n" .
			"        return 403;\n" .
			"    }\n" .
			'}';
	}
}
