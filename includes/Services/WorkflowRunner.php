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
use GCRM\DB\Schema;

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
		$context   = $this->enrich_context( $context );
		$guest_id  = $this->resolve_guest_id( $context );
		$workflows = ( new WorkflowRepository() )->get_by_trigger( $trigger_type );
		$run_repo  = new WorkflowRunRepository();

		foreach ( $workflows as $workflow ) {
			if ( ! $this->matches_trigger_config( $workflow, $context ) ) {
				continue;
			}

			$workflow_id = (int) $workflow['id'];
			if ( $run_repo->has_existing_run( $workflow_id, $guest_id, (int) ( $context['order_id'] ?? 0 ), $trigger_type ) ) {
				continue;
			}

			$run_repo->create(
				array(
					'workflow_id' => $workflow_id,
					'guest_id'    => $guest_id,
					'order_id'    => (int) ( $context['order_id'] ?? 0 ),
					'context'     => $context,
				)
			);
		}
	}

	/**
	 * Process due workflow runs and inactive-guest triggers.
	 */
	public function process_due_runs(): void {
		$this->process_inactive_triggers();

		$runs = ( new WorkflowRunRepository() )->get_due();
		foreach ( $runs as $run ) {
			$this->execute_run( $run );
		}
	}

	/**
	 * Queue workflows for guests inactive beyond configured days.
	 */
	public function process_inactive_triggers(): void {
		$workflows = ( new WorkflowRepository() )->get_by_trigger( 'no_order_days' );
		if ( ! $workflows ) {
			return;
		}

		global $wpdb;
		$table    = Schema::table( 'guest_customers' );
		$run_repo = new WorkflowRunRepository();

		foreach ( $workflows as $workflow ) {
			$config = json_decode( $workflow['trigger_config'] ?? '{}', true );
			$days   = (int) ( is_array( $config ) ? ( $config['days'] ?? 90 ) : 90 );
			if ( $days < 1 ) {
				continue;
			}

			$threshold = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
			$guests    = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, email FROM {$table} WHERE last_order_date IS NOT NULL AND last_order_date < %s AND email != '' LIMIT 100",
					$threshold
				),
				ARRAY_A
			);

			foreach ( $guests ?: array() as $guest ) {
				$guest_id = (int) $guest['id'];
				if ( $run_repo->has_existing_run( (int) $workflow['id'], $guest_id, 0, 'no_order_days' ) ) {
					continue;
				}

				$run_repo->create(
					array(
						'workflow_id' => (int) $workflow['id'],
						'guest_id'    => $guest_id,
						'order_id'    => 0,
						'context'     => array(
							'email'    => $guest['email'],
							'guest_id' => $guest_id,
						),
					)
				);
			}
		}
	}

	/**
	 * Execute workflow run step.
	 *
	 * @param array<string, mixed> $run Run row.
	 */
	private function execute_run( array $run ): void {
		$workflow_repo = new WorkflowRepository();
		$run_repo      = new WorkflowRunRepository();
		$workflow      = $workflow_repo->find( (int) $run['workflow_id'] );
		if ( ! $workflow ) {
			return;
		}

		$steps = json_decode( $workflow['steps'] ?? '[]', true );
		if ( ! is_array( $steps ) || ! $steps ) {
			$run_repo->advance( (int) $run['id'], 0, current_time( 'mysql' ), 'completed' );
			return;
		}

		$step_index = (int) $run['current_step'];
		$context    = json_decode( $run['context'] ?? '{}', true ) ?: array();
		$guest_id   = (int) $run['guest_id'];

		if ( ! $guest_id && ! empty( $context['email'] ) ) {
			$guest_id = $this->resolve_guest_id( $context );
		}
		if ( ! $guest_id && ! empty( $context['order_id'] ) ) {
			$guest_id = $this->resolve_guest_id( array( 'order_id' => (int) $context['order_id'] ) );
		}

		$guest = $guest_id ? ( new GuestRepository() )->find( $guest_id ) : null;

		while ( $step_index < count( $steps ) ) {
			$step = $steps[ $step_index ] ?? null;
			if ( ! $step ) {
				break;
			}

			if ( empty( $step['enabled'] ) ) {
				++$step_index;
				continue;
			}

			$context = $this->execute_step( $step, $guest, $context );
			$run_repo->update_context( (int) $run['id'], $context );

			++$step_index;
			if ( $step_index >= count( $steps ) ) {
				$run_repo->advance( (int) $run['id'], $step_index, current_time( 'mysql' ), 'completed' );
				return;
			}

			$delay_hours = (int) ( $steps[ $step_index ]['delay_hours'] ?? 0 );
			$next_run    = gmdate( 'Y-m-d H:i:s', time() + ( $delay_hours * HOUR_IN_SECONDS ) );
			$run_repo->advance( (int) $run['id'], $step_index, $next_run, 'pending' );
			return;
		}

		$run_repo->advance( (int) $run['id'], $step_index, current_time( 'mysql' ), 'completed' );
	}

	/**
	 * Execute single step and return updated context.
	 *
	 * @param array<string, mixed>      $step Step config.
	 * @param array<string, mixed>|null $guest Guest.
	 * @param array<string, mixed>      $context Context.
	 * @return array<string, mixed>
	 */
	private function execute_step( array $step, ?array $guest, array $context ): array {
		$type = $step['type'] ?? '';
		switch ( $type ) {
			case 'send_email':
				if ( $guest && ! empty( $guest['email'] ) ) {
					$body = $this->template_body( (string) ( $step['template'] ?? 'default' ), $guest, $context );
					( new Email() )->send_single(
						$guest['email'],
						__( 'Message from ', 'gcrm' ) . get_bloginfo( 'name' ),
						$body,
						( new Email() )->placeholders_from_guest( $guest, $this->placeholder_extras( $context ) )
					);
				}
				break;
			case 'send_whatsapp':
				if ( $guest && ! empty( $guest['phone'] ) ) {
					( new WhatsApp() )->send_single(
						$guest['phone'],
						$this->template_body( (string) ( $step['template'] ?? 'default' ), $guest, $context ),
						( new Email() )->placeholders_from_guest( $guest, $this->placeholder_extras( $context ) )
					);
				}
				break;
			case 'generate_coupon':
				if ( empty( $context['coupon_code'] ) ) {
					$context['coupon_code'] = ( new Coupons() )->generate(
						array( 'percent' => (float) ( $step['percent'] ?? 10 ) )
					);
				}
				break;
		}

		return $context;
	}

	/**
	 * Extra placeholders from workflow context.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<string, string>
	 */
	private function placeholder_extras( array $context ): array {
		$extra = array();
		if ( ! empty( $context['coupon_code'] ) ) {
			$extra['{coupon_code}'] = (string) $context['coupon_code'];
		}
		return $extra;
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
				return sprintf( __( 'VIP offer! Use code %s', 'gcrm' ), esc_html( $coupon ) );
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

	/**
	 * Add billing email / guest id from order when missing.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed>
	 */
	private function enrich_context( array $context ): array {
		if ( ! empty( $context['email'] ) ) {
			return $context;
		}

		if ( ! empty( $context['order_id'] ) ) {
			$order = wc_get_order( (int) $context['order_id'] );
			if ( $order ) {
				$context['email'] = $order->get_billing_email();
			}
		}

		$guest_id = $this->resolve_guest_id( $context );
		if ( $guest_id ) {
			$context['guest_id'] = $guest_id;
		}

		return $context;
	}

	/**
	 * Resolve guest ID from context email or order.
	 *
	 * @param array<string, mixed> $context Context.
	 */
	private function resolve_guest_id( array $context ): int {
		if ( ! empty( $context['guest_id'] ) ) {
			return (int) $context['guest_id'];
		}

		if ( ! empty( $context['email'] ) ) {
			$guest = ( new GuestRepository() )->find_by_email( (string) $context['email'] );
			return $guest ? (int) $guest['id'] : 0;
		}

		if ( ! empty( $context['order_id'] ) ) {
			$order = wc_get_order( (int) $context['order_id'] );
			if ( $order ) {
				$email = $order->get_billing_email();
				if ( $email ) {
					$guest = ( new GuestRepository() )->find_by_email( $email );
					return $guest ? (int) $guest['id'] : 0;
				}
			}
		}

		return 0;
	}
}
