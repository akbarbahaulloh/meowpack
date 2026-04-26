<?php
/**
 * Plugin Name: MeowPack
 * Plugin URI:  https://github.com/akbarbahaulloh/meowpack
 * Description: The ultimate security and optimization powerhouse for WordPress. Real-time local stats, AI-powered protection, malware scanning, and instant social engine — privacy-first, zero cloud dependencies, 100% control.
 * Version:     2.2.0
 * Author:      Akbar Bahaulloh
 * Author URI:  https://akbarbahaulloh.id
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: meowpack
 * Domain Path: /languages
 * Requires at least: 5.9
 * Requires PHP:      7.4
 *
 * @package MeowPack
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'MEOWPACK_VERSION', '2.2.0' );
define( 'MEOWPACK_FILE', __FILE__ );
define( 'MEOWPACK_DIR', plugin_dir_path( __FILE__ ) );
define( 'MEOWPACK_URL', plugin_dir_url( __FILE__ ) );
define( 'MEOWPACK_BASENAME', plugin_basename( __FILE__ ) );
define( 'MEOWPACK_MIN_WP', '5.9' );
define( 'MEOWPACK_MIN_PHP', '7.4' );

/**
 * Check requirements before loading.
 *
 * @return bool
 */
function meowpack_requirements_met() {
	global $wp_version;

	if ( version_compare( PHP_VERSION, MEOWPACK_MIN_PHP, '<' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>' .
				sprintf(
					/* translators: %s: required PHP version */
					esc_html__( 'MeowPack membutuhkan PHP %s atau lebih tinggi.', 'meowpack' ),
					MEOWPACK_MIN_PHP
				) .
				'</p></div>';
		} );
		return false;
	}

	if ( version_compare( $wp_version, MEOWPACK_MIN_WP, '<' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>' .
				sprintf(
					/* translators: %s: required WP version */
					esc_html__( 'MeowPack membutuhkan WordPress %s atau lebih tinggi.', 'meowpack' ),
					MEOWPACK_MIN_WP
				) .
				'</p></div>';
		} );
		return false;
	}

	return true;
}

/**
 * Load plugin text domain.
 */
function meowpack_load_textdomain() {
	load_plugin_textdomain( 'meowpack', false, dirname( MEOWPACK_BASENAME ) . '/languages' );
}
add_action( 'plugins_loaded', 'meowpack_load_textdomain' );

/**
 * Load all required files.
 */
function meowpack_load_files() {
	$includes = array(
		// Core infrastructure (load first).
		'includes/class-meowpack-database.php',
		'includes/class-meowpack-mmdb-reader.php',
		'includes/class-meowpack-bot-filter.php',
		'includes/class-meowpack-device-detector.php',   // v2.0.0
		'includes/class-meowpack-ai-bot-manager.php',    // v2.0.0
		// Main systems.
		'includes/class-meowpack-core.php',
		'includes/class-meowpack-tracker.php',
		'includes/class-meowpack-stats.php',
		'includes/class-meowpack-autoshare.php',
		'includes/class-meowpack-share-buttons.php',
		'includes/class-meowpack-view-counter.php',
		'includes/class-meowpack-widget.php',
		// v2.0.0 new modules.
		'includes/class-meowpack-click-tracker.php',
		'includes/class-meowpack-reading-time.php',
		'includes/class-meowpack-shortcodes.php',
		'includes/class-meowpack-anti-hotlink.php',
		'includes/class-meowpack-captcha.php',
		'includes/class-meowpack-content-moderation.php',
		'includes/class-meowpack-frontend-enhancer.php',
		'includes/class-meowpack-github-updater.php',
		'includes/class-meowpack-malware-scanner.php',

		// Admin (last — depends on all the above).
		'admin/class-meowpack-admin.php',
	);

	foreach ( $includes as $file ) {
		$path = MEOWPACK_DIR . $file;
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
}

/**
 * Initialize the plugin.
 */
function meowpack_init() {
	if ( ! meowpack_requirements_met() ) {
		return;
	}

	meowpack_load_files();

	// Boot core.
	MeowPack_Core::get_instance();

	// Register widget.
	add_action( 'widgets_init', function () {
		register_widget( 'MeowPack_Stats_Widget' );
		register_widget( 'MeowPack_Popular_Widget' );
		register_widget( 'MeowPack_Random_Widget' );
		register_widget( 'MeowPack_Recent_Widget' );
	} );
}
add_action( 'plugins_loaded', 'meowpack_init' );

/**
 * Plugin activation hook.
 */
function meowpack_activate() {
	meowpack_load_files();
	MeowPack_Database::install();

	// Schedule daily cron.
	if ( ! wp_next_scheduled( 'meowpack_daily_cron' ) ) {
		wp_schedule_event( strtotime( 'tomorrow 00:05:00' ), 'daily', 'meowpack_daily_cron' );
	}

	flush_rewrite_rules();
}
register_activation_hook( MEOWPACK_FILE, 'meowpack_activate' );

/**
 * Plugin deactivation hook.
 */
function meowpack_deactivate() {
	wp_clear_scheduled_hook( 'meowpack_daily_cron' );
	flush_rewrite_rules();
}
register_deactivation_hook( MEOWPACK_FILE, 'meowpack_deactivate' );
