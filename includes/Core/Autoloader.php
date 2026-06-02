<?php
/**
 * PSR-4 style SPL autoloader.
 *
 * @package GCRM\Core
 */

namespace GCRM\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Autoloader
 */
class Autoloader {

	/**
	 * Base path for includes.
	 *
	 * @var string
	 */
	private static string $base_path = '';

	/**
	 * Register autoloader.
	 *
	 * @param string $base_path Plugin includes directory.
	 */
	public static function register( string $base_path ): void {
		self::$base_path = trailingslashit( $base_path );
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload GCRM classes.
	 *
	 * @param string $class Fully qualified class name.
	 */
	public static function autoload( string $class ): void {
		if ( strpos( $class, 'GCRM\\' ) !== 0 ) {
			return;
		}

		$relative = substr( $class, 5 );
		$file     = self::$base_path . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
