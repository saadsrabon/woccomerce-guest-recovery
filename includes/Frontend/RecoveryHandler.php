<?php
/**
 * Cart recovery URL handler.
 *
 * @package GCRM\Frontend
 */

namespace GCRM\Frontend;

use GCRM\DB\Repositories\CartRepository;
use GCRM\Services\Carts;

defined( 'ABSPATH' ) || exit;

/**
 * Class RecoveryHandler
 */
class RecoveryHandler {

	/**
	 * Bootstrap recovery handler.
	 */
	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'handle_recovery' ) );
	}

	/**
	 * Restore cart from recovery token.
	 */
	public static function handle_recovery(): void {
		if ( empty( $_GET['gcrm_recover'] ) || ! function_exists( 'WC' ) ) {
			return;
		}

		$token = sanitize_text_field( wp_unslash( $_GET['gcrm_recover'] ) );
		$cart  = ( new CartRepository() )->find_by_token( $token );
		if ( ! $cart || empty( $cart['cart_contents'] ) ) {
			return;
		}

		$items = json_decode( $cart['cart_contents'], true );
		if ( ! is_array( $items ) || ! WC()->cart ) {
			return;
		}

		WC()->cart->empty_cart();
		foreach ( $items as $item ) {
			$product_id = (int) ( $item['product_id'] ?? 0 );
			$qty        = (int) ( $item['quantity'] ?? 1 );
			if ( $product_id ) {
				WC()->cart->add_to_cart( $product_id, $qty );
			}
		}

		WC()->session->set( 'gcrm_recover_cart_id', (int) $cart['id'] );
	}
}
