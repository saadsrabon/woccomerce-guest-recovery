<?php
/**
 * WP-Cron scheduling.
 *
 * @package GCRM\Core
 */

namespace GCRM\Core;

use GCRM\Services\Carts;

defined( 'ABSPATH' ) || exit;

/**
 * Class Cron
 */
class Cron {

	public const HOOK_PROCESS_QUEUE    = 'gcrm_process_queue';
	public const HOOK_ABANDONED_CARTS  = 'gcrm_mark_abandoned_carts';
	public const HOOK_RECOVERY_EMAILS  = 'gcrm_process_recovery';
	public const HOOK_SYNC_GUESTS      = 'gcrm_sync_guest_customers';
	public const HOOK_WORKFLOWS        = 'gcrm_process_workflows';

	/**
	 * Schedule cron events.
	 */
	public static function schedule_events(): void {
		if ( ! wp_next_scheduled( self::HOOK_PROCESS_QUEUE ) ) {
			wp_schedule_event( time(), 'every_five_minutes', self::HOOK_PROCESS_QUEUE );
		}
		if ( ! wp_next_scheduled( self::HOOK_ABANDONED_CARTS ) ) {
			wp_schedule_event( time(), 'every_five_minutes', self::HOOK_ABANDONED_CARTS );
		}
		if ( ! wp_next_scheduled( self::HOOK_RECOVERY_EMAILS ) ) {
			wp_schedule_event( time(), 'hourly', self::HOOK_RECOVERY_EMAILS );
		}
		if ( ! wp_next_scheduled( self::HOOK_SYNC_GUESTS ) ) {
			wp_schedule_event( time(), 'hourly', self::HOOK_SYNC_GUESTS );
		}
		if ( ! wp_next_scheduled( self::HOOK_WORKFLOWS ) ) {
			wp_schedule_event( time(), 'every_five_minutes', self::HOOK_WORKFLOWS );
		}
	}

	/**
	 * Clear scheduled events.
	 */
	public static function clear_scheduled_events(): void {
		foreach (
			array(
				self::HOOK_PROCESS_QUEUE,
				self::HOOK_ABANDONED_CARTS,
				self::HOOK_RECOVERY_EMAILS,
				self::HOOK_SYNC_GUESTS,
				self::HOOK_WORKFLOWS,
			) as $hook
		) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * Register custom cron schedules.
	 *
	 * @param array<string, array<string, int|string>> $schedules Schedules.
	 * @return array<string, array<string, int|string>>
	 */
	public static function add_schedules( array $schedules ): array {
		$schedules['every_five_minutes'] = array(
			'interval' => 300,
			'display'  => __( 'Every 5 Minutes', 'gcrm' ),
		);
		return $schedules;
	}

	/**
	 * Mark inactive carts as abandoned.
	 */
	public function mark_abandoned_carts(): void {
		$carts = new Carts();
		$carts->mark_abandoned_by_timeout();
	}

	/**
	 * Full guest customer sync.
	 */
	public function sync_guest_customers(): void {
		$sync = new \GCRM\Services\OrdersSync();
		$sync->sync_all_guests();
	}
}
