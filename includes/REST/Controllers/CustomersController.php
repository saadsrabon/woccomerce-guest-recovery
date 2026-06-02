<?php
/**
 * Customers REST controller.
 *
 * @package GCRM\REST\Controllers
 */

namespace GCRM\REST\Controllers;

use GCRM\REST\RestBootstrap;
use GCRM\Services\OrdersSync;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Class CustomersController
 */
class CustomersController {

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			'gcrm/v1',
			'/customers',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_customers' ),
				'permission_callback' => array( RestBootstrap::class, 'admin_permission' ),
			)
		);
	}

	/**
	 * List guests (AJAX/DataTables).
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function list_customers( WP_REST_Request $request ): WP_REST_Response {
		$sync = new OrdersSync();
		$data = $sync->get_guest_order_rows(
			array(
				'search'     => sanitize_text_field( (string) $request->get_param( 'search' ) ),
				'date_from'  => sanitize_text_field( (string) $request->get_param( 'date_from' ) ),
				'date_to'    => sanitize_text_field( (string) $request->get_param( 'date_to' ) ),
				'status'     => sanitize_text_field( (string) $request->get_param( 'status' ) ),
				'country'    => sanitize_text_field( (string) $request->get_param( 'country' ) ),
				'product_id' => absint( $request->get_param( 'product_id' ) ),
				'page'       => absint( $request->get_param( 'page' ) ) ?: 1,
				'per_page'   => absint( $request->get_param( 'per_page' ) ) ?: 20,
			)
		);
		return new WP_REST_Response( $data, 200 );
	}
}
