<?php
/**
 * WhatsApp messaging service.
 *
 * @package GCRM\Services
 */

namespace GCRM\Services;

use GCRM\Core\BackgroundQueue;
use GCRM\DB\Repositories\GuestRepository;
use GCRM\DB\Repositories\WhatsAppLogRepository;
use GCRM\Integrations\TwilioWhatsApp;
use GCRM\Integrations\WhatsAppCloud;

defined( 'ABSPATH' ) || exit;

/**
 * Class WhatsApp
 */
class WhatsApp {

	/**
	 * Queue bulk WhatsApp messages.
	 *
	 * @param array<int>  $guest_ids Guest IDs.
	 * @param string      $message Message template.
	 * @param int         $campaign_id Campaign ID.
	 * @param string|null $schedule_at Schedule.
	 */
	public function queue_bulk( array $guest_ids, string $message, int $campaign_id = 0, ?string $schedule_at = null ): int {
		$repo  = new GuestRepository();
		$count = 0;
		foreach ( $guest_ids as $id ) {
			$guest = $repo->find( (int) $id );
			if ( ! $guest || empty( $guest['phone'] ) ) {
				continue;
			}
			$log_id = ( new WhatsAppLogRepository() )->create(
				array(
					'campaign_id' => $campaign_id,
					'guest_id'    => (int) $id,
					'to_phone'    => $guest['phone'],
					'message'     => $message,
					'status'      => 'queued',
					'provider'    => get_option( 'gcrm_whatsapp_provider', 'cloud' ),
				)
			);
			BackgroundQueue::enqueue(
				'send_whatsapp',
				array(
					'phone'         => $guest['phone'],
					'message'       => $message,
					'placeholders'  => ( new Email() )->placeholders_from_guest( $guest ),
					'log_id'        => $log_id,
				),
				$schedule_at
			);
			++$count;
		}
		return $count;
	}

	/**
	 * Send single WhatsApp with rate limiting.
	 *
	 * @param string               $phone Phone.
	 * @param string               $message Message.
	 * @param array<string, string> $placeholders Placeholders.
	 * @param int                  $log_id Log ID.
	 */
	public function send_single( string $phone, string $message, array $placeholders = array(), int $log_id = 0 ): bool {
		if ( ! $this->check_rate_limit() ) {
			if ( $log_id ) {
				( new WhatsAppLogRepository() )->update_status( $log_id, 'failed', '', 'Rate limit exceeded' );
			}
			return false;
		}

		$message = ( new Email() )->replace_placeholders( $message, $placeholders );
		$phone   = preg_replace( '/[^0-9+]/', '', $phone );

		$provider = get_option( 'gcrm_whatsapp_provider', 'cloud' );
		$result   = 'twilio' === $provider
			? ( new TwilioWhatsApp() )->send( $phone, $message )
			: ( new WhatsAppCloud() )->send( $phone, $message );

		if ( $log_id ) {
			$repo = new WhatsAppLogRepository();
			if ( is_wp_error( $result ) ) {
				$repo->update_status( $log_id, 'failed', '', $result->get_error_message() );
				return false;
			}
			$repo->update_status( $log_id, 'sent', is_string( $result ) ? $result : '' );
		}

		$this->increment_rate_counter();
		return ! is_wp_error( $result );
	}

	/**
	 * Rate limit check (per hour).
	 */
	private function check_rate_limit(): bool {
		$limit   = (int) get_option( 'gcrm_whatsapp_rate_limit', 30 );
		$key     = 'gcrm_wa_count_' . gmdate( 'YmdH' );
		$current = (int) get_transient( $key );
		return $current < $limit;
	}

	/**
	 * Increment rate counter.
	 */
	private function increment_rate_counter(): void {
		$key = 'gcrm_wa_count_' . gmdate( 'YmdH' );
		$current = (int) get_transient( $key );
		set_transient( $key, $current + 1, HOUR_IN_SECONDS );
	}
}
