<?php
/**
 * Settings page.
 *
 * @package GCRM\Admin\Pages
 */

namespace GCRM\Admin\Pages;

use GCRM\Integrations\WhatsAppCloud;

defined( 'ABSPATH' ) || exit;

/**
 * Class SettingsPage
 */
class SettingsPage {

	/**
	 * Render settings.
	 */
	public function render(): void {
		if ( isset( $_POST['gcrm_save_settings'] ) && check_admin_referer( 'gcrm_settings' ) && current_user_can( 'manage_woocommerce' ) ) {
			$this->save_settings();
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'gcrm' ) . '</p></div>';
		}
		?>
		<div class="wrap gcrm-wrap">
			<h1><?php esc_html_e( 'GCRM Settings', 'gcrm' ); ?></h1>
			<form method="post">
				<?php wp_nonce_field( 'gcrm_settings' ); ?>
				<h2><?php esc_html_e( 'Abandoned Cart', 'gcrm' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Cart timeout (minutes)', 'gcrm' ); ?></th>
						<td><input type="number" name="gcrm_cart_timeout_minutes" value="<?php echo esc_attr( (string) get_option( 'gcrm_cart_timeout_minutes', 30 ) ); ?>" min="5" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Recovery emails enabled', 'gcrm' ); ?></th>
						<td><label><input type="checkbox" name="gcrm_recovery_email_enabled" value="yes" <?php checked( get_option( 'gcrm_recovery_email_enabled', 'yes' ), 'yes' ); ?> /> <?php esc_html_e( 'Enable', 'gcrm' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Recovery WhatsApp enabled', 'gcrm' ); ?></th>
						<td><label><input type="checkbox" name="gcrm_recovery_whatsapp_enabled" value="yes" <?php checked( get_option( 'gcrm_recovery_whatsapp_enabled', 'no' ), 'yes' ); ?> /> <?php esc_html_e( 'Enable', 'gcrm' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Abandoned cart coupon %', 'gcrm' ); ?></th>
						<td><input type="number" name="gcrm_abandoned_coupon_percent" value="<?php echo esc_attr( (string) get_option( 'gcrm_abandoned_coupon_percent', 10 ) ); ?>" step="0.1" /></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Email Sender', 'gcrm' ); ?></h2>
				<table class="form-table">
					<tr><th><?php esc_html_e( 'From name', 'gcrm' ); ?></th><td><input type="text" name="gcrm_email_from_name" class="regular-text" value="<?php echo esc_attr( (string) get_option( 'gcrm_email_from_name', get_bloginfo( 'name' ) ) ); ?>" /></td></tr>
					<tr><th><?php esc_html_e( 'From email', 'gcrm' ); ?></th><td><input type="email" name="gcrm_email_from_address" class="regular-text" value="<?php echo esc_attr( (string) get_option( 'gcrm_email_from_address', get_option( 'admin_email' ) ) ); ?>" /></td></tr>
				</table>

				<h2><?php esc_html_e( 'WhatsApp API', 'gcrm' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Provider', 'gcrm' ); ?></th>
						<td>
							<select name="gcrm_whatsapp_provider">
								<option value="cloud" <?php selected( get_option( 'gcrm_whatsapp_provider', 'cloud' ), 'cloud' ); ?>><?php esc_html_e( 'Meta WhatsApp Cloud API', 'gcrm' ); ?></option>
								<option value="twilio" <?php selected( get_option( 'gcrm_whatsapp_provider' ), 'twilio' ); ?>><?php esc_html_e( 'Twilio WhatsApp', 'gcrm' ); ?></option>
							</select>
						</td>
					</tr>
					<tr><th><?php esc_html_e( 'Cloud API Token', 'gcrm' ); ?></th><td><input type="password" name="gcrm_whatsapp_cloud_token" class="large-text" placeholder="<?php esc_attr_e( 'Leave blank to keep current', 'gcrm' ); ?>" /></td></tr>
					<tr><th><?php esc_html_e( 'Phone Number ID', 'gcrm' ); ?></th><td><input type="text" name="gcrm_whatsapp_phone_id" value="<?php echo esc_attr( (string) get_option( 'gcrm_whatsapp_phone_id', '' ) ); ?>" /></td></tr>
					<tr><th><?php esc_html_e( 'Twilio Account SID', 'gcrm' ); ?></th><td><input type="text" name="gcrm_twilio_account_sid" value="<?php echo esc_attr( (string) get_option( 'gcrm_twilio_account_sid', '' ) ); ?>" /></td></tr>
					<tr><th><?php esc_html_e( 'Twilio Auth Token', 'gcrm' ); ?></th><td><input type="password" name="gcrm_twilio_auth_token" placeholder="<?php esc_attr_e( 'Leave blank to keep current', 'gcrm' ); ?>" /></td></tr>
					<tr><th><?php esc_html_e( 'Twilio WhatsApp From', 'gcrm' ); ?></th><td><input type="text" name="gcrm_twilio_whatsapp_from" value="<?php echo esc_attr( (string) get_option( 'gcrm_twilio_whatsapp_from', '' ) ); ?>" /></td></tr>
					<tr><th><?php esc_html_e( 'Rate limit (per hour)', 'gcrm' ); ?></th><td><input type="number" name="gcrm_whatsapp_rate_limit" value="<?php echo esc_attr( (string) get_option( 'gcrm_whatsapp_rate_limit', 30 ) ); ?>" /></td></tr>
				</table>

				<h2><?php esc_html_e( 'Integrations', 'gcrm' ); ?></h2>
				<table class="form-table">
					<tr><th>Mailchimp API Key</th><td><input type="text" name="gcrm_mailchimp_api_key" class="large-text" value="<?php echo esc_attr( (string) get_option( 'gcrm_mailchimp_api_key', '' ) ); ?>" /></td></tr>
					<tr><th>Mailchimp List ID</th><td><input type="text" name="gcrm_mailchimp_list_id" value="<?php echo esc_attr( (string) get_option( 'gcrm_mailchimp_list_id', '' ) ); ?>" /></td></tr>
					<tr><th>Brevo API Key</th><td><input type="text" name="gcrm_brevo_api_key" class="large-text" value="<?php echo esc_attr( (string) get_option( 'gcrm_brevo_api_key', '' ) ); ?>" /></td></tr>
					<tr><th>SendGrid API Key</th><td><input type="text" name="gcrm_sendgrid_api_key" class="large-text" value="<?php echo esc_attr( (string) get_option( 'gcrm_sendgrid_api_key', '' ) ); ?>" /></td></tr>
					<tr><th>Webhook URL</th><td><input type="url" name="gcrm_webhook_url" class="large-text" value="<?php echo esc_url( (string) get_option( 'gcrm_webhook_url', '' ) ); ?>" /></td></tr>
				</table>

				<h2><?php esc_html_e( 'GDPR', 'gcrm' ); ?></h2>
				<table class="form-table">
					<tr><th><?php esc_html_e( 'Require marketing consent', 'gcrm' ); ?></th><td><label><input type="checkbox" name="gcrm_gdpr_consent_required" value="yes" <?php checked( get_option( 'gcrm_gdpr_consent_required', 'no' ), 'yes' ); ?> /> <?php esc_html_e( 'Yes', 'gcrm' ); ?></label></td></tr>
					<tr><th><?php esc_html_e( 'Remove data on uninstall', 'gcrm' ); ?></th><td><label><input type="checkbox" name="gcrm_remove_data_on_uninstall" value="yes" <?php checked( get_option( 'gcrm_remove_data_on_uninstall', 'no' ), 'yes' ); ?> /></label></td></tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'gcrm' ), 'primary', 'gcrm_save_settings' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Save settings.
	 */
	private function save_settings(): void {
		$checkboxes = array( 'gcrm_recovery_email_enabled', 'gcrm_recovery_whatsapp_enabled', 'gcrm_gdpr_consent_required', 'gcrm_remove_data_on_uninstall' );
		foreach ( $checkboxes as $key ) {
			update_option( $key, isset( $_POST[ $key ] ) ? 'yes' : 'no' );
		}

		$fields = array(
			'gcrm_cart_timeout_minutes', 'gcrm_email_from_name', 'gcrm_email_from_address',
			'gcrm_whatsapp_provider', 'gcrm_whatsapp_phone_id', 'gcrm_twilio_account_sid',
			'gcrm_twilio_whatsapp_from', 'gcrm_whatsapp_rate_limit', 'gcrm_abandoned_coupon_percent',
			'gcrm_mailchimp_api_key', 'gcrm_mailchimp_list_id', 'gcrm_brevo_api_key',
			'gcrm_sendgrid_api_key', 'gcrm_webhook_url',
		);
		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_option( $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
			}
		}

		if ( ! empty( $_POST['gcrm_whatsapp_cloud_token'] ) ) {
			WhatsAppCloud::store_credential( 'gcrm_whatsapp_cloud_token', sanitize_text_field( wp_unslash( $_POST['gcrm_whatsapp_cloud_token'] ) ) );
		}
		if ( ! empty( $_POST['gcrm_twilio_auth_token'] ) ) {
			WhatsAppCloud::store_credential( 'gcrm_twilio_auth_token', sanitize_text_field( wp_unslash( $_POST['gcrm_twilio_auth_token'] ) ) );
		}
	}
}
