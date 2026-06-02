<?php
/**
 * Abandoned Carts admin page.
 *
 * @package GCRM\Admin\Pages
 */

namespace GCRM\Admin\Pages;

use GCRM\Admin\ListTables\AbandonedCartsTable;
use GCRM\DB\Repositories\CartRepository;
use GCRM\Services\RecoveryAutomation;

defined( 'ABSPATH' ) || exit;

/**
 * Class AbandonedCartsPage
 */
class AbandonedCartsPage {

	/**
	 * Render page.
	 */
	public function render(): void {
		$this->handle_cart_actions();

		$table = new AbandonedCartsTable();
		$table->prepare_items();
		?>
		<div class="wrap gcrm-wrap">
			<h1><?php esc_html_e( 'Abandoned Carts', 'gcrm' ); ?></h1>
			<?php settings_errors( 'gcrm' ); ?>
			<form method="get">
				<input type="hidden" name="page" value="gcrm-abandoned-carts" />
				<div class="gcrm-filters tablenav top">
					<input type="search" name="s" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ) ); ?>" />
					<select name="recovered">
						<option value=""><?php esc_html_e( 'All', 'gcrm' ); ?></option>
						<option value="yes" <?php selected( sanitize_text_field( wp_unslash( $_GET['recovered'] ?? '' ) ), 'yes' ); ?>><?php esc_html_e( 'Recovered', 'gcrm' ); ?></option>
						<option value="no" <?php selected( sanitize_text_field( wp_unslash( $_GET['recovered'] ?? '' ) ), 'no' ); ?>><?php esc_html_e( 'Not Recovered', 'gcrm' ); ?></option>
					</select>
					<input type="date" name="date_from" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) ) ); ?>" />
					<input type="date" name="date_to" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) ) ); ?>" />
					<?php submit_button( __( 'Filter', 'gcrm' ), 'secondary', '', false ); ?>
				</div>
			</form>
			<?php $table->display(); ?>
		</div>
		<?php
	}

	/**
	 * Handle single cart actions.
	 */
	private function handle_cart_actions(): void {
		if ( empty( $_GET['gcrm_cart_action'] ) || empty( $_GET['cart_id'] ) ) {
			return;
		}
		$cart_id = absint( $_GET['cart_id'] );
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'gcrm_cart_' . $cart_id ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$repo = new CartRepository();
		$cart = $repo->find( $cart_id );
		if ( ! $cart ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_GET['gcrm_cart_action'] ) );
		$recovery = new RecoveryAutomation();

		switch ( $action ) {
			case 'recovery_email':
				$recovery->send_recovery_email( $cart );
				add_settings_error( 'gcrm', 'gcrm', __( 'Recovery email sent.', 'gcrm' ), 'success' );
				break;
			case 'recovery_whatsapp':
				$recovery->send_recovery_whatsapp( $cart );
				add_settings_error( 'gcrm', 'gcrm', __( 'WhatsApp reminder sent.', 'gcrm' ), 'success' );
				break;
			case 'delete':
				$repo->delete( $cart_id );
				add_settings_error( 'gcrm', 'gcrm', __( 'Cart record deleted.', 'gcrm' ), 'success' );
				break;
		}
	}
}
