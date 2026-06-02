<?php
/**
 * Base repository.
 *
 * @package GCRM\DB\Repositories
 */

namespace GCRM\DB\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Class BaseRepository
 */
abstract class BaseRepository {

	/**
	 * Table key for Schema::table().
	 */
	abstract protected function table_key(): string;

	/**
	 * Get table name.
	 */
	protected function table(): string {
		return \GCRM\DB\Schema::table( $this->table_key() );
	}

	/**
	 * Get row by ID.
	 *
	 * @param int $id Row ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE id = %d', $id ),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Delete by ID.
	 *
	 * @param int $id Row ID.
	 */
	public function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );
	}
}
