<?php
/**
 * Abandoned cart service.
 *
 * @package GCRM\Services
 */

namespace GCRM\Services;

use GCRM\DB\Repositories\CartRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class Carts
 */
class Carts {

	/**
	 * Track cart from session.
	 *
	 * @param array<string, mixed> $data Cart data.
	 */
	public function track( array $data ): int {
		$repo = new CartRepository();
		return $repo->upsert_session( $data );
	}

	/**
	 * Mark abandoned by timeout setting.
	 */
	public function mark_abandoned_by_timeout(): void {
		$minutes = (int) get_option( 'gcrm_cart_timeout_minutes', 30 );
		$repo    = new CartRepository();
		$repo->mark_stale_abandoned( $minutes );
	}

	/**
	 * Build cart contents from WC cart.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_cart_snapshot(): array {
		if ( ! WC()->cart ) {
			return array();
		}
		$items = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			$product = $item['data'] ?? null;
			$items[] = array(
				'product_id' => $item['product_id'] ?? 0,
				'quantity'   => $item['quantity'] ?? 0,
				'name'       => $product ? $product->get_name() : '',
				'price'      => $product ? $product->get_price() : 0,
				'image'      => $product ? wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) : '',
			);
		}
		return $items;
	}

	/**
	 * Recovery URL.
	 *
	 * @param string $token Recovery token.
	 */
	public static function recovery_url( string $token ): string {
		return add_query_arg( 'gcrm_recover', $token, wc_get_cart_url() );
	}

	/**
	 * Mark cart recovered.
	 *
	 * @param int $cart_id Cart ID.
	 * @param int $order_id Order ID.
	 */
	public function mark_recovered( int $cart_id, int $order_id = 0 ): void {
		global $wpdb;
		$table = \GCRM\DB\Schema::table( 'abandoned_carts' );
		$wpdb->update(
			$table,
			array(
				'status'             => 'recovered',
				'recovered_order_id' => $order_id,
			),
			array( 'id' => $cart_id ),
			array( '%s', '%d' ),
			array( '%d' )
		);
	}
}
