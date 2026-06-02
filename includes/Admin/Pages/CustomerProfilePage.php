<?php
/**
 * Customer profile drill-down page.
 *
 * @package GCRM\Admin\Pages
 */

namespace GCRM\Admin\Pages;

use GCRM\DB\Repositories\EmailLogRepository;
use GCRM\DB\Repositories\GuestRepository;
use GCRM\DB\Repositories\WhatsAppLogRepository;
use GCRM\DB\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Class CustomerProfilePage
 */
class CustomerProfilePage {

	/**
	 * Render profile.
	 */
	public function render(): void {
		$guest_id = absint( $_GET['guest_id'] ?? 0 );
		$guest    = ( new GuestRepository() )->find( $guest_id );

		if ( ! $guest ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Customer not found.', 'gcrm' ) . '</p></div>';
			return;
		}

		if ( isset( $_POST['gcrm_add_note'] ) && check_admin_referer( 'gcrm_note_' . $guest_id ) ) {
			global $wpdb;
			$wpdb->insert(
				Schema::table( 'customer_notes' ),
				array(
					'guest_id'  => $guest_id,
					'note'      => sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) ),
					'tags'      => sanitize_text_field( wp_unslash( $_POST['tags'] ?? '' ) ),
					'author_id' => get_current_user_id(),
				),
				array( '%d', '%s', '%s', '%d' )
			);
		}

		global $wpdb;
		$notes = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::table( 'customer_notes' ) . ' WHERE guest_id = %d ORDER BY created_at DESC', $guest_id ),
			ARRAY_A
		);

		$orders = wc_get_orders( array( 'billing_email' => $guest['email'], 'limit' => 20 ) );

		$email_logs = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::table( 'email_logs' ) . ' WHERE guest_id = %d OR to_email = %s ORDER BY created_at DESC LIMIT 20', $guest_id, $guest['email'] ),
			ARRAY_A
		);
		?>
		<div class="wrap gcrm-wrap gcrm-profile">
			<h1><?php echo esc_html( trim( $guest['first_name'] . ' ' . $guest['last_name'] ) ?: $guest['email'] ); ?></h1>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=gcrm-guest-customers' ) ); ?>">&larr; <?php esc_html_e( 'Back to Guest Customers', 'gcrm' ); ?></a></p>

			<div class="gcrm-profile-grid">
				<div class="gcrm-profile-card">
					<h2><?php esc_html_e( 'Contact', 'gcrm' ); ?></h2>
					<p><strong><?php esc_html_e( 'Email:', 'gcrm' ); ?></strong> <?php echo esc_html( $guest['email'] ); ?></p>
					<p><strong><?php esc_html_e( 'Phone:', 'gcrm' ); ?></strong> <?php echo esc_html( $guest['phone'] ); ?></p>
					<p><strong><?php esc_html_e( 'Country:', 'gcrm' ); ?></strong> <?php echo esc_html( $guest['country'] ); ?></p>
				</div>
				<div class="gcrm-profile-card">
					<h2><?php esc_html_e( 'Metrics', 'gcrm' ); ?></h2>
					<p><strong><?php esc_html_e( 'Total Spend:', 'gcrm' ); ?></strong> <?php echo wp_kses_post( wc_price( (float) $guest['total_spend'] ) ); ?></p>
					<p><strong><?php esc_html_e( 'LTV / Orders:', 'gcrm' ); ?></strong> <?php echo esc_html( (string) $guest['order_count'] ); ?></p>
					<p><strong><?php esc_html_e( 'Last Order:', 'gcrm' ); ?></strong> <?php echo esc_html( (string) $guest['last_order_date'] ); ?></p>
				</div>
			</div>

			<h2><?php esc_html_e( 'Order History', 'gcrm' ); ?></h2>
			<table class="wp-list-table widefat striped">
				<thead><tr><th>ID</th><th><?php esc_html_e( 'Date', 'gcrm' ); ?></th><th><?php esc_html_e( 'Total', 'gcrm' ); ?></th><th><?php esc_html_e( 'Status', 'gcrm' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $orders as $order ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo esc_html( (string) $order->get_id() ); ?></a></td>
							<td><?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->date_i18n() : '' ); ?></td>
							<td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
							<td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Email Activity', 'gcrm' ); ?></h2>
			<table class="wp-list-table widefat striped">
				<thead><tr><th><?php esc_html_e( 'Subject', 'gcrm' ); ?></th><th><?php esc_html_e( 'Status', 'gcrm' ); ?></th><th><?php esc_html_e( 'Sent', 'gcrm' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $email_logs as $log ) : ?>
						<tr>
							<td><?php echo esc_html( $log['subject'] ?? '' ); ?></td>
							<td><?php echo esc_html( $log['status'] ?? '' ); ?></td>
							<td><?php echo esc_html( (string) ( $log['sent_at'] ?? '' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Admin Notes & Tags', 'gcrm' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'gcrm_note_' . $guest_id ); ?>
				<textarea name="note" rows="3" class="large-text"></textarea>
				<input type="text" name="tags" placeholder="<?php esc_attr_e( 'Tags (comma separated)', 'gcrm' ); ?>" class="regular-text" />
				<?php submit_button( __( 'Add Note', 'gcrm' ), 'secondary', 'gcrm_add_note' ); ?>
			</form>
			<ul>
				<?php foreach ( $notes as $note ) : ?>
					<li><strong><?php echo esc_html( $note['tags'] ?? '' ); ?></strong> — <?php echo esc_html( $note['note'] ?? '' ); ?> <em>(<?php echo esc_html( $note['created_at'] ?? '' ); ?>)</em></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}
}
