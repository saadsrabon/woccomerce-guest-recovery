<?php
/**
 * Marketing workflow automation runner.
 *
 * @package GCRM\Services
 */

namespace GCRM\Services;

use GCRM\DB\Repositories\GuestRepository;
use GCRM\DB\Repositories\WorkflowRepository;
use GCRM\DB\Repositories\WorkflowRunRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class WorkflowRunner
 */
class WorkflowRunner {

	/**
	 * Trigger workflows.
	 *
	 * @param string               $trigger_type Trigger.
	 * @param array<string, mixed> $context Context.
	 */
	public function trigger( string $trigger_type, array $context = array() ): void {
		$workflows = ( new WorkflowRepository() )->get_by_trigger( $trigger_type );
		$guest_id  = 0;

		if ( ! empty( $context['email'] ) ) {
			$guest = ( new GuestRepository() )->find_by_email( (string) $context['email'] );
			$guest_id = $guest ? (int) $guest['id'] : 0;
		}

		foreach ( $workflows as $workflow ) {
			if ( ! $this->matches_trigger_config( $workflow, $context ) ) {
				continue;
			}
			( new WorkflowRunRepository() )->create(
				array(
					'workflow_id' => (int) $workflow['id'],
					'guest_id'    => $guest_id,
					'order_id'    => (int) ( $context['order_id'] ?? 0 ),
					'context'     => $context,
				)
			);
		}
	}

	/**
	 * Process due workflow runs.
	 */
	public function process_due_runs(): void {
		$runs = ( new WorkflowRunRepository() )->get_due();
		foreach ( $runs as $run ) {
			$this->execute_run( $run );
		}
	}

	/**
	 * Execute workflow run step.
	 *
	 * @param array<string, mixed> $run Run row.
	 */
	private function execute_run( array $run ): void {
		$workflow_repo = new WorkflowRepository();
		$workflow      = $workflow_repo->find( (int) $run['workflow_id'] );
		if ( ! $workflow ) {
			return;
		}

		$steps = json_decode( $workflow['steps'] ?? '[]', true );
		if ( ! is_array( $steps ) ) {
			return;
		}

		$step_index = (int) $run['current_step'];
		$step       = $steps[ $step_index ] ?? null;
		if ( ! $step ) {
			( new WorkflowRunRepository() )->advance( (int) $run['id'], $step_index, current_time( 'mysql' ), 'completed' );
			return;
		}

		$context  = json_decode( $run['context'] ?? '{}', true ) ?: array();
		$guest_id = (int) $run['guest_id'];
		$guest    = $guest_id ? ( new GuestRepository() )->find( $guest_id ) : null;

		$this->execute_step( $step, $guest, $context );

		$next_index = $step_index + 1;
		if ( $next_index >= count( $steps ) ) {
			( new WorkflowRunRepository() )->advance( (int) $run['id'], $next_index, current_time( 'mysql' ), 'completed' );
			return;
		}

		$delay_hours = (int) ( $steps[ $next_index ]['delay_hours'] ?? 0 );
		$next_run    = gmdate( 'Y-m-d H:i:s', time() + ( $delay_hours * HOUR_IN_SECONDS ) );
		( new WorkflowRunRepository() )->advance( (int) $run['id'], $next_index, $next_run, 'pending' );
	}

	/**
	 * Execute single step.
	 *
	 * @param array<string, mixed>      $step Step config.
	 * @param array<string, mixed>|null $guest Guest.
	 * @param array<string, mixed>      $context Context.
	 */
	private function execute_step( array $step, ?array $guest, array $context ): void {
		$type = $step['type'] ?? '';
		switch ( $type ) {
			case 'send_email':
				if ( $guest && ! empty( $guest['email'] ) ) {
					$body = $this->template_body( (string) ( $step['template'] ?? 'default' ), $guest, $context );
					( new Email() )->send_single(
						$guest['email'],
						__( 'Message from ', 'gcrm' ) . get_bloginfo( 'name' ),
						$body,
						( new Email() )->placeholders_from_guest( $guest )
					);
				}
				break;
			case 'send_whatsapp':
				if ( $guest && ! empty( $guest['phone'] ) ) {
					( new WhatsApp() )->send_single(
						$guest['phone'],
						$this->template_body( (string) ( $step['template'] ?? 'default' ), $guest, $context ),
						( new Email() )->placeholders_from_guest( $guest )
					);
				}
				break;
			case 'generate_coupon':
				$code = ( new Coupons() )->generate( array( 'percent' => (float) ( $step['percent'] ?? 10 ) ) );
				$context['coupon_code'] = $code;
				break;
		}
	}

	/**
	 * Simple template bodies.
	 *
	 * @param string               $template Template key.
	 * @param array<string, mixed> $guest Guest.
	 * @param array<string, mixed> $context Context.
	 */
	private function template_body( string $template, array $guest, array $context ): string {
		$coupon = $context['coupon_code'] ?? '';
		switch ( $template ) {
			case 'account_invitation':
				return '<p>' . sprintf( __( 'Hi %s, create your account for faster checkout!', 'gcrm' ), esc_html( $guest['first_name'] ?? '' ) ) . '</p>';
			case 'vip_thank_you':
				return '<p>' . __( 'Thank you for being a VIP customer!', 'gcrm' ) . '</p>';
			case 'reengagement':
				return '<p>' . __( 'We miss you! Here is a special offer to come back.', 'gcrm' ) . '</p>';
			case 'discount_offer':
				return '<p>' . sprintf( __( 'Use coupon %s on your next order.', 'gcrm' ), esc_html( $coupon ) ) . '</p>';
			case 'vip_coupon':
				return sprintf( __( 'VIP offer! Use code %s', 'gcrm' ), $coupon );
			default:
				return '<p>' . __( 'Hello from our store!', 'gcrm' ) . '</p>';
		}
	}

	/**
	 * Check trigger config match.
	 *
	 * @param array<string, mixed> $workflow Workflow.
	 * @param array<string, mixed> $context Context.
	 */
	private function matches_trigger_config( array $workflow, array $context ): bool {
		$config = json_decode( $workflow['trigger_config'] ?? '{}', true );
		if ( ! is_array( $config ) || empty( $config ) ) {
			return true;
		}
		if ( isset( $config['amount'] ) && ! empty( $context['order_id'] ) ) {
			$order = wc_get_order( (int) $context['order_id'] );
			return $order && (float) $order->get_total() >= (float) $config['amount'];
		}
		return true;
	}
}
