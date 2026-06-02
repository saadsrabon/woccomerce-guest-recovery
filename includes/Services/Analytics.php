<?php
/**
 * Analytics & revenue intelligence.
 *
 * @package GCRM\Services
 */

namespace GCRM\Services;

use GCRM\DB\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Class Analytics
 */
class Analytics {

	/**
	 * Dashboard stats.
	 *
	 * @return array<string, mixed>
	 */
	public function get_dashboard_stats(): array {
		global $wpdb;
		$guests_table = Schema::table( 'guest_customers' );
		$carts_table  = Schema::table( 'abandoned_carts' );

		$total_guests = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$guests_table}" );
		$total_abandoned = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$carts_table} WHERE status = 'abandoned'" );
		$recovered = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$carts_table} WHERE status = 'recovered'" );
		$recovery_rate = $total_abandoned > 0 ? round( ( $recovered / ( $total_abandoned + $recovered ) ) * 100, 2 ) : 0;

		$revenue_recovered = (float) $wpdb->get_var(
			"SELECT COALESCE(SUM(cart_value),0) FROM {$carts_table} WHERE status = 'recovered'"
		);

		$total_revenue = (float) $wpdb->get_var( "SELECT COALESCE(SUM(total_spend),0) FROM {$guests_table}" );

		$top_countries = $wpdb->get_results(
			"SELECT country, COUNT(*) as count, SUM(total_spend) as revenue FROM {$guests_table} WHERE country != '' GROUP BY country ORDER BY revenue DESC LIMIT 10",
			ARRAY_A
		);

		$top_customers = $wpdb->get_results(
			"SELECT first_name, last_name, email, total_spend FROM {$guests_table} ORDER BY total_spend DESC LIMIT 10",
			ARRAY_A
		);

		return array(
			'total_guests'       => $total_guests,
			'total_abandoned'    => $total_abandoned,
			'recovery_rate'      => $recovery_rate,
			'revenue_recovered'  => $revenue_recovered,
			'total_revenue'      => $total_revenue,
			'aov'                => $total_guests > 0 ? round( $total_revenue / $total_guests, 2 ) : 0,
			'top_countries'      => $top_countries ?: array(),
			'top_customers'      => $top_customers ?: array(),
			'email_stats'        => ( new \GCRM\DB\Repositories\EmailLogRepository() )->get_stats(),
		);
	}

	/**
	 * Chart data for revenue trend (last 30 days).
	 *
	 * @return array{labels: array<int, string>, values: array<int, float>}
	 */
	public function revenue_trend(): array {
		global $wpdb;
		$labels = array();
		$values = array();
		$table  = Schema::table( 'guest_customers' );

		for ( $i = 29; $i >= 0; $i-- ) {
			$date    = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
			$labels[] = $date;
			$values[] = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(total_spend),0) FROM {$table} WHERE DATE(last_order_date) = %s",
					$date
				)
			);
		}

		return array( 'labels' => $labels, 'values' => $values );
	}
}
