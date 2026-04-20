<?php
/**
 * Device Detector — parse User-Agent into device type, browser, and OS.
 *
 * Lightweight, zero-dependency UA parser.  No external API calls.
 *
 * @package MeowPack
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MeowPack_Device_Detector
 *
 * Provides static helpers to classify User-Agent strings.
 */
class MeowPack_Device_Detector {

	// -----------------------------------------------------------------------
	// Bot UA patterns — used to guard before device detection.
	// -----------------------------------------------------------------------

	/**
	 * Parse a User-Agent string and return device, browser, and OS.
	 *
	 * @param string $ua Raw User-Agent string.
	 * @return array{ device: string, browser: string, os: string }
	 */
	public static function parse( $ua ) {
		if ( empty( $ua ) ) {
			return array( 'device' => 'unknown', 'browser' => 'unknown', 'os' => 'unknown' );
		}

		return array(
			'device'  => self::get_device_type( $ua ),
			'browser' => self::get_browser( $ua ),
			'os'      => self::get_os( $ua ),
		);
	}

	// -----------------------------------------------------------------------
	// Device Type
	// -----------------------------------------------------------------------

	/**
	 * Classify UA as mobile, tablet, or desktop.
	 *
	 * @param string $ua User-Agent string.
	 * @return string mobile|tablet|desktop
	 */
	public static function get_device_type( $ua ) {
		$ua_lower = strtolower( $ua );

		// Tablets first (iPad matches 'mobile' on some iOS versions too).
		$tablet_patterns = array(
			'ipad', 'tablet', 'kindle', 'silk', 'playbook', 'nexus 7', 'nexus 10',
			'gt-p', 'samsung.*tab', 'hd-t', 'kftt', 'kfot', 'kfjwi', 'kfjwa',
			'sm-t', 'surface', 'gt-n51', 'gt-n81',
		);
		foreach ( $tablet_patterns as $pattern ) {
			if ( preg_match( '/' . $pattern . '/i', $ua ) ) {
				return 'tablet';
			}
		}

		// Mobile.
		$mobile_patterns = array(
			'mobile', 'android', 'iphone', 'ipod', 'blackberry', 'opera mini',
			'opera mobi', 'windows phone', 'windows ce', 'palm', 'symbian',
			'nokia', 'mobi', 'phone', 'j2me', 'bolt', 'ucweb', 'bada',
		);
		foreach ( $mobile_patterns as $pattern ) {
			if ( false !== strpos( $ua_lower, $pattern ) ) {
				return 'mobile';
			}
		}

		return 'desktop';
	}

	// -----------------------------------------------------------------------
	// Browser Detection
	// -----------------------------------------------------------------------

	/**
	 * Detect browser name from User-Agent.
	 *
	 * @param string $ua User-Agent string.
	 * @return string
	 */
	public static function get_browser( $ua ) {
		// Order matters: check specific browsers before generic ones.
		$browsers = array(
			'Edg/'         => 'Edge',
			'EdgA/'        => 'Edge',
			'Edge/'        => 'Edge',
			'OPR/'         => 'Opera',
			'OPX/'         => 'Opera',
			'Opera Mini'   => 'Opera Mini',
			'Opera'        => 'Opera',
			'Vivaldi'      => 'Vivaldi',
			'Brave'        => 'Brave',
			'YaBrowser'    => 'Yandex Browser',
			'SamsungBrowser' => 'Samsung Browser',
			'UCBrowser'    => 'UC Browser',
			'DuckDuckGo'   => 'DuckDuckGo',
			'Chrome'       => 'Chrome',
			'CriOS'        => 'Chrome (iOS)',
			'FxiOS'        => 'Firefox (iOS)',
			'Firefox'      => 'Firefox',
			'Safari'       => 'Safari',
			'MSIE'         => 'Internet Explorer',
			'Trident/'     => 'Internet Explorer',
		);

		foreach ( $browsers as $pattern => $name ) {
			if ( false !== strpos( $ua, $pattern ) ) {
				return $name;
			}
		}

		return 'Other';
	}

	// -----------------------------------------------------------------------
	// OS Detection
	// -----------------------------------------------------------------------

	/**
	 * Detect operating system from User-Agent.
	 *
	 * @param string $ua User-Agent string.
	 * @return string
	 */
	public static function get_os( $ua ) {
		$os_list = array(
			'Windows NT 10'   => 'Windows 10/11',
			'Windows NT 6.3'  => 'Windows 8.1',
			'Windows NT 6.2'  => 'Windows 8',
			'Windows NT 6.1'  => 'Windows 7',
			'Windows NT 6.0'  => 'Windows Vista',
			'Windows NT 5'    => 'Windows XP',
			'Windows Phone'   => 'Windows Phone',
			'Windows'         => 'Windows',
			'iPhone OS'       => 'iOS',
			'iPad; CPU OS'    => 'iPadOS',
			'Mac OS X'        => 'macOS',
			'Android'         => 'Android',
			'Linux'           => 'Linux',
			'CrOS'            => 'Chrome OS',
			'Ubuntu'          => 'Ubuntu',
			'Fedora'          => 'Fedora',
			'BlackBerry'      => 'BlackBerry',
			'Symbian'         => 'Symbian',
		);

		foreach ( $os_list as $pattern => $name ) {
			if ( false !== strpos( $ua, $pattern ) ) {
				return $name;
			}
		}

		return 'Other';
	}
}
