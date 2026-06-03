<?php
/**
 * Automated cart recovery sequences.
 *
 * @package GCRM\Services
 */

namespace GCRM\Services;

use GCRM\DB\Repositories\CartRepository;
use GCRM\DB\Repositories\EmailLogRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class RecoveryAutomation
 */
class RecoveryAutomation {

	/**
	 * Process recovery email/WhatsApp sequences.
	 */
	public function process_recovery_sequences(): void {
		if ( 'yes' !== get_option( 'gcrm_recovery_email_enabled', 'yes' ) ) {
			return;
		}

		$sequence = json_decode( (string) get_option( 'gcrm_recovery_sequence', '[]' ), true );
		if ( ! is_array( $sequence ) ) {
			return;
		}

		$carts = ( new CartRepository() )->get_due_for_recovery();
		foreach ( $carts as $cart ) {
			$this->process_cart_sequence( $cart, $sequence );
		}
	}

	/**
	 * Process one cart through sequence steps.
	 *
	 * @param array<string, mixed> $cart Cart row.
	 * @param array<int, array<string, mixed>> $sequence Sequence config.
	 */
	private function process_cart_sequence( array $cart, array $sequence ): void {
		$step         = (int) ( $cart['recovery_step'] ?? 0 );
		$abandoned_at = $cart['abandoned_at'] ?? $cart['last_activity'] ?? '';
		if ( ! $abandoned_at ) {
			return;
		}

		while ( $step < count( $sequence ) ) {
			$config = $sequence[ $step ] ?? null;
			if ( ! $config || empty( $config['enabled'] ) ) {
				++$step;
				continue;
			}

			$delay_hours = (int) ( $config['delay_hours'] ?? 1 );
			$due_time    = strtotime( $abandoned_at ) + ( $delay_hours * HOUR_IN_SECONDS );
			if ( time() < $due_time ) {
				return;
			}

			$type = $config['type'] ?? 'email';
			if ( 'email' === $type && ! empty( $cart['email'] ) ) {
				$this->send_recovery_email( $cart );
			} elseif ( 'whatsapp' === $type && ! empty( $cart['phone'] ) && 'yes' === get_option( 'gcrm_recovery_whatsapp_enabled', 'no' ) ) {
				$this->send_recovery_whatsapp( $cart );
			}

			global $wpdb;
			$wpdb->update(
				\GCRM\DB\Schema::table( 'abandoned_carts' ),
				array( 'recovery_step' => $step + 1 ),
				array( 'id' => (int) $cart['id'] ),
				array( '%d' ),
				array( '%d' )
			);
			return;
		}
	}

	/**
	 * Reuse one recovery coupon per abandoned cart.
	 *
	 * @param int $cart_id Cart ID.
	 */
	private function get_or_create_recovery_coupon( int $cart_id ): string {
		$option_key = 'gcrm_recovery_coupon_' . $cart_id;
		$existing   = get_option( $option_key, '' );
		if ( is_string( $existing ) && $existing ) {
			return $existing;
		}

		$code = ( new Coupons() )->for_abandoned_cart();
		update_option( $option_key, $code, false );
		return $code;
	}

	/**
	 * Send recovery email for cart.
	 *
	 * @param array<string, mixed> $cart Cart.
	 */
	public function send_recovery_email( array $cart ): void {
		$cart_id = (int) $cart['id'];
		$coupon  = $this->get_or_create_recovery_coupon( $cart_id );
		$items   = json_decode( $cart['cart_contents'] ?? '[]', true );
		$body    = $this->build_recovery_body( is_array( $items ) ? $items : array(), Carts::recovery_url( (string) $cart['recovery_token'] ), $coupon );

		$log_id = ( new EmailLogRepository() )->create(
			array(
				'cart_id'  => $cart_id,
				'to_email' => $cart['email'],
				'subject'  => sprintf( __( 'Complete your order at %s', 'gcrm' ), get_bloginfo( 'name' ) ),
				'body'     => $body,
				'status'   => 'queued',
			)
		);

		( new Email() )->send_single(
			(string) $cart['email'],
			sprintf( __( 'Your cart is waiting - %s', 'gcrm' ), get_bloginfo( 'name' ) ),
			$body,
			array(
				'{first_name}'    => explode( ' ', (string) $cart['customer_name'] )[0] ?? '',
				'{coupon_code}'   => $coupon,
				'{recovery_link}' => Carts::recovery_url( (string) $cart['recovery_token'] ),
				'{store_name}'    => get_bloginfo( 'name' ),
			),
			$log_id
		);
	}

	/**
	 * Send recovery WhatsApp.
	 *
	 * @param array<string, mixed> $cart Cart.
	 */
	public function send_recovery_whatsapp( array $cart ): void {
		$cart_id = (int) $cart['id'];
		$coupon  = $this->get_or_create_recovery_coupon( $cart_id );
		$message = sprintf(
			/* translators: 1: store name, 2: cart value, 3: recovery link, 4: coupon */
			__( "Hi! You left items in your cart at %1\$s (value: %2\$s). Complete checkout: %3\$s Use code %4\$s for a discount.", 'gcrm' ),
			get_bloginfo( 'name' ),
			wc_price( (float) $cart['cart_value'] ),
			Carts::recovery_url( (string) $cart['recovery_token'] ),
			$coupon
		);
		( new WhatsApp() )->send_single(
			(string) $cart['phone'],
			$message,
			array( '{coupon_code}' => $coupon, '{store_name}' => get_bloginfo( 'name' ) )
		);
	}

	/**
	 * Build recovery email HTML.
	 *
	 * @param array<int, array<string, mixed>> $items Cart items.
	 * @param string                           $recovery_link Link.
	 * @param string                           $coupon Coupon code.
	 */
	private function build_recovery_body( array $items, string $recovery_link, string $coupon ): string {
		$html = '<h2>' . esc_html__( 'Your cart is waiting!', 'gcrm' ) . '</h2><ul>';
		foreach ( $items as $item ) {
			$img = ! empty( $item['image'] ) ? '<img src="' . esc_url( $item['image'] ) . '" width="50" /> ' : '';
			$html .= '<li>' . $img . esc_html( $item['name'] ?? '' ) . ' x ' . esc_html( (string) ( $item['quantity'] ?? 1 ) ) . '</li>';
		}
		$html .= '</ul>';
		$html .= '<p><a href="' . esc_url( $recovery_link ) . '">' . esc_html__( 'Complete your purchase', 'gcrm' ) . '</a></p>';
		if ( $coupon ) {
			$html .= '<p>' . esc_html__( 'Use coupon:', 'gcrm' ) . ' <strong>' . esc_html( $coupon ) . '</strong></p>';
		}
		return $html;
	}
}
