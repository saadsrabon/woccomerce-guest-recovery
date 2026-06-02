<?php
/**
 * Email marketing service.
 *
 * @package GCRM\Services
 */

namespace GCRM\Services;

use GCRM\Core\BackgroundQueue;
use GCRM\DB\Repositories\EmailLogRepository;
use GCRM\DB\Repositories\GuestRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class Email
 */
class Email {

	/**
	 * Supported placeholders.
	 *
	 * @var array<int, string>
	 */
	public const PLACEHOLDERS = array(
		'{first_name}',
		'{last_name}',
		'{email}',
		'{phone}',
		'{order_id}',
		'{store_name}',
		'{coupon_code}',
		'{recovery_link}',
	);

	/**
	 * Send bulk to guest IDs.
	 *
	 * @param array<int>           $guest_ids IDs.
	 * @param string               $subject Subject.
	 * @param string               $body Body HTML.
	 * @param int                  $campaign_id Campaign ID.
	 * @param string|null          $schedule_at Schedule time.
	 */
	public function queue_bulk( array $guest_ids, string $subject, string $body, int $campaign_id = 0, ?string $schedule_at = null ): int {
		$repo  = new GuestRepository();
		$count = 0;
		foreach ( $guest_ids as $id ) {
			$guest = $repo->find( (int) $id );
			if ( ! $guest || empty( $guest['email'] ) ) {
				continue;
			}
			$log_id = ( new EmailLogRepository() )->create(
				array(
					'campaign_id' => $campaign_id,
					'guest_id'    => (int) $id,
					'to_email'    => $guest['email'],
					'subject'     => $subject,
					'body'        => $body,
					'status'      => 'queued',
				)
			);
			BackgroundQueue::enqueue(
				'send_email',
				array(
					'to'            => $guest['email'],
					'subject'       => $subject,
					'body'          => $body,
					'placeholders'  => $this->placeholders_from_guest( $guest ),
					'log_id'        => $log_id,
				),
				$schedule_at
			);
			++$count;
		}
		return $count;
	}

	/**
	 * Send single email.
	 *
	 * @param string               $to To email.
	 * @param string               $subject Subject.
	 * @param string               $body Body.
	 * @param array<string, string> $placeholders Placeholders.
	 * @param int                  $log_id Log ID.
	 */
	public function send_single( string $to, string $subject, string $body, array $placeholders = array(), int $log_id = 0 ): bool {
		$subject = $this->replace_placeholders( $subject, $placeholders );
		$body    = $this->replace_placeholders( $body, $placeholders );
		$body    = $this->append_tracking_pixel( $body, $log_id );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$from_name = get_option( 'gcrm_email_from_name', get_bloginfo( 'name' ) );
		$from_email = get_option( 'gcrm_email_from_address', get_option( 'admin_email' ) );
		$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';

		$sent = wp_mail( $to, $subject, $body, $headers );
		if ( $log_id ) {
			( new EmailLogRepository() )->update_status( $log_id, $sent ? 'sent' : 'failed', $sent ? '' : 'wp_mail failed' );
		}
		return $sent;
	}

	/**
	 * Preview rendered email.
	 *
	 * @param string               $body Body.
	 * @param array<string, string> $placeholders Placeholders.
	 */
	public function preview( string $body, array $placeholders ): string {
		return $this->replace_placeholders( $body, $placeholders );
	}

	/**
	 * Send test email to current admin.
	 *
	 * @param string $subject Subject.
	 * @param string $body Body.
	 */
	public function send_test( string $subject, string $body ): bool {
		$user = wp_get_current_user();
		return $this->send_single(
			$user->user_email,
			'[TEST] ' . $subject,
			$body,
			array(
				'{first_name}' => $user->first_name ?: 'Admin',
				'{last_name}'  => $user->last_name ?: '',
				'{email}'      => $user->user_email,
				'{store_name}' => get_bloginfo( 'name' ),
			)
		);
	}

	/**
	 * Replace placeholders.
	 *
	 * @param string               $text Text.
	 * @param array<string, string> $placeholders Map.
	 */
	public function replace_placeholders( string $text, array $placeholders ): string {
		return str_replace( array_keys( $placeholders ), array_values( $placeholders ), $text );
	}

	/**
	 * Build placeholders from guest row.
	 *
	 * @param array<string, mixed> $guest Guest.
	 * @return array<string, string>
	 */
	public function placeholders_from_guest( array $guest, array $extra = array() ): array {
		return array_merge(
			array(
				'{first_name}' => (string) ( $guest['first_name'] ?? '' ),
				'{last_name}'  => (string) ( $guest['last_name'] ?? '' ),
				'{email}'      => (string) ( $guest['email'] ?? '' ),
				'{phone}'      => (string) ( $guest['phone'] ?? '' ),
				'{order_id}'   => (string) ( $guest['last_order_id'] ?? '' ),
				'{store_name}' => get_bloginfo( 'name' ),
			),
			$extra
		);
	}

	/**
	 * Append open tracking pixel.
	 *
	 * @param string $body HTML body.
	 * @param int    $log_id Log ID.
	 */
	private function append_tracking_pixel( string $body, int $log_id ): string {
		if ( ! $log_id ) {
			return $body;
		}
		$log = ( new EmailLogRepository() )->find( $log_id );
		if ( ! $log || empty( $log['track_token'] ) ) {
			return $body;
		}
		$url = rest_url( 'gcrm/v1/track/open/' . $log['track_token'] );
		$body .= '<img src="' . esc_url( $url ) . '" width="1" height="1" alt="" style="display:none;" />';
		return $body;
	}
}
