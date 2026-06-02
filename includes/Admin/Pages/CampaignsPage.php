<?php
/**
 * Campaign manager page.
 *
 * @package GCRM\Admin\Pages
 */

namespace GCRM\Admin\Pages;

use GCRM\DB\Repositories\CampaignRepository;
use GCRM\DB\Repositories\SegmentRepository;
use GCRM\Services\Campaigns;

defined( 'ABSPATH' ) || exit;

/**
 * Class CampaignsPage
 */
class CampaignsPage {

	/**
	 * Render page.
	 */
	public function render(): void {
		if ( isset( $_POST['gcrm_create_campaign'] ) && check_admin_referer( 'gcrm_campaign' ) ) {
			$this->save_campaign();
		}
		if ( isset( $_GET['launch'] ) && check_admin_referer( 'gcrm_launch_' . absint( $_GET['launch'] ) ) ) {
			$result = ( new Campaigns() )->launch( absint( $_GET['launch'] ) );
			if ( is_wp_error( $result ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
			} else {
				echo '<div class="notice notice-success"><p>' . esc_html( sprintf( __( 'Launched — %d messages queued.', 'gcrm' ), $result ) ) . '</p></div>';
			}
		}

		$campaigns = ( new CampaignRepository() )->all();
		$segments  = ( new SegmentRepository() )->all();
		?>
		<div class="wrap gcrm-wrap">
			<h1><?php esc_html_e( 'Marketing Campaign Center', 'gcrm' ); ?></h1>

			<h2><?php esc_html_e( 'Create Campaign', 'gcrm' ); ?></h2>
			<form method="post" class="gcrm-campaign-form">
				<?php wp_nonce_field( 'gcrm_campaign' ); ?>
				<table class="form-table">
					<tr><th><?php esc_html_e( 'Name', 'gcrm' ); ?></th><td><input type="text" name="name" required class="regular-text" /></td></tr>
					<tr>
						<th><?php esc_html_e( 'Type', 'gcrm' ); ?></th>
						<td>
							<select name="type">
								<option value="email"><?php esc_html_e( 'Email', 'gcrm' ); ?></option>
								<option value="whatsapp"><?php esc_html_e( 'WhatsApp', 'gcrm' ); ?></option>
								<option value="mixed"><?php esc_html_e( 'Mixed (Email + WhatsApp)', 'gcrm' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Audience (Segment)', 'gcrm' ); ?></th>
						<td>
							<select name="segment_id" required>
								<?php foreach ( $segments as $seg ) : ?>
									<option value="<?php echo esc_attr( (string) $seg['id'] ); ?>"><?php echo esc_html( $seg['name'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr><th><?php esc_html_e( 'Subject', 'gcrm' ); ?></th><td><input type="text" name="subject" class="large-text" /></td></tr>
					<tr><th><?php esc_html_e( 'Email body', 'gcrm' ); ?></th><td><?php wp_editor( '', 'campaign_body', array( 'textarea_name' => 'body', 'textarea_rows' => 8 ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'WhatsApp body', 'gcrm' ); ?></th><td><textarea name="whatsapp_body" rows="4" class="large-text"></textarea></td></tr>
					<tr><th><?php esc_html_e( 'Schedule (optional)', 'gcrm' ); ?></th><td><input type="datetime-local" name="schedule_at" /></td></tr>
				</table>
				<?php submit_button( __( 'Save Campaign', 'gcrm' ), 'primary', 'gcrm_create_campaign' ); ?>
			</form>

			<h2><?php esc_html_e( 'Campaigns', 'gcrm' ); ?></h2>
			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'gcrm' ); ?></th>
						<th><?php esc_html_e( 'Type', 'gcrm' ); ?></th>
						<th><?php esc_html_e( 'Status', 'gcrm' ); ?></th>
						<th><?php esc_html_e( 'Metrics', 'gcrm' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'gcrm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $campaigns as $c ) :
						$metrics = json_decode( $c['metrics'] ?? '{}', true ) ?: array();
						?>
						<tr>
							<td><?php echo esc_html( $c['name'] ); ?></td>
							<td><?php echo esc_html( $c['type'] ); ?></td>
							<td><?php echo esc_html( $c['status'] ); ?></td>
							<td><?php echo esc_html( sprintf( 'Sent: %s', $metrics['sent'] ?? 0 ) ); ?></td>
							<td>
								<?php if ( 'draft' === $c['status'] || 'scheduled' === $c['status'] ) : ?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=gcrm-campaigns&launch=' . $c['id'] ), 'gcrm_launch_' . $c['id'] ) ); ?>" class="button"><?php esc_html_e( 'Launch', 'gcrm' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Save new campaign.
	 */
	private function save_campaign(): void {
		$schedule = sanitize_text_field( wp_unslash( $_POST['schedule_at'] ?? '' ) );
		( new CampaignRepository() )->create(
			array(
				'name'           => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
				'type'           => sanitize_text_field( wp_unslash( $_POST['type'] ?? 'email' ) ),
				'segment_id'     => absint( $_POST['segment_id'] ?? 0 ),
				'subject'        => sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) ),
				'body'           => wp_kses_post( wp_unslash( $_POST['body'] ?? '' ) ),
				'whatsapp_body'  => sanitize_textarea_field( wp_unslash( $_POST['whatsapp_body'] ?? '' ) ),
				'schedule_at'    => $schedule ? gmdate( 'Y-m-d H:i:s', strtotime( $schedule ) ) : null,
				'status'         => $schedule ? 'scheduled' : 'draft',
			)
		);
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Campaign saved.', 'gcrm' ) . '</p></div>';
	}
}
