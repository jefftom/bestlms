<?php
/**
 * Analytics Data Aggregation
 *
 * @package SwiftLMS
 */

namespace SwiftLMS\Modules\Reporting;

defined( 'ABSPATH' ) || exit;

/**
 * Analytics class for data aggregation.
 */
class Analytics {

    /**
     * Get overview statistics.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return array
     */
    public static function get_overview_stats( string $start_date = '', string $end_date = '' ): array {
        global $wpdb;

        $date_where = self::get_date_where( $start_date, $end_date, 'created_at' );

        // Total students (users with enrollments)
        $total_students = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}swiftlms_enrollments"
        );

        // New students in period
        $new_students = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}swiftlms_enrollments WHERE 1=1 {$date_where}"
        );

        // Total enrollments
        $total_enrollments = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}swiftlms_enrollments"
        );

        // New enrollments in period
        $new_enrollments = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}swiftlms_enrollments WHERE 1=1 {$date_where}"
        );

        // Course completions
        $completions = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}swiftlms_progress
                WHERE content_type = %s AND completed = 1" . str_replace( 'created_at', 'completed_at', $date_where ),
                'course'
            )
        );

        // Lessons completed
        $lessons_completed = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}swiftlms_progress
                WHERE content_type = %s AND completed = 1" . str_replace( 'created_at', 'completed_at', $date_where ),
                'lesson'
            )
        );

        // Quiz attempts
        $quiz_attempts = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}swiftlms_quiz_attempts WHERE 1=1 " . str_replace( 'created_at', 'started_at', $date_where )
        );

        // Quiz pass rate
        $quiz_stats = $wpdb->get_row(
            "SELECT COUNT(*) as total, SUM(passed) as passed
            FROM {$wpdb->prefix}swiftlms_quiz_attempts
            WHERE 1=1 " . str_replace( 'created_at', 'started_at', $date_where )
        );
        $quiz_pass_rate = $quiz_stats->total > 0 ? round( ( $quiz_stats->passed / $quiz_stats->total ) * 100, 1 ) : 0;

        // Certificates issued
        $certificates = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}swiftlms_certificates WHERE 1=1 " . str_replace( 'created_at', 'issued_at', $date_where )
        );

        // Average course completion rate
        $avg_completion = self::get_average_completion_rate();

        return array(
            'total_students'     => $total_students,
            'new_students'       => $new_students,
            'total_enrollments'  => $total_enrollments,
            'new_enrollments'    => $new_enrollments,
            'completions'        => $completions,
            'lessons_completed'  => $lessons_completed,
            'quiz_attempts'      => $quiz_attempts,
            'quiz_pass_rate'     => $quiz_pass_rate,
            'certificates'       => $certificates,
            'avg_completion_rate' => $avg_completion,
        );
    }

    /**
     * Get enrollment trends.
     *
     * @param string $start_date Start date.
     * @param string $end_date   End date.
     * @param string $interval   Interval: day, week, month.
     * @return array
     */
    public static function get_enrollment_trends( string $start_date, string $end_date, string $interval = 'day' ): array {
        global $wpdb;

        $date_format = self::get_date_format( $interval );
        $date_where  = self::get_date_where( $start_date, $end_date, 'created_at' );

        $results = $wpdb->get_results(
            "SELECT DATE_FORMAT(created_at, '{$date_format}') as period,
                    COUNT(*) as enrollments,
                    COUNT(DISTINCT user_id) as unique_students
            FROM {$wpdb->prefix}swiftlms_enrollments
            WHERE 1=1 {$date_where}
            GROUP BY period
            ORDER BY period ASC"
        );

        return self::fill_date_gaps( $results, $start_date, $end_date, $interval, array( 'enrollments' => 0, 'unique_students' => 0 ) );
    }

    /**
     * Get completion trends.
     *
     * @param string $start_date Start date.
     * @param string $end_date   End date.
     * @param string $interval   Interval.
     * @return array
     */
    public static function get_completion_trends( string $start_date, string $end_date, string $interval = 'day' ): array {
        global $wpdb;

        $date_format = self::get_date_format( $interval );
        $date_where  = self::get_date_where( $start_date, $end_date, 'completed_at' );

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE_FORMAT(completed_at, '{$date_format}') as period,
                        SUM(CASE WHEN content_type = %s THEN 1 ELSE 0 END) as courses,
                        SUM(CASE WHEN content_type = %s THEN 1 ELSE 0 END) as lessons
                FROM {$wpdb->prefix}swiftlms_progress
                WHERE completed = 1 {$date_where}
                GROUP BY period
                ORDER BY period ASC",
                'course',
                'lesson'
            )
        );

        return self::fill_date_gaps( $results, $start_date, $end_date, $interval, array( 'courses' => 0, 'lessons' => 0 ) );
    }

    /**
     * Get course analytics.
     *
     * @param int $course_id Course ID (0 for all).
     * @return array
     */
    public static function get_course_analytics( int $course_id = 0 ): array {
        global $wpdb;

        $where = $course_id ? $wpdb->prepare( "AND e.course_id = %d", $course_id ) : '';

        $courses = $wpdb->get_results(
            "SELECT
                e.course_id,
                p.post_title as course_title,
                COUNT(DISTINCT e.user_id) as total_enrolled,
                SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN e.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                AVG(pr.progress_percent) as avg_progress
            FROM {$wpdb->prefix}swiftlms_enrollments e
            LEFT JOIN {$wpdb->posts} p ON e.course_id = p.ID
            LEFT JOIN (
                SELECT user_id, content_id,
                       (completed_items / NULLIF(total_items, 0)) * 100 as progress_percent
                FROM {$wpdb->prefix}swiftlms_progress
                WHERE content_type = 'course'
            ) pr ON e.user_id = pr.user_id AND e.course_id = pr.content_id
            WHERE e.status != 'expired' {$where}
            GROUP BY e.course_id
            ORDER BY total_enrolled DESC"
        );

        $result = array();
        foreach ( $courses as $course ) {
            $completion_rate = $course->total_enrolled > 0
                ? round( ( $course->completed / $course->total_enrolled ) * 100, 1 )
                : 0;

            $result[] = array(
                'course_id'       => (int) $course->course_id,
                'course_title'    => $course->course_title,
                'total_enrolled'  => (int) $course->total_enrolled,
                'completed'       => (int) $course->completed,
                'in_progress'     => (int) $course->in_progress,
                'completion_rate' => $completion_rate,
                'avg_progress'    => round( (float) $course->avg_progress, 1 ),
            );
        }

        return $result;
    }

    /**
     * Get lesson analytics for a course.
     *
     * @param int $course_id Course ID.
     * @return array
     */
    public static function get_lesson_analytics( int $course_id ): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    p.ID as lesson_id,
                    p.post_title as lesson_title,
                    p.menu_order,
                    COUNT(DISTINCT pr.user_id) as views,
                    SUM(CASE WHEN pr.completed = 1 THEN 1 ELSE 0 END) as completions,
                    AVG(pr.time_spent) as avg_time_spent
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->prefix}swiftlms_progress pr
                    ON p.ID = pr.content_id AND pr.content_type = 'lesson'
                WHERE p.post_type = 'sfls_lesson'
                    AND p.post_status = 'publish'
                    AND p.ID IN (
                        SELECT post_id FROM {$wpdb->postmeta}
                        WHERE meta_key = '_sfls_course_id' AND meta_value = %d
                    )
                GROUP BY p.ID
                ORDER BY p.menu_order ASC",
                $course_id
            ),
            ARRAY_A
        );
    }

    /**
     * Get quiz analytics.
     *
     * @param int $course_id Course ID (0 for all).
     * @return array
     */
    public static function get_quiz_analytics( int $course_id = 0 ): array {
        global $wpdb;

        $where = '';
        if ( $course_id ) {
            $where = $wpdb->prepare(
                "AND q.ID IN (
                    SELECT post_id FROM {$wpdb->postmeta}
                    WHERE meta_key = '_sfls_course_id' AND meta_value = %d
                )",
                $course_id
            );
        }

        return $wpdb->get_results(
            "SELECT
                q.ID as quiz_id,
                q.post_title as quiz_title,
                COUNT(a.id) as total_attempts,
                COUNT(DISTINCT a.user_id) as unique_users,
                SUM(a.passed) as passed,
                AVG(a.percentage) as avg_score,
                AVG(a.time_taken) as avg_time
            FROM {$wpdb->posts} q
            LEFT JOIN {$wpdb->prefix}swiftlms_quiz_attempts a ON q.ID = a.quiz_id
            WHERE q.post_type = 'sfls_quiz' AND q.post_status = 'publish' {$where}
            GROUP BY q.ID
            ORDER BY total_attempts DESC",
            ARRAY_A
        );
    }

    /**
     * Get student analytics.
     *
     * @param int $user_id User ID (0 for all).
     * @param int $limit   Limit.
     * @return array
     */
    public static function get_student_analytics( int $user_id = 0, int $limit = 50 ): array {
        global $wpdb;

        $where = $user_id ? $wpdb->prepare( "AND e.user_id = %d", $user_id ) : '';
        $limit_sql = $limit > 0 ? "LIMIT {$limit}" : '';

        return $wpdb->get_results(
            "SELECT
                u.ID as user_id,
                u.display_name,
                u.user_email,
                u.user_registered,
                COUNT(DISTINCT e.course_id) as enrolled_courses,
                SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) as completed_courses,
                (SELECT COUNT(*) FROM {$wpdb->prefix}swiftlms_progress
                    WHERE user_id = u.ID AND content_type = 'lesson' AND completed = 1) as lessons_completed,
                (SELECT COUNT(*) FROM {$wpdb->prefix}swiftlms_quiz_attempts
                    WHERE user_id = u.ID AND passed = 1) as quizzes_passed,
                (SELECT AVG(percentage) FROM {$wpdb->prefix}swiftlms_quiz_attempts
                    WHERE user_id = u.ID) as avg_quiz_score,
                (SELECT SUM(time_spent) FROM {$wpdb->prefix}swiftlms_progress
                    WHERE user_id = u.ID) as total_time_spent,
                (SELECT MAX(updated_at) FROM {$wpdb->prefix}swiftlms_progress
                    WHERE user_id = u.ID) as last_activity
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->prefix}swiftlms_enrollments e ON u.ID = e.user_id
            WHERE 1=1 {$where}
            GROUP BY u.ID
            ORDER BY enrolled_courses DESC
            {$limit_sql}",
            ARRAY_A
        );
    }

    /**
     * Get detailed student report.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public static function get_student_report( int $user_id ): array {
        global $wpdb;

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return array();
        }

        // Enrollments
        $enrollments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.*, p.post_title as course_title,
                        pr.progress_percent, pr.completed_items, pr.total_items
                FROM {$wpdb->prefix}swiftlms_enrollments e
                LEFT JOIN {$wpdb->posts} p ON e.course_id = p.ID
                LEFT JOIN (
                    SELECT content_id,
                           (completed_items / NULLIF(total_items, 0)) * 100 as progress_percent,
                           completed_items, total_items
                    FROM {$wpdb->prefix}swiftlms_progress
                    WHERE user_id = %d AND content_type = 'course'
                ) pr ON e.course_id = pr.content_id
                WHERE e.user_id = %d
                ORDER BY e.created_at DESC",
                $user_id,
                $user_id
            ),
            ARRAY_A
        );

        // Quiz attempts
        $quiz_attempts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.*, p.post_title as quiz_title
                FROM {$wpdb->prefix}swiftlms_quiz_attempts a
                LEFT JOIN {$wpdb->posts} p ON a.quiz_id = p.ID
                WHERE a.user_id = %d
                ORDER BY a.started_at DESC
                LIMIT 20",
                $user_id
            ),
            ARRAY_A
        );

        // Certificates
        $certificates = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.*, p.post_title as course_title
                FROM {$wpdb->prefix}swiftlms_certificates c
                LEFT JOIN {$wpdb->posts} p ON c.course_id = p.ID
                WHERE c.user_id = %d
                ORDER BY c.issued_at DESC",
                $user_id
            ),
            ARRAY_A
        );

        // Activity timeline
        $activity = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 'lesson' as type, content_id, completed_at as activity_date, p.post_title
                FROM {$wpdb->prefix}swiftlms_progress pr
                LEFT JOIN {$wpdb->posts} p ON pr.content_id = p.ID
                WHERE pr.user_id = %d AND pr.content_type = 'lesson' AND pr.completed = 1
                ORDER BY completed_at DESC
                LIMIT 20",
                $user_id
            ),
            ARRAY_A
        );

        return array(
            'user'         => array(
                'id'           => $user->ID,
                'name'         => $user->display_name,
                'email'        => $user->user_email,
                'registered'   => $user->user_registered,
            ),
            'enrollments'  => $enrollments,
            'quiz_attempts' => $quiz_attempts,
            'certificates' => $certificates,
            'activity'     => $activity,
        );
    }

    /**
     * Get revenue analytics (requires WooCommerce).
     *
     * @param string $start_date Start date.
     * @param string $end_date   End date.
     * @return array
     */
    public static function get_revenue_analytics( string $start_date = '', string $end_date = '' ): array {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return array( 'enabled' => false );
        }

        global $wpdb;

        $date_where = '';
        if ( $start_date && $end_date ) {
            $date_where = $wpdb->prepare(
                "AND p.post_date BETWEEN %s AND %s",
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59'
            );
        }

        // Get orders with course products
        $revenue_data = $wpdb->get_row(
            "SELECT
                COUNT(DISTINCT p.ID) as total_orders,
                SUM(pm.meta_value) as total_revenue
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total'
            INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
                AND oim.meta_key = '_product_id'
            INNER JOIN {$wpdb->postmeta} course_link ON oim.meta_value = course_link.post_id
                AND course_link.meta_key = '_sfls_linked_course'
            WHERE p.post_type = 'shop_order'
                AND p.post_status IN ('wc-completed', 'wc-processing')
                {$date_where}"
        );

        // Revenue by course
        $by_course = $wpdb->get_results(
            "SELECT
                course_link.meta_value as course_id,
                cp.post_title as course_title,
                COUNT(DISTINCT p.ID) as orders,
                SUM(oim2.meta_value) as revenue
            FROM {$wpdb->posts} p
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
                {$date_where}
            GROUP BY course_link.meta_value
            ORDER BY revenue DESC",
            ARRAY_A
        );

        return array(
            'enabled'       => true,
            'total_orders'  => (int) $revenue_data->total_orders,
            'total_revenue' => (float) $revenue_data->total_revenue,
            'by_course'     => $by_course,
        );
    }

    /**
     * Get average completion rate.
     *
     * @return float
     */
    public static function get_average_completion_rate(): float {
        global $wpdb;

        $result = $wpdb->get_row(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM {$wpdb->prefix}swiftlms_enrollments
            WHERE status != 'expired'"
        );

        return $result->total > 0 ? round( ( $result->completed / $result->total ) * 100, 1 ) : 0;
    }

    /**
     * Get top performers.
     *
     * @param int    $limit  Limit.
     * @param string $metric Metric: completions, quiz_score, points.
     * @return array
     */
    public static function get_top_performers( int $limit = 10, string $metric = 'completions' ): array {
        global $wpdb;

        switch ( $metric ) {
            case 'quiz_score':
                return $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT u.ID, u.display_name, AVG(a.percentage) as value
                        FROM {$wpdb->users} u
                        INNER JOIN {$wpdb->prefix}swiftlms_quiz_attempts a ON u.ID = a.user_id
                        GROUP BY u.ID
                        HAVING COUNT(a.id) >= 3
                        ORDER BY value DESC
                        LIMIT %d",
                        $limit
                    ),
                    ARRAY_A
                );

            case 'points':
                return $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT u.ID, u.display_name, p.total_points as value
                        FROM {$wpdb->users} u
                        INNER JOIN {$wpdb->prefix}swiftlms_points p ON u.ID = p.user_id
                        ORDER BY p.total_points DESC
                        LIMIT %d",
                        $limit
                    ),
                    ARRAY_A
                );

            default: // completions
                return $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT u.ID, u.display_name,
                                COUNT(CASE WHEN e.status = 'completed' THEN 1 END) as value
                        FROM {$wpdb->users} u
                        INNER JOIN {$wpdb->prefix}swiftlms_enrollments e ON u.ID = e.user_id
                        GROUP BY u.ID
                        ORDER BY value DESC
                        LIMIT %d",
                        $limit
                    ),
                    ARRAY_A
                );
        }
    }

    /**
     * Get date WHERE clause.
     *
     * @param string $start_date  Start date.
     * @param string $end_date    End date.
     * @param string $date_column Date column name.
     * @return string
     */
    private static function get_date_where( string $start_date, string $end_date, string $date_column ): string {
        global $wpdb;

        if ( ! $start_date || ! $end_date ) {
            return '';
        }

        return $wpdb->prepare(
            " AND {$date_column} BETWEEN %s AND %s",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        );
    }

    /**
     * Get date format for grouping.
     *
     * @param string $interval Interval.
     * @return string
     */
    private static function get_date_format( string $interval ): string {
        switch ( $interval ) {
            case 'month':
                return '%Y-%m';
            case 'week':
                return '%Y-%u';
            default:
                return '%Y-%m-%d';
        }
    }

    /**
     * Fill date gaps in results.
     *
     * @param array  $results   Results.
     * @param string $start     Start date.
     * @param string $end       End date.
     * @param string $interval  Interval.
     * @param array  $defaults  Default values.
     * @return array
     */
    private static function fill_date_gaps( array $results, string $start, string $end, string $interval, array $defaults ): array {
        $indexed = array();
        foreach ( $results as $row ) {
            $indexed[ $row->period ] = (array) $row;
        }

        $filled  = array();
        $current = new \DateTime( $start );
        $end_dt  = new \DateTime( $end );

        $interval_map = array(
            'day'   => 'P1D',
            'week'  => 'P1W',
            'month' => 'P1M',
        );

        $format_map = array(
            'day'   => 'Y-m-d',
            'week'  => 'Y-W',
            'month' => 'Y-m',
        );

        while ( $current <= $end_dt ) {
            $key = $current->format( $format_map[ $interval ] );

            if ( isset( $indexed[ $key ] ) ) {
                $filled[] = $indexed[ $key ];
            } else {
                $filled[] = array_merge( array( 'period' => $key ), $defaults );
            }

            $current->add( new \DateInterval( $interval_map[ $interval ] ) );
        }

        return $filled;
    }
}
