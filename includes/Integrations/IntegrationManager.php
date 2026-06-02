<?php
/**
 * Third-party integration manager (scaffolding).
 *
 * @package GCRM\Integrations
 */

namespace GCRM\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class IntegrationManager
 */
class IntegrationManager {

	/**
	 * Available integrations.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function available(): array {
		return array(
			'mailchimp'   => array( 'label' => 'Mailchimp', 'option' => 'gcrm_mailchimp_api_key' ),
			'brevo'       => array( 'label' => 'Brevo', 'option' => 'gcrm_brevo_api_key' ),
			'sendgrid'    => array( 'label' => 'SendGrid', 'option' => 'gcrm_sendgrid_api_key' ),
			'fluentcrm'   => array( 'label' => 'FluentCRM', 'option' => 'gcrm_fluentcrm_enabled' ),
			'elementor'   => array( 'label' => 'Elementor Forms', 'option' => 'gcrm_elementor_webhook' ),
			'cf7'         => array( 'label' => 'Contact Form 7', 'option' => 'gcrm_cf7_enabled' ),
			'wpforms'     => array( 'label' => 'WPForms', 'option' => 'gcrm_wpforms_enabled' ),
		);
	}

	/**
	 * Fire webhook for external integrations.
	 *
	 * @param string               $event Event name.
	 * @param array<string, mixed> $payload Payload.
	 */
	public static function webhook( string $event, array $payload ): void {
		$url = get_option( 'gcrm_webhook_url', '' );
		if ( ! $url ) {
			return;
		}
		wp_remote_post(
			$url,
			array(
				'headers' => array( 'Content-Type' => 'application/json', 'X-GCRM-Event' => $event ),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 15,
			)
		);
	}

	/**
	 * Sync guest to Mailchimp (when API key set).
	 *
	 * @param array<string, mixed> $guest Guest data.
	 */
	public static function sync_mailchimp( array $guest ): void {
		$api_key = get_option( 'gcrm_mailchimp_api_key', '' );
		$list_id = get_option( 'gcrm_mailchimp_list_id', '' );
		if ( ! $api_key || ! $list_id || empty( $guest['email'] ) ) {
			return;
		}
		$dc = substr( $api_key, strpos( $api_key, '-' ) + 1 );
		$url = "https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}/members";
		wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( 'user:' . $api_key ),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'email_address' => $guest['email'],
						'status'        => 'subscribed',
						'merge_fields'  => array(
							'FNAME' => $guest['first_name'] ?? '',
							'LNAME' => $guest['last_name'] ?? '',
						),
					)
				),
			)
		);
	}
}
