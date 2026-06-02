<?php
/**
 * Abandoned carts repository.
 *
 * @package GCRM\DB\Repositories
 */

namespace GCRM\DB\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Class CartRepository
 */
class CartRepository extends BaseRepository {

	protected function table_key(): string {
		return 'abandoned_carts';
	}

	/**
	 * Insert or update cart session.
	 *
	 * @param array<string, mixed> $data Cart data.
	 * @return int Cart ID.
	 */
	public function upsert_session( array $data ): int {
		global $wpdb;
		$session = sanitize_text_field( (string) ( $data['session_key'] ?? '' ) );
		if ( ! $session ) {
			return 0;
		}

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . $this->table() . ' WHERE session_key = %s AND status IN (%s, %s)',
				$session,
				'active',
				'abandoned'
			)
		);

		$row = array(
			'session_key'    => $session,
			'user_id'        => (int) ( $data['user_id'] ?? 0 ),
			'email'          => sanitize_email( (string) ( $data['email'] ?? '' ) ),
			'phone'          => sanitize_text_field( (string) ( $data['phone'] ?? '' ) ),
			'customer_name'  => sanitize_text_field( (string) ( $data['customer_name'] ?? '' ) ),
			'cart_contents'  => wp_json_encode( $data['cart_contents'] ?? array() ),
			'cart_value'     => (float) ( $data['cart_value'] ?? 0 ),
			'currency'       => sanitize_text_field( (string) ( $data['currency'] ?? get_woocommerce_currency() ) ),
			'status'         => sanitize_text_field( (string) ( $data['status'] ?? 'active' ) ),
			'last_activity'  => current_time( 'mysql' ),
		);

		if ( empty( $data['recovery_token'] ) && ! $existing ) {
			$row['recovery_token'] = wp_generate_password( 32, false );
		}

		if ( $existing ) {
			$wpdb->update( $this->table(), $row, array( 'id' => (int) $existing ) );
			return (int) $existing;
		}

		$row['recovery_token'] = $row['recovery_token'] ?? wp_generate_password( 32, false );
		$wpdb->insert( $this->table(), $row );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Query carts.
	 *
	 * @param array<string, mixed> $args Args.
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public function query( array $args = array() ): array {
		global $wpdb;
		$defaults = array(
			'search'     => '',
			'status'     => '',
			'recovered'  => '',
			'date_from'  => '',
			'date_to'    => '',
			'per_page'   => 20,
			'page'       => 1,
		);
		$args  = wp_parse_args( $args, $defaults );
		$where = array( '1=1' );
		$params = array();

		if ( $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(customer_name LIKE %s OR email LIKE %s OR phone LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( 'yes' === $args['recovered'] ) {
			$where[] = "status = 'recovered'";
		} elseif ( 'no' === $args['recovered'] ) {
			$where[] = "status IN ('active', 'abandoned')";
		}
		if ( $args['date_from'] ) {
			$where[]  = 'last_activity >= %s';
			$params[] = $args['date_from'] . ' 00:00:00';
		}
		if ( $args['date_to'] ) {
			$where[]  = 'last_activity <= %s';
			$params[] = $args['date_to'] . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$this->table()} WHERE {$where_sql}";
		if ( $params ) {
			$count_sql = $wpdb->prepare( $count_sql, ...$params );
		}
		$total = (int) $wpdb->get_var( $count_sql );

		$offset      = max( 0, ( (int) $args['page'] - 1 ) * (int) $args['per_page'] );
		$list_params = array_merge( $params, array( (int) $args['per_page'], $offset ) );
		$list_sql    = "SELECT * FROM {$this->table()} WHERE {$where_sql} ORDER BY last_activity DESC LIMIT %d OFFSET %d";
		$list_sql    = $wpdb->prepare( $list_sql, ...$list_params );
		$items       = $wpdb->get_results( $list_sql, ARRAY_A );

		return array( 'items' => $items ?: array(), 'total' => $total );
	}

	/**
	 * Mark stale active carts abandoned.
	 *
	 * @param int $timeout_minutes Minutes.
	 * @return int Affected rows.
	 */
	public function mark_stale_abandoned( int $timeout_minutes ): int {
		global $wpdb;
		$threshold = gmdate( 'Y-m-d H:i:s', time() - ( $timeout_minutes * 60 ) );
		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table()} SET status = 'abandoned', abandoned_at = %s WHERE status = 'active' AND last_activity < %s",
				current_time( 'mysql' ),
				$threshold
			)
		);
	}

	/**
	 * Find by recovery token.
	 *
	 * @param string $token Token.
	 * @return array<string, mixed>|null
	 */
	public function find_by_token( string $token ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE recovery_token = %s', $token ),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Get abandoned carts due for recovery step.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_due_for_recovery(): array {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT * FROM {$this->table()} WHERE status = 'abandoned' AND email != ''",
			ARRAY_A
		) ?: array();
	}
}
