<?php
/**
 * Admin assets.
 *
 * @package GCRM\Admin
 */

namespace GCRM\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class Assets
 */
class Assets {

	/**
	 * Enqueue on GCRM pages.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function enqueue( string $hook ): void {
		if ( strpos( $hook, 'gcrm' ) === false && strpos( $hook, 'woocommerce_page_gcrm' ) === false ) {
			return;
		}

		wp_enqueue_style( 'gcrm-admin', GCRM_PLUGIN_URL . 'admin/css/admin.css', array(), GCRM_VERSION );
		wp_enqueue_script( 'gcrm-datatables', 'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js', array( 'jquery' ), '1.13.8', true );
		wp_enqueue_style( 'gcrm-datatables', 'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css', array(), '1.13.8' );
		wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', array(), '4.4.1', true );
		wp_enqueue_script( 'gcrm-admin', GCRM_PLUGIN_URL . 'admin/js/admin.js', array( 'jquery', 'gcrm-datatables', 'chart-js' ), GCRM_VERSION, true );

		wp_enqueue_editor();
		wp_enqueue_media();

		wp_localize_script(
			'gcrm-admin',
			'gcrmAdmin',
			array(
				'restUrl'      => rest_url( 'gcrm/v1/' ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'placeholders' => \GCRM\Services\Email::PLACEHOLDERS,
				'i18n'         => array(
					'confirmBulk' => __( 'Send to selected customers?', 'gcrm' ),
				),
			)
		);
	}
}
