<?php
/**
 * Abandoned carts list table.
 *
 * @package GCRM\Admin\ListTables
 */

namespace GCRM\Admin\ListTables;

use GCRM\DB\Repositories\CartRepository;
use GCRM\Services\RecoveryAutomation;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

defined( 'ABSPATH' ) || exit;

/**
 * Class AbandonedCartsTable
 */
class AbandonedCartsTable extends \WP_List_Table {

	public function __construct() {
		parent::__construct( array( 'singular' => 'cart', 'plural' => 'carts' ) );
	}

	public function get_columns(): array {
		return array(
			'customer_name' => __( 'Customer', 'gcrm' ),
			'email'         => __( 'Email', 'gcrm' ),
			'phone'         => __( 'Phone', 'gcrm' ),
			'products'      => __( 'Products', 'gcrm' ),
			'cart_value'    => __( 'Cart Value', 'gcrm' ),
			'status'        => __( 'Recovery Status', 'gcrm' ),
			'last_activity' => __( 'Last Activity', 'gcrm' ),
			'actions'       => __( 'Actions', 'gcrm' ),
		);
	}

	protected function column_default( $item, $column_name ): string {
		if ( 'products' === $column_name ) {
			$items = json_decode( $item['cart_contents'] ?? '[]', true );
			$names = array();
			if ( is_array( $items ) ) {
				foreach ( $items as $i ) {
					$names[] = ( $i['name'] ?? '' ) . ' x' . ( $i['quantity'] ?? 1 );
				}
			}
			return esc_html( implode( ', ', $names ) );
		}
		if ( 'cart_value' === $column_name ) {
			return wc_price( (float) ( $item['cart_value'] ?? 0 ), array( 'currency' => $item['currency'] ?? '' ) );
		}
		if ( 'actions' === $column_name ) {
			$id = (int) $item['id'];
			$links  = array();
			if ( ! empty( $item['email'] ) ) {
				$links[] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=gcrm-abandoned-carts&gcrm_cart_action=recovery_email&cart_id=' . $id ), 'gcrm_cart_' . $id ) ) . '">' . esc_html__( 'Send Recovery Email', 'gcrm' ) . '</a>';
			}
			if ( ! empty( $item['phone'] ) ) {
				$links[] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=gcrm-abandoned-carts&gcrm_cart_action=recovery_whatsapp&cart_id=' . $id ), 'gcrm_cart_' . $id ) ) . '">' . esc_html__( 'Send WhatsApp', 'gcrm' ) . '</a>';
			}
			$links[] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=gcrm-abandoned-carts&gcrm_cart_action=delete&cart_id=' . $id ), 'gcrm_cart_' . $id ) ) . '" class="submitdelete">' . esc_html__( 'Delete', 'gcrm' ) . '</a>';
			return implode( ' | ', $links );
		}
		return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
	}

	public function prepare_items(): void {
		$repo   = new CartRepository();
		$result = $repo->query(
			array(
				'search'    => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
				'recovered' => sanitize_text_field( wp_unslash( $_GET['recovered'] ?? '' ) ),
				'date_from' => sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) ),
				'date_to'   => sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) ),
				'page'      => $this->get_pagenum(),
				'per_page'  => 20,
			)
		);
		$this->items = $result['items'];
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => 20,
				'total_pages' => ceil( $result['total'] / 20 ),
			)
		);
	}
}
