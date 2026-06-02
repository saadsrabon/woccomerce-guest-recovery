<?php
/**
 * Segments repository.
 *
 * @package GCRM\DB\Repositories
 */

namespace GCRM\DB\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Class SegmentRepository
 */
class SegmentRepository extends BaseRepository {

	protected function table_key(): string {
		return 'segments';
	}

	/**
	 * Save segment.
	 *
	 * @param array<string, mixed> $data Data.
	 */
	public function save( array $data ): int {
		global $wpdb;
		$id = (int) ( $data['id'] ?? 0 );
		$row = array(
			'name'        => sanitize_text_field( (string) ( $data['name'] ?? '' ) ),
			'slug'        => sanitize_title( (string) ( $data['slug'] ?? ( $data['name'] ?? '' ) ) ),
			'description' => sanitize_textarea_field( (string) ( $data['description'] ?? '' ) ),
			'rules'       => is_string( $data['rules'] ?? '' ) ? $data['rules'] : wp_json_encode( $data['rules'] ?? array() ),
		);

		if ( $id ) {
			$wpdb->update( $this->table(), $row, array( 'id' => $id ) );
			return $id;
		}
		$wpdb->insert( $this->table(), $row );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Duplicate segment.
	 *
	 * @param int $id Segment ID.
	 */
	public function duplicate( int $id ): int {
		$seg = $this->find( $id );
		if ( ! $seg ) {
			return 0;
		}
		unset( $seg['id'] );
		$seg['name'] = $seg['name'] . ' (Copy)';
		$seg['slug'] = $seg['slug'] . '-copy-' . time();
		$seg['is_prebuilt'] = 0;
		return $this->save( $seg );
	}

	/**
	 * All segments.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		global $wpdb;
		return $wpdb->get_results( "SELECT * FROM {$this->table()} ORDER BY name ASC", ARRAY_A ) ?: array();
	}
}
