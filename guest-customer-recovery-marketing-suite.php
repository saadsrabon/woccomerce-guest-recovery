<?php
/**
 * Plugin Name:       Guest Customer Recovery & Marketing Suite
 * Plugin URI:        https://github.com/saadsrabon/woccomerce-guest-recovery
 * Description:       WooCommerce guest customer recovery, abandoned cart tracking, bulk email/WhatsApp marketing, segmentation, and analytics.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Saad Srabon
 * Author URI:        https://saadsrabon.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gcrm
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   9.0
 *
 * @package GCRM
 */

defined( 'ABSPATH' ) || exit;

define( 'GCRM_VERSION', '1.0.0' );
define( 'GCRM_PLUGIN_FILE', __FILE__ );
define( 'GCRM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GCRM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GCRM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'GCRM_DB_VERSION', '1.0.0' );

require_once GCRM_PLUGIN_DIR . 'includes/Core/PhpCompat.php';

if ( ! GCRM\Core\PhpCompat::meets_requirements() ) {
	GCRM\Core\PhpCompat::register_admin_notice();
	return;
}

require_once GCRM_PLUGIN_DIR . 'includes/Core/Autoloader.php';

GCRM\Core\Autoloader::register( GCRM_PLUGIN_DIR . 'includes/' );

// Register custom cron schedules early (needed during activation).
add_filter( 'cron_schedules', array( 'GCRM\\Core\\Cron', 'add_schedules' ) );

/**
 * HPOS compatibility declaration.
 */
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', GCRM_PLUGIN_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', GCRM_PLUGIN_FILE, true );
		}
	}
);

/**
 * Activation / deactivation hooks.
 */
register_activation_hook( __FILE__, array( 'GCRM\\Core\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GCRM\\Core\\Deactivator', 'deactivate' ) );

/**
 * Bootstrap plugin.
 *
 * @return GCRM\Core\Plugin
 */
function gcrm(): GCRM\Core\Plugin {
	return GCRM\Core\Plugin::instance();
}

add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					echo '<div class="notice notice-error"><p>';
					esc_html_e( 'Guest Customer Recovery & Marketing Suite requires WooCommerce to be installed and active.', 'gcrm' );
					echo '</p></div>';
				}
			);
			return;
		}

		load_plugin_textdomain( 'gcrm', false, dirname( GCRM_PLUGIN_BASENAME ) . '/languages' );
		gcrm()->run();
	},
	20
);
