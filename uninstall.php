<?php
/**
 * Fired when plugin is uninstalled.
 *
 * @package MeowPack
 */

// If uninstall.php is called from outside WordPress, abort.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-meowpack-database.php';

// Remove all plugin options.
delete_option( 'meowpack_db_version' );
delete_option( 'meowpack_settings' );

// Drop database tables.
MeowPack_Database::drop_tables();

// Clear scheduled events.
wp_clear_scheduled_hook( 'meowpack_daily_cron' );
wp_clear_scheduled_hook( 'meowpack_delayed_share' );

// Delete all transients.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_meowpack_%' OR option_name LIKE '_transient_timeout_meowpack_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
