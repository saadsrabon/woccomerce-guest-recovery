<?php
/**
 * Cart tracking REST controller.
 *
 * @package GCRM\REST\Controllers
 */

namespace GCRM\REST\Controllers;

use GCRM\REST\RestBootstrap;
use GCRM\Services\Carts;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Class CartController
 */
class CartController {

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			'gcrm/v1',
			'/cart/track',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'track' ),
				'permission_callback' => array( RestBootstrap::class, 'cart_track_permission' ),
			)
		);
	}

	/**
	 * Track cart.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function track( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params() ?: $request->get_params();

		if ( ! WC()->session ) {
			return new WP_REST_Response( array( 'success' => false ), 400 );
		}

		$session_key = WC()->session->get_customer_id();
		$cart        = WC()->cart;
		$data        = array(
			'session_key'   => (string) $session_key,
			'user_id'       => get_current_user_id(),
			'email'         => sanitize_email( (string) ( $params['email'] ?? '' ) ),
			'phone'         => sanitize_text_field( (string) ( $params['phone'] ?? '' ) ),
			'customer_name' => sanitize_text_field( (string) ( $params['name'] ?? '' ) ),
			'cart_contents' => Carts::get_cart_snapshot(),
			'cart_value'    => $cart ? (float) $cart->get_total( 'edit' ) : 0,
			'currency'      => get_woocommerce_currency(),
			'status'        => 'active',
		);

		$id = ( new Carts() )->track( $data );
		return new WP_REST_Response( array( 'success' => true, 'cart_id' => $id ), 200 );
	}
}
