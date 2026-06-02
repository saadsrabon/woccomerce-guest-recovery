<?php
/**
 * Lightweight XLSX writer (ZIP + SpreadsheetML). Zero external deps.
 *
 * @package GCRM\Services
 */

namespace GCRM\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Class XlsxWriter
 */
class XlsxWriter {

	/**
	 * Sheets data.
	 *
	 * @var array<int, array{name: string, headers: array<int, string>, rows: array<int, array<int, mixed>>}>
	 */
	private array $sheets = array();

	/**
	 * Add sheet.
	 *
	 * @param string                             $name Sheet name.
	 * @param array<int, string>                 $headers Headers.
	 * @param array<int, array<int, mixed>>      $rows Rows.
	 */
	public function add_sheet( string $name, array $headers, array $rows ): void {
		$this->sheets[] = array(
			'name'    => $name,
			'headers' => $headers,
			'rows'    => $rows,
		);
	}

	/**
	 * Output file and exit.
	 *
	 * @param string $filename Filename.
	 */
	public function output( string $filename ): void {
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'ZipArchive is required for Excel export. Please enable the PHP zip extension.', 'gcrm' ) );
		}

		$tmp = wp_tempnam( 'gcrm-xlsx' );
		if ( ! $tmp ) {
			wp_die( esc_html__( 'Could not create temporary file.', 'gcrm' ) );
		}

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $tmp, \ZipArchive::OVERWRITE ) ) {
			wp_die( esc_html__( 'Could not create XLSX archive.', 'gcrm' ) );
		}

		$zip->addFromString( '[Content_Types].xml', $this->content_types() );
		$zip->addFromString( '_rels/.rels', $this->rels() );
		$zip->addFromString( 'xl/workbook.xml', $this->workbook() );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', $this->workbook_rels() );
		$zip->addFromString( 'xl/styles.xml', $this->styles() );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', $this->sheet_xml( 0 ) );
		$zip->close();

		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp );
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		exit;
	}

	/**
	 * Build sheet XML.
	 *
	 * @param int $index Sheet index.
	 */
	private function sheet_xml( int $index ): string {
		$sheet = $this->sheets[ $index ] ?? $this->sheets[0];
		$rows  = array_merge( array( $sheet['headers'] ), $sheet['rows'] );
		$xml   = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$xml  .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
		$r = 1;
		foreach ( $rows as $row ) {
			$xml .= '<row r="' . $r . '">';
			$c = 0;
			foreach ( $row as $cell ) {
				$col   = $this->col_letter( $c );
				$value = htmlspecialchars( (string) $cell, ENT_XML1 );
				$xml  .= '<c r="' . $col . $r . '" t="inlineStr"><is><t>' . $value . '</t></is></c>';
				++$c;
			}
			$xml .= '</row>';
			++$r;
		}
		$xml .= '</sheetData></worksheet>';
		return $xml;
	}

	/**
	 * Column letter from index.
	 *
	 * @param int $index Zero-based index.
	 */
	private function col_letter( int $index ): string {
		$letter = '';
		++$index;
		while ( $index > 0 ) {
			$mod    = ( $index - 1 ) % 26;
			$letter = chr( 65 + $mod ) . $letter;
			$index  = (int) floor( ( $index - $mod ) / 26 );
		}
		return $letter;
	}

	private function content_types(): string {
		return '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
		<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
		<Default Extension="xml" ContentType="application/xml"/>
		<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
		<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
		<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
		</Types>';
	}

	private function rels(): string {
		return '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
		<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
		</Relationships>';
	}

	private function workbook(): string {
		return '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
		<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>';
	}

	private function workbook_rels(): string {
		return '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
		<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
		<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
		</Relationships>';
	}

	private function styles(): string {
		return '<?xml version="1.0"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"/><fills count="1"/><borders count="1"/></styleSheet>';
	}
}
