<?php
/**
 * Email logs repository.
 *
 * @package GCRM\DB\Repositories
 */

namespace GCRM\DB\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Class EmailLogRepository
 */
class EmailLogRepository extends BaseRepository {

	protected function table_key(): string {
		return 'email_logs';
	}

	/**
	 * Create log entry.
	 *
	 * @param array<string, mixed> $data Data.
	 */
	public function create( array $data ): int {
		global $wpdb;
		$token = wp_generate_password( 32, false );
		$wpdb->insert(
			$this->table(),
			array(
				'campaign_id'  => (int) ( $data['campaign_id'] ?? 0 ),
				'guest_id'     => (int) ( $data['guest_id'] ?? 0 ),
				'cart_id'      => (int) ( $data['cart_id'] ?? 0 ),
				'to_email'     => sanitize_email( (string) ( $data['to_email'] ?? '' ) ),
				'subject'      => sanitize_text_field( (string) ( $data['subject'] ?? '' ) ),
				'body'         => wp_kses_post( (string) ( $data['body'] ?? '' ) ),
				'status'       => sanitize_text_field( (string) ( $data['status'] ?? 'queued' ) ),
				'track_token'  => $token,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update status.
	 *
	 * @param int    $id Log ID.
	 * @param string $status Status.
	 * @param string $error Optional error.
	 */
	public function update_status( int $id, string $status, string $error = '' ): void {
		global $wpdb;
		$data = array( 'status' => $status );
		$fmt  = array( '%s' );
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

	/**
	 * Find by track token.
	 *
	 * @param string $token Token.
	 */
	public function find_by_token( string $token ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE track_token = %s', $token ),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Analytics counts.
	 *
	 * @return array<string, int>
	 */
	public function get_stats(): array {
		global $wpdb;
		$table = $this->table();
		return array(
			'sent'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'sent'" ),
			'opened'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE opened_at IS NOT NULL" ),
			'clicked' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE clicked_at IS NOT NULL" ),
			'failed'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'failed'" ),
		);
	}
}
