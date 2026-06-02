<?php
/**
 * Settings page.
 *
 * @package GCRM\Admin\Pages
 */

namespace GCRM\Admin\Pages;

use GCRM\Core\Updater;
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

		if ( ! empty( $_GET['gcrm_update_found'] ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html( sprintf(
				/* translators: %s: version number */
				__( 'Update available: version %s. Use the button below or go to Plugins → Updates.', 'gcrm' ),
				sanitize_text_field( wp_unslash( $_GET['gcrm_update_found'] ) )
			) ) . '</p></div>';
		}
		if ( ! empty( $_GET['gcrm_update_none'] ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'You are running the latest version (or no update server is configured).', 'gcrm' ) . '</p></div>';
		}

		$updater_status = ( new Updater() )->get_status();
		$check_updates_url = wp_nonce_url(
			admin_url( 'admin.php?page=gcrm-settings&gcrm_check_updates=1' ),
			'gcrm_check_updates'
		);
		$update_now_url = '';
		if ( $updater_status['update_available'] && current_user_can( 'update_plugins' ) ) {
			$update_now_url = wp_nonce_url(
				self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( GCRM_PLUGIN_BASENAME ) ),
				'upgrade-plugin_' . GCRM_PLUGIN_BASENAME
			);
		}
		?>
		<div class="wrap gcrm-wrap">
			<h1><?php esc_html_e( 'GCRM Settings', 'gcrm' ); ?></h1>

			<div class="gcrm-update-panel card">
				<h2><?php esc_html_e( 'Plugin Updates', 'gcrm' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Installed version', 'gcrm' ); ?></th>
						<td><strong><?php echo esc_html( $updater_status['current'] ); ?></strong></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Latest available', 'gcrm' ); ?></th>
						<td>
							<?php if ( $updater_status['latest'] ) : ?>
								<strong><?php echo esc_html( $updater_status['latest'] ); ?></strong>
								<?php if ( $updater_status['update_available'] ) : ?>
									<span class="gcrm-badge-update"><?php esc_html_e( 'Update available', 'gcrm' ); ?></span>
								<?php else : ?>
									<span class="gcrm-badge-ok"><?php esc_html_e( 'Up to date', 'gcrm' ); ?></span>
								<?php endif; ?>
							<?php elseif ( $updater_status['configured'] ) : ?>
								<?php esc_html_e( 'Could not reach update server. Try again.', 'gcrm' ); ?>
							<?php else : ?>
								<?php esc_html_e( 'Configure update URL below to enable checks.', 'gcrm' ); ?>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<p>
					<?php if ( current_user_can( 'update_plugins' ) ) : ?>
						<a href="<?php echo esc_url( $check_updates_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Check for updates', 'gcrm' ); ?></a>
						<?php if ( $update_now_url ) : ?>
							<a href="<?php echo esc_url( $update_now_url ); ?>" class="button button-primary"><?php esc_html_e( 'Update now', 'gcrm' ); ?></a>
						<?php endif; ?>
						<a href="<?php echo esc_url( self_admin_url( 'plugins.php' ) ); ?>" class="button"><?php esc_html_e( 'Go to Plugins', 'gcrm' ); ?></a>
					<?php endif; ?>
				</p>
				<p class="description"><?php esc_html_e( 'When a new release is published, admins see a dashboard notice and can install updates from Plugins → Updates (same as WordPress.org plugins).', 'gcrm' ); ?></p>
			</div>

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

				<h2><?php esc_html_e( 'Updates', 'gcrm' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="gcrm_update_check_url"><?php esc_html_e( 'Update check URL', 'gcrm' ); ?></label></th>
						<td>
							<input type="url" id="gcrm_update_check_url" name="gcrm_update_check_url" class="large-text" value="<?php echo esc_attr( (string) get_option( 'gcrm_update_check_url', 'https://github.com/saadsrabon/woccomerce-guest-recovery' ) ); ?>" placeholder="https://github.com/saadsrabon/woccomerce-guest-recovery" />
							<p class="description">
								<?php esc_html_e( 'GitHub repository URL for releases (recommended) or a custom update.json URL.', 'gcrm' ); ?>
								<br />
								<a href="https://github.com/saadsrabon/woccomerce-guest-recovery/releases" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View releases on GitHub', 'gcrm' ); ?></a>
							</p>
						</td>
					</tr>
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
			'gcrm_sendgrid_api_key', 'gcrm_webhook_url', 'gcrm_update_check_url',
		);
		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$value = 'gcrm_update_check_url' === $field
					? esc_url_raw( wp_unslash( $_POST[ $field ] ) )
					: sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
				update_option( $field, $value );
			}
		}

		if ( isset( $_POST['gcrm_update_check_url'] ) ) {
			( new Updater() )->clear_cache();
			delete_site_transient( 'update_plugins' );
		}
		update_option( 'gcrm_update_last_check', current_time( 'mysql' ) );

		if ( ! empty( $_POST['gcrm_whatsapp_cloud_token'] ) ) {
			WhatsAppCloud::store_credential( 'gcrm_whatsapp_cloud_token', sanitize_text_field( wp_unslash( $_POST['gcrm_whatsapp_cloud_token'] ) ) );
		}
		if ( ! empty( $_POST['gcrm_twilio_auth_token'] ) ) {
			WhatsAppCloud::store_credential( 'gcrm_twilio_auth_token', sanitize_text_field( wp_unslash( $_POST['gcrm_twilio_auth_token'] ) ) );
		}
	}
}
