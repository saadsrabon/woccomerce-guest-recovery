<?php
/**
 * Uninstall handler.
 *
 * @package GCRM
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'GCRM_PLUGIN_DIR' ) ) {
	define( 'GCRM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

require_once GCRM_PLUGIN_DIR . 'includes/Core/Autoloader.php';
GCRM\Core\Autoloader::register( GCRM_PLUGIN_DIR . 'includes/' );

$remove_data = get_option( 'gcrm_remove_data_on_uninstall', 'no' ) === 'yes';

if ( ! $remove_data ) {
	return;
}

global $wpdb;

$tables = array(
	'gcrm_guest_customers',
	'gcrm_abandoned_carts',
	'gcrm_email_logs',
	'gcrm_whatsapp_logs',
	'gcrm_campaigns',
	'gcrm_segments',
	'gcrm_workflows',
	'gcrm_workflow_runs',
	'gcrm_queue',
	'gcrm_consent_logs',
	'gcrm_customer_notes',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

$options = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'gcrm_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
foreach ( $options as $option ) {
	delete_option( $option );
}

wp_clear_scheduled_hook( 'gcrm_process_queue' );
wp_clear_scheduled_hook( 'gcrm_mark_abandoned_carts' );
wp_clear_scheduled_hook( 'gcrm_process_recovery' );
wp_clear_scheduled_hook( 'gcrm_sync_guest_customers' );
wp_clear_scheduled_hook( 'gcrm_process_workflows' );
