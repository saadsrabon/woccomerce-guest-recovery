<?php
/**
 * Workflow runs repository.
 *
 * @package GCRM\DB\Repositories
 */

namespace GCRM\DB\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Class WorkflowRunRepository
 */
class WorkflowRunRepository extends BaseRepository {

	protected function table_key(): string {
		return 'workflow_runs';
	}

	/**
	 * Create run.
	 *
	 * @param array<string, mixed> $data Data.
	 */
	public function create( array $data ): int {
		global $wpdb;
		$wpdb->insert(
			$this->table(),
			array(
				'workflow_id'  => (int) ( $data['workflow_id'] ?? 0 ),
				'guest_id'     => (int) ( $data['guest_id'] ?? 0 ),
				'order_id'     => (int) ( $data['order_id'] ?? 0 ),
				'current_step' => 0,
				'status'       => 'pending',
				'next_run_at'  => $data['next_run_at'] ?? current_time( 'mysql' ),
				'context'      => wp_json_encode( $data['context'] ?? array() ),
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Whether an active or recent run already exists for this workflow target.
	 *
	 * @param int    $workflow_id Workflow ID.
	 * @param int    $guest_id Guest ID.
	 * @param int    $order_id Order ID.
	 * @param string $trigger_type Trigger type.
	 */
	public function has_existing_run( int $workflow_id, int $guest_id, int $order_id, string $trigger_type ): bool {
		global $wpdb;

		if ( 'guest_order_completed' === $trigger_type && $order_id > 0 ) {
			$sql = $wpdb->prepare(
				"SELECT id FROM {$this->table()} WHERE workflow_id = %d AND order_id = %d AND status IN ('pending','running','completed') LIMIT 1",
				$workflow_id,
				$order_id
			);
			return (bool) $wpdb->get_var( $sql );
		}

		if ( $guest_id > 0 ) {
			$sql = $wpdb->prepare(
				"SELECT id FROM {$this->table()} WHERE workflow_id = %d AND guest_id = %d AND status IN ('pending','running','completed') LIMIT 1",
				$workflow_id,
				$guest_id
			);
			return (bool) $wpdb->get_var( $sql );
		}

		return false;
	}

	/**
	 * Get due runs.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_due(): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE status IN ('pending','running') AND next_run_at <= %s LIMIT 50",
				current_time( 'mysql' )
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Persist run context JSON.
	 *
	 * @param int                  $id Run ID.
	 * @param array<string, mixed> $context Context.
	 */
	public function update_context( int $id, array $context ): void {
		global $wpdb;
		$wpdb->update(
			$this->table(),
			array( 'context' => wp_json_encode( $context ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Advance run.
	 *
	 * @param int    $id Run ID.
	 * @param int    $step Next step.
	 * @param string $next_run_at Next run time.
	 * @param string $status Status.
	 */
	public function advance( int $id, int $step, string $next_run_at, string $status = 'pending' ): void {
		global $wpdb;
		$wpdb->update(
			$this->table(),
			array(
				'current_step' => $step,
				'next_run_at'  => $next_run_at,
				'status'       => $status,
			),
			array( 'id' => $id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);
	}
}
