<?php
/**
 * Segments admin page.
 *
 * @package GCRM\Admin\Pages
 */

namespace GCRM\Admin\Pages;

use GCRM\DB\Repositories\SegmentRepository;
use GCRM\Services\Segments;

defined( 'ABSPATH' ) || exit;

/**
 * Class SegmentsPage
 */
class SegmentsPage {

	/**
	 * Render page.
	 */
	public function render(): void {
		$repo = new SegmentRepository();

		if ( isset( $_POST['gcrm_save_segment'] ) && check_admin_referer( 'gcrm_segment' ) ) {
			$rules = array(
				'logic'      => sanitize_text_field( wp_unslash( $_POST['logic'] ?? 'AND' ) ),
				'conditions' => array(
					array(
						'field'    => sanitize_text_field( wp_unslash( $_POST['field'] ?? 'total_spend' ) ),
						'operator' => sanitize_text_field( wp_unslash( $_POST['operator'] ?? 'gte' ) ),
						'value'    => sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) ),
					),
				),
			);
			$repo->save(
				array(
					'id'          => absint( $_POST['segment_id'] ?? 0 ),
					'name'        => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
					'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
					'rules'       => $rules,
				)
			);
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Segment saved.', 'gcrm' ) . '</p></div>';
		}

		if ( isset( $_GET['duplicate'] ) && check_admin_referer( 'gcrm_dup_' . absint( $_GET['duplicate'] ) ) ) {
			$repo->duplicate( absint( $_GET['duplicate'] ) );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Segment duplicated.', 'gcrm' ) . '</p></div>';
		}

		$segments = $repo->all();
		?>
		<div class="wrap gcrm-wrap">
			<h1><?php esc_html_e( 'Customer Segments', 'gcrm' ); ?></h1>

			<h2><?php esc_html_e( 'Create / Edit Segment', 'gcrm' ); ?></h2>
			<form method="post" id="gcrm-segment-form">
				<?php wp_nonce_field( 'gcrm_segment' ); ?>
				<table class="form-table">
					<tr><th><?php esc_html_e( 'Name', 'gcrm' ); ?></th><td><input type="text" name="name" required /></td></tr>
					<tr><th><?php esc_html_e( 'Description', 'gcrm' ); ?></th><td><textarea name="description" rows="2" class="large-text"></textarea></td></tr>
					<tr>
						<th><?php esc_html_e( 'Logic', 'gcrm' ); ?></th>
						<td>
							<select name="logic"><option value="AND">AND</option><option value="OR">OR</option></select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Condition', 'gcrm' ); ?></th>
						<td>
							<select name="field">
								<option value="total_spend"><?php esc_html_e( 'Total Spend', 'gcrm' ); ?></option>
								<option value="order_count"><?php esc_html_e( 'Order Count', 'gcrm' ); ?></option>
								<option value="country"><?php esc_html_e( 'Country', 'gcrm' ); ?></option>
								<option value="days_since_order"><?php esc_html_e( 'Days Since Last Order', 'gcrm' ); ?></option>
								<option value="abandoned_cart_value"><?php esc_html_e( 'Abandoned Cart Value', 'gcrm' ); ?></option>
							</select>
							<select name="operator">
								<option value="eq">=</option>
								<option value="gt">&gt;</option>
								<option value="gte">&gt;=</option>
								<option value="lt">&lt;</option>
								<option value="lte">&lt;=</option>
							</select>
							<input type="text" name="value" placeholder="<?php esc_attr_e( 'Value', 'gcrm' ); ?>" />
							<button type="button" class="button" id="gcrm-preview-segment"><?php esc_html_e( 'Preview Audience', 'gcrm' ); ?></button>
							<span id="gcrm-segment-count"></span>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save Segment', 'gcrm' ), 'primary', 'gcrm_save_segment' ); ?>
			</form>

			<h2><?php esc_html_e( 'Saved Segments', 'gcrm' ); ?></h2>
			<table class="wp-list-table widefat striped gcrm-datatable">
				<thead><tr><th><?php esc_html_e( 'Name', 'gcrm' ); ?></th><th><?php esc_html_e( 'Slug', 'gcrm' ); ?></th><th><?php esc_html_e( 'Prebuilt', 'gcrm' ); ?></th><th><?php esc_html_e( 'Actions', 'gcrm' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $segments as $seg ) : ?>
						<tr>
							<td><?php echo esc_html( $seg['name'] ); ?></td>
							<td><?php echo esc_html( $seg['slug'] ); ?></td>
							<td><?php echo ! empty( $seg['is_prebuilt'] ) ? esc_html__( 'Yes', 'gcrm' ) : esc_html__( 'No', 'gcrm' ); ?></td>
							<td>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=gcrm-segments&duplicate=' . $seg['id'] ), 'gcrm_dup_' . $seg['id'] ) ); ?>"><?php esc_html_e( 'Duplicate', 'gcrm' ); ?></a>
								| <?php echo esc_html( ( new Segments() )->preview_count( json_decode( $seg['rules'] ?? '{}', true ) ?: array() ) ); ?> <?php esc_html_e( 'customers', 'gcrm' ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
