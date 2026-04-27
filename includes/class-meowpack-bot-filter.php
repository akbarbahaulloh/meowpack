<?php
/**
 * Bot Filter class — detects bots and marks traffic accordingly.
 *
 * @package MeowPack
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MeowPack_Bot_Filter
 *
 * Provides bot detection logic. Does NOT block bots —
 * only flags them (is_bot = 1) so they are excluded from human stats.
 */
class MeowPack_Bot_Filter {

	/**
	 * Known bot user-agent substrings (~50 entries).
	 *
	 * @var string[]
	 */
	private static $bot_strings = array(
		// Search engines.
		'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider',
		'yandexbot', 'sogou', 'exabot', 'facebot', 'ia_archiver',
		'mojeekbot', 'seznambot', 'applebot', 'semrushbot', 'ahrefsbot',
		'mj12bot', 'dotbot', 'blexbot', 'rogerbot', 'sistrix',
		// Social / preview crawlers.
		'facebookexternalhit', 'facebookcatalog', 'twitterbot', 'linkedinbot',
		'pinterestbot', 'slackbot', 'telegrambot', 'whatsapp', 'discordbot',
		// SEO / auditing tools.
		'screaming frog', 'deepcrawl', 'lumar', 'seokicks',
		'majestic', 'cognitiveseo', 'datanyze', 'netsystemsresearch',
		// Archive / scraping bots.
		'httrack', 'wget', 'curl', 'python-requests', 'python-urllib',
		'guzzlehttp', 'scrapy', 'mechanize', 'libwww-perl',
		// Uptime / monitoring.
		'pingdom', 'uptimerobot', 'statuscake', 'site24x7', 'freshping',
		// Generic.
		'bot', 'crawler', 'spider', 'checker', 'monitoring', 'headlesschrome',
		'phantomjs', 'selenium', 'webdriver',
	);

	/**
	 * Known search engine domains for referrer check.
	 *
	 * @var string[]
	 */
	public static $search_engines = array(
		'google.com', 'google.co.id', 'bing.com', 'yahoo.com',
		'duckduckgo.com', 'yandex.com', 'yandex.ru', 'baidu.com',
		'ecosia.org', 'brave.com', 'ask.com', 'aol.com', 'dogpile.com',
		'searchxl.com', 'naver.com', 'sogou.com', 'seznam.cz',
	);

	/**
	 * Known social media domains.
	 *
	 * @var string[]
	 */
	public static $social_domains = array(
		'facebook.com', 'fb.com', 'fb.me', 'instagram.com', 'm.facebook.com',
		'twitter.com', 'x.com', 't.co',
		'linkedin.com', 'lnkd.in',
		'pinterest.com', 'pin.it',
		'youtube.com', 'youtu.be',
		'tiktok.com', 'vm.tiktok.com',
		'reddit.com', 'redd.it',
		'telegram.org', 't.me',
		'whatsapp.com', 'wa.me',
		'line.me', 'line.naver.jp',
		'threads.net',
		'bsky.app', 'bsky.social',
	);

	/**
	 * Known email client referrers.
	 *
	 * @var string[]
	 */
	public static $email_domains = array(
		'mail.google.com', 'outlook.live.com', 'outlook.office.com',
		'mail.yahoo.com', 'webmail', 'roundcube', 'squirrelmail',
	);

	/**
	 * Determine if the current request looks like a bot.
	 *
	 * @param string|null $user_agent User-agent string (defaults to server UA).
	 * @param string|null $ip         Remote IP (defaults to server IP).
	 * @return bool True if bot detected.
	 */
	public static function is_bot( $user_agent = null, $ip = null ) {
		$ua = $user_agent ?? ( isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' );
		
		// Empty or very short UA.
		if ( strlen( $ua ) < 10 ) {
			return true;
		}

		// UA matches known bot strings.
		$ua_lower = strtolower( $ua );
		foreach ( self::$bot_strings as $bot ) {
			if ( false !== strpos( $ua_lower, $bot ) ) {
				return true;
			}
		}

		// Removed Accept-Language and IP rate limiting checks 
		// because they can aggressively block real traffic on load balancers or certain fetch configurations.

		return false;
	}

	/**
	 * Check if an IP has exceeded the rate limit (30 req/min).
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	private static function is_rate_limited( $ip ) {
		$key   = 'meowpack_ratelimit_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= 30 ) {
			return true;
		}

		if ( 0 === $count ) {
			set_transient( $key, 1, 60 );
		} else {
			set_transient( $key, $count + 1, 60 );
		}

		return false;
	}

	/**
	 * Get the real client IP, handling proxies.
	 *
	 * @return string
	 */
	public static function get_client_ip() {
		$headers = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare.
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
				$ip  = trim( $ips[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Hash an IP address for privacy (SHA-256 + WP salt, non-reversible).
	 *
	 * @param string $ip Raw IP address.
	 * @return string
	 */
	public static function hash_ip( $ip ) {
		return hash( 'sha256', $ip . wp_salt( 'auth' ) );
	}

	/**
	 * Categorize the source type from a referrer URL and UTM params.
	 *
	 * @param string $referrer    Referrer URL.
	 * @param string $utm_source  UTM source parameter.
	 * @param string $utm_medium  UTM medium parameter.
	 * @return array{ source_type: string, source_name: string }
	 */
	public static function parse_source( $referrer, $utm_source = '', $utm_medium = '' ) {
		// Explicit UTM overrides everything.
		if ( ! empty( $utm_source ) ) {
			$type = 'direct';
			$med  = strtolower( $utm_medium );
			if ( in_array( $med, array( 'cpc', 'ppc', 'paid', 'display' ), true ) ) {
				$type = 'search';
			} elseif ( in_array( $med, array( 'social', 'social-network', 'social-media', 'sm', 'social network' ), true ) ) {
				$type = 'social';
			} elseif ( in_array( $med, array( 'email', 'e-mail', 'newsletter' ), true ) ) {
				$type = 'email';
			} elseif ( ! empty( $med ) ) {
				$type = 'referral';
			}
			return array(
				'source_type' => $type,
				'source_name' => sanitize_text_field( $utm_source ),
			);
		}

		if ( empty( $referrer ) ) {
			return array( 'source_type' => 'direct', 'source_name' => '' );
		}

		$host = strtolower( wp_parse_url( $referrer, PHP_URL_HOST ) ?? '' );
		$host = ltrim( $host, 'www.' );

		// Check search engines.
		foreach ( self::$search_engines as $engine ) {
			if ( false !== strpos( $host, $engine ) ) {
				$name = str_replace( array( '.com', '.co.id', '.co.uk', '.com.au' ), '', $engine );
				return array( 'source_type' => 'search', 'source_name' => $name );
			}
		}

		// Check social media.
		foreach ( self::$social_domains as $social ) {
			if ( false !== strpos( $host, $social ) ) {
				$name = explode( '.', $social )[0];
				return array( 'source_type' => 'social', 'source_name' => $name );
			}
		}

		// Check email clients.
		foreach ( self::$email_domains as $email ) {
			if ( false !== strpos( $host, $email ) ) {
				return array( 'source_type' => 'email', 'source_name' => $email );
			}
		}

		// Check if referrer is from the same site.
		$site_host = strtolower( wp_parse_url( home_url(), PHP_URL_HOST ) ?? '' );
		if ( $host === $site_host || false !== strpos( $host, $site_host ) ) {
			return array( 'source_type' => 'direct', 'source_name' => '' );
		}

		// Referral from external site.
		return array( 'source_type' => 'referral', 'source_name' => $host );
	}
}
