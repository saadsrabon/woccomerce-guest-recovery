<?php
/**
 * Queue repository.
 *
 * @package GCRM\DB\Repositories
 */

namespace GCRM\DB\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Class QueueRepository
 */
class QueueRepository extends BaseRepository {

	protected function table_key(): string {
		return 'queue';
	}

	/**
	 * Insert job.
	 *
	 * @param array<string, mixed> $data Data.
	 */
	public function insert( array $data ): int {
		global $wpdb;
		$wpdb->insert(
			$this->table(),
			array(
				'job_type'     => sanitize_text_field( (string) ( $data['job_type'] ?? '' ) ),
				'payload'      => (string) ( $data['payload'] ?? '{}' ),
				'status'       => sanitize_text_field( (string) ( $data['status'] ?? 'pending' ) ),
				'scheduled_at' => $data['scheduled_at'] ?? current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Get pending jobs.
	 *
	 * @param int $limit Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_pending( int $limit ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE status = 'pending' AND scheduled_at <= %s ORDER BY scheduled_at ASC LIMIT %d",
				current_time( 'mysql' ),
				$limit
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Mark processing.
	 *
	 * @param int $id Job ID.
	 */
	public function mark_processing( int $id ): void {
		global $wpdb;
		$wpdb->update( $this->table(), array( 'status' => 'processing' ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
	}

	/**
	 * Mark completed.
	 *
	 * @param int $id Job ID.
	 */
	public function mark_completed( int $id ): void {
		global $wpdb;
		$wpdb->update(
			$this->table(),
			array( 'status' => 'completed', 'processed_at' => current_time( 'mysql' ) ),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Mark failed.
	 *
	 * @param int    $id Job ID.
	 * @param string $error Error message.
	 */
	public function mark_failed( int $id, string $error ): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table()} SET status = 'failed', attempts = attempts + 1, error_message = %s WHERE id = %d",
				$error,
				$id
			)
		);
	}
}
