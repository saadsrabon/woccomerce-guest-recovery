<?php
/**
 * Coupon & offer engine.
 *
 * @package GCRM\Services
 */

namespace GCRM\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Class Coupons
 */
class Coupons {

	/**
	 * Generate WooCommerce coupon.
	 *
	 * @param array<string, mixed> $args Coupon args.
	 * @return string Coupon code or empty.
	 */
	public function generate( array $args = array() ): string {
		$percent   = (float) ( $args['percent'] ?? 10 );
		$prefix    = sanitize_text_field( (string) ( $args['prefix'] ?? 'GCRM' ) );
		$code      = $prefix . '-' . strtoupper( wp_generate_password( 8, false ) );
		$expiry    = (int) ( $args['expiry_days'] ?? 7 );
		$usage     = (int) ( $args['usage_limit'] ?? 1 );
		$min_spend = (float) ( $args['minimum_amount'] ?? 0 );

		$coupon = new \WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( $percent );
		$coupon->set_usage_limit( $usage );
		$coupon->set_date_expires( gmdate( 'Y-m-d', time() + ( $expiry * DAY_IN_SECONDS ) ) );
		if ( $min_spend > 0 ) {
			$coupon->set_minimum_amount( $min_spend );
		}
		if ( ! empty( $args['product_ids'] ) ) {
			$coupon->set_product_ids( array_map( 'absint', (array) $args['product_ids'] ) );
		}
		$coupon->save();
		return $code;
	}

	/**
	 * Abandoned cart recovery coupon.
	 */
	public function for_abandoned_cart(): string {
		return $this->generate(
			array(
				'percent'     => (float) get_option( 'gcrm_abandoned_coupon_percent', 10 ),
				'prefix'      => 'RECOVER',
				'expiry_days' => (int) get_option( 'gcrm_abandoned_coupon_expiry', 3 ),
			)
		);
	}
}
