<?php
/**
 * Meta WhatsApp Cloud API integration.
 *
 * @package GCRM\Integrations
 */

namespace GCRM\Integrations;

use GCRM\Core\PhpCompat;

defined( 'ABSPATH' ) || exit;

/**
 * Class WhatsAppCloud
 */
class WhatsAppCloud {

	/**
	 * Send message via Cloud API.
	 *
	 * @param string $phone Phone number (E.164).
	 * @param string $message Message text.
	 * @return string|\WP_Error Message ID or error.
	 */
	public function send( string $phone, string $message ) {
		$token       = $this->get_credential( 'gcrm_whatsapp_cloud_token' );
		$phone_id    = get_option( 'gcrm_whatsapp_phone_id', '' );
		$api_version = get_option( 'gcrm_whatsapp_api_version', 'v18.0' );

		if ( ! $token || ! $phone_id ) {
			return new \WP_Error( 'gcrm_whatsapp', __( 'WhatsApp Cloud API credentials not configured.', 'gcrm' ) );
		}

		$phone = ltrim( $phone, '+' );
		$url   = "https://graph.facebook.com/{$api_version}/{$phone_id}/messages";

		$body = array(
			'messaging_product' => 'whatsapp',
			'to'                => $phone,
			'type'              => 'text',
			'text'              => array( 'body' => $message ),
		);

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			return new \WP_Error( 'gcrm_whatsapp', $data['error']['message'] ?? __( 'WhatsApp API error.', 'gcrm' ) );
		}

		return $data['messages'][0]['id'] ?? 'sent';
	}

	/**
	 * Get decrypted credential.
	 *
	 * @param string $option Option name.
	 */
	private function get_credential( string $option ): string {
		$encrypted = get_option( $option, '' );
		if ( ! $encrypted ) {
			return '';
		}
		if ( function_exists( 'openssl_decrypt' ) && PhpCompat::str_starts_with( $encrypted, 'enc:' ) ) {
			$key = wp_salt( 'auth' );
			$raw = base64_decode( substr( $encrypted, 4 ), true );
			if ( $raw ) {
				$parts = explode( '::', $raw, 2 );
				if ( count( $parts ) === 2 ) {
					$decrypted = openssl_decrypt( $parts[1], 'AES-256-CBC', $key, 0, $parts[0] );
					return $decrypted ?: '';
				}
			}
		}
		return (string) $encrypted;
	}

	/**
	 * Encrypt and store credential.
	 *
	 * @param string $option Option name.
	 * @param string $value Plain value.
	 */
	public static function store_credential( string $option, string $value ): void {
		if ( ! $value ) {
			delete_option( $option );
			return;
		}
		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv  = openssl_random_pseudo_bytes( 16 );
			$enc = openssl_encrypt( $value, 'AES-256-CBC', wp_salt( 'auth' ), 0, $iv );
			update_option( $option, 'enc:' . base64_encode( $iv . '::' . $enc ) );
			return;
		}
		update_option( $option, $value );
	}
}
