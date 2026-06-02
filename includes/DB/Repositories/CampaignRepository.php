<?php
/**
 * Campaigns repository.
 *
 * @package GCRM\DB\Repositories
 */

namespace GCRM\DB\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Class CampaignRepository
 */
class CampaignRepository extends BaseRepository {

	protected function table_key(): string {
		return 'campaigns';
	}

	/**
	 * Create campaign.
	 *
	 * @param array<string, mixed> $data Data.
	 */
	public function create( array $data ): int {
		global $wpdb;
		$wpdb->insert(
			$this->table(),
			array(
				'name'           => sanitize_text_field( (string) ( $data['name'] ?? '' ) ),
				'type'           => sanitize_text_field( (string) ( $data['type'] ?? 'email' ) ),
				'segment_id'     => (int) ( $data['segment_id'] ?? 0 ),
				'subject'        => sanitize_text_field( (string) ( $data['subject'] ?? '' ) ),
				'body'           => wp_kses_post( (string) ( $data['body'] ?? '' ) ),
				'whatsapp_body'  => sanitize_textarea_field( (string) ( $data['whatsapp_body'] ?? '' ) ),
				'schedule_at'    => $data['schedule_at'] ?? null,
				'status'         => sanitize_text_field( (string) ( $data['status'] ?? 'draft' ) ),
				'metrics'        => wp_json_encode( $data['metrics'] ?? array() ),
				'created_by'     => get_current_user_id(),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update metrics.
	 *
	 * @param int                  $id Campaign ID.
	 * @param array<string, mixed> $metrics Metrics.
	 */
	public function update_metrics( int $id, array $metrics ): void {
		global $wpdb;
		$campaign = $this->find( $id );
		$current  = json_decode( $campaign['metrics'] ?? '{}', true );
		if ( ! is_array( $current ) ) {
			$current = array();
		}
		$merged = array_merge( $current, $metrics );
		$wpdb->update(
			$this->table(),
			array( 'metrics' => wp_json_encode( $merged ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * List campaigns.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		global $wpdb;
		return $wpdb->get_results( "SELECT * FROM {$this->table()} ORDER BY created_at DESC", ARRAY_A ) ?: array();
	}
}
