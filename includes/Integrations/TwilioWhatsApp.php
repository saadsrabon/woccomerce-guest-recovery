<?php
/**
 * Twilio WhatsApp API integration.
 *
 * @package GCRM\Integrations
 */

namespace GCRM\Integrations;

use GCRM\Core\PhpCompat;

defined( 'ABSPATH' ) || exit;

/**
 * Class TwilioWhatsApp
 */
class TwilioWhatsApp {

	/**
	 * Send via Twilio.
	 *
	 * @param string $phone To phone.
	 * @param string $message Message.
	 * @return string|\WP_Error
	 */
	public function send( string $phone, string $message ) {
		$sid        = get_option( 'gcrm_twilio_account_sid', '' );
		$auth_token = $this->get_token();
		$from       = get_option( 'gcrm_twilio_whatsapp_from', '' );

		if ( ! $sid || ! $auth_token || ! $from ) {
			return new \WP_Error( 'gcrm_twilio', __( 'Twilio credentials not configured.', 'gcrm' ) );
		}

		$phone = 'whatsapp:' . ( PhpCompat::str_starts_with( $phone, '+' ) ? $phone : '+' . $phone );
		$url   = 'https://api.twilio.com/2010-04-01/Accounts/' . $sid . '/Messages.json';

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $sid . ':' . $auth_token ),
				),
				'body'    => array(
					'From' => 'whatsapp:' . $from,
					'To'   => $phone,
					'Body' => $message,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! empty( $data['sid'] ) ) {
			return $data['sid'];
		}
		return new \WP_Error( 'gcrm_twilio', $data['message'] ?? __( 'Twilio send failed.', 'gcrm' ) );
	}

	/**
	 * Get auth token (encrypted option).
	 */
	private function get_token(): string {
		$encrypted = get_option( 'gcrm_twilio_auth_token', '' );
		if ( PhpCompat::str_starts_with( (string) $encrypted, 'enc:' ) && function_exists( 'openssl_decrypt' ) ) {
			$key = wp_salt( 'auth' );
			$raw = base64_decode( substr( $encrypted, 4 ), true );
			if ( $raw ) {
				$parts = explode( '::', $raw, 2 );
				if ( count( $parts ) === 2 ) {
					return (string) openssl_decrypt( $parts[1], 'AES-256-CBC', $key, 0, $parts[0] );
				}
			}
		}
		return (string) $encrypted;
	}
}
