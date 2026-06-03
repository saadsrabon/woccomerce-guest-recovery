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
		if ( empty( $_GET['gcrm_recover'] ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$token = sanitize_text_field( wp_unslash( $_GET['gcrm_recover'] ) );
		$cart  = ( new CartRepository() )->find_by_token( $token );
		if ( ! $cart || empty( $cart['cart_contents'] ) ) {
			return;
		}

		$items = json_decode( $cart['cart_contents'], true );
		if ( ! is_array( $items ) ) {
			return;
		}

		WC()->cart->empty_cart();
		foreach ( $items as $item ) {
			self::add_item_to_cart( $item );
		}

		if ( WC()->session ) {
			WC()->session->set( 'gcrm_recover_cart_id', (int) $cart['id'] );
		}
	}

	/**
	 * Add a snapshot line item back into the cart.
	 *
	 * @param array<string, mixed> $item Snapshot item.
	 */
	private static function add_item_to_cart( array $item ): void {
		$product_id   = (int) ( $item['product_id'] ?? 0 );
		$variation_id = (int) ( $item['variation_id'] ?? 0 );
		$qty          = max( 1, (int) ( $item['quantity'] ?? 1 ) );

		if ( ! $product_id ) {
			return;
		}

		$cart_item_data = array();
		if ( ! empty( $item['cart_item_data'] ) && is_array( $item['cart_item_data'] ) ) {
			$cart_item_data = $item['cart_item_data'];
		}

		$added = WC()->cart->add_to_cart( $product_id, $qty, $variation_id, array(), $cart_item_data );
		if ( ! $added && $variation_id ) {
			WC()->cart->add_to_cart( $product_id, $qty );
		}
	}
}
