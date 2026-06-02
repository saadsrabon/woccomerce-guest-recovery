<?php
/**
 * Debug logger (WooCommerce logger when available).
 *
 * @package GCRM\Core
 */

namespace GCRM\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Logger
 */
class Logger {

	/**
	 * Log message.
	 *
	 * @param string $message Message.
	 * @param string $level Level: debug|info|warning|error.
	 */
	public static function log( string $message, string $level = 'info' ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			$logger->log( $level, $message, array( 'source' => 'gcrm' ) );
			return;
		}
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[GCRM][' . $level . '] ' . $message );
		}
	}
}
