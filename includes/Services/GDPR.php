<?php
/**
 * GDPR / CCPA compliance service.
 *
 * @package GCRM\Services
 */

namespace GCRM\Services;

use GCRM\DB\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Class GDPR
 */
class GDPR {

	/**
	 * Register personal data exporter.
	 *
	 * @param array<string, callable> $exporters Exporters.
	 * @return array<string, callable>
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['gcrm-guest-data'] = array(
			'exporter_friendly_name' => __( 'GCRM Guest & Marketing Data', 'gcrm' ),
			'callback'               => array( $this, 'export_personal_data' ),
		);
		return $exporters;
	}

	/**
	 * Register eraser.
	 *
	 * @param array<string, callable> $erasers Erasers.
	 * @return array<string, callable>
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['gcrm-guest-data'] = array(
			'eraser_friendly_name' => __( 'GCRM Guest & Marketing Data', 'gcrm' ),
			'callback'             => array( $this, 'erase_personal_data' ),
		);
		return $erasers;
	}

	/**
	 * Export user data.
	 *
	 * @param string $email_address Email.
	 * @param int    $page Page.
	 * @return array<string, mixed>
	 */
	public function export_personal_data( string $email_address, int $page = 1 ): array {
		global $wpdb;
		$email = sanitize_email( $email_address );
		$data  = array();

		$guest = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::table( 'guest_customers' ) . ' WHERE email = %s', $email ),
			ARRAY_A
		);
		if ( $guest ) {
			$data[] = array(
				'group_id'    => 'gcrm-guest',
				'group_label' => __( 'Guest Customer Profile', 'gcrm' ),
				'item_id'     => 'guest-' . $guest['id'],
				'data'        => array(
					array( 'name' => __( 'Email', 'gcrm' ), 'value' => $guest['email'] ),
					array( 'name' => __( 'Phone', 'gcrm' ), 'value' => $guest['phone'] ),
					array( 'name' => __( 'Total Spend', 'gcrm' ), 'value' => $guest['total_spend'] ),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Erase user data.
	 *
	 * @param string $email_address Email.
	 * @param int    $page Page.
	 * @return array<string, mixed>
	 */
	public function erase_personal_data( string $email_address, int $page = 1 ): array {
		global $wpdb;
		$email = sanitize_email( $email_address );

		$wpdb->delete( Schema::table( 'guest_customers' ), array( 'email' => $email ), array( '%s' ) );
		$wpdb->delete( Schema::table( 'abandoned_carts' ), array( 'email' => $email ), array( '%s' ) );
		$wpdb->delete( Schema::table( 'email_logs' ), array( 'to_email' => $email ), array( '%s' ) );
		$wpdb->delete( Schema::table( 'consent_logs' ), array( 'email' => $email ), array( '%s' ) );

		$this->log_consent( $email, 'data_deleted' );

		return array(
			'items_removed'  => true,
			'items_retained' => false,
			'messages'       => array( __( 'GCRM data erased.', 'gcrm' ) ),
			'done'           => true,
		);
	}

	/**
	 * Log consent action.
	 *
	 * @param string               $email Email.
	 * @param string               $action Action.
	 * @param array<string, mixed> $meta Meta.
	 */
	public function log_consent( string $email, string $action, array $meta = array() ): void {
		global $wpdb;
		$wpdb->insert(
			Schema::table( 'consent_logs' ),
			array(
				'email'      => sanitize_email( $email ),
				'action'     => sanitize_text_field( $action ),
				'ip_address' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_textarea_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'meta'       => wp_json_encode( $meta ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}
}
