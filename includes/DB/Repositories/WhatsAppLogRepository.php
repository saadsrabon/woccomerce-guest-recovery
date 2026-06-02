<?php
/**
 * WhatsApp logs repository.
 *
 * @package GCRM\DB\Repositories
 */

namespace GCRM\DB\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Class WhatsAppLogRepository
 */
class WhatsAppLogRepository extends BaseRepository {

	protected function table_key(): string {
		return 'whatsapp_logs';
	}

	/**
	 * Create log.
	 *
	 * @param array<string, mixed> $data Data.
	 */
	public function create( array $data ): int {
		global $wpdb;
		$wpdb->insert(
			$this->table(),
			array(
				'campaign_id' => (int) ( $data['campaign_id'] ?? 0 ),
				'guest_id'    => (int) ( $data['guest_id'] ?? 0 ),
				'cart_id'     => (int) ( $data['cart_id'] ?? 0 ),
				'to_phone'    => sanitize_text_field( (string) ( $data['to_phone'] ?? '' ) ),
				'message'     => sanitize_textarea_field( (string) ( $data['message'] ?? '' ) ),
				'status'      => sanitize_text_field( (string) ( $data['status'] ?? 'queued' ) ),
				'provider'    => sanitize_text_field( (string) ( $data['provider'] ?? '' ) ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update status.
	 *
	 * @param int    $id Log ID.
	 * @param string $status Status.
	 * @param string $provider_id Provider message ID.
	 * @param string $error Error.
	 */
	public function update_status( int $id, string $status, string $provider_id = '', string $error = '' ): void {
		global $wpdb;
		$data = array( 'status' => $status );
		$fmt  = array( '%s' );
		if ( $provider_id ) {
			$data['provider_msg_id'] = $provider_id;
			$fmt[]                   = '%s';
		}
		if ( 'sent' === $status ) {
			$data['sent_at'] = current_time( 'mysql' );
			$fmt[]           = '%s';
		}
		if ( $error ) {
			$data['error_message'] = $error;
			$fmt[]                 = '%s';
		}
		$wpdb->update( $this->table(), $data, array( 'id' => $id ), $fmt, array( '%d' ) );
	}
}
