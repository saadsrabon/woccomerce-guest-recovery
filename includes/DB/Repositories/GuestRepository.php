<?php
/**
 * Guest customers repository.
 *
 * @package GCRM\DB\Repositories
 */

namespace GCRM\DB\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Class GuestRepository
 */
class GuestRepository extends BaseRepository {

	protected function table_key(): string {
		return 'guest_customers';
	}

	/**
	 * Upsert guest by email.
	 *
	 * @param array<string, mixed> $data Guest data.
	 * @return int Guest ID.
	 */
	public function upsert( array $data ): int {
		global $wpdb;
		$email = sanitize_email( (string) ( $data['email'] ?? '' ) );
		if ( ! $email ) {
			return 0;
		}

		$existing = $wpdb->get_var(
			$wpdb->prepare( 'SELECT id FROM ' . $this->table() . ' WHERE email = %s', $email )
		);

		$row = array(
			'email'             => $email,
			'first_name'        => sanitize_text_field( (string) ( $data['first_name'] ?? '' ) ),
			'last_name'         => sanitize_text_field( (string) ( $data['last_name'] ?? '' ) ),
			'phone'             => sanitize_text_field( (string) ( $data['phone'] ?? '' ) ),
			'billing_address'   => wp_json_encode( $data['billing_address'] ?? array() ),
			'shipping_address'  => wp_json_encode( $data['shipping_address'] ?? array() ),
			'city'              => sanitize_text_field( (string) ( $data['city'] ?? '' ) ),
			'state'             => sanitize_text_field( (string) ( $data['state'] ?? '' ) ),
			'country'           => sanitize_text_field( (string) ( $data['country'] ?? '' ) ),
			'zip'               => sanitize_text_field( (string) ( $data['zip'] ?? '' ) ),
			'order_count'       => (int) ( $data['order_count'] ?? 0 ),
			'total_spend'       => (float) ( $data['total_spend'] ?? 0 ),
			'last_order_id'     => (int) ( $data['last_order_id'] ?? 0 ),
			'last_order_date'   => $data['last_order_date'] ?? null,
			'last_order_status' => sanitize_text_field( (string) ( $data['last_order_status'] ?? '' ) ),
		);

		if ( $existing ) {
			$wpdb->update( $this->table(), $row, array( 'id' => (int) $existing ) );
			return (int) $existing;
		}

		$wpdb->insert( $this->table(), $row );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Query guests with filters.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public function query( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'search'        => '',
			'date_from'     => '',
			'date_to'       => '',
			'status'        => '',
			'country'       => '',
			'product_id'    => 0,
			'per_page'      => 20,
			'page'          => 1,
			'orderby'       => 'last_order_date',
			'order'         => 'DESC',
		);
		$args   = wp_parse_args( $args, $defaults );
		$where  = array( '1=1' );
		$params = array();

		if ( $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( $args['date_from'] ) {
			$where[]  = 'last_order_date >= %s';
			$params[] = $args['date_from'] . ' 00:00:00';
		}
		if ( $args['date_to'] ) {
			$where[]  = 'last_order_date <= %s';
			$params[] = $args['date_to'] . ' 23:59:59';
		}
		if ( $args['status'] ) {
			$where[]  = 'last_order_status = %s';
			$params[] = $args['status'];
		}
		if ( $args['country'] ) {
			$where[]  = 'country = %s';
			$params[] = $args['country'];
		}

		$where_sql = implode( ' AND ', $where );
		$allowed_orderby = array( 'last_order_date', 'total_spend', 'order_count', 'email', 'created_at' );
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'last_order_date';
		$order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$count_sql = "SELECT COUNT(*) FROM {$this->table()} WHERE {$where_sql}";
		if ( $params ) {
			$count_sql = $wpdb->prepare( $count_sql, ...$params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$offset = max( 0, ( (int) $args['page'] - 1 ) * (int) $args['per_page'] );
		$list_params = array_merge( $params, array( (int) $args['per_page'], $offset ) );
		$list_sql    = "SELECT * FROM {$this->table()} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$list_sql    = $wpdb->prepare( $list_sql, ...$list_params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$items       = $wpdb->get_results( $list_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( (int) $args['product_id'] > 0 && $items ) {
			$items = $this->filter_by_product( $items, (int) $args['product_id'] );
		}

		return array(
			'items' => $items ?: array(),
			'total' => $total,
		);
	}

	/**
	 * Filter guests who purchased a product.
	 *
	 * @param array<int, array<string, mixed>> $items Guests.
	 * @param int                              $product_id Product ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function filter_by_product( array $items, int $product_id ): array {
		return array_values(
			array_filter(
				$items,
				function ( $guest ) use ( $product_id ) {
					return $this->guest_purchased_product( (string) $guest['email'], $product_id );
				}
			)
		);
	}

	/**
	 * Check if guest email has order with product.
	 *
	 * @param string $email Email.
	 * @param int    $product_id Product ID.
	 */
	public function guest_purchased_product( string $email, int $product_id ): bool {
		$orders = wc_get_orders(
			array(
				'billing_email' => $email,
				'customer_id'   => 0,
				'limit'         => -1,
				'return'        => 'ids',
			)
		);
		foreach ( $orders as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}
			foreach ( $order->get_items() as $item ) {
				if ( (int) $item->get_product_id() === $product_id || (int) $item->get_variation_id() === $product_id ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Get all for export.
	 *
	 * @param array<string, mixed> $args Filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_for_export( array $args = array() ): array {
		$args['per_page'] = 99999;
		$args['page']     = 1;
		return $this->query( $args )['items'];
	}

	/**
	 * Find by email.
	 *
	 * @param string $email Email.
	 * @return array<string, mixed>|null
	 */
	public function find_by_email( string $email ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE email = %s', sanitize_email( $email ) ),
			ARRAY_A
		);
		return $row ?: null;
	}
}
