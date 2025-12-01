<?php
/**
 * Submissions Database Table
 *
 * @package SwiftLMS
 */

namespace SwiftLMS\Modules\Assignments;

defined( 'ABSPATH' ) || exit;

/**
 * Submissions Table class.
 */
class Submissions_Table {

    /**
     * Table name.
     *
     * @var string
     */
    private static string $table_name = 'swiftlms_submissions';

    /**
     * Submission statuses.
     */
    const STATUS_PENDING  = 'pending';
    const STATUS_GRADED   = 'graded';
    const STATUS_RETURNED = 'returned';
    const STATUS_LATE     = 'late';

    /**
     * Create table.
     */
    public static function create_table(): void {
        global $wpdb;

        $table_name      = $wpdb->prefix . self::$table_name;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            assignment_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            attempt_number int(11) UNSIGNED NOT NULL DEFAULT 1,
            submission_type varchar(20) NOT NULL DEFAULT 'file_upload',
            file_url text DEFAULT NULL,
            file_name varchar(255) DEFAULT NULL,
            file_size bigint(20) UNSIGNED DEFAULT NULL,
            text_content longtext DEFAULT NULL,
            url_submission varchar(500) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            is_late tinyint(1) NOT NULL DEFAULT 0,
            grade decimal(5,2) DEFAULT NULL,
            grade_percentage decimal(5,2) DEFAULT NULL,
            feedback longtext DEFAULT NULL,
            graded_by bigint(20) UNSIGNED DEFAULT NULL,
            graded_at datetime DEFAULT NULL,
            submitted_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY assignment_id (assignment_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY assignment_user (assignment_id, user_id),
            KEY submitted_at (submitted_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Get table name.
     *
     * @return string
     */
    public static function get_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::$table_name;
    }

    /**
     * Insert submission.
     *
     * @param array $data Submission data.
     * @return int|false Submission ID or false on failure.
     */
    public static function insert( array $data ) {
        global $wpdb;

        $defaults = array(
            'attempt_number'  => 1,
            'submission_type' => 'file_upload',
            'status'          => self::STATUS_PENDING,
            'is_late'         => 0,
            'submitted_at'    => current_time( 'mysql' ),
        );

        $data = wp_parse_args( $data, $defaults );

        $result = $wpdb->insert(
            self::get_table_name(),
            $data,
            array(
                '%d', // assignment_id
                '%d', // user_id
                '%d', // attempt_number
                '%s', // submission_type
                '%s', // file_url
                '%s', // file_name
                '%d', // file_size
                '%s', // text_content
                '%s', // url_submission
                '%s', // status
                '%d', // is_late
                '%f', // grade
                '%f', // grade_percentage
                '%s', // feedback
                '%d', // graded_by
                '%s', // graded_at
                '%s', // submitted_at
                '%s', // updated_at
            )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update submission.
     *
     * @param int   $submission_id Submission ID.
     * @param array $data          Data to update.
     * @return bool
     */
    public static function update( int $submission_id, array $data ): bool {
        global $wpdb;

        $data['updated_at'] = current_time( 'mysql' );

        return (bool) $wpdb->update(
            self::get_table_name(),
            $data,
            array( 'id' => $submission_id )
        );
    }

    /**
     * Get submission by ID.
     *
     * @param int $submission_id Submission ID.
     * @return object|null
     */
    public static function get( int $submission_id ): ?object {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE id = %d",
                self::get_table_name(),
                $submission_id
            )
        );
    }

    /**
     * Get user submissions for assignment.
     *
     * @param int $assignment_id Assignment ID.
     * @param int $user_id       User ID.
     * @return array
     */
    public static function get_user_submissions( int $assignment_id, int $user_id ): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE assignment_id = %d AND user_id = %d ORDER BY attempt_number DESC",
                self::get_table_name(),
                $assignment_id,
                $user_id
            )
        );
    }

    /**
     * Get latest submission for user.
     *
     * @param int $assignment_id Assignment ID.
     * @param int $user_id       User ID.
     * @return object|null
     */
    public static function get_latest_submission( int $assignment_id, int $user_id ): ?object {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE assignment_id = %d AND user_id = %d ORDER BY attempt_number DESC LIMIT 1",
                self::get_table_name(),
                $assignment_id,
                $user_id
            )
        );
    }

    /**
     * Get attempt count.
     *
     * @param int $assignment_id Assignment ID.
     * @param int $user_id       User ID.
     * @return int
     */
    public static function get_attempt_count( int $assignment_id, int $user_id ): int {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE assignment_id = %d AND user_id = %d",
                self::get_table_name(),
                $assignment_id,
                $user_id
            )
        );
    }

    /**
     * Get all submissions for assignment.
     *
     * @param int    $assignment_id Assignment ID.
     * @param string $status        Optional status filter.
     * @param array  $args          Additional arguments.
     * @return array
     */
    public static function get_assignment_submissions( int $assignment_id, string $status = '', array $args = array() ): array {
        global $wpdb;

        $defaults = array(
            'orderby'  => 'submitted_at',
            'order'    => 'DESC',
            'per_page' => 20,
            'page'     => 1,
        );

        $args = wp_parse_args( $args, $defaults );

        $where = $wpdb->prepare( 'assignment_id = %d', $assignment_id );

        if ( $status ) {
            $where .= $wpdb->prepare( ' AND status = %s', $status );
        }

        $orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
        $offset  = ( $args['page'] - 1 ) * $args['per_page'];

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*, u.display_name, u.user_email
                FROM %i s
                LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
                WHERE {$where}
                ORDER BY {$orderby}
                LIMIT %d OFFSET %d",
                self::get_table_name(),
                $args['per_page'],
                $offset
            )
        );
    }

    /**
     * Get submission counts for assignment.
     *
     * @param int $assignment_id Assignment ID.
     * @return array
     */
    public static function get_submission_counts( int $assignment_id ): array {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT status, COUNT(*) as count FROM %i WHERE assignment_id = %d GROUP BY status",
                self::get_table_name(),
                $assignment_id
            ),
            OBJECT_K
        );

        return array(
            'total'    => array_sum( wp_list_pluck( $results, 'count' ) ),
            'pending'  => isset( $results[ self::STATUS_PENDING ] ) ? (int) $results[ self::STATUS_PENDING ]->count : 0,
            'graded'   => isset( $results[ self::STATUS_GRADED ] ) ? (int) $results[ self::STATUS_GRADED ]->count : 0,
            'returned' => isset( $results[ self::STATUS_RETURNED ] ) ? (int) $results[ self::STATUS_RETURNED ]->count : 0,
        );
    }

    /**
     * Grade submission.
     *
     * @param int   $submission_id Submission ID.
     * @param float $grade         Grade points.
     * @param int   $total_points  Total possible points.
     * @param int   $grader_id     Grader user ID.
     * @param string $feedback     Optional feedback.
     * @return bool
     */
    public static function grade_submission( int $submission_id, float $grade, int $total_points, int $grader_id, string $feedback = '' ): bool {
        $percentage = $total_points > 0 ? ( $grade / $total_points ) * 100 : 0;

        return self::update(
            $submission_id,
            array(
                'grade'            => $grade,
                'grade_percentage' => $percentage,
                'feedback'         => $feedback,
                'graded_by'        => $grader_id,
                'graded_at'        => current_time( 'mysql' ),
                'status'           => self::STATUS_GRADED,
            )
        );
    }

    /**
     * Delete submission.
     *
     * @param int $submission_id Submission ID.
     * @return bool
     */
    public static function delete( int $submission_id ): bool {
        global $wpdb;

        // Get submission first to delete file if exists
        $submission = self::get( $submission_id );
        if ( $submission && $submission->file_url ) {
            $attachment_id = attachment_url_to_postid( $submission->file_url );
            if ( $attachment_id ) {
                wp_delete_attachment( $attachment_id, true );
            }
        }

        return (bool) $wpdb->delete(
            self::get_table_name(),
            array( 'id' => $submission_id )
        );
    }

    /**
     * Get user's assignment grades.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public static function get_user_grades( int $user_id ): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*, p.post_title as assignment_title
                FROM %i s
                LEFT JOIN {$wpdb->posts} p ON s.assignment_id = p.ID
                WHERE s.user_id = %d AND s.status = %s
                ORDER BY s.graded_at DESC",
                self::get_table_name(),
                $user_id,
                self::STATUS_GRADED
            )
        );
    }

    /**
     * Get average grade for assignment.
     *
     * @param int $assignment_id Assignment ID.
     * @return float
     */
    public static function get_average_grade( int $assignment_id ): float {
        global $wpdb;

        return (float) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT AVG(grade_percentage) FROM %i WHERE assignment_id = %d AND status = %s",
                self::get_table_name(),
                $assignment_id,
                self::STATUS_GRADED
            )
        ) ?: 0.0;
    }
}
