<?php
/**
 * Enrollment Table class.
 *
 * @package SwiftLMS\Database
 */

namespace SwiftLMS\Database;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles enrollment table operations.
 *
 * Provides methods for managing user course enrollments
 * in the custom database table.
 */
class EnrollmentTable {

    /**
     * Get the table name.
     *
     * @return string
     */
    public static function table_name(): string {
        return Schema::enrollments_table();
    }

    /**
     * Get an enrollment record.
     *
     * @param int $user_id   The user ID.
     * @param int $course_id The course ID.
     * @return object|null The enrollment record or null.
     */
    public static function get( int $user_id, int $course_id ): ?object {
        global $wpdb;

        $table = self::table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d AND course_id = %d",
                $user_id,
                $course_id
            )
        );

        return $result ?: null;
    }

    /**
     * Get enrollment by ID.
     *
     * @param int $id The enrollment ID.
     * @return object|null The enrollment record or null.
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
     * Get all enrollments for a user.
     *
     * @param int         $user_id The user ID.
     * @param string|null $status  Optional. Filter by status.
     * @return array<object> Array of enrollment records.
     */
    public static function get_by_user( int $user_id, ?string $status = null ): array {
        global $wpdb;

        $table = self::table_name();

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d",
            $user_id
        );

        if ( $status ) {
            $sql .= $wpdb->prepare( ' AND status = %s', $status );
        }

        $sql .= ' ORDER BY enrolled_at DESC';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results( $sql );

        return $results ?: array();
    }

    /**
     * Get all enrollments for a course.
     *
     * @param int         $course_id The course ID.
     * @param string|null $status    Optional. Filter by status.
     * @param int         $limit     Optional. Number of records to return.
     * @param int         $offset    Optional. Number of records to skip.
     * @return array<object> Array of enrollment records.
     */
    public static function get_by_course( int $course_id, ?string $status = null, int $limit = -1, int $offset = 0 ): array {
        global $wpdb;

        $table = self::table_name();

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE course_id = %d",
            $course_id
        );

        if ( $status ) {
            $sql .= $wpdb->prepare( ' AND status = %s', $status );
        }

        $sql .= ' ORDER BY enrolled_at DESC';

        if ( $limit > 0 ) {
            $sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results( $sql );

        return $results ?: array();
    }

    /**
     * Insert a new enrollment.
     *
     * @param array $data The enrollment data.
     * @return int|false The inserted ID or false on failure.
     */
    public static function insert( array $data ) {
        global $wpdb;

        $table = self::table_name();

        $defaults = array(
            'user_id'           => 0,
            'course_id'         => 0,
            'status'            => 'active',
            'enrolled_at'       => current_time( 'mysql' ),
            'expires_at'        => null,
            'completed_at'      => null,
            'progress_percent'  => 0,
            'last_activity_at'  => null,
            'enrollment_source' => 'manual',
            'meta'              => null,
        );

        $data = wp_parse_args( $data, $defaults );

        // Validate required fields.
        if ( empty( $data['user_id'] ) || empty( $data['course_id'] ) ) {
            return false;
        }

        // Check for existing enrollment.
        $existing = self::get( $data['user_id'], $data['course_id'] );
        if ( $existing ) {
            return false;
        }

        // Encode meta as JSON if it's an array.
        if ( is_array( $data['meta'] ) ) {
            $data['meta'] = wp_json_encode( $data['meta'] );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert(
            $table,
            array(
                'user_id'           => absint( $data['user_id'] ),
                'course_id'         => absint( $data['course_id'] ),
                'status'            => sanitize_key( $data['status'] ),
                'enrolled_at'       => $data['enrolled_at'],
                'expires_at'        => $data['expires_at'],
                'completed_at'      => $data['completed_at'],
                'progress_percent'  => floatval( $data['progress_percent'] ),
                'last_activity_at'  => $data['last_activity_at'],
                'enrollment_source' => sanitize_key( $data['enrollment_source'] ),
                'meta'              => $data['meta'],
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update an enrollment.
     *
     * @param int   $user_id   The user ID.
     * @param int   $course_id The course ID.
     * @param array $data      The data to update.
     * @return bool True on success, false on failure.
     */
    public static function update( int $user_id, int $course_id, array $data ): bool {
        global $wpdb;

        $table = self::table_name();

        $update_data    = array();
        $update_formats = array();

        $allowed_fields = array(
            'status'           => '%s',
            'expires_at'       => '%s',
            'completed_at'     => '%s',
            'progress_percent' => '%f',
            'last_activity_at' => '%s',
            'meta'             => '%s',
        );

        foreach ( $allowed_fields as $field => $format ) {
            if ( array_key_exists( $field, $data ) ) {
                $value = $data[ $field ];

                // Encode meta as JSON if it's an array.
                if ( $field === 'meta' && is_array( $value ) ) {
                    $value = wp_json_encode( $value );
                }

                $update_data[ $field ] = $value;
                $update_formats[]      = $format;
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
                'course_id' => $course_id,
            ),
            $update_formats,
            array( '%d', '%d' )
        );

        return $result !== false;
    }

    /**
     * Delete an enrollment.
     *
     * @param int $user_id   The user ID.
     * @param int $course_id The course ID.
     * @return bool True on success, false on failure.
     */
    public static function delete( int $user_id, int $course_id ): bool {
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

        return $result !== false;
    }

    /**
     * Check if a user is enrolled in a course.
     *
     * @param int  $user_id       The user ID.
     * @param int  $course_id     The course ID.
     * @param bool $active_only   Whether to only check for active enrollments.
     * @return bool True if enrolled.
     */
    public static function is_enrolled( int $user_id, int $course_id, bool $active_only = true ): bool {
        $enrollment = self::get( $user_id, $course_id );

        if ( ! $enrollment ) {
            return false;
        }

        if ( $active_only && $enrollment->status !== 'active' ) {
            return false;
        }

        // Check if enrollment has expired.
        if ( $enrollment->expires_at && strtotime( $enrollment->expires_at ) < time() ) {
            return false;
        }

        return true;
    }

    /**
     * Count enrollments for a course.
     *
     * @param int         $course_id The course ID.
     * @param string|null $status    Optional. Filter by status.
     * @return int The enrollment count.
     */
    public static function count_by_course( int $course_id, ?string $status = null ): int {
        global $wpdb;

        $table = self::table_name();

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE course_id = %d",
            $course_id
        );

        if ( $status ) {
            $sql .= $wpdb->prepare( ' AND status = %s', $status );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $count = $wpdb->get_var( $sql );

        return (int) $count;
    }

    /**
     * Count enrollments for a user.
     *
     * @param int         $user_id The user ID.
     * @param string|null $status  Optional. Filter by status.
     * @return int The enrollment count.
     */
    public static function count_by_user( int $user_id, ?string $status = null ): int {
        global $wpdb;

        $table = self::table_name();

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
            $user_id
        );

        if ( $status ) {
            $sql .= $wpdb->prepare( ' AND status = %s', $status );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $count = $wpdb->get_var( $sql );

        return (int) $count;
    }

    /**
     * Get expired enrollments that need to be marked as expired.
     *
     * @param int $limit The number of records to return.
     * @return array<object> Array of expired enrollments.
     */
    public static function get_expired( int $limit = 100 ): array {
        global $wpdb;

        $table = self::table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE status = 'active'
                AND expires_at IS NOT NULL
                AND expires_at < %s
                LIMIT %d",
                current_time( 'mysql' ),
                $limit
            )
        );

        return $results ?: array();
    }

    /**
     * Expire enrollments that have passed their expiration date.
     *
     * @return int The number of enrollments expired.
     */
    public static function expire_enrollments(): int {
        global $wpdb;

        $table = self::table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                SET status = 'expired'
                WHERE status = 'active'
                AND expires_at IS NOT NULL
                AND expires_at < %s",
                current_time( 'mysql' )
            )
        );

        return $result ?: 0;
    }

    /**
     * Get enrollment statistics.
     *
     * @return object Statistics object.
     */
    public static function get_stats(): object {
        global $wpdb;

        $table = self::table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $stats = $wpdb->get_row(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                AVG(progress_percent) as avg_progress
            FROM {$table}"
        );

        return $stats ?: (object) array(
            'total'        => 0,
            'active'       => 0,
            'completed'    => 0,
            'expired'      => 0,
            'cancelled'    => 0,
            'avg_progress' => 0,
        );
    }
}
