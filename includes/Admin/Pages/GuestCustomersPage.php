<?php
/**
 * Guest Customers admin page.
 *
 * @package GCRM\Admin\Pages
 */

namespace GCRM\Admin\Pages;

use GCRM\Admin\ListTables\GuestCustomersTable;

defined( 'ABSPATH' ) || exit;

/**
 * Class GuestCustomersPage
 */
class GuestCustomersPage {

	/**
	 * Render page.
	 */
	public function render(): void {
		$table = new GuestCustomersTable();
		$table->prepare_items();

		$export_nonce = wp_create_nonce( 'gcrm_export' );
		$base_url     = admin_url( 'admin.php?page=gcrm-guest-customers' );
		$query        = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $query['gcrm_export'] );
		$filter_qs = http_build_query( array_map( 'sanitize_text_field', $query ) );
		?>
		<div class="wrap gcrm-wrap">
			<h1><?php esc_html_e( 'Guest Customers', 'gcrm' ); ?></h1>
			<?php settings_errors( 'gcrm' ); ?>

			<form method="get">
				<input type="hidden" name="page" value="gcrm-guest-customers" />
				<div class="gcrm-filters tablenav top">
					<input type="search" name="s" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ) ); ?>" placeholder="<? esc_attr_e( 'Search name, email, phone...', 'gcrm' ); ?>" />
					<input type="date" name="date_from" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) ) ); ?>" />
					<input type="date" name="date_to" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) ) ); ?>" />
					<select name="order_status">
						<option value=""><?php esc_html_e( 'All statuses', 'gcrm' ); ?></option>
						<?php foreach ( wc_get_order_statuses() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( str_replace( 'wc-', '', $key ) ); ?>" <?php selected( sanitize_text_field( wp_unslash( $_GET['order_status'] ?? '' ) ), str_replace( 'wc-', '', $key ) ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<input type="text" name="country" placeholder="<? esc_attr_e( 'Country code', 'gcrm' ); ?>" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['country'] ?? '' ) ) ); ?>" />
					<input type="number" name="product_id" placeholder="<? esc_attr_e( 'Product ID', 'gcrm' ); ?>" value="<?php echo esc_attr( (string) absint( $_GET['product_id'] ?? 0 ) ); ?>" />
					<?php submit_button( __( 'Filter', 'gcrm' ), 'secondary', '', false ); ?>
					<a class="button" href="<?php echo esc_url( $base_url . '&gcrm_export=csv&_wpnonce=' . $export_nonce . '&' . $filter_qs ); ?>"><?php esc_html_e( 'Export CSV', 'gcrm' ); ?></a>
					<a class="button" href="<?php echo esc_url( $base_url . '&gcrm_export=xlsx&_wpnonce=' . $export_nonce . '&' . $filter_qs ); ?>"><?php esc_html_e( 'Export Excel', 'gcrm' ); ?></a>
				</div>
			</form>

			<form method="post" id="gcrm-guests-form">
				<?php wp_nonce_field( 'gcrm_bulk' ); ?>
				<?php $table->display(); ?>

				<div class="gcrm-bulk-panel">
					<h3><?php esc_html_e( 'Bulk Actions', 'gcrm' ); ?></h3>
					<select name="gcrm_action">
						<option value=""><?php esc_html_e( 'Choose action...', 'gcrm' ); ?></option>
						<option value="convert_users"><?php esc_html_e( 'Convert to WooCommerce Accounts', 'gcrm' ); ?></option>
						<option value="send_email"><?php esc_html_e( 'Send Email to Selected', 'gcrm' ); ?></option>
						<option value="send_whatsapp"><?php esc_html_e( 'Send WhatsApp to Selected', 'gcrm' ); ?></option>
					</select>
					<?php submit_button( __( 'Apply', 'gcrm' ), 'primary', 'submit', false ); ?>

					<div class="gcrm-email-builder" style="margin-top:20px;">
						<h4><?php esc_html_e( 'Email Builder', 'gcrm' ); ?></h4>
						<input type="text" name="email_subject" class="large-text" placeholder="<? esc_attr_e( 'Subject', 'gcrm' ); ?>" />
						<?php
						wp_editor(
							'',
							'gcrm_email_body',
							array(
								'textarea_name' => 'email_body',
								'media_buttons' => true,
								'textarea_rows' => 8,
							)
						);
						?>
						<p class="description"><?php esc_html_e( 'Placeholders: {first_name}, {last_name}, {email}, {phone}, {order_id}, {store_name}', 'gcrm' ); ?></p>
						<button type="button" class="button gcrm-preview-email"><?php esc_html_e( 'Preview Email', 'gcrm' ); ?></button>
						<button type="button" class="button gcrm-test-email"><?php esc_html_e( 'Send Test Email', 'gcrm' ); ?></button>
					</div>

					<div class="gcrm-whatsapp-builder" style="margin-top:20px;">
						<h4><?php esc_html_e( 'WhatsApp Message', 'gcrm' ); ?></h4>
						<textarea name="whatsapp_message" rows="4" class="large-text" placeholder="<? esc_attr_e( 'Message with {first_name}, {order_id}, {store_name}', 'gcrm' ); ?>"></textarea>
					</div>
				</div>
			</form>
			<div id="gcrm-preview-modal" class="gcrm-modal" style="display:none;"><div class="gcrm-modal-content"></div></div>
		</div>
		<?php
	}
}
