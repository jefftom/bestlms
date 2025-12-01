<?php
/**
 * Awarded Certificates Database Table
 *
 * @package SwiftLMS\Modules\Certificates
 * @since 1.0.0
 */

namespace SwiftLMS\Modules\Certificates;

defined( 'ABSPATH' ) || exit;

/**
 * Certificates table class.
 */
class CertificatesTable {

    /**
     * Table name without prefix.
     *
     * @var string
     */
    const TABLE_NAME = 'swiftlms_certificates';

    /**
     * Get full table name with prefix.
     *
     * @return string
     */
    public static function get_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Create the table.
     *
     * @return void
     */
    public static function create_table(): void {
        global $wpdb;

        $table_name      = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            certificate_code VARCHAR(50) NOT NULL UNIQUE,
            user_id BIGINT UNSIGNED NOT NULL,
            course_id BIGINT UNSIGNED NOT NULL,
            template_id BIGINT UNSIGNED NOT NULL,
            issued_at DATETIME NOT NULL,
            expires_at DATETIME DEFAULT NULL,
            status ENUM('active', 'revoked', 'expired') DEFAULT 'active',
            pdf_path VARCHAR(500) DEFAULT NULL,
            meta_data LONGTEXT COMMENT 'JSON of additional data at time of issue',
            revoked_at DATETIME DEFAULT NULL,
            revoked_by BIGINT UNSIGNED DEFAULT NULL,
            revoke_reason TEXT DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX idx_certificate_code (certificate_code),
            INDEX idx_user_id (user_id),
            INDEX idx_course_id (course_id),
            INDEX idx_user_course (user_id, course_id),
            INDEX idx_status (status),
            INDEX idx_issued_at (issued_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Generate unique certificate code.
     *
     * @return string
     */
    public static function generate_code(): string {
        $prefix = apply_filters( 'swiftlms_certificate_code_prefix', 'CERT' );
        $length = apply_filters( 'swiftlms_certificate_code_length', 12 );

        do {
            $code = $prefix . '-' . strtoupper( wp_generate_password( $length, false, false ) );
        } while ( self::code_exists( $code ) );

        return $code;
    }

    /**
     * Check if certificate code exists.
     *
     * @param string $code Certificate code.
     * @return bool
     */
    public static function code_exists( string $code ): bool {
        global $wpdb;

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM " . self::get_table_name() . " WHERE certificate_code = %s",
                $code
            )
        );
    }

    /**
     * Issue a new certificate.
     *
     * @param int   $user_id     User ID.
     * @param int   $course_id   Course ID.
     * @param int   $template_id Template ID.
     * @param array $meta_data   Additional metadata.
     * @return int|false Certificate ID or false.
     */
    public static function issue( int $user_id, int $course_id, int $template_id, array $meta_data = array() ) {
        global $wpdb;

        // Check if already issued.
        $existing = self::get_by_user_course( $user_id, $course_id );
        if ( $existing && 'active' === $existing->status ) {
            return (int) $existing->id;
        }

        $code = self::generate_code();

        // Get user and course data for metadata.
        $user   = get_userdata( $user_id );
        $course = get_post( $course_id );

        $meta = array_merge(
            array(
                'student_name'    => $user ? $user->display_name : '',
                'student_email'   => $user ? $user->user_email : '',
                'course_title'    => $course ? $course->post_title : '',
                'completion_date' => current_time( 'Y-m-d' ),
            ),
            $meta_data
        );

        $result = $wpdb->insert(
            self::get_table_name(),
            array(
                'certificate_code' => $code,
                'user_id'          => $user_id,
                'course_id'        => $course_id,
                'template_id'      => $template_id,
                'issued_at'        => current_time( 'mysql' ),
                'status'           => 'active',
                'meta_data'        => wp_json_encode( $meta ),
            ),
            array( '%s', '%d', '%d', '%d', '%s', '%s', '%s' )
        );

        if ( ! $result ) {
            return false;
        }

        $cert_id = $wpdb->insert_id;

        /**
         * Fires when a certificate is issued.
         *
         * @param int    $cert_id   Certificate ID.
         * @param string $code      Certificate code.
         * @param int    $user_id   User ID.
         * @param int    $course_id Course ID.
         */
        do_action( 'swiftlms_certificate_issued', $cert_id, $code, $user_id, $course_id );

        return $cert_id;
    }

    /**
     * Get certificate by ID.
     *
     * @param int $id Certificate ID.
     * @return object|null
     */
    public static function get( int $id ): ?object {
        global $wpdb;

        $cert = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::get_table_name() . " WHERE id = %d",
                $id
            )
        );

        if ( $cert && $cert->meta_data ) {
            $cert->meta = json_decode( $cert->meta_data, true );
        }

        return $cert;
    }

    /**
     * Get certificate by code.
     *
     * @param string $code Certificate code.
     * @return object|null
     */
    public static function get_by_code( string $code ): ?object {
        global $wpdb;

        $cert = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::get_table_name() . " WHERE certificate_code = %s",
                $code
            )
        );

        if ( $cert && $cert->meta_data ) {
            $cert->meta = json_decode( $cert->meta_data, true );
        }

        return $cert;
    }

    /**
     * Get certificate by user and course.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course ID.
     * @return object|null
     */
    public static function get_by_user_course( int $user_id, int $course_id ): ?object {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::get_table_name() . "
                WHERE user_id = %d AND course_id = %d
                ORDER BY issued_at DESC
                LIMIT 1",
                $user_id,
                $course_id
            )
        );
    }

    /**
     * Get all certificates for a user.
     *
     * @param int    $user_id User ID.
     * @param string $status  Optional status filter.
     * @return array
     */
    public static function get_user_certificates( int $user_id, string $status = '' ): array {
        global $wpdb;

        $sql = "SELECT c.*, p.post_title as course_title
                FROM " . self::get_table_name() . " c
                LEFT JOIN {$wpdb->posts} p ON c.course_id = p.ID
                WHERE c.user_id = %d";

        $params = array( $user_id );

        if ( $status ) {
            $sql     .= " AND c.status = %s";
            $params[] = $status;
        }

        $sql .= " ORDER BY c.issued_at DESC";

        return $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
    }

    /**
     * Get certificates for a course.
     *
     * @param int $course_id Course ID.
     * @param int $limit     Limit results.
     * @param int $offset    Offset.
     * @return array
     */
    public static function get_course_certificates( int $course_id, int $limit = 50, int $offset = 0 ): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.*, u.display_name as student_name
                FROM " . self::get_table_name() . " c
                LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
                WHERE c.course_id = %d
                ORDER BY c.issued_at DESC
                LIMIT %d OFFSET %d",
                $course_id,
                $limit,
                $offset
            )
        );
    }

    /**
     * Update PDF path for certificate.
     *
     * @param int    $id       Certificate ID.
     * @param string $pdf_path Path to PDF file.
     * @return bool
     */
    public static function update_pdf_path( int $id, string $pdf_path ): bool {
        global $wpdb;

        return (bool) $wpdb->update(
            self::get_table_name(),
            array( 'pdf_path' => $pdf_path ),
            array( 'id' => $id ),
            array( '%s' ),
            array( '%d' )
        );
    }

    /**
     * Revoke a certificate.
     *
     * @param int    $id       Certificate ID.
     * @param int    $admin_id Admin user ID revoking.
     * @param string $reason   Reason for revocation.
     * @return bool
     */
    public static function revoke( int $id, int $admin_id, string $reason = '' ): bool {
        global $wpdb;

        $result = $wpdb->update(
            self::get_table_name(),
            array(
                'status'        => 'revoked',
                'revoked_at'    => current_time( 'mysql' ),
                'revoked_by'    => $admin_id,
                'revoke_reason' => $reason,
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%d', '%s' ),
            array( '%d' )
        );

        if ( $result ) {
            $cert = self::get( $id );

            /**
             * Fires when a certificate is revoked.
             *
             * @param int    $id       Certificate ID.
             * @param object $cert     Certificate data.
             * @param int    $admin_id Admin who revoked.
             * @param string $reason   Revocation reason.
             */
            do_action( 'swiftlms_certificate_revoked', $id, $cert, $admin_id, $reason );
        }

        return (bool) $result;
    }

    /**
     * Verify a certificate.
     *
     * @param string $code Certificate code.
     * @return array Verification result.
     */
    public static function verify( string $code ): array {
        $cert = self::get_by_code( $code );

        if ( ! $cert ) {
            return array(
                'valid'   => false,
                'message' => __( 'Certificate not found.', 'swiftlms' ),
            );
        }

        if ( 'revoked' === $cert->status ) {
            return array(
                'valid'   => false,
                'message' => __( 'This certificate has been revoked.', 'swiftlms' ),
                'cert'    => $cert,
            );
        }

        if ( 'expired' === $cert->status ) {
            return array(
                'valid'   => false,
                'message' => __( 'This certificate has expired.', 'swiftlms' ),
                'cert'    => $cert,
            );
        }

        if ( $cert->expires_at && strtotime( $cert->expires_at ) < time() ) {
            // Update status to expired.
            global $wpdb;
            $wpdb->update(
                self::get_table_name(),
                array( 'status' => 'expired' ),
                array( 'id' => $cert->id )
            );

            return array(
                'valid'   => false,
                'message' => __( 'This certificate has expired.', 'swiftlms' ),
                'cert'    => $cert,
            );
        }

        // Get additional details.
        $user   = get_userdata( $cert->user_id );
        $course = get_post( $cert->course_id );

        return array(
            'valid'       => true,
            'message'     => __( 'Certificate is valid.', 'swiftlms' ),
            'cert'        => $cert,
            'student'     => $user ? $user->display_name : ( $cert->meta['student_name'] ?? '' ),
            'course'      => $course ? $course->post_title : ( $cert->meta['course_title'] ?? '' ),
            'issued_date' => $cert->issued_at,
        );
    }

    /**
     * Get statistics.
     *
     * @return array
     */
    public static function get_stats(): array {
        global $wpdb;

        $table = self::get_table_name();

        return array(
            'total'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ),
            'active'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'active'" ),
            'revoked'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'revoked'" ),
            'this_month'  => (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE issued_at >= %s",
                    gmdate( 'Y-m-01 00:00:00' )
                )
            ),
        );
    }
}
