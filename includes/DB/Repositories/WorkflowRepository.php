<?php
/**
 * Workflows repository.
 *
 * @package GCRM\DB\Repositories
 */

namespace GCRM\DB\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Class WorkflowRepository
 */
class WorkflowRepository extends BaseRepository {

	protected function table_key(): string {
		return 'workflows';
	}

	/**
	 * Get active workflows by trigger.
	 *
	 * @param string $trigger_type Trigger type.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_by_trigger( string $trigger_type ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE trigger_type = %s AND status = 'active'",
				$trigger_type
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * All workflows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		global $wpdb;
		return $wpdb->get_results( "SELECT * FROM {$this->table()} ORDER BY name ASC", ARRAY_A ) ?: array();
	}
}
