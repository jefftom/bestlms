<?php
/**
 * Points Database Table
 *
 * @package SwiftLMS
 */

namespace SwiftLMS\Modules\Gamification;

defined( 'ABSPATH' ) || exit;

/**
 * Points Table class.
 */
class Points_Table {

    /**
     * Table name.
     *
     * @var string
     */
    private static string $table_name = 'swiftlms_points';

    /**
     * Transactions table name.
     *
     * @var string
     */
    private static string $transactions_table = 'swiftlms_point_transactions';

    /**
     * Point action types.
     */
    const ACTION_LESSON_COMPLETE  = 'lesson_complete';
    const ACTION_COURSE_COMPLETE  = 'course_complete';
    const ACTION_QUIZ_PASS        = 'quiz_pass';
    const ACTION_QUIZ_PERFECT     = 'quiz_perfect';
    const ACTION_ASSIGNMENT_SUBMIT = 'assignment_submit';
    const ACTION_ASSIGNMENT_GRADED = 'assignment_graded';
    const ACTION_DAILY_LOGIN      = 'daily_login';
    const ACTION_STREAK_BONUS     = 'streak_bonus';
    const ACTION_BADGE_EARNED     = 'badge_earned';
    const ACTION_ADMIN_AWARD      = 'admin_award';
    const ACTION_ADMIN_DEDUCT     = 'admin_deduct';

    /**
     * Create tables.
     */
    public static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // User points balance table
        $points_table = $wpdb->prefix . self::$table_name;
        $sql_points   = "CREATE TABLE {$points_table} (
            user_id bigint(20) UNSIGNED NOT NULL,
            total_points bigint(20) NOT NULL DEFAULT 0,
            available_points bigint(20) NOT NULL DEFAULT 0,
            level_id int(11) UNSIGNED NOT NULL DEFAULT 1,
            current_streak int(11) UNSIGNED NOT NULL DEFAULT 0,
            longest_streak int(11) UNSIGNED NOT NULL DEFAULT 0,
            last_activity_date date DEFAULT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (user_id),
            KEY total_points (total_points),
            KEY level_id (level_id)
        ) {$charset_collate};";

        // Point transactions table
        $transactions_table = $wpdb->prefix . self::$transactions_table;
        $sql_transactions   = "CREATE TABLE {$transactions_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            points int(11) NOT NULL,
            action_type varchar(50) NOT NULL,
            reference_id bigint(20) UNSIGNED DEFAULT NULL,
            reference_type varchar(50) DEFAULT NULL,
            description varchar(255) DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action_type (action_type),
            KEY created_at (created_at),
            KEY user_action (user_id, action_type)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_points );
        dbDelta( $sql_transactions );
    }

    /**
     * Get points table name.
     *
     * @return string
     */
    public static function get_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::$table_name;
    }

    /**
     * Get transactions table name.
     *
     * @return string
     */
    public static function get_transactions_table(): string {
        global $wpdb;
        return $wpdb->prefix . self::$transactions_table;
    }

    /**
     * Get user points.
     *
     * @param int $user_id User ID.
     * @return object|null
     */
    public static function get_user_points( int $user_id ): ?object {
        global $wpdb;

        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE user_id = %d",
                self::get_table_name(),
                $user_id
            )
        );

        if ( ! $record ) {
            // Create initial record
            self::initialize_user( $user_id );
            return self::get_user_points( $user_id );
        }

        return $record;
    }

    /**
     * Initialize user points record.
     *
     * @param int $user_id User ID.
     */
    public static function initialize_user( int $user_id ): void {
        global $wpdb;

        $wpdb->insert(
            self::get_table_name(),
            array(
                'user_id'          => $user_id,
                'total_points'     => 0,
                'available_points' => 0,
                'level_id'         => 1,
                'current_streak'   => 0,
                'longest_streak'   => 0,
                'updated_at'       => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%d', '%d', '%d', '%d', '%s' )
        );
    }

    /**
     * Award points to user.
     *
     * @param int    $user_id        User ID.
     * @param int    $points         Points to award.
     * @param string $action_type    Action type.
     * @param int    $reference_id   Reference ID (optional).
     * @param string $reference_type Reference type (optional).
     * @param string $description    Description (optional).
     * @return bool
     */
    public static function award_points(
        int $user_id,
        int $points,
        string $action_type,
        int $reference_id = 0,
        string $reference_type = '',
        string $description = ''
    ): bool {
        global $wpdb;

        if ( $points <= 0 ) {
            return false;
        }

        // Ensure user record exists
        $user_points = self::get_user_points( $user_id );

        // Add transaction
        $result = $wpdb->insert(
            self::get_transactions_table(),
            array(
                'user_id'        => $user_id,
                'points'         => $points,
                'action_type'    => $action_type,
                'reference_id'   => $reference_id ?: null,
                'reference_type' => $reference_type ?: null,
                'description'    => $description ?: self::get_action_description( $action_type, $points ),
                'created_at'     => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%d', '%s', '%s', '%s' )
        );

        if ( ! $result ) {
            return false;
        }

        // Update user balance
        $new_total     = (int) $user_points->total_points + $points;
        $new_available = (int) $user_points->available_points + $points;

        // Check for level up
        $new_level = Levels::get_level_for_points( $new_total );

        $wpdb->update(
            self::get_table_name(),
            array(
                'total_points'     => $new_total,
                'available_points' => $new_available,
                'level_id'         => $new_level['id'],
                'updated_at'       => current_time( 'mysql' ),
            ),
            array( 'user_id' => $user_id ),
            array( '%d', '%d', '%d', '%s' ),
            array( '%d' )
        );

        // Check for level up and trigger action
        if ( $new_level['id'] > (int) $user_points->level_id ) {
            do_action( 'swiftlms_user_level_up', $user_id, $new_level, (int) $user_points->level_id );
        }

        do_action( 'swiftlms_points_awarded', $user_id, $points, $action_type, $reference_id );

        return true;
    }

    /**
     * Deduct points from user.
     *
     * @param int    $user_id     User ID.
     * @param int    $points      Points to deduct.
     * @param string $action_type Action type.
     * @param string $description Description.
     * @return bool
     */
    public static function deduct_points(
        int $user_id,
        int $points,
        string $action_type = self::ACTION_ADMIN_DEDUCT,
        string $description = ''
    ): bool {
        global $wpdb;

        if ( $points <= 0 ) {
            return false;
        }

        $user_points = self::get_user_points( $user_id );

        // Can only deduct available points
        $actual_deduction = min( $points, (int) $user_points->available_points );

        if ( $actual_deduction <= 0 ) {
            return false;
        }

        // Add negative transaction
        $wpdb->insert(
            self::get_transactions_table(),
            array(
                'user_id'     => $user_id,
                'points'      => -$actual_deduction,
                'action_type' => $action_type,
                'description' => $description ?: sprintf( __( 'Points deducted: %d', 'swiftlms' ), $actual_deduction ),
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%s' )
        );

        // Update balance
        $wpdb->update(
            self::get_table_name(),
            array(
                'available_points' => (int) $user_points->available_points - $actual_deduction,
                'updated_at'       => current_time( 'mysql' ),
            ),
            array( 'user_id' => $user_id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        return true;
    }

    /**
     * Get user transactions.
     *
     * @param int   $user_id User ID.
     * @param array $args    Query arguments.
     * @return array
     */
    public static function get_user_transactions( int $user_id, array $args = array() ): array {
        global $wpdb;

        $defaults = array(
            'per_page' => 20,
            'page'     => 1,
            'order'    => 'DESC',
        );

        $args   = wp_parse_args( $args, $defaults );
        $offset = ( $args['page'] - 1 ) * $args['per_page'];
        $order  = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE user_id = %d ORDER BY created_at {$order} LIMIT %d OFFSET %d",
                self::get_transactions_table(),
                $user_id,
                $args['per_page'],
                $offset
            )
        );
    }

    /**
     * Get action description.
     *
     * @param string $action_type Action type.
     * @param int    $points      Points.
     * @return string
     */
    public static function get_action_description( string $action_type, int $points ): string {
        $descriptions = array(
            self::ACTION_LESSON_COMPLETE   => __( 'Completed a lesson', 'swiftlms' ),
            self::ACTION_COURSE_COMPLETE   => __( 'Completed a course', 'swiftlms' ),
            self::ACTION_QUIZ_PASS         => __( 'Passed a quiz', 'swiftlms' ),
            self::ACTION_QUIZ_PERFECT      => __( 'Perfect quiz score', 'swiftlms' ),
            self::ACTION_ASSIGNMENT_SUBMIT => __( 'Submitted assignment', 'swiftlms' ),
            self::ACTION_ASSIGNMENT_GRADED => __( 'Assignment graded', 'swiftlms' ),
            self::ACTION_DAILY_LOGIN       => __( 'Daily login bonus', 'swiftlms' ),
            self::ACTION_STREAK_BONUS      => __( 'Streak bonus', 'swiftlms' ),
            self::ACTION_BADGE_EARNED      => __( 'Badge earned', 'swiftlms' ),
            self::ACTION_ADMIN_AWARD       => __( 'Points awarded by admin', 'swiftlms' ),
        );

        return $descriptions[ $action_type ] ?? sprintf( __( 'Earned %d points', 'swiftlms' ), $points );
    }

    /**
     * Update user streak.
     *
     * @param int $user_id User ID.
     * @return array Streak data.
     */
    public static function update_streak( int $user_id ): array {
        global $wpdb;

        $user_points = self::get_user_points( $user_id );
        $today       = current_time( 'Y-m-d' );
        $last_date   = $user_points->last_activity_date;

        $current_streak = (int) $user_points->current_streak;
        $longest_streak = (int) $user_points->longest_streak;
        $streak_broken  = false;
        $new_streak     = false;

        if ( $last_date === $today ) {
            // Already logged in today
            return array(
                'current'  => $current_streak,
                'longest'  => $longest_streak,
                'is_new'   => false,
                'broken'   => false,
            );
        }

        $yesterday = date( 'Y-m-d', strtotime( '-1 day' ) );

        if ( $last_date === $yesterday ) {
            // Consecutive day
            $current_streak++;
            $new_streak = true;
        } elseif ( $last_date && $last_date !== $today ) {
            // Streak broken
            $current_streak = 1;
            $streak_broken  = true;
            $new_streak     = true;
        } else {
            // First activity
            $current_streak = 1;
            $new_streak     = true;
        }

        // Update longest streak
        if ( $current_streak > $longest_streak ) {
            $longest_streak = $current_streak;
        }

        $wpdb->update(
            self::get_table_name(),
            array(
                'current_streak'     => $current_streak,
                'longest_streak'     => $longest_streak,
                'last_activity_date' => $today,
                'updated_at'         => current_time( 'mysql' ),
            ),
            array( 'user_id' => $user_id ),
            array( '%d', '%d', '%s', '%s' ),
            array( '%d' )
        );

        return array(
            'current'  => $current_streak,
            'longest'  => $longest_streak,
            'is_new'   => $new_streak,
            'broken'   => $streak_broken,
        );
    }

    /**
     * Get leaderboard.
     *
     * @param string $period  Period: all_time, monthly, weekly.
     * @param int    $limit   Number of users.
     * @return array
     */
    public static function get_leaderboard( string $period = 'all_time', int $limit = 10 ): array {
        global $wpdb;

        if ( 'all_time' === $period ) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT p.*, u.display_name, u.user_email
                    FROM %i p
                    LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
                    WHERE p.total_points > 0
                    ORDER BY p.total_points DESC
                    LIMIT %d",
                    self::get_table_name(),
                    $limit
                )
            );
        }

        // For monthly/weekly, sum from transactions
        $date_filter = 'monthly' === $period
            ? "DATE(t.created_at) >= DATE_FORMAT(NOW(), '%Y-%m-01')"
            : "t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.user_id, SUM(t.points) as period_points, u.display_name, u.user_email,
                        p.total_points, p.level_id
                FROM %i t
                LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
                LEFT JOIN %i p ON t.user_id = p.user_id
                WHERE {$date_filter} AND t.points > 0
                GROUP BY t.user_id
                ORDER BY period_points DESC
                LIMIT %d",
                self::get_transactions_table(),
                self::get_table_name(),
                $limit
            )
        );
    }

    /**
     * Get user rank.
     *
     * @param int    $user_id User ID.
     * @param string $period  Period.
     * @return int
     */
    public static function get_user_rank( int $user_id, string $period = 'all_time' ): int {
        global $wpdb;

        $user_points = self::get_user_points( $user_id );

        if ( 'all_time' === $period ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) + 1 FROM %i WHERE total_points > %d",
                    self::get_table_name(),
                    $user_points->total_points
                )
            );
        }

        // For period-based ranking
        $date_filter = 'monthly' === $period
            ? "DATE(created_at) >= DATE_FORMAT(NOW(), '%Y-%m-01')"
            : "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";

        $user_period_points = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(points), 0) FROM %i WHERE user_id = %d AND {$date_filter} AND points > 0",
                self::get_transactions_table(),
                $user_id
            )
        );

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) + 1
                FROM %i
                WHERE {$date_filter} AND points > 0
                GROUP BY user_id
                HAVING SUM(points) > %d",
                self::get_transactions_table(),
                $user_period_points
            )
        ) ?: 1;
    }

    /**
     * Check if action already rewarded today.
     *
     * @param int    $user_id     User ID.
     * @param string $action_type Action type.
     * @param int    $reference_id Reference ID (optional).
     * @return bool
     */
    public static function has_action_today( int $user_id, string $action_type, int $reference_id = 0 ): bool {
        global $wpdb;

        $where = $wpdb->prepare(
            "user_id = %d AND action_type = %s AND DATE(created_at) = %s",
            $user_id,
            $action_type,
            current_time( 'Y-m-d' )
        );

        if ( $reference_id ) {
            $where .= $wpdb->prepare( " AND reference_id = %d", $reference_id );
        }

        return (bool) $wpdb->get_var(
            "SELECT COUNT(*) FROM " . self::get_transactions_table() . " WHERE {$where}"
        );
    }
}
