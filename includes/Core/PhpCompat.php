<?php
/**
 * PHP compatibility helpers.
 *
 * @package GCRM\Core
 */

namespace GCRM\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class PhpCompat
 */
class PhpCompat {

	/**
	 * Minimum required PHP version.
	 */
	public const MIN_PHP = '7.4';

	/**
	 * Check PHP version before loading plugin classes.
	 *
	 * @return bool True if version is OK.
	 */
	public static function meets_requirements(): bool {
		return version_compare( PHP_VERSION, self::MIN_PHP, '>=' );
	}

	/**
	 * Show admin notice when PHP is too old.
	 */
	public static function register_admin_notice(): void {
		add_action(
			'admin_notices',
			static function (): void {
				echo '<div class="notice notice-error"><p>';
				printf(
					/* translators: 1: required version, 2: current version */
					esc_html__( 'Guest Customer Recovery & Marketing Suite requires PHP %1$s or higher. This server is running PHP %2$s.', 'gcrm' ),
					esc_html( self::MIN_PHP ),
					esc_html( PHP_VERSION )
				);
				echo '</p></div>';
			}
		);
	}

	/**
	 * Polyfill for str_starts_with (PHP 8.0+).
	 *
	 * @param string $haystack Haystack.
	 * @param string $needle Needle.
	 */
	public static function str_starts_with( string $haystack, string $needle ): bool {
		if ( function_exists( 'str_starts_with' ) ) {
			return str_starts_with( $haystack, $needle );
		}
		return '' === $needle || 0 === strpos( $haystack, $needle );
	}

	/**
	 * Polyfill for str_ends_with (PHP 8.0+).
	 *
	 * @param string $haystack Haystack.
	 * @param string $needle Needle.
	 */
	public static function str_ends_with( string $haystack, string $needle ): bool {
		if ( function_exists( 'str_ends_with' ) ) {
			return str_ends_with( $haystack, $needle );
		}
		if ( '' === $needle ) {
			return true;
		}
		$len = strlen( $needle );
		return $len <= strlen( $haystack ) && 0 === substr_compare( $haystack, $needle, -$len, $len );
	}

	/**
	 * Polyfill for str_contains (PHP 8.0+).
	 *
	 * @param string $haystack Haystack.
	 * @param string $needle Needle.
	 */
	public static function str_contains( string $haystack, string $needle ): bool {
		if ( function_exists( 'str_contains' ) ) {
			return str_contains( $haystack, $needle );
		}
		return '' === $needle || false !== strpos( $haystack, $needle );
	}
}
