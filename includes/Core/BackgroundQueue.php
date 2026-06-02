<?php
/**
 * Background batch queue processor.
 *
 * @package GCRM\Core
 */

namespace GCRM\Core;

use GCRM\DB\Repositories\QueueRepository;
use GCRM\Services\Email;
use GCRM\Services\WhatsApp;

defined( 'ABSPATH' ) || exit;

/**
 * Class BackgroundQueue
 */
class BackgroundQueue {

	/**
	 * Process queued jobs.
	 */
	public static function process_batch(): void {
		$repo  = new QueueRepository();
		$limit = (int) get_option( 'gcrm_batch_size', 25 );
		$jobs  = $repo->get_pending( $limit );

		foreach ( $jobs as $job ) {
			$repo->mark_processing( (int) $job['id'] );
			$payload = json_decode( $job['payload'] ?? '{}', true );
			if ( ! is_array( $payload ) ) {
				$repo->mark_failed( (int) $job['id'], 'Invalid payload' );
				continue;
			}

			try {
				self::dispatch( $job['job_type'], $payload );
				$repo->mark_completed( (int) $job['id'] );
			} catch ( \Throwable $e ) {
				$repo->mark_failed( (int) $job['id'], $e->getMessage() );
				Logger::log( $e->getMessage(), 'error' );
			}
		}
	}

	/**
	 * Dispatch job by type.
	 *
	 * @param string               $type Job type.
	 * @param array<string, mixed> $payload Payload.
	 */
	private static function dispatch( string $type, array $payload ): void {
		switch ( $type ) {
			case 'send_email':
				$email = new Email();
				$email->send_single(
					(string) ( $payload['to'] ?? '' ),
					(string) ( $payload['subject'] ?? '' ),
					(string) ( $payload['body'] ?? '' ),
					(array) ( $payload['placeholders'] ?? array() ),
					(int) ( $payload['log_id'] ?? 0 )
				);
				break;
			case 'send_whatsapp':
				$wa = new WhatsApp();
				$wa->send_single(
					(string) ( $payload['phone'] ?? '' ),
					(string) ( $payload['message'] ?? '' ),
					(array) ( $payload['placeholders'] ?? array() ),
					(int) ( $payload['log_id'] ?? 0 )
				);
				break;
			default:
				throw new \InvalidArgumentException( 'Unknown job type: ' . $type );
		}
	}

	/**
	 * Enqueue a job.
	 *
	 * @param string               $type Job type.
	 * @param array<string, mixed> $payload Payload.
	 * @param string|null          $scheduled_at MySQL datetime.
	 * @return int Job ID.
	 */
	public static function enqueue( string $type, array $payload, ?string $scheduled_at = null ): int {
		$repo = new QueueRepository();
		return $repo->insert(
			array(
				'job_type'     => $type,
				'payload'      => wp_json_encode( $payload ),
				'status'       => 'pending',
				'scheduled_at' => $scheduled_at ?? current_time( 'mysql' ),
			)
		);
	}
}
