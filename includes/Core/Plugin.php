<?php
/**
 * Main plugin singleton.
 *
 * @package GCRM\Core
 */

namespace GCRM\Core;

use GCRM\Admin\Assets;
use GCRM\Admin\Menu;
use GCRM\Frontend\CartTracker;
use GCRM\REST\RestBootstrap;
use GCRM\Services\OrdersSync;
use GCRM\Services\RecoveryAutomation;
use GCRM\Services\WorkflowRunner;
use GCRM\Services\GDPR;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Hook loader.
	 *
	 * @var Loader
	 */
	private Loader $loader;

	/**
	 * Get singleton.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->loader = new Loader();
	}

	/**
	 * Run plugin.
	 */
	public function run(): void {
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_cron_hooks();
		$this->define_rest_hooks();
		$this->define_woocommerce_hooks();
		$this->define_gdpr_hooks();

		$this->loader->run();
	}

	/**
	 * Admin hooks.
	 */
	private function define_admin_hooks(): void {
		$menu   = new Menu();
		$assets = new Assets();

		$this->loader->add_action( 'admin_menu', $menu, 'register_menus', 60 );
		$this->loader->add_action( 'admin_enqueue_scripts', $assets, 'enqueue', 10, 1 );
		$this->loader->add_action( 'admin_init', $menu, 'handle_exports', 5 );
		$this->loader->add_action( 'admin_init', $menu, 'handle_bulk_actions', 5 );
	}

	/**
	 * Public hooks.
	 */
	private function define_public_hooks(): void {
		$tracker = new CartTracker();
		$this->loader->add_action( 'wp_enqueue_scripts', $tracker, 'enqueue_scripts' );
		$this->loader->add_action( 'woocommerce_cart_updated', $tracker, 'on_cart_updated', 20 );
		$this->loader->add_action( 'woocommerce_checkout_update_order_review', $tracker, 'capture_checkout_fields', 10, 1 );
	}

	/**
	 * Cron hooks.
	 */
	private function define_cron_hooks(): void {
		$cron     = new Cron();
		$recovery = new RecoveryAutomation();
		$workflow = new WorkflowRunner();

		$this->loader->add_action( Cron::HOOK_PROCESS_QUEUE, BackgroundQueue::class, 'process_batch', 10, 0 );
		$this->loader->add_action( Cron::HOOK_ABANDONED_CARTS, $cron, 'mark_abandoned_carts', 10, 0 );
		$this->loader->add_action( Cron::HOOK_RECOVERY_EMAILS, $recovery, 'process_recovery_sequences', 10, 0 );
		$this->loader->add_action( Cron::HOOK_SYNC_GUESTS, $cron, 'sync_guest_customers', 10, 0 );
		$this->loader->add_action( Cron::HOOK_WORKFLOWS, $workflow, 'process_due_runs', 10, 0 );
	}

	/**
	 * REST API hooks.
	 */
	private function define_rest_hooks(): void {
		$rest = new RestBootstrap();
		$this->loader->add_action( 'rest_api_init', $rest, 'register_routes' );
	}

	/**
	 * WooCommerce hooks.
	 */
	private function define_woocommerce_hooks(): void {
		$sync = new OrdersSync();
		$this->loader->add_action( 'woocommerce_new_order', $sync, 'on_order_created', 20, 1 );
		$this->loader->add_action( 'woocommerce_order_status_changed', $sync, 'on_order_status_changed', 20, 4 );
		$this->loader->add_action( 'woocommerce_checkout_order_processed', $sync, 'on_checkout_processed', 20, 1 );
		$this->loader->add_action( 'woocommerce_thankyou', $this, 'mark_cart_recovered_on_order', 10, 1 );
		$this->loader->add_action( 'wp_ajax_gcrm_send_test_email', $this, 'ajax_send_test_email' );
	}

	/**
	 * Mark abandoned cart recovered when order placed.
	 *
	 * @param int $order_id Order ID.
	 */
	public function mark_cart_recovered_on_order( $order_id ): void {
		if ( ! WC()->session ) {
			return;
		}
		$cart_id = (int) WC()->session->get( 'gcrm_recover_cart_id', 0 );
		if ( $cart_id ) {
			( new \GCRM\Services\Carts() )->mark_recovered( $cart_id, (int) $order_id );
			WC()->session->set( 'gcrm_recover_cart_id', 0 );
		}
	}

	/**
	 * AJAX test email.
	 */
	public function ajax_send_test_email(): void {
		check_ajax_referer( 'wp_rest', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}
		$email = new \GCRM\Services\Email();
		$sent  = $email->send_test(
			sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) ),
			wp_kses_post( wp_unslash( $_POST['body'] ?? '' ) )
		);
		$sent ? wp_send_json_success() : wp_send_json_error();
	}

	/**
	 * GDPR hooks.
	 */
	private function define_gdpr_hooks(): void {
		$gdpr = new GDPR();
		$this->loader->add_filter( 'wp_privacy_personal_data_exporters', $gdpr, 'register_exporter', 10, 1 );
		$this->loader->add_filter( 'wp_privacy_personal_data_erasers', $gdpr, 'register_eraser', 10, 1 );
	}

	/**
	 * Get loader.
	 */
	public function get_loader(): Loader {
		return $this->loader;
	}
}
