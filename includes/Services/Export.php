<?php
/**
 * CSV and XLSX export service.
 *
 * @package GCRM\Services
 */

namespace GCRM\Services;

use GCRM\DB\Repositories\GuestRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class Export
 */
class Export {

	/**
	 * Export headers.
	 *
	 * @return array<int, string>
	 */
	public static function headers(): array {
		return array(
			'Name',
			'Email',
			'Phone',
			'Address',
			'City',
			'State',
			'Country',
			'Zip Code',
			'Order Count',
			'Total Spend',
			'Last Order Date',
		);
	}

	/**
	 * Build rows from guests.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return array<int, array<int, string|int|float>>
	 */
	public function get_rows( array $filters = array() ): array {
		$repo   = new GuestRepository();
		$guests = $repo->get_for_export( $filters );
		$rows   = array();

		foreach ( $guests as $g ) {
			$billing = json_decode( $g['billing_address'] ?? '{}', true );
			$address = is_array( $billing ) ? implode( ' ', array_filter( array( $billing['address_1'] ?? '', $billing['address_2'] ?? '' ) ) ) : '';
			$rows[]  = array(
				trim( ( $g['first_name'] ?? '' ) . ' ' . ( $g['last_name'] ?? '' ) ),
				$g['email'] ?? '',
				$g['phone'] ?? '',
				$address,
				$g['city'] ?? '',
				$g['state'] ?? '',
				$g['country'] ?? '',
				$g['zip'] ?? '',
				(int) ( $g['order_count'] ?? 0 ),
				(float) ( $g['total_spend'] ?? 0 ),
				$g['last_order_date'] ?? '',
			);
		}
		return $rows;
	}

	/**
	 * Output CSV download.
	 *
	 * @param array<string, mixed> $filters Filters.
	 */
	public function download_csv( array $filters = array() ): void {
		$filename = 'guest-customers-' . gmdate( 'Y-m-d' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			return;
		}
		fputcsv( $out, self::headers() );
		foreach ( $this->get_rows( $filters ) as $row ) {
			fputcsv( $out, $row );
		}
		fclose( $out );
		exit;
	}

	/**
	 * Output XLSX download (SpreadsheetML in ZIP).
	 *
	 * @param array<string, mixed> $filters Filters.
	 */
	public function download_xlsx( array $filters = array() ): void {
		$writer = new XlsxWriter();
		$writer->add_sheet( 'Guests', self::headers(), $this->get_rows( $filters ) );
		$writer->output( 'guest-customers-' . gmdate( 'Y-m-d' ) . '.xlsx' );
	}
}
