<?php
/**
 * Frontend cart tracker.
 *
 * @package GCRM\Frontend
 */

namespace GCRM\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Class CartTracker
 */
class CartTracker {

	/**
	 * Enqueue tracker script.
	 */
	public function enqueue_scripts(): void {
		if ( is_admin() || ! function_exists( 'is_checkout' ) ) {
			return;
		}
		if ( ! is_cart() && ! is_checkout() ) {
			return;
		}

		wp_enqueue_script(
			'gcrm-cart-tracker',
			GCRM_PLUGIN_URL . 'admin/js/cart-tracker.js',
			array( 'jquery' ),
			GCRM_VERSION,
			true
		);
		wp_localize_script(
			'gcrm-cart-tracker',
			'gcrmTracker',
			array(
				'restUrl' => rest_url( 'gcrm/v1/cart/track' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * On cart updated.
	 */
	public function on_cart_updated(): void {
		$this->send_track_request();
	}

	/**
	 * Capture checkout fields from order review AJAX.
	 *
	 * @param string $posted_data Posted data string.
	 */
	public function capture_checkout_fields( $posted_data ): void {
		parse_str( $posted_data, $data );
		if ( ! empty( $data['billing_email'] ) ) {
			WC()->session->set( 'gcrm_capture_email', sanitize_email( $data['billing_email'] ) );
		}
		if ( ! empty( $data['billing_phone'] ) ) {
			WC()->session->set( 'gcrm_capture_phone', sanitize_text_field( $data['billing_phone'] ) );
		}
		$this->send_track_request( $data );
	}

	/**
	 * Server-side track fallback.
	 *
	 * @param array<string, mixed> $data Optional checkout data.
	 */
	private function send_track_request( array $data = array() ): void {
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}

		$email = $data['billing_email'] ?? WC()->session->get( 'gcrm_capture_email', '' );
		$phone = $data['billing_phone'] ?? WC()->session->get( 'gcrm_capture_phone', '' );
		$name  = trim( ( $data['billing_first_name'] ?? '' ) . ' ' . ( $data['billing_last_name'] ?? '' ) );

		( new \GCRM\Services\Carts() )->track(
			array(
				'session_key'   => (string) WC()->session->get_customer_id(),
				'user_id'       => get_current_user_id(),
				'email'         => $email,
				'phone'         => $phone,
				'customer_name' => $name,
				'cart_contents' => \GCRM\Services\Carts::get_cart_snapshot(),
				'cart_value'    => (float) WC()->cart->get_total( 'edit' ),
				'status'        => 'active',
			)
		);
	}
}
