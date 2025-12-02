<?php
/**
 * Excel Spreadsheet Parser
 *
 * Parses .xlsx files without external dependencies.
 * Uses native PHP ZipArchive to read the XML structure.
 *
 * @package SwiftLMS\Modules\GoogleDocsImport
 */

namespace SwiftLMS\Modules\GoogleDocsImport;

defined( 'ABSPATH' ) || exit;

/**
 * Excel_Parser class.
 *
 * Parses Microsoft Excel spreadsheets (.xlsx) for bulk import.
 */
class Excel_Parser {

    /**
     * Excel namespace URIs.
     */
    const NS_SPREADSHEET = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    const NS_RELATIONSHIPS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * ZipArchive instance.
     *
     * @var \ZipArchive
     */
    private $zip;

    /**
     * Shared strings.
     *
     * @var array
     */
    private $shared_strings = array();

    /**
     * Worksheets info.
     *
     * @var array
     */
    private $worksheets = array();

    /**
     * Workbook title.
     *
     * @var string
     */
    private $title = '';

    /**
     * File path.
     *
     * @var string
     */
    private $file_path;

    /**
     * Constructor.
     *
     * @param string $file_path Path to .xlsx file.
     */
    public function __construct( string $file_path ) {
        $this->file_path = $file_path;
    }

    /**
     * Open and parse the workbook structure.
     *
     * @return true|WP_Error
     */
    public function open(): bool|\WP_Error {
        if ( ! file_exists( $this->file_path ) ) {
            return new \WP_Error( 'file_not_found', __( 'File not found.', 'swiftlms' ) );
        }

        if ( ! class_exists( 'ZipArchive' ) ) {
            return new \WP_Error( 'zip_not_available', __( 'ZipArchive extension is required.', 'swiftlms' ) );
        }

        $this->zip = new \ZipArchive();
        $result = $this->zip->open( $this->file_path );

        if ( true !== $result ) {
            return new \WP_Error( 'invalid_xlsx', __( 'Could not open spreadsheet. Make sure it is a valid .xlsx file.', 'swiftlms' ) );
        }

        // Load shared strings.
        $this->load_shared_strings();

        // Load workbook info.
        $this->load_workbook();

        return true;
    }

    /**
     * Close the workbook.
     */
    public function close(): void {
        if ( $this->zip ) {
            $this->zip->close();
        }
    }

    /**
     * Load shared strings.
     */
    private function load_shared_strings(): void {
        $content = $this->zip->getFromName( 'xl/sharedStrings.xml' );
        if ( false === $content ) {
            return;
        }

        $xml = simplexml_load_string( $content );
        if ( false === $xml ) {
            return;
        }

        $xml->registerXPathNamespace( 'x', self::NS_SPREADSHEET );

        foreach ( $xml->xpath( '//x:si' ) as $si ) {
            // Handle rich text (multiple t elements) and plain text.
            $text_parts = array();
            foreach ( $si->xpath( './/x:t' ) as $t ) {
                $text_parts[] = (string) $t;
            }
            $this->shared_strings[] = implode( '', $text_parts );
        }
    }

    /**
     * Load workbook info.
     */
    private function load_workbook(): void {
        $content = $this->zip->getFromName( 'xl/workbook.xml' );
        if ( false === $content ) {
            return;
        }

        $xml = simplexml_load_string( $content );
        if ( false === $xml ) {
            return;
        }

        $xml->registerXPathNamespace( 'x', self::NS_SPREADSHEET );

        // Get sheets.
        foreach ( $xml->xpath( '//x:sheet' ) as $sheet ) {
            $attrs = $sheet->attributes();
            $name = (string) $attrs['name'];
            $sheet_id = (string) $attrs['sheetId'];

            // Get relationship ID for sheet path.
            $r_attrs = $sheet->attributes( self::NS_RELATIONSHIPS );
            $r_id = isset( $r_attrs['id'] ) ? (string) $r_attrs['id'] : 'rId' . $sheet_id;

            $this->worksheets[] = array(
                'name'     => $name,
                'sheet_id' => $sheet_id,
                'r_id'     => $r_id,
            );
        }

        // Get title from properties.
        $props = $this->zip->getFromName( 'docProps/core.xml' );
        if ( false !== $props ) {
            $props_xml = simplexml_load_string( $props );
            if ( $props_xml ) {
                $namespaces = $props_xml->getNamespaces( true );
                if ( isset( $namespaces['dc'] ) ) {
                    $dc = $props_xml->children( $namespaces['dc'] );
                    if ( isset( $dc->title ) ) {
                        $this->title = (string) $dc->title;
                    }
                }
            }
        }
    }

    /**
     * Get workbook title.
     *
     * @return string
     */
    public function get_title(): string {
        return $this->title ?: basename( $this->file_path, '.xlsx' );
    }

    /**
     * Get list of worksheets.
     *
     * @return array
     */
    public function get_worksheets(): array {
        return array_map( function( $ws ) {
            return array(
                'title'    => $ws['name'],
                'sheet_id' => $ws['sheet_id'],
            );
        }, $this->worksheets );
    }

    /**
     * Read a worksheet.
     *
     * @param string|int $sheet Sheet name or index (0-based).
     * @return array|WP_Error
     */
    public function read_sheet( $sheet = 0 ): array|\WP_Error {
        // Find the sheet.
        $sheet_info = null;
        if ( is_int( $sheet ) ) {
            $sheet_info = $this->worksheets[ $sheet ] ?? null;
        } else {
            foreach ( $this->worksheets as $ws ) {
                if ( $ws['name'] === $sheet ) {
                    $sheet_info = $ws;
                    break;
                }
            }
        }

        if ( ! $sheet_info ) {
            return new \WP_Error( 'sheet_not_found', __( 'Worksheet not found.', 'swiftlms' ) );
        }

        // Get sheet file path from relationships.
        $sheet_path = $this->get_sheet_path( $sheet_info['r_id'] );
        if ( ! $sheet_path ) {
            // Try default path.
            $index = array_search( $sheet_info, $this->worksheets );
            $sheet_path = 'xl/worksheets/sheet' . ( $index + 1 ) . '.xml';
        }

        $content = $this->zip->getFromName( $sheet_path );
        if ( false === $content ) {
            return new \WP_Error( 'sheet_read_error', __( 'Could not read worksheet.', 'swiftlms' ) );
        }

        $xml = simplexml_load_string( $content );
        if ( false === $xml ) {
            return new \WP_Error( 'parse_error', __( 'Could not parse worksheet.', 'swiftlms' ) );
        }

        $xml->registerXPathNamespace( 'x', self::NS_SPREADSHEET );

        // Parse rows.
        $data = array(
            'values' => array(),
        );

        $rows = $xml->xpath( '//x:sheetData/x:row' );
        $max_col = 0;

        foreach ( $rows as $row ) {
            $row->registerXPathNamespace( 'x', self::NS_SPREADSHEET );
            $row_num = (int) $row['r'];
            $row_data = array();

            $cells = $row->xpath( './/x:c' );
            foreach ( $cells as $cell ) {
                $cell_ref = (string) $cell['r'];
                $col_index = $this->col_letter_to_index( preg_replace( '/[0-9]/', '', $cell_ref ) );

                // Get cell value.
                $value = $this->get_cell_value( $cell );

                // Ensure array is large enough.
                while ( count( $row_data ) < $col_index ) {
                    $row_data[] = '';
                }
                $row_data[ $col_index ] = $value;
                $max_col = max( $max_col, $col_index + 1 );
            }

            // Pad row to max columns.
            while ( count( $row_data ) < $max_col ) {
                $row_data[] = '';
            }

            $data['values'][] = $row_data;
        }

        // Ensure all rows have same column count.
        foreach ( $data['values'] as &$row ) {
            while ( count( $row ) < $max_col ) {
                $row[] = '';
            }
        }

        return $data;
    }

    /**
     * Get sheet path from relationships.
     *
     * @param string $r_id Relationship ID.
     * @return string|null
     */
    private function get_sheet_path( string $r_id ): ?string {
        $rels_content = $this->zip->getFromName( 'xl/_rels/workbook.xml.rels' );
        if ( false === $rels_content ) {
            return null;
        }

        $xml = simplexml_load_string( $rels_content );
        if ( false === $xml ) {
            return null;
        }

        foreach ( $xml->Relationship as $rel ) {
            if ( (string) $rel['Id'] === $r_id ) {
                return 'xl/' . (string) $rel['Target'];
            }
        }

        return null;
    }

    /**
     * Get cell value.
     *
     * @param \SimpleXMLElement $cell Cell element.
     * @return string
     */
    private function get_cell_value( \SimpleXMLElement $cell ): string {
        $type = (string) $cell['t'];
        $value = '';

        // Get value element.
        $v = $cell->v;
        if ( ! $v && isset( $cell->is->t ) ) {
            // Inline string.
            return (string) $cell->is->t;
        }

        if ( ! $v ) {
            return '';
        }

        $raw_value = (string) $v;

        switch ( $type ) {
            case 's': // Shared string.
                $index = (int) $raw_value;
                $value = $this->shared_strings[ $index ] ?? '';
                break;

            case 'b': // Boolean.
                $value = $raw_value === '1' ? 'TRUE' : 'FALSE';
                break;

            case 'e': // Error.
                $value = '#ERROR';
                break;

            case 'str': // Formula string.
            case 'inlineStr': // Inline string.
                $value = $raw_value;
                break;

            default: // Number or date.
                // Check for date format (basic check).
                $style = (string) $cell['s'];
                if ( $this->is_date_cell( $style, $raw_value ) ) {
                    $value = $this->excel_date_to_string( (float) $raw_value );
                } else {
                    $value = $raw_value;
                }
                break;
        }

        return $value;
    }

    /**
     * Check if cell is a date.
     *
     * @param string $style_index Style index.
     * @param string $value       Cell value.
     * @return bool
     */
    private function is_date_cell( string $style_index, string $value ): bool {
        // Basic heuristic: if value is a number between reasonable date range.
        if ( ! is_numeric( $value ) ) {
            return false;
        }

        $num = (float) $value;
        // Excel dates typically range from 1 (Jan 1, 1900) to ~55000+ (year 2050+).
        // We'll use a simple range check.
        return $num > 1 && $num < 100000 && floor( $num ) === $num;
    }

    /**
     * Convert Excel date serial to string.
     *
     * @param float $serial Excel date serial number.
     * @return string
     */
    private function excel_date_to_string( float $serial ): string {
        // Excel epoch is December 30, 1899 (accounting for the 1900 leap year bug).
        // For simplicity, we'll use Unix timestamp conversion.
        if ( $serial < 1 ) {
            return '';
        }

        // Days since 1899-12-30.
        $unix_days = $serial - 25569; // Days between 1899-12-30 and 1970-01-01.
        $timestamp = $unix_days * 86400;

        return gmdate( 'Y-m-d', (int) $timestamp );
    }

    /**
     * Convert column letter to index.
     *
     * @param string $letters Column letters (A, B, ..., Z, AA, AB, ...).
     * @return int 0-based index.
     */
    private function col_letter_to_index( string $letters ): int {
        $letters = strtoupper( $letters );
        $index = 0;
        $len = strlen( $letters );

        for ( $i = 0; $i < $len; $i++ ) {
            $index = $index * 26 + ( ord( $letters[ $i ] ) - ord( 'A' ) + 1 );
        }

        return $index - 1;
    }

    /**
     * Parse sheet data for import (compatible with Sheets_Parser).
     *
     * @param array  $raw_data    Raw sheet data from read_sheet().
     * @param array  $column_map  Column mapping.
     * @return array Parsed rows.
     */
    public static function parse_sheet_data( array $raw_data, array $column_map ): array {
        $values = $raw_data['values'] ?? array();
        if ( count( $values ) < 2 ) {
            return array();
        }

        $headers = $values[0];
        $rows = array();

        // Build header index map.
        $header_indices = array();
        foreach ( $headers as $index => $header ) {
            $header_indices[ $header ] = $index;
        }

        // Parse data rows.
        for ( $i = 1; $i < count( $values ); $i++ ) {
            $row = $values[ $i ];
            $parsed_row = array();

            foreach ( $column_map as $field => $header ) {
                if ( empty( $header ) || ! isset( $header_indices[ $header ] ) ) {
                    $parsed_row[ $field ] = '';
                    continue;
                }

                $col_index = $header_indices[ $header ];
                $parsed_row[ $field ] = $row[ $col_index ] ?? '';
            }

            // Skip completely empty rows.
            $has_data = false;
            foreach ( $parsed_row as $value ) {
                if ( ! empty( trim( $value ) ) ) {
                    $has_data = true;
                    break;
                }
            }

            if ( $has_data ) {
                $rows[] = $parsed_row;
            }
        }

        return $rows;
    }

    /**
     * Auto-detect column mapping.
     *
     * @param array  $headers     Header row.
     * @param string $import_type Import type.
     * @return array
     */
    public static function auto_detect_columns( array $headers, string $import_type ): array {
        // Reuse Sheets_Parser logic.
        return Sheets_Parser::auto_detect_columns( $headers, $import_type );
    }

    /**
     * Validate parsed data.
     *
     * @param array  $rows        Parsed rows.
     * @param string $import_type Import type.
     * @return array
     */
    public static function validate_data( array $rows, string $import_type ): array {
        // Reuse Sheets_Parser logic.
        return Sheets_Parser::validate_data( $rows, $import_type );
    }

    /**
     * Read CSV file and return in same format as read_sheet().
     *
     * @param string $file_path Path to CSV file.
     * @return array|WP_Error
     */
    public static function read_csv( string $file_path ): array|\WP_Error {
        if ( ! file_exists( $file_path ) ) {
            return new \WP_Error( 'file_not_found', __( 'File not found.', 'swiftlms' ) );
        }

        $handle = fopen( $file_path, 'r' );
        if ( false === $handle ) {
            return new \WP_Error( 'file_read_error', __( 'Could not read file.', 'swiftlms' ) );
        }

        $data = array(
            'values' => array(),
        );

        // Detect delimiter.
        $first_line = fgets( $handle );
        rewind( $handle );

        $comma_count = substr_count( $first_line, ',' );
        $semicolon_count = substr_count( $first_line, ';' );
        $tab_count = substr_count( $first_line, "\t" );

        $delimiter = ',';
        if ( $semicolon_count > $comma_count && $semicolon_count > $tab_count ) {
            $delimiter = ';';
        } elseif ( $tab_count > $comma_count && $tab_count > $semicolon_count ) {
            $delimiter = "\t";
        }

        // Read rows.
        while ( ( $row = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
            $data['values'][] = $row;
        }

        fclose( $handle );

        return $data;
    }
}
