<?php
/**
 * Plugin deactivation.
 *
 * @package GCRM\Core
 */

namespace GCRM\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Deactivator
 */
class Deactivator {

	/**
	 * Deactivate plugin.
	 */
	public static function deactivate(): void {
		Cron::clear_scheduled_events();
		flush_rewrite_rules();
	}
}
