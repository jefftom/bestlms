<?php
/**
 * Database Schema class.
 *
 * @package SwiftLMS\Database
 */

namespace SwiftLMS\Database;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manages database schema creation and updates.
 *
 * Creates custom tables for performance-critical data that
 * shouldn't be stored in WordPress postmeta.
 */
class Schema {

    /**
     * Database version.
     *
     * Increment this when making schema changes.
     *
     * @var string
     */
    public const DB_VERSION = '1.0.0';

    /**
     * Table names cache.
     *
     * @var array<string, string>
     */
    protected static array $tables = array();

    /**
     * Get table name with prefix.
     *
     * @param string $table The table name without prefix.
     * @return string The full table name with prefix.
     */
    public static function get_table_name( string $table ): string {
        global $wpdb;

        if ( ! isset( self::$tables[ $table ] ) ) {
            self::$tables[ $table ] = $wpdb->prefix . 'swiftlms_' . $table;
        }

        return self::$tables[ $table ];
    }

    /**
     * Get the progress table name.
     *
     * @return string
     */
    public static function progress_table(): string {
        return self::get_table_name( 'progress' );
    }

    /**
     * Get the enrollments table name.
     *
     * @return string
     */
    public static function enrollments_table(): string {
        return self::get_table_name( 'enrollments' );
    }

    /**
     * Get the analytics table name.
     *
     * @return string
     */
    public static function analytics_table(): string {
        return self::get_table_name( 'analytics' );
    }

    /**
     * Create all database tables.
     *
     * @return void
     */
    public static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Create progress table.
        self::create_progress_table( $charset_collate );

        // Create enrollments table.
        self::create_enrollments_table( $charset_collate );

        // Create analytics table.
        self::create_analytics_table( $charset_collate );

        // Store the database version.
        update_option( 'swiftlms_db_version', self::DB_VERSION );
    }

    /**
     * Create the progress table.
     *
     * @param string $charset_collate The database charset collate.
     * @return void
     */
    protected static function create_progress_table( string $charset_collate ): void {
        $table_name = self::progress_table();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            course_id BIGINT UNSIGNED NOT NULL,
            lesson_id BIGINT UNSIGNED NOT NULL,
            topic_id BIGINT UNSIGNED DEFAULT NULL,
            status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
            progress_percent DECIMAL(5,2) DEFAULT 0,
            video_position INT UNSIGNED DEFAULT 0,
            video_duration INT UNSIGNED DEFAULT 0,
            seconds_watched_vector LONGTEXT,
            started_at DATETIME DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_course (user_id, course_id),
            INDEX idx_user_lesson (user_id, lesson_id),
            INDEX idx_course_status (course_id, status),
            INDEX idx_user_topic (user_id, topic_id),
            UNIQUE KEY unique_user_lesson (user_id, lesson_id)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    /**
     * Create the enrollments table.
     *
     * @param string $charset_collate The database charset collate.
     * @return void
     */
    protected static function create_enrollments_table( string $charset_collate ): void {
        $table_name = self::enrollments_table();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            course_id BIGINT UNSIGNED NOT NULL,
            status ENUM('active', 'expired', 'cancelled', 'completed') DEFAULT 'active',
            enrolled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            progress_percent DECIMAL(5,2) DEFAULT 0,
            last_activity_at DATETIME DEFAULT NULL,
            enrollment_source VARCHAR(50) DEFAULT 'manual',
            meta JSON,
            UNIQUE KEY unique_enrollment (user_id, course_id),
            INDEX idx_user (user_id),
            INDEX idx_course (course_id),
            INDEX idx_status (status),
            INDEX idx_expires (expires_at)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    /**
     * Create the analytics table.
     *
     * @param string $charset_collate The database charset collate.
     * @return void
     */
    protected static function create_analytics_table( string $charset_collate ): void {
        $table_name = self::analytics_table();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            object_type VARCHAR(50) DEFAULT NULL,
            object_id BIGINT UNSIGNED DEFAULT NULL,
            event_data JSON,
            session_id VARCHAR(64) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_event (user_id, event_type),
            INDEX idx_created (created_at),
            INDEX idx_object (object_type, object_id),
            INDEX idx_session (session_id)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    /**
     * Check if a table exists.
     *
     * @param string $table The table name without prefix.
     * @return bool True if the table exists.
     */
    public static function table_exists( string $table ): bool {
        global $wpdb;

        $table_name = self::get_table_name( $table );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $table_name
            )
        );

        return $result === $table_name;
    }

    /**
     * Drop all SwiftLMS tables.
     *
     * Use with caution - this permanently deletes all data.
     *
     * @return void
     */
    public static function drop_tables(): void {
        global $wpdb;

        $tables = array(
            self::progress_table(),
            self::enrollments_table(),
            self::analytics_table(),
        );

        foreach ( $tables as $table ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
        }

        delete_option( 'swiftlms_db_version' );
    }

    /**
     * Check if database needs upgrading.
     *
     * @return bool True if upgrade is needed.
     */
    public static function needs_upgrade(): bool {
        $current_version = get_option( 'swiftlms_db_version', '0.0.0' );
        return version_compare( $current_version, self::DB_VERSION, '<' );
    }

    /**
     * Run database upgrades.
     *
     * @return void
     */
    public static function maybe_upgrade(): void {
        if ( ! self::needs_upgrade() ) {
            return;
        }

        $current_version = get_option( 'swiftlms_db_version', '0.0.0' );

        // Run version-specific upgrades.
        // Example:
        // if ( version_compare( $current_version, '1.1.0', '<' ) ) {
        //     self::upgrade_to_1_1_0();
        // }

        // Re-create tables (dbDelta handles existing tables gracefully).
        self::create_tables();
    }
}
