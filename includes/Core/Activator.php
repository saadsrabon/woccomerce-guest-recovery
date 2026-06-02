<?php
/**
 * Plugin activation.
 *
 * @package GCRM\Core
 */

namespace GCRM\Core;

use GCRM\DB\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Class Activator
 */
class Activator {

	/**
	 * Activate plugin.
	 */
	public static function activate(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( GCRM_PLUGIN_BASENAME );
			wp_die(
				esc_html__( 'Guest Customer Recovery & Marketing Suite requires WooCommerce.', 'gcrm' ),
				esc_html__( 'Plugin Activation Error', 'gcrm' ),
				array( 'back_link' => true )
			);
		}

		Schema::create_tables();
		self::set_default_options();
		Cron::schedule_events();

		update_option( 'gcrm_db_version', GCRM_DB_VERSION );
		flush_rewrite_rules();
	}

	/**
	 * Default plugin options.
	 */
	private static function set_default_options(): void {
		$defaults = array(
			'gcrm_cart_timeout_minutes'     => 30,
			'gcrm_email_from_name'            => get_bloginfo( 'name' ),
			'gcrm_email_from_address'         => get_option( 'admin_email' ),
			'gcrm_whatsapp_provider'          => 'cloud',
			'gcrm_recovery_email_enabled'     => 'yes',
			'gcrm_recovery_whatsapp_enabled'  => 'no',
			'gcrm_recovery_sequence'          => wp_json_encode(
				array(
					array( 'delay_hours' => 1, 'type' => 'email', 'enabled' => true ),
					array( 'delay_hours' => 24, 'type' => 'email', 'enabled' => true ),
					array( 'delay_hours' => 72, 'type' => 'email', 'enabled' => true ),
				)
			),
			'gcrm_whatsapp_rate_limit'        => 30,
			'gcrm_batch_size'                 => 25,
			'gcrm_gdpr_consent_required'      => 'no',
			'gcrm_export_delimiter'           => ',',
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}
}
