<?php
/**
 * Campaign manager service.
 *
 * @package GCRM\Services
 */

namespace GCRM\Services;

use GCRM\DB\Repositories\CampaignRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class Campaigns
 */
class Campaigns {

	/**
	 * Launch campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return int|\WP_Error Queued count or error.
	 */
	public function launch( int $campaign_id ) {
		$repo     = new CampaignRepository();
		$campaign = $repo->find( $campaign_id );
		if ( ! $campaign ) {
			return new \WP_Error( 'gcrm_campaign', __( 'Campaign not found.', 'gcrm' ) );
		}

		$segment_ids = ( new Segments() )->get_audience_ids( (int) $campaign['segment_id'] );
		if ( empty( $segment_ids ) ) {
			return new \WP_Error( 'gcrm_audience', __( 'No audience matched segment.', 'gcrm' ) );
		}

		$type  = $campaign['type'] ?? 'email';
		$count = 0;

		if ( in_array( $type, array( 'email', 'mixed' ), true ) ) {
			$count += ( new Email() )->queue_bulk(
				$segment_ids,
				(string) $campaign['subject'],
				(string) $campaign['body'],
				$campaign_id,
				$campaign['schedule_at'] ?? null
			);
		}
		if ( in_array( $type, array( 'whatsapp', 'mixed' ), true ) ) {
			$count += ( new WhatsApp() )->queue_bulk(
				$segment_ids,
				(string) ( $campaign['whatsapp_body'] ?? $campaign['body'] ),
				$campaign_id,
				$campaign['schedule_at'] ?? null
			);
		}

		global $wpdb;
		$wpdb->update(
			\GCRM\DB\Schema::table( 'campaigns' ),
			array( 'status' => 'running' ),
			array( 'id' => $campaign_id ),
			array( '%s' ),
			array( '%d' )
		);

		$repo->update_metrics( $campaign_id, array( 'sent' => $count ) );
		return $count;
	}
}
