<?php
/**
 * Segments REST controller.
 *
 * @package GCRM\REST\Controllers
 */

namespace GCRM\REST\Controllers;

use GCRM\REST\RestBootstrap;
use GCRM\Services\Segments;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Class SegmentsController
 */
class SegmentsController {

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			'gcrm/v1',
			'/segments/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => array( RestBootstrap::class, 'admin_permission' ),
			)
		);
	}

	/**
	 * Preview segment count.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function preview( WP_REST_Request $request ): WP_REST_Response {
		$rules = $request->get_json_params();
		if ( ! is_array( $rules ) ) {
			return new WP_REST_Response( array( 'count' => 0 ), 400 );
		}
		$count = ( new Segments() )->preview_count( $rules );
		return new WP_REST_Response( array( 'count' => $count ), 200 );
	}
}
