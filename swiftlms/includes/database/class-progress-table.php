<?php
/**
 * Progress Table class.
 *
 * @package SwiftLMS\Database
 */

namespace SwiftLMS\Database;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles progress table operations.
 *
 * Provides methods for inserting, updating, and querying
 * progress data from the custom database table.
 */
class ProgressTable {

    /**
     * Get the table name.
     *
     * @return string
     */
    public static function table_name(): string {
        return Schema::progress_table();
    }

    /**
     * Get progress record by user and lesson.
     *
     * @param int $user_id   The user ID.
     * @param int $lesson_id The lesson ID.
     * @return object|null The progress record or null.
     */
    public static function get( int $user_id, int $lesson_id ): ?object {
        global $wpdb;

        $table = self::table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d AND lesson_id = %d",
                $user_id,
                $lesson_id
            )
        );

        return $result ?: null;
    }

    /**
     * Get progress record by ID.
     *
     * @param int $id The progress record ID.
     * @return object|null The progress record or null.
     */
    public static function get_by_id( int $id ): ?object {
        global $wpdb;

        $table = self::table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $id
            )
        );

        return $result ?: null;
    }

    /**
     * Get all progress records for a user in a course.
     *
     * @param int $user_id   The user ID.
     * @param int $course_id The course ID.
     * @return array<object> Array of progress records.
     */
    public static function get_by_course( int $user_id, int $course_id ): array {
        global $wpdb;

        $table = self::table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d AND course_id = %d",
                $user_id,
                $course_id
            )
        );

        return $results ?: array();
    }

    /**
     * Get all progress records for a user.
     *
     * @param int $user_id The user ID.
     * @param int $limit   Optional. Number of records to return. Default -1 (all).
     * @param int $offset  Optional. Number of records to skip. Default 0.
     * @return array<object> Array of progress records.
     */
    public static function get_by_user( int $user_id, int $limit = -1, int $offset = 0 ): array {
        global $wpdb;

        $table = self::table_name();

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY updated_at DESC",
            $user_id
        );

        if ( $limit > 0 ) {
            $sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results( $sql );

        return $results ?: array();
    }

    /**
     * Insert a new progress record.
     *
     * @param array $data The progress data.
     * @return int|false The inserted ID or false on failure.
     */
    public static function insert( array $data ) {
        global $wpdb;

        $table = self::table_name();

        $defaults = array(
            'user_id'                => 0,
            'course_id'              => 0,
            'lesson_id'              => 0,
            'topic_id'               => null,
            'status'                 => 'not_started',
            'progress_percent'       => 0,
            'video_position'         => 0,
            'video_duration'         => 0,
            'seconds_watched_vector' => null,
            'started_at'             => null,
            'completed_at'           => null,
        );

        $data = wp_parse_args( $data, $defaults );

        // Validate required fields.
        if ( empty( $data['user_id'] ) || empty( $data['course_id'] ) || empty( $data['lesson_id'] ) ) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert(
            $table,
            array(
                'user_id'                => absint( $data['user_id'] ),
                'course_id'              => absint( $data['course_id'] ),
                'lesson_id'              => absint( $data['lesson_id'] ),
                'topic_id'               => $data['topic_id'] ? absint( $data['topic_id'] ) : null,
                'status'                 => sanitize_key( $data['status'] ),
                'progress_percent'       => floatval( $data['progress_percent'] ),
                'video_position'         => absint( $data['video_position'] ),
                'video_duration'         => absint( $data['video_duration'] ),
                'seconds_watched_vector' => $data['seconds_watched_vector'],
                'started_at'             => $data['started_at'],
                'completed_at'           => $data['completed_at'],
            ),
            array( '%d', '%d', '%d', '%d', '%s', '%f', '%d', '%d', '%s', '%s', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update a progress record.
     *
     * @param int   $user_id   The user ID.
     * @param int   $lesson_id The lesson ID.
     * @param array $data      The data to update.
     * @return bool True on success, false on failure.
     */
    public static function update( int $user_id, int $lesson_id, array $data ): bool {
        global $wpdb;

        $table = self::table_name();

        // Build the update data and formats.
        $update_data    = array();
        $update_formats = array();

        $allowed_fields = array(
            'status'                 => '%s',
            'progress_percent'       => '%f',
            'video_position'         => '%d',
            'video_duration'         => '%d',
            'seconds_watched_vector' => '%s',
            'started_at'             => '%s',
            'completed_at'           => '%s',
            'topic_id'               => '%d',
        );

        foreach ( $allowed_fields as $field => $format ) {
            if ( array_key_exists( $field, $data ) ) {
                $update_data[ $field ]    = $data[ $field ];
                $update_formats[]         = $format;
            }
        }

        if ( empty( $update_data ) ) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $table,
            $update_data,
            array(
                'user_id'   => $user_id,
                'lesson_id' => $lesson_id,
            ),
            $update_formats,
            array( '%d', '%d' )
        );

        return $result !== false;
    }

    /**
     * Insert or update a progress record.
     *
     * @param int   $user_id   The user ID.
     * @param int   $lesson_id The lesson ID.
     * @param array $data      The progress data.
     * @return int|false The record ID or false on failure.
     */
    public static function upsert( int $user_id, int $lesson_id, array $data ) {
        $existing = self::get( $user_id, $lesson_id );

        if ( $existing ) {
            $result = self::update( $user_id, $lesson_id, $data );
            return $result ? $existing->id : false;
        }

        $data['user_id']   = $user_id;
        $data['lesson_id'] = $lesson_id;

        return self::insert( $data );
    }

    /**
     * Delete a progress record.
     *
     * @param int $user_id   The user ID.
     * @param int $lesson_id The lesson ID.
     * @return bool True on success, false on failure.
     */
    public static function delete( int $user_id, int $lesson_id ): bool {
        global $wpdb;

        $table = self::table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->delete(
            $table,
            array(
                'user_id'   => $user_id,
                'lesson_id' => $lesson_id,
            ),
            array( '%d', '%d' )
        );

        return $result !== false;
    }

    /**
     * Delete all progress records for a user in a course.
     *
     * @param int $user_id   The user ID.
     * @param int $course_id The course ID.
     * @return int The number of deleted records.
     */
    public static function delete_by_course( int $user_id, int $course_id ): int {
        global $wpdb;

        $table = self::table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->delete(
            $table,
            array(
                'user_id'   => $user_id,
                'course_id' => $course_id,
            ),
            array( '%d', '%d' )
        );

        return $result ?: 0;
    }

    /**
     * Count completed lessons in a course.
     *
     * @param int $user_id   The user ID.
     * @param int $course_id The course ID.
     * @return int The count of completed lessons.
     */
    public static function count_completed( int $user_id, int $course_id ): int {
        global $wpdb;

        $table = self::table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND course_id = %d AND status = 'completed'",
                $user_id,
                $course_id
            )
        );

        return (int) $count;
    }

    /**
     * Get the most recent activity for a user.
     *
     * @param int $user_id The user ID.
     * @param int $limit   The number of records to return.
     * @return array<object> Array of recent progress records.
     */
    public static function get_recent_activity( int $user_id, int $limit = 10 ): array {
        global $wpdb;

        $table = self::table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY updated_at DESC LIMIT %d",
                $user_id,
                $limit
            )
        );

        return $results ?: array();
    }

    /**
     * Get progress statistics for a course.
     *
     * @param int $course_id The course ID.
     * @return object Statistics object with totals.
     */
    public static function get_course_stats( int $course_id ): object {
        global $wpdb;

        $table = self::table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(DISTINCT user_id) as total_users,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
                    AVG(progress_percent) as avg_progress
                FROM {$table}
                WHERE course_id = %d",
                $course_id
            )
        );

        return $stats ?: (object) array(
            'total_users'       => 0,
            'completed_count'   => 0,
            'in_progress_count' => 0,
            'avg_progress'      => 0,
        );
    }
}
