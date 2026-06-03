<?php
/**
 * Guest customers list table.
 *
 * @package GCRM\Admin\ListTables
 */

namespace GCRM\Admin\ListTables;

use GCRM\Services\OrdersSync;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

defined( 'ABSPATH' ) || exit;

/**
 * Class GuestCustomersTable
 */
class GuestCustomersTable extends \WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'guest',
				'plural'   => 'guests',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Table CSS classes.
	 *
	 * @return array<int, string>
	 */
	protected function get_table_classes(): array {
		return array( 'widefat', 'striped', 'gcrm-datatable', $this->_args['plural'] );
	}

	/**
	 * Columns.
	 */
	public function get_columns(): array {
		return array(
			'cb'               => '<input type="checkbox" />',
			'order_id'         => __( 'Order ID', 'gcrm' ),
			'customer_name'    => __( 'Customer Name', 'gcrm' ),
			'email'            => __( 'Email', 'gcrm' ),
			'phone'            => __( 'Phone', 'gcrm' ),
			'billing_address'  => __( 'Billing Address', 'gcrm' ),
			'shipping_address' => __( 'Shipping Address', 'gcrm' ),
			'city'             => __( 'City', 'gcrm' ),
			'country'          => __( 'Country', 'gcrm' ),
			'order_total'      => __( 'Order Total', 'gcrm' ),
			'order_date'       => __( 'Order Date', 'gcrm' ),
			'order_status'     => __( 'Order Status', 'gcrm' ),
		);
	}

	/**
	 * Checkbox column.
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="guest_ids[]" value="%d" />', (int) $item['guest_id'] );
	}

	/**
	 * Default column.
	 *
	 * @param array<string, mixed> $item Item.
	 * @param string               $column_name Column.
	 */
	protected function column_default( $item, $column_name ): string {
		if ( 'customer_name' === $column_name ) {
			$url = admin_url( 'admin.php?page=gcrm-customer-profile&guest_id=' . (int) $item['guest_id'] );
			return '<a href="' . esc_url( $url ) . '">' . esc_html( $item[ $column_name ] ?? '' ) . '</a>';
		}
		if ( 'order_id' === $column_name && ! empty( $item['order_id'] ) ) {
			return '<a href="' . esc_url( get_edit_post_link( (int) $item['order_id'] ) ) . '">#' . esc_html( (string) $item['order_id'] ) . '</a>';
		}
		return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
	}

	/**
	 * Prepare items.
	 */
	public function prepare_items(): void {
		$per_page = 20;
		$page     = $this->get_pagenum();
		$sync     = new OrdersSync();
		$result   = $sync->get_guest_order_rows(
			array(
				'search'     => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
				'date_from'  => sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) ),
				'date_to'    => sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) ),
				'status'     => sanitize_text_field( wp_unslash( $_GET['order_status'] ?? '' ) ),
				'country'    => sanitize_text_field( wp_unslash( $_GET['country'] ?? '' ) ),
				'product_id' => absint( $_GET['product_id'] ?? 0 ),
				'page'       => $page,
				'per_page'   => $per_page,
			)
		);

		$this->items = $result['items'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => ceil( $result['total'] / $per_page ),
			)
		);
	}
}
