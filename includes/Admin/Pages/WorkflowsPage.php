<?php
/**
 * Workflows admin page.
 *
 * @package GCRM\Admin\Pages
 */

namespace GCRM\Admin\Pages;

use GCRM\DB\Repositories\WorkflowRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class WorkflowsPage
 */
class WorkflowsPage {

	/**
	 * Render page.
	 */
	public function render(): void {
		$workflows = ( new WorkflowRepository() )->all();
		?>
		<div class="wrap gcrm-wrap">
			<h1><?php esc_html_e( 'Automated Marketing Workflows', 'gcrm' ); ?></h1>
			<p><?php esc_html_e( 'Pre-configured workflows run automatically when triggers match (guest orders, VIP spend, inactive customers).', 'gcrm' ); ?></p>
			<table class="wp-list-table widefat striped gcrm-datatable">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'gcrm' ); ?></th>
						<th><?php esc_html_e( 'Trigger', 'gcrm' ); ?></th>
						<th><?php esc_html_e( 'Status', 'gcrm' ); ?></th>
						<th><?php esc_html_e( 'Steps', 'gcrm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $workflows as $wf ) :
						$steps = json_decode( $wf['steps'] ?? '[]', true );
						?>
						<tr>
							<td><strong><?php echo esc_html( $wf['name'] ); ?></strong></td>
							<td><code><?php echo esc_html( $wf['trigger_type'] ); ?></code></td>
							<td><?php echo esc_html( $wf['status'] ); ?></td>
							<td><?php echo esc_html( is_array( $steps ) ? count( $steps ) . ' steps' : '0' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
