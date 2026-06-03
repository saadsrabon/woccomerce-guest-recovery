<?php
/**
 * REST API bootstrap.
 *
 * @package GCRM\REST
 */

namespace GCRM\REST;

use GCRM\REST\Controllers\CartController;
use WP_REST_Request;
use GCRM\REST\Controllers\CustomersController;
use GCRM\REST\Controllers\SegmentsController;
use GCRM\REST\Controllers\TrackingController;

defined( 'ABSPATH' ) || exit;

/**
 * Class RestBootstrap
 */
class RestBootstrap {

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		( new CartController() )->register_routes();
		( new CustomersController() )->register_routes();
		( new SegmentsController() )->register_routes();
		( new TrackingController() )->register_routes();
	}

	/**
	 * Permission check for admin endpoints.
	 */
	public static function admin_permission(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Permission check for storefront cart tracking (requires REST nonce + WC session).
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function cart_track_permission( WP_REST_Request $request ): bool {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return false;
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return false;
		}

		return true;
	}
}
