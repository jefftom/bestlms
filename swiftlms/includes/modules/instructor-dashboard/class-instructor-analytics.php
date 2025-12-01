<?php
/**
 * Instructor Analytics
 *
 * @package SwiftLMS\Modules\InstructorDashboard
 */

namespace SwiftLMS\Modules\InstructorDashboard;

defined( 'ABSPATH' ) || exit;

/**
 * Instructor_Analytics class.
 *
 * Provides analytics data specific to instructors.
 */
class Instructor_Analytics {

    /**
     * Get instructor overview stats.
     *
     * @param int    $instructor_id Instructor ID.
     * @param string $period        Period (all, week, month, year).
     * @return array
     */
    public static function get_overview_stats( int $instructor_id, string $period = 'all' ): array {
        global $wpdb;

        $course_ids = self::get_instructor_course_ids( $instructor_id );

        if ( empty( $course_ids ) ) {
            return self::empty_stats();
        }

        $placeholders = implode( ',', array_fill( 0, count( $course_ids ), '%d' ) );
        $date_condition = self::get_date_condition( $period );

        // Total enrollments.
        $params = $course_ids;
        if ( $date_condition['value'] ) {
            $params[] = $date_condition['value'];
        }

        $total_enrollments = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}swiftlms_enrollments
             WHERE course_id IN ({$placeholders})" .
            ( $date_condition['sql'] ? " AND {$date_condition['sql']}" : '' ),
            $params
        ) );

        // Active students (enrolled and in_progress).
        $active_students = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}swiftlms_enrollments
             WHERE course_id IN ({$placeholders}) AND status IN ('enrolled', 'in_progress')",
            $course_ids
        ) );

        // Completed students.
        $params = $course_ids;
        if ( $date_condition['value'] ) {
            $params[] = $date_condition['value'];
        }

        $completions = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}swiftlms_enrollments
             WHERE course_id IN ({$placeholders}) AND status = 'completed'" .
            ( $date_condition['sql'] ? " AND completed_at >= %s" : '' ),
            $params
        ) );

        // Average progress.
        $avg_progress = $wpdb->get_var( $wpdb->prepare(
            "SELECT AVG(progress) FROM {$wpdb->prefix}swiftlms_enrollments
             WHERE course_id IN ({$placeholders})",
            $course_ids
        ) );

        // Total lessons.
        $total_lessons = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'sfls_lesson'
               AND p.post_status = 'publish'
               AND pm.meta_key = '_sfls_course_id'
               AND pm.meta_value IN ({$placeholders})",
            $course_ids
        ) );

        // Total quizzes.
        $total_quizzes = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'sfls_quiz'
               AND p.post_status = 'publish'
               AND pm.meta_key = '_sfls_course_id'
               AND pm.meta_value IN ({$placeholders})",
            $course_ids
        ) );

        // Quiz attempts.
        $quiz_attempts = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}swiftlms_quiz_attempts qa
             INNER JOIN {$wpdb->postmeta} pm ON qa.quiz_id = pm.post_id
             WHERE pm.meta_key = '_sfls_course_id'
               AND pm.meta_value IN ({$placeholders})",
            $course_ids
        ) );

        // Average quiz score.
        $avg_quiz_score = $wpdb->get_var( $wpdb->prepare(
            "SELECT AVG(qa.score) FROM {$wpdb->prefix}swiftlms_quiz_attempts qa
             INNER JOIN {$wpdb->postmeta} pm ON qa.quiz_id = pm.post_id
             WHERE pm.meta_key = '_sfls_course_id'
               AND pm.meta_value IN ({$placeholders})
               AND qa.status = 'completed'",
            $course_ids
        ) );

        // Pending assignments.
        $pending_assignments = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}swiftlms_submissions s
             INNER JOIN {$wpdb->postmeta} pm ON s.assignment_id = pm.post_id
             WHERE pm.meta_key = '_sfls_course_id'
               AND pm.meta_value IN ({$placeholders})
               AND s.status = 'submitted'",
            $course_ids
        ) );

        // Reviews/ratings.
        $reviews = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) as count, AVG(meta_value) as average
             FROM {$wpdb->comments} c
             INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
             WHERE c.comment_post_ID IN ({$placeholders})
               AND c.comment_type = 'sfls_review'
               AND c.comment_approved = '1'
               AND cm.meta_key = '_sfls_rating'",
            $course_ids
        ), ARRAY_A );

        return array(
            'total_courses'       => count( $course_ids ),
            'total_enrollments'   => (int) $total_enrollments,
            'active_students'     => (int) $active_students,
            'completions'         => (int) $completions,
            'completion_rate'     => $total_enrollments > 0 ? round( ( $completions / $total_enrollments ) * 100, 1 ) : 0,
            'avg_progress'        => round( (float) $avg_progress, 1 ),
            'total_lessons'       => (int) $total_lessons,
            'total_quizzes'       => (int) $total_quizzes,
            'quiz_attempts'       => (int) $quiz_attempts,
            'avg_quiz_score'      => round( (float) $avg_quiz_score, 1 ),
            'pending_assignments' => (int) $pending_assignments,
            'total_reviews'       => (int) $reviews['count'],
            'avg_rating'          => round( (float) $reviews['average'], 1 ),
        );
    }

    /**
     * Get empty stats structure.
     *
     * @return array
     */
    private static function empty_stats(): array {
        return array(
            'total_courses'       => 0,
            'total_enrollments'   => 0,
            'active_students'     => 0,
            'completions'         => 0,
            'completion_rate'     => 0,
            'avg_progress'        => 0,
            'total_lessons'       => 0,
            'total_quizzes'       => 0,
            'quiz_attempts'       => 0,
            'avg_quiz_score'      => 0,
            'pending_assignments' => 0,
            'total_reviews'       => 0,
            'avg_rating'          => 0,
        );
    }

    /**
     * Get instructor course IDs.
     *
     * @param int $instructor_id Instructor ID.
     * @return array
     */
    public static function get_instructor_course_ids( int $instructor_id ): array {
        $courses = Instructor_Role::get_instructor_courses( $instructor_id );
        return wp_list_pluck( $courses, 'ID' );
    }

    /**
     * Get date condition for SQL.
     *
     * @param string $period Period.
     * @return array
     */
    private static function get_date_condition( string $period ): array {
        switch ( $period ) {
            case 'week':
                return array(
                    'sql'   => 'enrolled_at >= %s',
                    'value' => gmdate( 'Y-m-d 00:00:00', strtotime( '-7 days' ) ),
                );
            case 'month':
                return array(
                    'sql'   => 'enrolled_at >= %s',
                    'value' => gmdate( 'Y-m-01 00:00:00' ),
                );
            case 'year':
                return array(
                    'sql'   => 'enrolled_at >= %s',
                    'value' => gmdate( 'Y-01-01 00:00:00' ),
                );
            default:
                return array( 'sql' => '', 'value' => '' );
        }
    }

    /**
     * Get enrollment trends for instructor.
     *
     * @param int    $instructor_id Instructor ID.
     * @param int    $days          Number of days.
     * @return array
     */
    public static function get_enrollment_trends( int $instructor_id, int $days = 30 ): array {
        global $wpdb;

        $course_ids = self::get_instructor_course_ids( $instructor_id );

        if ( empty( $course_ids ) ) {
            return array();
        }

        $placeholders = implode( ',', array_fill( 0, count( $course_ids ), '%d' ) );
        $start_date = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

        $params = array_merge( $course_ids, array( $start_date ) );

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT
                DATE(enrolled_at) as date,
                COUNT(*) as enrollments,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completions
             FROM {$wpdb->prefix}swiftlms_enrollments
             WHERE course_id IN ({$placeholders})
               AND enrolled_at >= %s
             GROUP BY DATE(enrolled_at)
             ORDER BY date ASC",
            $params
        ), ARRAY_A );
    }

    /**
     * Get course performance.
     *
     * @param int $instructor_id Instructor ID.
     * @return array
     */
    public static function get_course_performance( int $instructor_id ): array {
        global $wpdb;

        $courses = Instructor_Role::get_instructor_courses( $instructor_id );
        $performance = array();

        foreach ( $courses as $course ) {
            $stats = $wpdb->get_row( $wpdb->prepare(
                "SELECT
                    COUNT(*) as enrollments,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completions,
                    AVG(progress) as avg_progress
                 FROM {$wpdb->prefix}swiftlms_enrollments
                 WHERE course_id = %d",
                $course->ID
            ), ARRAY_A );

            // Get rating.
            $rating = $wpdb->get_row( $wpdb->prepare(
                "SELECT COUNT(*) as count, AVG(meta_value) as average
                 FROM {$wpdb->comments} c
                 INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
                 WHERE c.comment_post_ID = %d
                   AND c.comment_type = 'sfls_review'
                   AND c.comment_approved = '1'
                   AND cm.meta_key = '_sfls_rating'",
                $course->ID
            ), ARRAY_A );

            $performance[] = array(
                'course_id'       => $course->ID,
                'title'           => $course->post_title,
                'status'          => $course->post_status,
                'enrollments'     => (int) $stats['enrollments'],
                'completions'     => (int) $stats['completions'],
                'completion_rate' => $stats['enrollments'] > 0 ? round( ( $stats['completions'] / $stats['enrollments'] ) * 100, 1 ) : 0,
                'avg_progress'    => round( (float) $stats['avg_progress'], 1 ),
                'reviews'         => (int) $rating['count'],
                'rating'          => round( (float) $rating['average'], 1 ),
            );
        }

        return $performance;
    }

    /**
     * Get student list for instructor.
     *
     * @param int   $instructor_id Instructor ID.
     * @param array $args          Query args.
     * @return array
     */
    public static function get_students( int $instructor_id, array $args = array() ): array {
        global $wpdb;

        $defaults = array(
            'course_id' => 0,
            'status'    => '',
            'search'    => '',
            'orderby'   => 'enrolled_at',
            'order'     => 'DESC',
            'limit'     => 20,
            'offset'    => 0,
        );

        $args = wp_parse_args( $args, $defaults );

        $course_ids = $args['course_id']
            ? array( (int) $args['course_id'] )
            : self::get_instructor_course_ids( $instructor_id );

        if ( empty( $course_ids ) ) {
            return array( 'students' => array(), 'total' => 0 );
        }

        $placeholders = implode( ',', array_fill( 0, count( $course_ids ), '%d' ) );

        $where = "e.course_id IN ({$placeholders})";
        $params = $course_ids;

        if ( $args['status'] ) {
            $where .= ' AND e.status = %s';
            $params[] = $args['status'];
        }

        if ( $args['search'] ) {
            $where .= ' AND (u.display_name LIKE %s OR u.user_email LIKE %s)';
            $search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $params[] = $search;
            $params[] = $search;
        }

        // Get total.
        $total = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT e.user_id)
             FROM {$wpdb->prefix}swiftlms_enrollments e
             INNER JOIN {$wpdb->users} u ON e.user_id = u.ID
             WHERE {$where}",
            $params
        ) );

        // Get students.
        $orderby = in_array( $args['orderby'], array( 'enrolled_at', 'progress', 'last_activity' ), true )
            ? $args['orderby'] : 'enrolled_at';
        $order = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

        $params[] = $args['limit'];
        $params[] = $args['offset'];

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                e.user_id,
                u.display_name,
                u.user_email,
                COUNT(DISTINCT e.course_id) as enrolled_courses,
                SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) as completed_courses,
                AVG(e.progress) as avg_progress,
                MAX(e.enrolled_at) as last_enrolled,
                MAX(e.last_activity) as last_activity
             FROM {$wpdb->prefix}swiftlms_enrollments e
             INNER JOIN {$wpdb->users} u ON e.user_id = u.ID
             WHERE {$where}
             GROUP BY e.user_id
             ORDER BY {$orderby} {$order}
             LIMIT %d OFFSET %d",
            $params
        ), ARRAY_A );

        // Add avatar URLs.
        foreach ( $results as &$student ) {
            $student['avatar'] = get_avatar_url( $student['user_id'], array( 'size' => 40 ) );
            $student['avg_progress'] = round( (float) $student['avg_progress'], 1 );
        }

        return array(
            'students' => $results,
            'total'    => (int) $total,
        );
    }

    /**
     * Get student detail.
     *
     * @param int $instructor_id Instructor ID.
     * @param int $student_id    Student ID.
     * @return array|null
     */
    public static function get_student_detail( int $instructor_id, int $student_id ): ?array {
        global $wpdb;

        $course_ids = self::get_instructor_course_ids( $instructor_id );

        if ( empty( $course_ids ) ) {
            return null;
        }

        $user = get_user_by( 'id', $student_id );
        if ( ! $user ) {
            return null;
        }

        $placeholders = implode( ',', array_fill( 0, count( $course_ids ), '%d' ) );
        $params = array_merge( array( $student_id ), $course_ids );

        // Get enrollments in instructor's courses.
        $enrollments = $wpdb->get_results( $wpdb->prepare(
            "SELECT e.*, p.post_title as course_title
             FROM {$wpdb->prefix}swiftlms_enrollments e
             INNER JOIN {$wpdb->posts} p ON e.course_id = p.ID
             WHERE e.user_id = %d AND e.course_id IN ({$placeholders})
             ORDER BY e.enrolled_at DESC",
            $params
        ), ARRAY_A );

        // Get quiz attempts.
        $quiz_attempts = $wpdb->get_results( $wpdb->prepare(
            "SELECT qa.*, q.post_title as quiz_title
             FROM {$wpdb->prefix}swiftlms_quiz_attempts qa
             INNER JOIN {$wpdb->posts} q ON qa.quiz_id = q.ID
             INNER JOIN {$wpdb->postmeta} pm ON qa.quiz_id = pm.post_id
             WHERE qa.user_id = %d
               AND pm.meta_key = '_sfls_course_id'
               AND pm.meta_value IN ({$placeholders})
             ORDER BY qa.started_at DESC
             LIMIT 20",
            $params
        ), ARRAY_A );

        // Get assignment submissions.
        $submissions = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.*, a.post_title as assignment_title
             FROM {$wpdb->prefix}swiftlms_submissions s
             INNER JOIN {$wpdb->posts} a ON s.assignment_id = a.ID
             INNER JOIN {$wpdb->postmeta} pm ON s.assignment_id = pm.post_id
             WHERE s.student_id = %d
               AND pm.meta_key = '_sfls_course_id'
               AND pm.meta_value IN ({$placeholders})
             ORDER BY s.submitted_at DESC
             LIMIT 20",
            $params
        ), ARRAY_A );

        return array(
            'user_id'      => $student_id,
            'display_name' => $user->display_name,
            'email'        => $user->user_email,
            'avatar'       => get_avatar_url( $student_id, array( 'size' => 80 ) ),
            'registered'   => $user->user_registered,
            'enrollments'  => $enrollments,
            'quiz_attempts' => $quiz_attempts,
            'submissions'  => $submissions,
        );
    }

    /**
     * Get pending submissions for instructor.
     *
     * @param int   $instructor_id Instructor ID.
     * @param array $args          Query args.
     * @return array
     */
    public static function get_pending_submissions( int $instructor_id, array $args = array() ): array {
        global $wpdb;

        $defaults = array(
            'course_id' => 0,
            'limit'     => 20,
            'offset'    => 0,
        );

        $args = wp_parse_args( $args, $defaults );

        $course_ids = $args['course_id']
            ? array( (int) $args['course_id'] )
            : self::get_instructor_course_ids( $instructor_id );

        if ( empty( $course_ids ) ) {
            return array( 'submissions' => array(), 'total' => 0 );
        }

        $placeholders = implode( ',', array_fill( 0, count( $course_ids ), '%d' ) );

        // Get total.
        $total = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->prefix}swiftlms_submissions s
             INNER JOIN {$wpdb->postmeta} pm ON s.assignment_id = pm.post_id
             WHERE pm.meta_key = '_sfls_course_id'
               AND pm.meta_value IN ({$placeholders})
               AND s.status = 'submitted'",
            $course_ids
        ) );

        // Get submissions.
        $params = array_merge( $course_ids, array( $args['limit'], $args['offset'] ) );

        $submissions = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                s.*,
                a.post_title as assignment_title,
                u.display_name as student_name,
                c.post_title as course_title
             FROM {$wpdb->prefix}swiftlms_submissions s
             INNER JOIN {$wpdb->posts} a ON s.assignment_id = a.ID
             INNER JOIN {$wpdb->users} u ON s.student_id = u.ID
             INNER JOIN {$wpdb->postmeta} pm ON s.assignment_id = pm.post_id
             INNER JOIN {$wpdb->posts} c ON pm.meta_value = c.ID
             WHERE pm.meta_key = '_sfls_course_id'
               AND pm.meta_value IN ({$placeholders})
               AND s.status = 'submitted'
             ORDER BY s.submitted_at ASC
             LIMIT %d OFFSET %d",
            $params
        ), ARRAY_A );

        // Add avatar URLs.
        foreach ( $submissions as &$submission ) {
            $submission['student_avatar'] = get_avatar_url( $submission['student_id'], array( 'size' => 40 ) );
        }

        return array(
            'submissions' => $submissions,
            'total'       => (int) $total,
        );
    }

    /**
     * Get recent activity for instructor.
     *
     * @param int $instructor_id Instructor ID.
     * @param int $limit         Number of items.
     * @return array
     */
    public static function get_recent_activity( int $instructor_id, int $limit = 20 ): array {
        global $wpdb;

        $course_ids = self::get_instructor_course_ids( $instructor_id );

        if ( empty( $course_ids ) ) {
            return array();
        }

        $placeholders = implode( ',', array_fill( 0, count( $course_ids ), '%d' ) );
        $activities = array();

        // Recent enrollments.
        $enrollments = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                'enrollment' as type,
                e.enrolled_at as date,
                e.user_id,
                u.display_name,
                e.course_id,
                c.post_title as course_title
             FROM {$wpdb->prefix}swiftlms_enrollments e
             INNER JOIN {$wpdb->users} u ON e.user_id = u.ID
             INNER JOIN {$wpdb->posts} c ON e.course_id = c.ID
             WHERE e.course_id IN ({$placeholders})
             ORDER BY e.enrolled_at DESC
             LIMIT 10",
            $course_ids
        ), ARRAY_A );

        $activities = array_merge( $activities, $enrollments );

        // Recent completions.
        $completions = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                'completion' as type,
                e.completed_at as date,
                e.user_id,
                u.display_name,
                e.course_id,
                c.post_title as course_title
             FROM {$wpdb->prefix}swiftlms_enrollments e
             INNER JOIN {$wpdb->users} u ON e.user_id = u.ID
             INNER JOIN {$wpdb->posts} c ON e.course_id = c.ID
             WHERE e.course_id IN ({$placeholders})
               AND e.status = 'completed'
               AND e.completed_at IS NOT NULL
             ORDER BY e.completed_at DESC
             LIMIT 10",
            $course_ids
        ), ARRAY_A );

        $activities = array_merge( $activities, $completions );

        // Recent submissions.
        $submissions = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                'submission' as type,
                s.submitted_at as date,
                s.student_id as user_id,
                u.display_name,
                pm.meta_value as course_id,
                a.post_title as assignment_title
             FROM {$wpdb->prefix}swiftlms_submissions s
             INNER JOIN {$wpdb->posts} a ON s.assignment_id = a.ID
             INNER JOIN {$wpdb->users} u ON s.student_id = u.ID
             INNER JOIN {$wpdb->postmeta} pm ON s.assignment_id = pm.post_id
             WHERE pm.meta_key = '_sfls_course_id'
               AND pm.meta_value IN ({$placeholders})
             ORDER BY s.submitted_at DESC
             LIMIT 10",
            $course_ids
        ), ARRAY_A );

        $activities = array_merge( $activities, $submissions );

        // Recent reviews.
        $reviews = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                'review' as type,
                c.comment_date as date,
                c.user_id,
                u.display_name,
                c.comment_post_ID as course_id,
                p.post_title as course_title,
                cm.meta_value as rating
             FROM {$wpdb->comments} c
             INNER JOIN {$wpdb->users} u ON c.user_id = u.ID
             INNER JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
             LEFT JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id AND cm.meta_key = '_sfls_rating'
             WHERE c.comment_post_ID IN ({$placeholders})
               AND c.comment_type = 'sfls_review'
               AND c.comment_approved = '1'
             ORDER BY c.comment_date DESC
             LIMIT 10",
            $course_ids
        ), ARRAY_A );

        $activities = array_merge( $activities, $reviews );

        // Sort by date descending.
        usort( $activities, function( $a, $b ) {
            return strtotime( $b['date'] ) - strtotime( $a['date'] );
        } );

        // Limit and add avatars.
        $activities = array_slice( $activities, 0, $limit );

        foreach ( $activities as &$activity ) {
            $activity['avatar'] = get_avatar_url( $activity['user_id'], array( 'size' => 32 ) );
            $activity['time_ago'] = human_time_diff( strtotime( $activity['date'] ), current_time( 'timestamp' ) );
        }

        return $activities;
    }
}
