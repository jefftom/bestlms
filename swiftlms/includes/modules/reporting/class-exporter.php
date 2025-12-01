<?php
/**
 * Report Exporter
 *
 * @package SwiftLMS
 */

namespace SwiftLMS\Modules\Reporting;

defined( 'ABSPATH' ) || exit;

/**
 * Exporter class for generating CSV/Excel reports.
 */
class Exporter {

    /**
     * Export types.
     */
    const TYPE_ENROLLMENTS = 'enrollments';
    const TYPE_PROGRESS    = 'progress';
    const TYPE_STUDENTS    = 'students';
    const TYPE_QUIZZES     = 'quizzes';
    const TYPE_COURSES     = 'courses';
    const TYPE_REVENUE     = 'revenue';

    /**
     * Export data to CSV.
     *
     * @param string $type       Export type.
     * @param array  $filters    Filters.
     * @param string $filename   Filename.
     */
    public static function export_csv( string $type, array $filters = array(), string $filename = '' ): void {
        $data    = self::get_export_data( $type, $filters );
        $headers = self::get_headers( $type );

        if ( empty( $data ) ) {
            wp_die( __( 'No data to export.', 'swiftlms' ) );
        }

        $filename = $filename ?: 'swiftlms-' . $type . '-' . date( 'Y-m-d' ) . '.csv';

        // Set headers for download
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        // Add BOM for Excel UTF-8 compatibility
        fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

        // Write headers
        fputcsv( $output, $headers );

        // Write data rows
        foreach ( $data as $row ) {
            fputcsv( $output, self::format_row( $row, $type ) );
        }

        fclose( $output );
        exit;
    }

    /**
     * Get export data.
     *
     * @param string $type    Export type.
     * @param array  $filters Filters.
     * @return array
     */
    public static function get_export_data( string $type, array $filters = array() ): array {
        global $wpdb;

        $start_date = $filters['start_date'] ?? '';
        $end_date   = $filters['end_date'] ?? '';
        $course_id  = $filters['course_id'] ?? 0;

        switch ( $type ) {
            case self::TYPE_ENROLLMENTS:
                return self::get_enrollments_data( $start_date, $end_date, $course_id );

            case self::TYPE_PROGRESS:
                return self::get_progress_data( $course_id );

            case self::TYPE_STUDENTS:
                return Analytics::get_student_analytics( 0, 0 );

            case self::TYPE_QUIZZES:
                return self::get_quiz_data( $course_id );

            case self::TYPE_COURSES:
                return Analytics::get_course_analytics( $course_id );

            case self::TYPE_REVENUE:
                return self::get_revenue_data( $start_date, $end_date );

            default:
                return array();
        }
    }

    /**
     * Get enrollments data.
     *
     * @param string $start_date Start date.
     * @param string $end_date   End date.
     * @param int    $course_id  Course ID.
     * @return array
     */
    private static function get_enrollments_data( string $start_date, string $end_date, int $course_id ): array {
        global $wpdb;

        $where = "WHERE 1=1";

        if ( $start_date && $end_date ) {
            $where .= $wpdb->prepare(
                " AND e.created_at BETWEEN %s AND %s",
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59'
            );
        }

        if ( $course_id ) {
            $where .= $wpdb->prepare( " AND e.course_id = %d", $course_id );
        }

        return $wpdb->get_results(
            "SELECT
                e.id,
                u.display_name as student_name,
                u.user_email as student_email,
                c.post_title as course_title,
                e.status,
                e.created_at as enrolled_date,
                e.expires_at,
                COALESCE(pr.progress_percent, 0) as progress
            FROM {$wpdb->prefix}swiftlms_enrollments e
            LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
            LEFT JOIN {$wpdb->posts} c ON e.course_id = c.ID
            LEFT JOIN (
                SELECT user_id, content_id,
                       (completed_items / NULLIF(total_items, 0)) * 100 as progress_percent
                FROM {$wpdb->prefix}swiftlms_progress
                WHERE content_type = 'course'
            ) pr ON e.user_id = pr.user_id AND e.course_id = pr.content_id
            {$where}
            ORDER BY e.created_at DESC",
            ARRAY_A
        );
    }

    /**
     * Get progress data.
     *
     * @param int $course_id Course ID.
     * @return array
     */
    private static function get_progress_data( int $course_id ): array {
        global $wpdb;

        $where = $course_id ? $wpdb->prepare( "AND e.course_id = %d", $course_id ) : '';

        return $wpdb->get_results(
            "SELECT
                u.display_name as student_name,
                u.user_email as student_email,
                c.post_title as course_title,
                COALESCE(pr.completed_items, 0) as lessons_completed,
                COALESCE(pr.total_items, 0) as total_lessons,
                ROUND(COALESCE((pr.completed_items / NULLIF(pr.total_items, 0)) * 100, 0), 1) as progress_percent,
                COALESCE(pr.time_spent, 0) as time_spent_seconds,
                pr.completed_at,
                pr.updated_at as last_activity
            FROM {$wpdb->prefix}swiftlms_enrollments e
            LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
            LEFT JOIN {$wpdb->posts} c ON e.course_id = c.ID
            LEFT JOIN {$wpdb->prefix}swiftlms_progress pr
                ON e.user_id = pr.user_id AND e.course_id = pr.content_id AND pr.content_type = 'course'
            WHERE e.status != 'expired' {$where}
            ORDER BY c.post_title, u.display_name",
            ARRAY_A
        );
    }

    /**
     * Get quiz data.
     *
     * @param int $course_id Course ID.
     * @return array
     */
    private static function get_quiz_data( int $course_id ): array {
        global $wpdb;

        $where = '';
        if ( $course_id ) {
            $where = $wpdb->prepare(
                "AND a.quiz_id IN (
                    SELECT post_id FROM {$wpdb->postmeta}
                    WHERE meta_key = '_sfls_course_id' AND meta_value = %d
                )",
                $course_id
            );
        }

        return $wpdb->get_results(
            "SELECT
                u.display_name as student_name,
                u.user_email as student_email,
                q.post_title as quiz_title,
                a.attempt_number,
                a.score,
                a.total_points,
                a.percentage,
                CASE WHEN a.passed = 1 THEN 'Passed' ELSE 'Failed' END as status,
                a.time_taken as time_seconds,
                a.started_at,
                a.completed_at
            FROM {$wpdb->prefix}swiftlms_quiz_attempts a
            LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
            LEFT JOIN {$wpdb->posts} q ON a.quiz_id = q.ID
            WHERE 1=1 {$where}
            ORDER BY a.started_at DESC",
            ARRAY_A
        );
    }

    /**
     * Get revenue data.
     *
     * @param string $start_date Start date.
     * @param string $end_date   End date.
     * @return array
     */
    private static function get_revenue_data( string $start_date, string $end_date ): array {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return array();
        }

        global $wpdb;

        $where = '';
        if ( $start_date && $end_date ) {
            $where = $wpdb->prepare(
                "AND p.post_date BETWEEN %s AND %s",
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59'
            );
        }

        return $wpdb->get_results(
            "SELECT
                p.ID as order_id,
                p.post_date as order_date,
                pm.meta_value as order_total,
                u.display_name as customer_name,
                u.user_email as customer_email,
                cp.post_title as course_title,
                oim2.meta_value as line_total
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total'
            INNER JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_customer_user'
            LEFT JOIN {$wpdb->users} u ON pm_customer.meta_value = u.ID
            INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
                AND oim.meta_key = '_product_id'
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id
                AND oim2.meta_key = '_line_total'
            INNER JOIN {$wpdb->postmeta} course_link ON oim.meta_value = course_link.post_id
                AND course_link.meta_key = '_sfls_linked_course'
            LEFT JOIN {$wpdb->posts} cp ON course_link.meta_value = cp.ID
            WHERE p.post_type = 'shop_order'
                AND p.post_status IN ('wc-completed', 'wc-processing')
                {$where}
            ORDER BY p.post_date DESC",
            ARRAY_A
        );
    }

    /**
     * Get column headers.
     *
     * @param string $type Export type.
     * @return array
     */
    private static function get_headers( string $type ): array {
        $headers = array(
            self::TYPE_ENROLLMENTS => array(
                'ID', 'Student Name', 'Email', 'Course', 'Status', 'Enrolled Date', 'Expires', 'Progress %'
            ),
            self::TYPE_PROGRESS => array(
                'Student Name', 'Email', 'Course', 'Lessons Completed', 'Total Lessons',
                'Progress %', 'Time Spent', 'Completed Date', 'Last Activity'
            ),
            self::TYPE_STUDENTS => array(
                'User ID', 'Name', 'Email', 'Registered', 'Enrolled Courses', 'Completed Courses',
                'Lessons Completed', 'Quizzes Passed', 'Avg Quiz Score', 'Total Time', 'Last Activity'
            ),
            self::TYPE_QUIZZES => array(
                'Student Name', 'Email', 'Quiz', 'Attempt #', 'Score', 'Total Points',
                'Percentage', 'Status', 'Time Taken', 'Started', 'Completed'
            ),
            self::TYPE_COURSES => array(
                'Course ID', 'Course Title', 'Total Enrolled', 'Completed', 'In Progress',
                'Completion Rate %', 'Avg Progress %'
            ),
            self::TYPE_REVENUE => array(
                'Order ID', 'Date', 'Order Total', 'Customer', 'Email', 'Course', 'Line Total'
            ),
        );

        return $headers[ $type ] ?? array();
    }

    /**
     * Format row for export.
     *
     * @param array  $row  Row data.
     * @param string $type Export type.
     * @return array
     */
    private static function format_row( array $row, string $type ): array {
        switch ( $type ) {
            case self::TYPE_ENROLLMENTS:
                return array(
                    $row['id'],
                    $row['student_name'],
                    $row['student_email'],
                    $row['course_title'],
                    ucfirst( $row['status'] ),
                    self::format_date( $row['enrolled_date'] ),
                    $row['expires_at'] ? self::format_date( $row['expires_at'] ) : 'Never',
                    round( $row['progress'], 1 ) . '%',
                );

            case self::TYPE_PROGRESS:
                return array(
                    $row['student_name'],
                    $row['student_email'],
                    $row['course_title'],
                    $row['lessons_completed'],
                    $row['total_lessons'],
                    $row['progress_percent'] . '%',
                    self::format_time( $row['time_spent_seconds'] ),
                    $row['completed_at'] ? self::format_date( $row['completed_at'] ) : '',
                    $row['last_activity'] ? self::format_date( $row['last_activity'] ) : '',
                );

            case self::TYPE_STUDENTS:
                return array(
                    $row['user_id'],
                    $row['display_name'],
                    $row['user_email'],
                    self::format_date( $row['user_registered'] ),
                    $row['enrolled_courses'],
                    $row['completed_courses'],
                    $row['lessons_completed'],
                    $row['quizzes_passed'],
                    $row['avg_quiz_score'] ? round( $row['avg_quiz_score'], 1 ) . '%' : 'N/A',
                    self::format_time( $row['total_time_spent'] ),
                    $row['last_activity'] ? self::format_date( $row['last_activity'] ) : '',
                );

            case self::TYPE_QUIZZES:
                return array(
                    $row['student_name'],
                    $row['student_email'],
                    $row['quiz_title'],
                    $row['attempt_number'],
                    $row['score'],
                    $row['total_points'],
                    round( $row['percentage'], 1 ) . '%',
                    $row['status'],
                    self::format_time( $row['time_seconds'] ),
                    self::format_date( $row['started_at'] ),
                    $row['completed_at'] ? self::format_date( $row['completed_at'] ) : '',
                );

            case self::TYPE_COURSES:
                return array(
                    $row['course_id'],
                    $row['course_title'],
                    $row['total_enrolled'],
                    $row['completed'],
                    $row['in_progress'],
                    $row['completion_rate'] . '%',
                    $row['avg_progress'] . '%',
                );

            case self::TYPE_REVENUE:
                return array(
                    $row['order_id'],
                    self::format_date( $row['order_date'] ),
                    wc_price( $row['order_total'] ),
                    $row['customer_name'],
                    $row['customer_email'],
                    $row['course_title'],
                    wc_price( $row['line_total'] ),
                );

            default:
                return array_values( $row );
        }
    }

    /**
     * Format date for export.
     *
     * @param string $date Date string.
     * @return string
     */
    private static function format_date( string $date ): string {
        if ( empty( $date ) ) {
            return '';
        }
        return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $date ) );
    }

    /**
     * Format time duration.
     *
     * @param int $seconds Seconds.
     * @return string
     */
    private static function format_time( ?int $seconds ): string {
        if ( ! $seconds ) {
            return '0m';
        }

        $hours   = floor( $seconds / 3600 );
        $minutes = floor( ( $seconds % 3600 ) / 60 );

        if ( $hours > 0 ) {
            return sprintf( '%dh %dm', $hours, $minutes );
        }

        return sprintf( '%dm', $minutes );
    }
}
