<?php
/**
 * HPOS-safe guest order sync.
 *
 * @package GCRM\Services
 */

namespace GCRM\Services;

use GCRM\DB\Repositories\GuestRepository;
use GCRM\Services\WorkflowRunner;

defined( 'ABSPATH' ) || exit;

/**
 * Class OrdersSync
 */
class OrdersSync {

	/**
	 * On new order.
	 *
	 * @param int $order_id Order ID.
	 */
	public function on_order_created( $order_id ): void {
		$this->sync_order( (int) $order_id );
	}

	/**
	 * On status change.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $from From status.
	 * @param string $to To status.
	 * @param object $order Order object.
	 */
	public function on_order_status_changed( $order_id, $from, $to, $order ): void {
		$this->sync_order( (int) $order_id );
	}

	/**
	 * On checkout processed.
	 *
	 * @param int $order_id Order ID.
	 */
	public function on_checkout_processed( $order_id ): void {
		$this->sync_order( (int) $order_id );
		$order = wc_get_order( $order_id );
		if ( $order && 0 === (int) $order->get_customer_id() ) {
			( new WorkflowRunner() )->trigger( 'guest_order_completed', array( 'order_id' => $order_id ) );
		}
	}

	/**
	 * Sync single order to guest table.
	 *
	 * @param int $order_id Order ID.
	 */
	public function sync_order( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		if ( (int) $order->get_customer_id() > 0 ) {
			return;
		}

		$email = $order->get_billing_email();
		if ( ! $email ) {
			return;
		}

		$stats = $this->aggregate_guest_stats( $email );

		$repo = new GuestRepository();
		$repo->upsert(
			array(
				'email'             => $email,
				'first_name'        => $order->get_billing_first_name(),
				'last_name'         => $order->get_billing_last_name(),
				'phone'             => $order->get_billing_phone(),
				'billing_address'   => $order->get_address( 'billing' ),
				'shipping_address'  => $order->get_address( 'shipping' ),
				'city'              => $order->get_billing_city(),
				'state'             => $order->get_billing_state(),
				'country'           => $order->get_billing_country(),
				'zip'               => $order->get_billing_postcode(),
				'order_count'       => $stats['order_count'],
				'total_spend'       => $stats['total_spend'],
				'last_order_id'     => $order_id,
				'last_order_date'   => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : current_time( 'mysql' ),
				'last_order_status' => $order->get_status(),
			)
		);

		if ( $stats['total_spend'] >= 500 ) {
			( new WorkflowRunner() )->trigger( 'order_total_over', array( 'order_id' => $order_id, 'email' => $email ) );
		}
	}

	/**
	 * Aggregate stats for guest email (HPOS).
	 *
	 * @param string $email Billing email.
	 * @return array{order_count: int, total_spend: float}
	 */
	public function aggregate_guest_stats( string $email ): array {
		$orders = wc_get_orders(
			array(
				'billing_email' => $email,
				'customer_id'   => 0,
				'limit'         => -1,
				'status'        => array_keys( wc_get_order_statuses() ),
			)
		);

		$count = 0;
		$total = 0.0;
		foreach ( $orders as $order ) {
			++$count;
			$total += (float) $order->get_total();
		}

		return array(
			'order_count' => $count,
			'total_spend' => $total,
		);
	}

	/**
	 * Full sync of all guest orders.
	 */
	public function sync_all_guests(): void {
		$page  = 1;
		$limit = 100;

		do {
			$orders = wc_get_orders(
				array(
					'customer_id' => 0,
					'limit'       => $limit,
					'page'        => $page,
					'orderby'     => 'date',
					'order'       => 'DESC',
				)
			);

			foreach ( $orders as $order ) {
				$this->sync_order( $order->get_id() );
			}
			++$page;
		} while ( count( $orders ) === $limit );
	}

	/**
	 * Get guest order rows for list table (latest order per guest).
	 *
	 * @param array<string, mixed> $args Filters.
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public function get_guest_order_rows( array $args = array() ): array {
		$repo   = new GuestRepository();
		$result = $repo->query( $args );
		$items  = array();

		foreach ( $result['items'] as $guest ) {
			$order = $guest['last_order_id'] ? wc_get_order( (int) $guest['last_order_id'] ) : null;
			$items[] = array(
				'guest_id'         => (int) $guest['id'],
				'order_id'         => (int) $guest['last_order_id'],
				'customer_name'    => trim( $guest['first_name'] . ' ' . $guest['last_name'] ),
				'email'            => $guest['email'],
				'phone'            => $guest['phone'],
				'billing_address'  => $this->format_address( json_decode( $guest['billing_address'] ?? '{}', true ) ),
				'shipping_address' => $this->format_address( json_decode( $guest['shipping_address'] ?? '{}', true ) ),
				'city'             => $guest['city'],
				'country'          => $guest['country'],
				'order_total'      => $order ? wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) : wc_price( $guest['total_spend'] ),
				'order_date'       => $guest['last_order_date'],
				'order_status'     => $guest['last_order_status'],
				'order_count'      => (int) $guest['order_count'],
				'total_spend'      => (float) $guest['total_spend'],
			);
		}

		return array( 'items' => $items, 'total' => $result['total'] );
	}

	/**
	 * Format address array.
	 *
	 * @param array<string, mixed>|null $address Address.
	 */
	private function format_address( $address ): string {
		if ( ! is_array( $address ) ) {
			return '';
		}
		$parts = array_filter(
			array(
				$address['address_1'] ?? '',
				$address['address_2'] ?? '',
				$address['city'] ?? '',
				$address['state'] ?? '',
				$address['postcode'] ?? '',
			)
		);
		return implode( ', ', $parts );
	}
}
