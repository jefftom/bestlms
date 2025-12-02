<?php
/**
 * Google Sheets Parser
 *
 * @package SwiftLMS\Modules\GoogleDocsImport
 */

namespace SwiftLMS\Modules\GoogleDocsImport;

defined( 'ABSPATH' ) || exit;

/**
 * Sheets_Parser class.
 *
 * Parses Google Sheets data for bulk imports.
 */
class Sheets_Parser {

    /**
     * Sheets API endpoint.
     */
    const SHEETS_API = 'https://sheets.googleapis.com/v4/spreadsheets/';

    /**
     * Import types and their expected columns.
     */
    const IMPORT_TYPES = array(
        'students' => array(
            'label'    => 'Students',
            'required' => array( 'email' ),
            'optional' => array( 'first_name', 'last_name', 'username', 'password', 'role' ),
            'description' => 'Bulk create student accounts',
        ),
        'enrollments' => array(
            'label'    => 'Enrollments',
            'required' => array( 'email', 'course' ),
            'optional' => array( 'status', 'enrolled_date', 'expiry_date' ),
            'description' => 'Enroll students in courses',
        ),
        'courses' => array(
            'label'    => 'Courses',
            'required' => array( 'title' ),
            'optional' => array( 'description', 'price', 'category', 'instructor', 'status', 'difficulty', 'duration' ),
            'description' => 'Create multiple courses at once',
        ),
        'lessons' => array(
            'label'    => 'Lessons',
            'required' => array( 'title', 'course' ),
            'optional' => array( 'content', 'type', 'video_url', 'duration', 'order', 'section' ),
            'description' => 'Add lessons to existing courses',
        ),
        'quiz_questions' => array(
            'label'    => 'Quiz Questions',
            'required' => array( 'question', 'quiz' ),
            'optional' => array( 'type', 'answers', 'correct_answer', 'points', 'explanation' ),
            'description' => 'Import quiz questions in bulk',
        ),
        'coupons' => array(
            'label'    => 'Coupons',
            'required' => array( 'code' ),
            'optional' => array( 'discount_type', 'amount', 'courses', 'usage_limit', 'expiry_date' ),
            'description' => 'Create discount coupons',
        ),
        'certificates' => array(
            'label'    => 'Certificate Awards',
            'required' => array( 'email', 'course' ),
            'optional' => array( 'date_awarded', 'certificate_id' ),
            'description' => 'Award certificates to students',
        ),
        'grades' => array(
            'label'    => 'Grades',
            'required' => array( 'email', 'assignment' ),
            'optional' => array( 'grade', 'feedback', 'status' ),
            'description' => 'Import assignment grades',
        ),
    );

    /**
     * Get spreadsheet data.
     *
     * @param string $spreadsheet_id Spreadsheet ID.
     * @param string $range          Range (e.g., "Sheet1!A1:Z100").
     * @return array|WP_Error
     */
    public static function get_spreadsheet_data( string $spreadsheet_id, string $range = '' ) {
        $url = self::SHEETS_API . $spreadsheet_id;

        if ( $range ) {
            $url .= '/values/' . urlencode( $range );
        }

        $url .= '?valueRenderOption=FORMATTED_VALUE';

        return Google_API_Client::request( $url );
    }

    /**
     * Get spreadsheet metadata.
     *
     * @param string $spreadsheet_id Spreadsheet ID.
     * @return array|WP_Error
     */
    public static function get_spreadsheet_metadata( string $spreadsheet_id ) {
        $url = self::SHEETS_API . $spreadsheet_id . '?fields=properties.title,sheets.properties';
        return Google_API_Client::request( $url );
    }

    /**
     * Get available sheets in spreadsheet.
     *
     * @param string $spreadsheet_id Spreadsheet ID.
     * @return array|WP_Error
     */
    public static function get_sheets_list( string $spreadsheet_id ) {
        $metadata = self::get_spreadsheet_metadata( $spreadsheet_id );

        if ( is_wp_error( $metadata ) ) {
            return $metadata;
        }

        $sheets = array();
        foreach ( $metadata['sheets'] ?? array() as $sheet ) {
            $props = $sheet['properties'] ?? array();
            $sheets[] = array(
                'id'    => $props['sheetId'] ?? 0,
                'title' => $props['title'] ?? 'Unnamed',
                'index' => $props['index'] ?? 0,
            );
        }

        return array(
            'title'  => $metadata['properties']['title'] ?? '',
            'sheets' => $sheets,
        );
    }

    /**
     * Parse sheet data into structured format.
     *
     * @param array $raw_data   Raw spreadsheet values.
     * @param array $column_map Column mapping (column_name => header_index).
     * @return array
     */
    public static function parse_sheet_data( array $raw_data, array $column_map ): array {
        $values = $raw_data['values'] ?? array();

        if ( count( $values ) < 2 ) {
            return array();
        }

        // First row is headers.
        $headers = array_shift( $values );
        $rows = array();

        foreach ( $values as $row_index => $row ) {
            $parsed_row = array(
                '_row_number' => $row_index + 2, // +2 because 1-indexed and header row.
            );

            foreach ( $column_map as $field => $column_index ) {
                if ( $column_index !== null && isset( $row[ $column_index ] ) ) {
                    $parsed_row[ $field ] = trim( $row[ $column_index ] );
                } else {
                    $parsed_row[ $field ] = '';
                }
            }

            // Skip completely empty rows.
            $has_data = false;
            foreach ( $parsed_row as $key => $value ) {
                if ( $key !== '_row_number' && ! empty( $value ) ) {
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
     * Auto-detect column mapping from headers.
     *
     * @param array  $headers     Header row.
     * @param string $import_type Import type.
     * @return array
     */
    public static function auto_detect_columns( array $headers, string $import_type ): array {
        $type_config = self::IMPORT_TYPES[ $import_type ] ?? array();
        $all_columns = array_merge(
            $type_config['required'] ?? array(),
            $type_config['optional'] ?? array()
        );

        $mapping = array();
        $normalized_headers = array_map( function( $h ) {
            return strtolower( preg_replace( '/[^a-z0-9]/', '_', strtolower( trim( $h ) ) ) );
        }, $headers );

        // Common aliases for column names.
        $aliases = array(
            'email'          => array( 'email', 'email_address', 'e_mail', 'student_email', 'user_email' ),
            'first_name'     => array( 'first_name', 'firstname', 'first', 'given_name' ),
            'last_name'      => array( 'last_name', 'lastname', 'last', 'surname', 'family_name' ),
            'username'       => array( 'username', 'user_name', 'login', 'user' ),
            'password'       => array( 'password', 'pass', 'pwd' ),
            'title'          => array( 'title', 'name', 'course_title', 'lesson_title', 'quiz_title' ),
            'description'    => array( 'description', 'desc', 'summary', 'content' ),
            'course'         => array( 'course', 'course_title', 'course_name', 'course_id' ),
            'price'          => array( 'price', 'cost', 'amount' ),
            'category'       => array( 'category', 'categories', 'cat' ),
            'status'         => array( 'status', 'state' ),
            'question'       => array( 'question', 'question_text', 'q' ),
            'answers'        => array( 'answers', 'options', 'choices' ),
            'correct_answer' => array( 'correct_answer', 'correct', 'answer', 'right_answer' ),
            'type'           => array( 'type', 'question_type', 'lesson_type' ),
            'points'         => array( 'points', 'score', 'value' ),
            'video_url'      => array( 'video_url', 'video', 'video_link', 'url' ),
            'duration'       => array( 'duration', 'length', 'time' ),
            'order'          => array( 'order', 'position', 'sort_order', 'sequence' ),
            'section'        => array( 'section', 'module', 'chapter' ),
            'quiz'           => array( 'quiz', 'quiz_title', 'quiz_name', 'quiz_id' ),
            'instructor'     => array( 'instructor', 'teacher', 'author' ),
            'difficulty'     => array( 'difficulty', 'level', 'skill_level' ),
            'code'           => array( 'code', 'coupon_code', 'coupon' ),
            'discount_type'  => array( 'discount_type', 'type' ),
            'usage_limit'    => array( 'usage_limit', 'limit', 'max_uses' ),
            'expiry_date'    => array( 'expiry_date', 'expiry', 'expires', 'end_date', 'valid_until' ),
            'grade'          => array( 'grade', 'score', 'marks' ),
            'feedback'       => array( 'feedback', 'comments', 'notes' ),
            'assignment'     => array( 'assignment', 'assignment_title', 'assignment_name' ),
            'enrolled_date'  => array( 'enrolled_date', 'enrollment_date', 'date_enrolled', 'start_date' ),
            'date_awarded'   => array( 'date_awarded', 'awarded_date', 'date' ),
            'certificate_id' => array( 'certificate_id', 'certificate', 'cert_id' ),
            'role'           => array( 'role', 'user_role' ),
            'explanation'    => array( 'explanation', 'hint', 'feedback' ),
        );

        foreach ( $all_columns as $column ) {
            $mapping[ $column ] = null;

            // Check aliases.
            $column_aliases = $aliases[ $column ] ?? array( $column );

            foreach ( $normalized_headers as $index => $header ) {
                if ( in_array( $header, $column_aliases, true ) ) {
                    $mapping[ $column ] = $index;
                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * Validate parsed data.
     *
     * @param array  $data        Parsed data rows.
     * @param string $import_type Import type.
     * @return array Validation result with errors.
     */
    public static function validate_data( array $data, string $import_type ): array {
        $type_config = self::IMPORT_TYPES[ $import_type ] ?? array();
        $required = $type_config['required'] ?? array();

        $errors = array();
        $valid_rows = array();

        foreach ( $data as $row ) {
            $row_errors = array();
            $row_number = $row['_row_number'];

            // Check required fields.
            foreach ( $required as $field ) {
                if ( empty( $row[ $field ] ) ) {
                    $row_errors[] = sprintf(
                        __( 'Row %d: Missing required field "%s"', 'swiftlms' ),
                        $row_number,
                        $field
                    );
                }
            }

            // Type-specific validation.
            switch ( $import_type ) {
                case 'students':
                case 'enrollments':
                case 'certificates':
                case 'grades':
                    if ( ! empty( $row['email'] ) && ! is_email( $row['email'] ) ) {
                        $row_errors[] = sprintf(
                            __( 'Row %d: Invalid email address "%s"', 'swiftlms' ),
                            $row_number,
                            $row['email']
                        );
                    }
                    break;

                case 'courses':
                    if ( ! empty( $row['price'] ) && ! is_numeric( $row['price'] ) ) {
                        $row_errors[] = sprintf(
                            __( 'Row %d: Price must be a number', 'swiftlms' ),
                            $row_number
                        );
                    }
                    break;

                case 'quiz_questions':
                    if ( empty( $row['answers'] ) && empty( $row['correct_answer'] ) ) {
                        $row_errors[] = sprintf(
                            __( 'Row %d: Question needs answers', 'swiftlms' ),
                            $row_number
                        );
                    }
                    break;
            }

            if ( empty( $row_errors ) ) {
                $valid_rows[] = $row;
            } else {
                $errors = array_merge( $errors, $row_errors );
            }
        }

        return array(
            'valid'      => empty( $errors ),
            'valid_rows' => $valid_rows,
            'errors'     => $errors,
            'stats'      => array(
                'total'   => count( $data ),
                'valid'   => count( $valid_rows ),
                'invalid' => count( $data ) - count( $valid_rows ),
            ),
        );
    }

    /**
     * Extract spreadsheet ID from URL.
     *
     * @param string $url Spreadsheet URL.
     * @return string|null
     */
    public static function extract_spreadsheet_id( string $url ): ?string {
        // Match: https://docs.google.com/spreadsheets/d/SPREADSHEET_ID/...
        if ( preg_match( '/spreadsheets\/d\/([a-zA-Z0-9_-]+)/', $url, $matches ) ) {
            return $matches[1];
        }

        // If it looks like just an ID.
        if ( preg_match( '/^[a-zA-Z0-9_-]{20,}$/', $url ) ) {
            return $url;
        }

        return null;
    }

    /**
     * Get import type configuration.
     *
     * @param string $type Import type.
     * @return array|null
     */
    public static function get_import_type_config( string $type ): ?array {
        return self::IMPORT_TYPES[ $type ] ?? null;
    }

    /**
     * Get all import types.
     *
     * @return array
     */
    public static function get_all_import_types(): array {
        return self::IMPORT_TYPES;
    }

    /**
     * Generate sample CSV template for import type.
     *
     * @param string $import_type Import type.
     * @return string CSV content.
     */
    public static function generate_template( string $import_type ): string {
        $config = self::IMPORT_TYPES[ $import_type ] ?? null;

        if ( ! $config ) {
            return '';
        }

        $headers = array_merge( $config['required'], $config['optional'] );
        $csv = implode( ',', $headers ) . "\n";

        // Add sample row.
        $samples = self::get_sample_data( $import_type );
        if ( $samples ) {
            $row = array();
            foreach ( $headers as $header ) {
                $row[] = $samples[ $header ] ?? '';
            }
            $csv .= implode( ',', $row ) . "\n";
        }

        return $csv;
    }

    /**
     * Get sample data for template.
     *
     * @param string $import_type Import type.
     * @return array
     */
    private static function get_sample_data( string $import_type ): array {
        $samples = array(
            'students' => array(
                'email'      => 'student@example.com',
                'first_name' => 'John',
                'last_name'  => 'Doe',
                'username'   => 'johndoe',
                'password'   => 'securepass123',
                'role'       => 'student',
            ),
            'enrollments' => array(
                'email'         => 'student@example.com',
                'course'        => 'Introduction to Marketing',
                'status'        => 'enrolled',
                'enrolled_date' => '2024-01-15',
                'expiry_date'   => '',
            ),
            'courses' => array(
                'title'       => 'Introduction to Marketing',
                'description' => 'Learn the fundamentals of marketing',
                'price'       => '99.00',
                'category'    => 'Business',
                'instructor'  => 'admin@example.com',
                'status'      => 'draft',
                'difficulty'  => 'beginner',
                'duration'    => '4 hours',
            ),
            'lessons' => array(
                'title'     => 'Getting Started',
                'course'    => 'Introduction to Marketing',
                'content'   => 'Welcome to the course...',
                'type'      => 'text',
                'video_url' => '',
                'duration'  => '15 minutes',
                'order'     => '1',
                'section'   => 'Module 1',
            ),
            'quiz_questions' => array(
                'question'       => 'What is the primary goal of marketing?',
                'quiz'           => 'Marketing Basics Quiz',
                'type'           => 'multiple_choice',
                'answers'        => 'Sales|Brand awareness|Customer satisfaction|All of the above',
                'correct_answer' => 'All of the above',
                'points'         => '10',
                'explanation'    => 'Marketing encompasses all these goals.',
            ),
            'coupons' => array(
                'code'          => 'SAVE20',
                'discount_type' => 'percent',
                'amount'        => '20',
                'courses'       => 'all',
                'usage_limit'   => '100',
                'expiry_date'   => '2024-12-31',
            ),
            'certificates' => array(
                'email'          => 'student@example.com',
                'course'         => 'Introduction to Marketing',
                'date_awarded'   => '2024-02-01',
                'certificate_id' => '',
            ),
            'grades' => array(
                'email'      => 'student@example.com',
                'assignment' => 'Week 1 Assignment',
                'grade'      => '85',
                'feedback'   => 'Good work!',
                'status'     => 'graded',
            ),
        );

        return $samples[ $import_type ] ?? array();
    }
}
