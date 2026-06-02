<?php
/**
 * REST API bootstrap.
 *
 * @package GCRM\REST
 */

namespace GCRM\REST;

use GCRM\REST\Controllers\CartController;
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
}
