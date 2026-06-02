<?php
/**
 * Convert guest customers to WooCommerce users.
 *
 * @package GCRM\Services
 */

namespace GCRM\Services;

use GCRM\DB\Repositories\GuestRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class Convert
 */
class Convert {

	/**
	 * Convert guests by IDs.
	 *
	 * @param array<int> $guest_ids Guest IDs.
	 * @return array{success: int, failed: int, messages: array<int, string>}
	 */
	public function convert_guests( array $guest_ids ): array {
		$repo    = new GuestRepository();
		$success = 0;
		$failed  = 0;
		$messages = array();

		foreach ( $guest_ids as $id ) {
			$guest = $repo->find( (int) $id );
			if ( ! $guest ) {
				++$failed;
				continue;
			}

			$result = $this->convert_single( $guest );
			if ( is_wp_error( $result ) ) {
				++$failed;
				$messages[] = $result->get_error_message();
			} else {
				++$success;
			}
		}

		return array( 'success' => $success, 'failed' => $failed, 'messages' => $messages );
	}

	/**
	 * Convert one guest.
	 *
	 * @param array<string, mixed> $guest Guest row.
	 * @return int|\WP_Error User ID or error.
	 */
	public function convert_single( array $guest ) {
		$email = sanitize_email( (string) $guest['email'] );
		if ( ! $email ) {
			return new \WP_Error( 'gcrm_invalid_email', __( 'Invalid email.', 'gcrm' ) );
		}

		if ( email_exists( $email ) ) {
			$user = get_user_by( 'email', $email );
			$this->link_orders_to_user( $email, (int) $user->ID );
			return (int) $user->ID;
		}

		$password = wp_generate_password( 12, true );
		$user_id  = wc_create_new_customer(
			$email,
			'',
			$password,
			array(
				'first_name' => $guest['first_name'] ?? '',
				'last_name'  => $guest['last_name'] ?? '',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		global $wpdb;
		$wpdb->update(
			\GCRM\DB\Schema::table( 'guest_customers' ),
			array( 'user_id' => $user_id ),
			array( 'id' => (int) $guest['id'] ),
			array( '%d' ),
			array( '%d' )
		);

		$this->link_orders_to_user( $email, $user_id );

		wp_new_user_notification( $user_id, null, 'both' );

		return $user_id;
	}

	/**
	 * Link guest orders to user (HPOS).
	 *
	 * @param string $email Email.
	 * @param int    $user_id User ID.
	 */
	public function link_orders_to_user( string $email, int $user_id ): void {
		$orders = wc_get_orders(
			array(
				'billing_email' => $email,
				'customer_id'   => 0,
				'limit'         => -1,
			)
		);
		foreach ( $orders as $order ) {
			$order->set_customer_id( $user_id );
			$order->save();
		}
	}
}
