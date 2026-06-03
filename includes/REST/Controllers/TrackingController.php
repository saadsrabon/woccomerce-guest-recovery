<?php
/**
 * Email tracking REST controller.
 *
 * @package GCRM\REST\Controllers
 */

namespace GCRM\REST\Controllers;

use GCRM\DB\Repositories\EmailLogRepository;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Class TrackingController
 */
class TrackingController {

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			'gcrm/v1',
			'/track/open/(?P<token>[a-zA-Z0-9]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'track_open' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'gcrm/v1',
			'/track/click/(?P<token>[a-zA-Z0-9]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'track_click' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Track email open (1x1 pixel).
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function track_open( WP_REST_Request $request ): void {
		$token = sanitize_text_field( (string) $request->get_param( 'token' ) );
		$log   = ( new EmailLogRepository() )->find_by_token( $token );
		if ( $log ) {
			global $wpdb;
			$wpdb->update(
				\GCRM\DB\Schema::table( 'email_logs' ),
				array( 'opened_at' => current_time( 'mysql' ), 'status' => 'opened' ),
				array( 'id' => (int) $log['id'] ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}
		header( 'Content-Type: image/gif' );
		// 1x1 transparent GIF.
		echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
		exit;
	}

	/**
	 * Track click and redirect.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function track_click( WP_REST_Request $request ): void {
		$token = sanitize_text_field( (string) $request->get_param( 'token' ) );
		$url   = esc_url_raw( (string) $request->get_param( 'url' ) );
		$log   = ( new EmailLogRepository() )->find_by_token( $token );
		if ( $log ) {
			global $wpdb;
			$wpdb->update(
				\GCRM\DB\Schema::table( 'email_logs' ),
				array( 'clicked_at' => current_time( 'mysql' ), 'status' => 'clicked' ),
				array( 'id' => (int) $log['id'] ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}
		$target = $url ?: home_url( '/' );
		$validated = wp_validate_redirect( $target, home_url( '/' ) );
		if ( $validated ) {
			wp_safe_redirect( $validated );
		} else {
			wp_redirect( $target, 302 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		}
		exit;
	}
}
