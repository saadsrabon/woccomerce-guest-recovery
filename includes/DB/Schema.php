<?php
/**
 * Database schema.
 *
 * @package GCRM\DB
 */

namespace GCRM\DB;

defined( 'ABSPATH' ) || exit;

/**
 * Class Schema
 */
class Schema {

	/**
	 * Table names (without prefix).
	 *
	 * @return array<string, string>
	 */
	public static function tables(): array {
		return array(
			'guest_customers'  => 'gcrm_guest_customers',
			'abandoned_carts'  => 'gcrm_abandoned_carts',
			'email_logs'       => 'gcrm_email_logs',
			'whatsapp_logs'    => 'gcrm_whatsapp_logs',
			'campaigns'        => 'gcrm_campaigns',
			'segments'         => 'gcrm_segments',
			'workflows'        => 'gcrm_workflows',
			'workflow_runs'    => 'gcrm_workflow_runs',
			'queue'            => 'gcrm_queue',
			'consent_logs'     => 'gcrm_consent_logs',
			'customer_notes'   => 'gcrm_customer_notes',
		);
	}

	/**
	 * Get full table name.
	 *
	 * @param string $key Table key.
	 */
	public static function table( string $key ): string {
		global $wpdb;
		$tables = self::tables();
		return $wpdb->prefix . ( $tables[ $key ] ?? $key );
	}

	/**
	 * Run dbDelta when the plugin DB version changes.
	 */
	public static function maybe_upgrade(): void {
		$installed = (string) get_option( 'gcrm_db_version', '' );
		if ( $installed && version_compare( $installed, GCRM_DB_VERSION, '>=' ) ) {
			return;
		}

		self::create_tables();
		update_option( 'gcrm_db_version', GCRM_DB_VERSION );
	}

	/**
	 * Create all tables.
	 */
	public static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		$sql = array();

		$sql[] = "CREATE TABLE " . self::table( 'guest_customers' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(255) NOT NULL,
			first_name varchar(100) DEFAULT '',
			last_name varchar(100) DEFAULT '',
			phone varchar(50) DEFAULT '',
			billing_address longtext,
			shipping_address longtext,
			city varchar(100) DEFAULT '',
			state varchar(100) DEFAULT '',
			country varchar(10) DEFAULT '',
			zip varchar(20) DEFAULT '',
			order_count int(11) DEFAULT 0,
			total_spend decimal(15,2) DEFAULT 0.00,
			last_order_id bigint(20) unsigned DEFAULT 0,
			last_order_date datetime DEFAULT NULL,
			last_order_status varchar(50) DEFAULT '',
			consent tinyint(1) DEFAULT 0,
			user_id bigint(20) unsigned DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY email (email),
			KEY country (country),
			KEY last_order_date (last_order_date)
		) $charset;";

		$sql[] = "CREATE TABLE " . self::table( 'abandoned_carts' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_key varchar(64) DEFAULT '',
			user_id bigint(20) unsigned DEFAULT 0,
			email varchar(255) DEFAULT '',
			phone varchar(50) DEFAULT '',
			customer_name varchar(200) DEFAULT '',
			cart_contents longtext,
			cart_value decimal(15,2) DEFAULT 0.00,
			currency varchar(10) DEFAULT '',
			status varchar(20) DEFAULT 'active',
			recovery_token varchar(64) DEFAULT '',
			recovery_step int(11) DEFAULT 0,
			recovered_order_id bigint(20) unsigned DEFAULT 0,
			last_activity datetime DEFAULT NULL,
			abandoned_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY session_key (session_key),
			KEY email (email),
			KEY status (status),
			KEY recovery_token (recovery_token)
		) $charset;";

		$sql[] = "CREATE TABLE " . self::table( 'email_logs' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned DEFAULT 0,
			guest_id bigint(20) unsigned DEFAULT 0,
			cart_id bigint(20) unsigned DEFAULT 0,
			to_email varchar(255) NOT NULL,
			subject varchar(500) DEFAULT '',
			body longtext,
			status varchar(20) DEFAULT 'queued',
			provider varchar(50) DEFAULT 'wp_mail',
			track_token varchar(64) DEFAULT '',
			opened_at datetime DEFAULT NULL,
			clicked_at datetime DEFAULT NULL,
			error_message text,
			sent_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY campaign_id (campaign_id),
			KEY status (status),
			KEY track_token (track_token)
		) $charset;";

		$sql[] = "CREATE TABLE " . self::table( 'whatsapp_logs' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned DEFAULT 0,
			guest_id bigint(20) unsigned DEFAULT 0,
			cart_id bigint(20) unsigned DEFAULT 0,
			to_phone varchar(50) NOT NULL,
			message longtext,
			status varchar(20) DEFAULT 'queued',
			provider varchar(50) DEFAULT '',
			provider_msg_id varchar(100) DEFAULT '',
			error_message text,
			sent_at datetime DEFAULT NULL,
			delivered_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY campaign_id (campaign_id),
			KEY status (status)
		) $charset;";

		$sql[] = "CREATE TABLE " . self::table( 'campaigns' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			type varchar(20) DEFAULT 'email',
			segment_id bigint(20) unsigned DEFAULT 0,
			subject varchar(500) DEFAULT '',
			body longtext,
			whatsapp_body longtext,
			schedule_at datetime DEFAULT NULL,
			status varchar(20) DEFAULT 'draft',
			metrics longtext,
			created_by bigint(20) unsigned DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY status (status),
			KEY segment_id (segment_id)
		) $charset;";

		$sql[] = "CREATE TABLE " . self::table( 'segments' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			slug varchar(255) DEFAULT '',
			description text,
			rules longtext,
			is_prebuilt tinyint(1) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug)
		) $charset;";

		$sql[] = "CREATE TABLE " . self::table( 'workflows' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			trigger_type varchar(50) NOT NULL,
			trigger_config longtext,
			steps longtext,
			status varchar(20) DEFAULT 'active',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset;";

		$sql[] = "CREATE TABLE " . self::table( 'workflow_runs' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workflow_id bigint(20) unsigned NOT NULL,
			guest_id bigint(20) unsigned DEFAULT 0,
			order_id bigint(20) unsigned DEFAULT 0,
			current_step int(11) DEFAULT 0,
			status varchar(20) DEFAULT 'pending',
			next_run_at datetime DEFAULT NULL,
			context longtext,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY workflow_id (workflow_id),
			KEY next_run_at (next_run_at)
		) $charset;";

		$sql[] = "CREATE TABLE " . self::table( 'queue' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_type varchar(50) NOT NULL,
			payload longtext,
			status varchar(20) DEFAULT 'pending',
			attempts int(11) DEFAULT 0,
			error_message text,
			scheduled_at datetime DEFAULT NULL,
			processed_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY status_scheduled (status, scheduled_at)
		) $charset;";

		$sql[] = "CREATE TABLE " . self::table( 'consent_logs' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(255) NOT NULL,
			action varchar(50) NOT NULL,
			ip_address varchar(45) DEFAULT '',
			user_agent text,
			meta longtext,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY email (email)
		) $charset;";

		$sql[] = "CREATE TABLE " . self::table( 'customer_notes' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			guest_id bigint(20) unsigned NOT NULL,
			note longtext,
			tags varchar(500) DEFAULT '',
			author_id bigint(20) unsigned DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY guest_id (guest_id)
		) $charset;";

		foreach ( $sql as $query ) {
			dbDelta( $query );
		}

		self::seed_prebuilt_segments();
		self::seed_default_workflows();
	}

	/**
	 * Seed prebuilt segments.
	 */
	private static function seed_prebuilt_segments(): void {
		global $wpdb;
		$table = self::table( 'segments' );
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $count > 0 ) {
			return;
		}

		$segments = array(
			array( 'name' => 'Guests with One Order', 'slug' => 'guests-one-order', 'rules' => array( 'logic' => 'AND', 'conditions' => array( array( 'field' => 'order_count', 'operator' => 'eq', 'value' => 1 ) ) ) ),
			array( 'name' => 'Guests with Multiple Orders', 'slug' => 'guests-multiple-orders', 'rules' => array( 'logic' => 'AND', 'conditions' => array( array( 'field' => 'order_count', 'operator' => 'gt', 'value' => 1 ) ) ) ),
			array( 'name' => 'High Spending Guests ($500+)', 'slug' => 'guests-high-spend', 'rules' => array( 'logic' => 'AND', 'conditions' => array( array( 'field' => 'total_spend', 'operator' => 'gte', 'value' => 500 ) ) ) ),
			array( 'name' => 'Inactive 90 Days', 'slug' => 'inactive-90-days', 'rules' => array( 'logic' => 'AND', 'conditions' => array( array( 'field' => 'days_since_order', 'operator' => 'gte', 'value' => 90 ) ) ) ),
			array( 'name' => 'High Value Abandoned Carts', 'slug' => 'high-value-abandoned', 'rules' => array( 'logic' => 'AND', 'conditions' => array( array( 'field' => 'abandoned_cart_value', 'operator' => 'gte', 'value' => 200 ) ) ) ),
		);

		foreach ( $segments as $seg ) {
			$wpdb->insert(
				$table,
				array(
					'name'        => $seg['name'],
					'slug'        => $seg['slug'],
					'rules'       => wp_json_encode( $seg['rules'] ),
					'is_prebuilt' => 1,
				),
				array( '%s', '%s', '%s', '%d' )
			);
		}
	}

	/**
	 * Seed default workflows.
	 */
	private static function seed_default_workflows(): void {
		global $wpdb;
		$table = self::table( 'workflows' );
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $count > 0 ) {
			return;
		}

		$workflows = array(
			array(
				'name'           => 'Guest Account Invitation',
				'trigger_type'   => 'guest_order_completed',
				'trigger_config' => array(),
				'steps'          => array(
					array( 'type' => 'send_email', 'template' => 'account_invitation', 'delay_hours' => 0 ),
					array( 'type' => 'generate_coupon', 'percent' => 10, 'delay_hours' => 1 ),
					array( 'type' => 'send_email', 'template' => 'discount_offer', 'delay_hours' => 1 ),
				),
			),
			array(
				'name'           => 'VIP Thank You',
				'trigger_type'   => 'order_total_over',
				'trigger_config' => array( 'amount' => 500 ),
				'steps'          => array(
					array( 'type' => 'send_email', 'template' => 'vip_thank_you', 'delay_hours' => 0 ),
					array( 'type' => 'send_whatsapp', 'template' => 'vip_coupon', 'delay_hours' => 2 ),
				),
			),
			array(
				'name'           => 'Inactive Customer Re-engagement',
				'trigger_type'   => 'no_order_days',
				'trigger_config' => array( 'days' => 90 ),
				'steps'          => array(
					array( 'type' => 'send_email', 'template' => 'reengagement', 'delay_hours' => 0 ),
					array( 'type' => 'generate_coupon', 'percent' => 15, 'delay_hours' => 0 ),
				),
			),
		);

		foreach ( $workflows as $wf ) {
			$wpdb->insert(
				$table,
				array(
					'name'           => $wf['name'],
					'trigger_type'   => $wf['trigger_type'],
					'trigger_config' => wp_json_encode( $wf['trigger_config'] ),
					'steps'          => wp_json_encode( $wf['steps'] ),
					'status'         => 'active',
				),
				array( '%s', '%s', '%s', '%s', '%s' )
			);
		}
	}
}
