<?php
/**
 * Admin menu registration.
 *
 * @package GCRM\Admin
 */

namespace GCRM\Admin;

use GCRM\Admin\Pages\AbandonedCartsPage;
use GCRM\Admin\Pages\CampaignsPage;
use GCRM\Admin\Pages\GuestCustomersPage;
use GCRM\Admin\Pages\InsightsPage;
use GCRM\Admin\Pages\SegmentsPage;
use GCRM\Admin\Pages\SettingsPage;
use GCRM\Admin\Pages\WorkflowsPage;
use GCRM\Admin\Pages\CustomerProfilePage;
use GCRM\Services\Convert;
use GCRM\Services\Email;
use GCRM\Services\Export;
use GCRM\Services\RecoveryAutomation;
use GCRM\Services\WhatsApp;

defined( 'ABSPATH' ) || exit;

/**
 * Class Menu
 */
class Menu {

	/**
	 * Register WooCommerce submenus.
	 */
	public function register_menus(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Guest Customers', 'gcrm' ),
			__( 'Guest Customers', 'gcrm' ),
			'manage_woocommerce',
			'gcrm-guest-customers',
			array( new GuestCustomersPage(), 'render' )
		);
		add_submenu_page(
			'woocommerce',
			__( 'Abandoned Carts', 'gcrm' ),
			__( 'Abandoned Carts', 'gcrm' ),
			'manage_woocommerce',
			'gcrm-abandoned-carts',
			array( new AbandonedCartsPage(), 'render' )
		);
		add_submenu_page(
			'woocommerce',
			__( 'Campaigns', 'gcrm' ),
			__( 'Campaigns', 'gcrm' ),
			'manage_woocommerce',
			'gcrm-campaigns',
			array( new CampaignsPage(), 'render' )
		);
		add_submenu_page(
			'woocommerce',
			__( 'Segments', 'gcrm' ),
			__( 'Segments', 'gcrm' ),
			'manage_woocommerce',
			'gcrm-segments',
			array( new SegmentsPage(), 'render' )
		);
		add_submenu_page(
			'woocommerce',
			__( 'Workflows', 'gcrm' ),
			__( 'Workflows', 'gcrm' ),
			'manage_woocommerce',
			'gcrm-workflows',
			array( new WorkflowsPage(), 'render' )
		);
		add_submenu_page(
			'woocommerce',
			__( 'Insights', 'gcrm' ),
			__( 'Insights', 'gcrm' ),
			'manage_woocommerce',
			'gcrm-insights',
			array( new InsightsPage(), 'render' )
		);
		add_submenu_page(
			'woocommerce',
			__( 'GCRM Settings', 'gcrm' ),
			__( 'GCRM Settings', 'gcrm' ),
			'manage_woocommerce',
			'gcrm-settings',
			array( new SettingsPage(), 'render' )
		);
		// Hidden profile page.
		add_submenu_page(
			null,
			__( 'Customer Profile', 'gcrm' ),
			__( 'Customer Profile', 'gcrm' ),
			'manage_woocommerce',
			'gcrm-customer-profile',
			array( new CustomerProfilePage(), 'render' )
		);
	}

	/**
	 * Handle export downloads.
	 */
	public function handle_exports(): void {
		if ( ! isset( $_GET['gcrm_export'], $_GET['_wpnonce'] ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'gcrm_export' ) ) {
			return;
		}

		$filters = $this->get_filters_from_request();
		$export  = new Export();
		$type    = sanitize_text_field( wp_unslash( $_GET['gcrm_export'] ) );

		if ( 'csv' === $type ) {
			$export->download_csv( $filters );
		}
		if ( 'xlsx' === $type ) {
			$export->download_xlsx( $filters );
		}
	}

	/**
	 * Handle bulk admin actions.
	 */
	public function handle_bulk_actions(): void {
		if ( ! isset( $_POST['gcrm_action'], $_POST['_wpnonce'] ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'gcrm_bulk' ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['gcrm_action'] ) );
		$ids    = array_map( 'absint', (array) ( $_POST['guest_ids'] ?? array() ) );

		switch ( $action ) {
			case 'convert_users':
				$result = ( new Convert() )->convert_guests( $ids );
				add_settings_error( 'gcrm', 'gcrm_convert', sprintf( __( 'Converted %d guests.', 'gcrm' ), $result['success'] ), 'success' );
				break;
			case 'send_email':
				$subject = sanitize_text_field( wp_unslash( $_POST['email_subject'] ?? '' ) );
				$body    = wp_kses_post( wp_unslash( $_POST['email_body'] ?? '' ) );
				$count   = ( new Email() )->queue_bulk( $ids, $subject, $body );
				add_settings_error( 'gcrm', 'gcrm_email', sprintf( __( 'Queued %d emails.', 'gcrm' ), $count ), 'success' );
				break;
			case 'send_whatsapp':
				$message = sanitize_textarea_field( wp_unslash( $_POST['whatsapp_message'] ?? '' ) );
				$count   = ( new WhatsApp() )->queue_bulk( $ids, $message );
				add_settings_error( 'gcrm', 'gcrm_wa', sprintf( __( 'Queued %d WhatsApp messages.', 'gcrm' ), $count ), 'success' );
				break;
		}
	}

	/**
	 * Build filters from GET.
	 *
	 * @return array<string, mixed>
	 */
	private function get_filters_from_request(): array {
		return array(
			'search'     => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
			'date_from'  => sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) ),
			'date_to'    => sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) ),
			'status'     => sanitize_text_field( wp_unslash( $_GET['order_status'] ?? '' ) ),
			'country'    => sanitize_text_field( wp_unslash( $_GET['country'] ?? '' ) ),
			'product_id' => absint( $_GET['product_id'] ?? 0 ),
		);
	}
}
