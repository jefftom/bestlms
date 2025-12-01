<?php
/**
 * Analytics Table class.
 *
 * @package SwiftLMS\Database
 */

namespace SwiftLMS\Database;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles analytics table operations.
 *
 * Provides methods for tracking and querying learning analytics events.
 */
class AnalyticsTable {

    /**
     * Event types.
     */
    public const EVENT_COURSE_VIEW      = 'course_view';
    public const EVENT_LESSON_START     = 'lesson_start';
    public const EVENT_LESSON_COMPLETE  = 'lesson_complete';
    public const EVENT_VIDEO_PLAY       = 'video_play';
    public const EVENT_VIDEO_PAUSE      = 'video_pause';
    public const EVENT_VIDEO_COMPLETE   = 'video_complete';
    public const EVENT_VIDEO_MILESTONE  = 'video_milestone';
    public const EVENT_QUIZ_START       = 'quiz_start';
    public const EVENT_QUIZ_COMPLETE    = 'quiz_complete';
    public const EVENT_COURSE_COMPLETE  = 'course_complete';
    public const EVENT_ENROLLMENT       = 'enrollment';
    public const EVENT_DOWNLOAD         = 'download';

    /**
     * Get the table name.
     *
     * @return string
     */
    public static function table_name(): string {
        return Schema::analytics_table();
    }

    /**
     * Track an event.
     *
     * @param int         $user_id     The user ID.
     * @param string      $event_type  The event type.
     * @param string|null $object_type Optional. The object type (course, lesson, etc.).
     * @param int|null    $object_id   Optional. The object ID.
     * @param array|null  $event_data  Optional. Additional event data.
     * @return int|false The inserted ID or false on failure.
     */
    public static function track( int $user_id, string $event_type, ?string $object_type = null, ?int $object_id = null, ?array $event_data = null ) {
        global $wpdb;

        $table = self::table_name();

        // Get session ID.
        $session_id = self::get_session_id();

        // Get user agent and IP.
        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : null;
        $ip_address = self::get_client_ip();

        // Truncate user agent if too long.
        if ( $user_agent && strlen( $user_agent ) > 255 ) {
            $user_agent = substr( $user_agent, 0, 255 );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert(
            $table,
            array(
                'user_id'     => $user_id,
                'event_type'  => sanitize_key( $event_type ),
                'object_type' => $object_type ? sanitize_key( $object_type ) : null,
                'object_id'   => $object_id ? absint( $object_id ) : null,
                'event_data'  => $event_data ? wp_json_encode( $event_data ) : null,
                'session_id'  => $session_id,
                'ip_address'  => $ip_address,
                'user_agent'  => $user_agent,
            ),
            array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get events by user.
     *
     * @param int         $user_id    The user ID.
     * @param string|null $event_type Optional. Filter by event type.
     * @param int         $limit      Optional. Number of records to return.
     * @param int         $offset     Optional. Number of records to skip.
     * @return array<object> Array of event records.
     */
    public static function get_by_user( int $user_id, ?string $event_type = null, int $limit = 100, int $offset = 0 ): array {
        global $wpdb;

        $table = self::table_name();

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d",
            $user_id
        );

        if ( $event_type ) {
            $sql .= $wpdb->prepare( ' AND event_type = %s', $event_type );
        }

        $sql .= ' ORDER BY created_at DESC';
        $sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results( $sql );

        return $results ?: array();
    }

    /**
     * Get events by object.
     *
     * @param string      $object_type The object type.
     * @param int         $object_id   The object ID.
     * @param string|null $event_type  Optional. Filter by event type.
     * @param int         $limit       Optional. Number of records to return.
     * @return array<object> Array of event records.
     */
    public static function get_by_object( string $object_type, int $object_id, ?string $event_type = null, int $limit = 100 ): array {
        global $wpdb;

        $table = self::table_name();

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE object_type = %s AND object_id = %d",
            $object_type,
            $object_id
        );

        if ( $event_type ) {
            $sql .= $wpdb->prepare( ' AND event_type = %s', $event_type );
        }

        $sql .= ' ORDER BY created_at DESC';
        $sql .= $wpdb->prepare( ' LIMIT %d', $limit );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results( $sql );

        return $results ?: array();
    }

    /**
     * Count events by type for a time period.
     *
     * @param string      $event_type The event type.
     * @param string|null $start_date Optional. Start date (Y-m-d format).
     * @param string|null $end_date   Optional. End date (Y-m-d format).
     * @return int The event count.
     */
    public static function count_events( string $event_type, ?string $start_date = null, ?string $end_date = null ): int {
        global $wpdb;

        $table = self::table_name();

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE event_type = %s",
            $event_type
        );

        if ( $start_date ) {
            $sql .= $wpdb->prepare( ' AND created_at >= %s', $start_date . ' 00:00:00' );
        }

        if ( $end_date ) {
            $sql .= $wpdb->prepare( ' AND created_at <= %s', $end_date . ' 23:59:59' );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $count = $wpdb->get_var( $sql );

        return (int) $count;
    }

    /**
     * Get event counts grouped by date.
     *
     * @param string $event_type The event type.
     * @param int    $days       Number of days to look back.
     * @return array<string, int> Array of counts keyed by date.
     */
    public static function get_daily_counts( string $event_type, int $days = 30 ): array {
        global $wpdb;

        $table = self::table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) as date, COUNT(*) as count
                FROM {$table}
                WHERE event_type = %s
                AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC",
                $event_type,
                $days
            )
        );

        $counts = array();

        if ( $results ) {
            foreach ( $results as $row ) {
                $counts[ $row->date ] = (int) $row->count;
            }
        }

        return $counts;
    }

    /**
     * Get unique users for an event type.
     *
     * @param string      $event_type The event type.
     * @param string|null $start_date Optional. Start date.
     * @param string|null $end_date   Optional. End date.
     * @return int The unique user count.
     */
    public static function count_unique_users( string $event_type, ?string $start_date = null, ?string $end_date = null ): int {
        global $wpdb;

        $table = self::table_name();

        $sql = $wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE event_type = %s",
            $event_type
        );

        if ( $start_date ) {
            $sql .= $wpdb->prepare( ' AND created_at >= %s', $start_date . ' 00:00:00' );
        }

        if ( $end_date ) {
            $sql .= $wpdb->prepare( ' AND created_at <= %s', $end_date . ' 23:59:59' );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $count = $wpdb->get_var( $sql );

        return (int) $count;
    }

    /**
     * Get most viewed courses.
     *
     * @param int $limit Number of courses to return.
     * @param int $days  Number of days to look back.
     * @return array<object> Array of course view data.
     */
    public static function get_popular_courses( int $limit = 10, int $days = 30 ): array {
        global $wpdb;

        $table = self::table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT object_id, COUNT(*) as views, COUNT(DISTINCT user_id) as unique_users
                FROM {$table}
                WHERE event_type = %s
                AND object_type = 'course'
                AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                GROUP BY object_id
                ORDER BY views DESC
                LIMIT %d",
                self::EVENT_COURSE_VIEW,
                $days,
                $limit
            )
        );

        return $results ?: array();
    }

    /**
     * Delete old analytics records.
     *
     * @param int $days Number of days to keep.
     * @return int The number of deleted records.
     */
    public static function cleanup( int $days = 365 ): int {
        global $wpdb;

        $table = self::table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );

        return $result ?: 0;
    }

    /**
     * Get or create a session ID.
     *
     * @return string The session ID.
     */
    protected static function get_session_id(): string {
        if ( ! session_id() && ! headers_sent() ) {
            session_start();
        }

        if ( isset( $_SESSION['swiftlms_session_id'] ) ) {
            return $_SESSION['swiftlms_session_id'];
        }

        $session_id = wp_generate_uuid4();

        if ( session_id() ) {
            $_SESSION['swiftlms_session_id'] = $session_id;
        }

        return $session_id;
    }

    /**
     * Get the client IP address.
     *
     * @return string|null The IP address or null.
     */
    protected static function get_client_ip(): ?string {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare.
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        );

        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

                // Handle comma-separated list (X-Forwarded-For).
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }

                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return null;
    }
}
