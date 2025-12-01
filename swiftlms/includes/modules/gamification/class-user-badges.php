<?php
/**
 * User Badges Table
 *
 * @package SwiftLMS
 */

namespace SwiftLMS\Modules\Gamification;

defined( 'ABSPATH' ) || exit;

/**
 * User Badges class.
 */
class User_Badges {

    /**
     * Table name.
     *
     * @var string
     */
    private static string $table_name = 'swiftlms_user_badges';

    /**
     * Create table.
     */
    public static function create_table(): void {
        global $wpdb;

        $table_name      = $wpdb->prefix . self::$table_name;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            badge_id bigint(20) UNSIGNED NOT NULL,
            earned_at datetime NOT NULL,
            notified tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY user_badge (user_id, badge_id),
            KEY user_id (user_id),
            KEY badge_id (badge_id),
            KEY earned_at (earned_at)
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
     * Award badge to user.
     *
     * @param int $user_id  User ID.
     * @param int $badge_id Badge ID.
     * @return bool
     */
    public static function award_badge( int $user_id, int $badge_id ): bool {
        global $wpdb;

        // Check if already awarded
        if ( self::has_badge( $user_id, $badge_id ) ) {
            return false;
        }

        // Get badge data
        $badge = Badge::get_badge( $badge_id );
        if ( ! $badge ) {
            return false;
        }

        // Insert badge record
        $result = $wpdb->insert(
            self::get_table_name(),
            array(
                'user_id'   => $user_id,
                'badge_id'  => $badge_id,
                'earned_at' => current_time( 'mysql' ),
                'notified'  => 0,
            ),
            array( '%d', '%d', '%s', '%d' )
        );

        if ( ! $result ) {
            return false;
        }

        // Award bonus points
        if ( $badge['points_reward'] > 0 ) {
            Points_Table::award_points(
                $user_id,
                $badge['points_reward'],
                Points_Table::ACTION_BADGE_EARNED,
                $badge_id,
                'badge',
                sprintf( __( 'Earned badge: %s', 'swiftlms' ), $badge['title'] )
            );
        }

        // Trigger action
        do_action( 'swiftlms_badge_earned', $user_id, $badge_id, $badge );

        return true;
    }

    /**
     * Check if user has badge.
     *
     * @param int $user_id  User ID.
     * @param int $badge_id Badge ID.
     * @return bool
     */
    public static function has_badge( int $user_id, int $badge_id ): bool {
        global $wpdb;

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE user_id = %d AND badge_id = %d",
                self::get_table_name(),
                $user_id,
                $badge_id
            )
        );
    }

    /**
     * Get user badges.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public static function get_user_badges( int $user_id ): array {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ub.*, p.post_title as badge_title
                FROM %i ub
                LEFT JOIN {$wpdb->posts} p ON ub.badge_id = p.ID
                WHERE ub.user_id = %d
                ORDER BY ub.earned_at DESC",
                self::get_table_name(),
                $user_id
            )
        );

        $badges = array();
        foreach ( $results as $row ) {
            $badge_data = Badge::get_badge( (int) $row->badge_id );
            if ( $badge_data ) {
                $badge_data['earned_at'] = $row->earned_at;
                $badges[]                = $badge_data;
            }
        }

        return $badges;
    }

    /**
     * Get user badge count.
     *
     * @param int $user_id User ID.
     * @return int
     */
    public static function get_user_badge_count( int $user_id ): int {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE user_id = %d",
                self::get_table_name(),
                $user_id
            )
        );
    }

    /**
     * Get badge award count.
     *
     * @param int $badge_id Badge ID.
     * @return int
     */
    public static function get_badge_award_count( int $badge_id ): int {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE badge_id = %d",
                self::get_table_name(),
                $badge_id
            )
        );
    }

    /**
     * Get unnotified badges.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public static function get_unnotified_badges( int $user_id ): array {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT badge_id FROM %i WHERE user_id = %d AND notified = 0",
                self::get_table_name(),
                $user_id
            )
        );

        $badges = array();
        foreach ( $results as $row ) {
            $badge = Badge::get_badge( (int) $row->badge_id );
            if ( $badge ) {
                $badges[] = $badge;
            }
        }

        return $badges;
    }

    /**
     * Mark badges as notified.
     *
     * @param int $user_id User ID.
     * @return bool
     */
    public static function mark_badges_notified( int $user_id ): bool {
        global $wpdb;

        return (bool) $wpdb->update(
            self::get_table_name(),
            array( 'notified' => 1 ),
            array( 'user_id' => $user_id, 'notified' => 0 ),
            array( '%d' ),
            array( '%d', '%d' )
        );
    }

    /**
     * Revoke badge from user.
     *
     * @param int $user_id  User ID.
     * @param int $badge_id Badge ID.
     * @return bool
     */
    public static function revoke_badge( int $user_id, int $badge_id ): bool {
        global $wpdb;

        return (bool) $wpdb->delete(
            self::get_table_name(),
            array(
                'user_id'  => $user_id,
                'badge_id' => $badge_id,
            ),
            array( '%d', '%d' )
        );
    }

    /**
     * Get recent badge earners.
     *
     * @param int $limit Limit.
     * @return array
     */
    public static function get_recent_earners( int $limit = 10 ): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ub.*, u.display_name, p.post_title as badge_title
                FROM %i ub
                LEFT JOIN {$wpdb->users} u ON ub.user_id = u.ID
                LEFT JOIN {$wpdb->posts} p ON ub.badge_id = p.ID
                ORDER BY ub.earned_at DESC
                LIMIT %d",
                self::get_table_name(),
                $limit
            )
        );
    }
}
