<?php
/**
 * Insights / Revenue Intelligence dashboard.
 *
 * @package GCRM\Admin\Pages
 */

namespace GCRM\Admin\Pages;

use GCRM\Services\Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * Class InsightsPage
 */
class InsightsPage {

	/**
	 * Render dashboard.
	 */
	public function render(): void {
		$stats  = ( new Analytics() )->get_dashboard_stats();
		$trend  = ( new Analytics() )->revenue_trend();
		?>
		<div class="wrap gcrm-wrap">
			<h1><?php esc_html_e( 'Revenue Intelligence Dashboard', 'gcrm' ); ?></h1>

			<div class="gcrm-stats-grid">
				<div class="gcrm-stat-card"><span class="label"><?php esc_html_e( 'Total Guest Customers', 'gcrm' ); ?></span><span class="value"><?php echo esc_html( (string) $stats['total_guests'] ); ?></span></div>
				<div class="gcrm-stat-card"><span class="label"><?php esc_html_e( 'Abandoned Carts', 'gcrm' ); ?></span><span class="value"><?php echo esc_html( (string) $stats['total_abandoned'] ); ?></span></div>
				<div class="gcrm-stat-card"><span class="label"><?php esc_html_e( 'Recovery Rate', 'gcrm' ); ?></span><span class="value"><?php echo esc_html( $stats['recovery_rate'] . '%' ); ?></span></div>
				<div class="gcrm-stat-card"><span class="label"><?php esc_html_e( 'Revenue Recovered', 'gcrm' ); ?></span><span class="value"><?php echo wp_kses_post( wc_price( $stats['revenue_recovered'] ) ); ?></span></div>
				<div class="gcrm-stat-card"><span class="label"><?php esc_html_e( 'Total Guest Revenue', 'gcrm' ); ?></span><span class="value"><?php echo wp_kses_post( wc_price( $stats['total_revenue'] ) ); ?></span></div>
				<div class="gcrm-stat-card"><span class="label"><?php esc_html_e( 'Avg Order Value', 'gcrm' ); ?></span><span class="value"><?php echo wp_kses_post( wc_price( $stats['aov'] ) ); ?></span></div>
			</div>

			<div class="gcrm-charts">
				<canvas id="gcrm-revenue-chart" width="400" height="200"></canvas>
			</div>

			<h2><?php esc_html_e( 'Top Countries', 'gcrm' ); ?></h2>
			<table class="wp-list-table widefat striped">
				<thead><tr><th><?php esc_html_e( 'Country', 'gcrm' ); ?></th><th><?php esc_html_e( 'Customers', 'gcrm' ); ?></th><th><?php esc_html_e( 'Revenue', 'gcrm' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $stats['top_countries'] as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['country'] ?? '' ); ?></td>
							<td><?php echo esc_html( (string) ( $row['count'] ?? 0 ) ); ?></td>
							<td><?php echo wp_kses_post( wc_price( (float) ( $row['revenue'] ?? 0 ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Top Spending Customers', 'gcrm' ); ?></h2>
			<table class="wp-list-table widefat striped">
				<thead><tr><th><?php esc_html_e( 'Name', 'gcrm' ); ?></th><th><?php esc_html_e( 'Email', 'gcrm' ); ?></th><th><?php esc_html_e( 'Spend', 'gcrm' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $stats['top_customers'] as $c ) : ?>
						<tr>
							<td><?php echo esc_html( trim( ( $c['first_name'] ?? '' ) . ' ' . ( $c['last_name'] ?? '' ) ) ); ?></td>
							<td><?php echo esc_html( $c['email'] ?? '' ); ?></td>
							<td><?php echo wp_kses_post( wc_price( (float) ( $c['total_spend'] ?? 0 ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<script type="application/json" id="gcrm-chart-data"><?php echo wp_json_encode( $trend ); ?></script>
		</div>
		<?php
	}
}
