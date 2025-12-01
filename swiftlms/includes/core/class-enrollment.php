<?php
/**
 * Enrollment class.
 *
 * @package SwiftLMS\Core
 */

namespace SwiftLMS\Core;

use SwiftLMS\Database\EnrollmentTable;
use SwiftLMS\Database\AnalyticsTable;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manages user course enrollments.
 *
 * Handles enrollment, unenrollment, access control, and enrollment status.
 */
class Enrollment {

    /**
     * Enrollment statuses.
     */
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';

    /**
     * Enrollment sources.
     */
    public const SOURCE_MANUAL       = 'manual';
    public const SOURCE_SELF         = 'self';
    public const SOURCE_PURCHASE     = 'purchase';
    public const SOURCE_ADMIN        = 'admin';
    public const SOURCE_IMPORT       = 'import';
    public const SOURCE_INTEGRATION  = 'integration';

    /**
     * Enroll a user in a course.
     *
     * @param int         $user_id   The user ID.
     * @param int         $course_id The course ID.
     * @param array       $args      Optional. Additional enrollment arguments.
     * @return int|false The enrollment ID or false on failure.
     */
    public function enroll( int $user_id, int $course_id, array $args = array() ) {
        // Validate user and course exist.
        if ( ! get_userdata( $user_id ) ) {
            return false;
        }

        $course = get_post( $course_id );
        if ( ! $course || $course->post_type !== 'sfls_course' ) {
            return false;
        }

        // Check if already enrolled.
        if ( $this->is_enrolled( $user_id, $course_id ) ) {
            return false;
        }

        // Check max students limit.
        if ( $this->is_course_full( $course_id ) ) {
            return false;
        }

        $defaults = array(
            'status'            => self::STATUS_ACTIVE,
            'enrollment_source' => self::SOURCE_SELF,
            'expires_at'        => null,
            'meta'              => array(),
        );

        $args = wp_parse_args( $args, $defaults );

        // Insert enrollment.
        $enrollment_id = EnrollmentTable::insert(
            array(
                'user_id'           => $user_id,
                'course_id'         => $course_id,
                'status'            => $args['status'],
                'enrollment_source' => $args['enrollment_source'],
                'expires_at'        => $args['expires_at'],
                'meta'              => $args['meta'],
            )
        );

        if ( ! $enrollment_id ) {
            return false;
        }

        // Track analytics event.
        AnalyticsTable::track(
            $user_id,
            AnalyticsTable::EVENT_ENROLLMENT,
            'course',
            $course_id,
            array(
                'source'    => $args['enrollment_source'],
                'expires'   => $args['expires_at'],
            )
        );

        /**
         * Fires after a user is enrolled in a course.
         *
         * @param int   $user_id       The user ID.
         * @param int   $course_id     The course ID.
         * @param int   $enrollment_id The enrollment ID.
         * @param array $args          The enrollment arguments.
         */
        do_action( 'swiftlms_user_enrolled', $user_id, $course_id, $enrollment_id, $args );

        return $enrollment_id;
    }

    /**
     * Unenroll a user from a course.
     *
     * @param int    $user_id   The user ID.
     * @param int    $course_id The course ID.
     * @param string $reason    Optional. Reason for unenrollment.
     * @return bool True on success, false on failure.
     */
    public function unenroll( int $user_id, int $course_id, string $reason = '' ): bool {
        $enrollment = $this->get_enrollment( $user_id, $course_id );

        if ( ! $enrollment ) {
            return false;
        }

        // Update status to cancelled.
        $result = EnrollmentTable::update(
            $user_id,
            $course_id,
            array(
                'status' => self::STATUS_CANCELLED,
                'meta'   => array(
                    'cancelled_at'     => current_time( 'mysql' ),
                    'cancellation_reason' => $reason,
                ),
            )
        );

        if ( ! $result ) {
            return false;
        }

        /**
         * Fires after a user is unenrolled from a course.
         *
         * @param int    $user_id   The user ID.
         * @param int    $course_id The course ID.
         * @param string $reason    The unenrollment reason.
         */
        do_action( 'swiftlms_user_unenrolled', $user_id, $course_id, $reason );

        return true;
    }

    /**
     * Check if a user is enrolled in a course.
     *
     * @param int  $user_id     The user ID.
     * @param int  $course_id   The course ID.
     * @param bool $active_only Whether to only check active enrollments.
     * @return bool True if enrolled.
     */
    public function is_enrolled( int $user_id, int $course_id, bool $active_only = true ): bool {
        // Admins can access all courses.
        if ( user_can( $user_id, 'manage_options' ) ) {
            return true;
        }

        // Check if course is open access.
        $access_type = get_post_meta( $course_id, '_swiftlms_access_type', true );
        if ( $access_type === 'open' ) {
            return true;
        }

        return EnrollmentTable::is_enrolled( $user_id, $course_id, $active_only );
    }

    /**
     * Get enrollment record.
     *
     * @param int $user_id   The user ID.
     * @param int $course_id The course ID.
     * @return object|null The enrollment record or null.
     */
    public function get_enrollment( int $user_id, int $course_id ): ?object {
        return EnrollmentTable::get( $user_id, $course_id );
    }

    /**
     * Get all enrollments for a user.
     *
     * @param int         $user_id The user ID.
     * @param string|null $status  Optional. Filter by status.
     * @return array<object> Array of enrollment records.
     */
    public function get_user_enrollments( int $user_id, ?string $status = null ): array {
        return EnrollmentTable::get_by_user( $user_id, $status );
    }

    /**
     * Get all enrollments for a course.
     *
     * @param int         $course_id The course ID.
     * @param string|null $status    Optional. Filter by status.
     * @param int         $limit     Optional. Number of records.
     * @param int         $offset    Optional. Offset.
     * @return array<object> Array of enrollment records.
     */
    public function get_course_enrollments( int $course_id, ?string $status = null, int $limit = -1, int $offset = 0 ): array {
        return EnrollmentTable::get_by_course( $course_id, $status, $limit, $offset );
    }

    /**
     * Update enrollment progress.
     *
     * @param int   $user_id          The user ID.
     * @param int   $course_id        The course ID.
     * @param float $progress_percent The progress percentage.
     * @return bool True on success.
     */
    public function update_progress( int $user_id, int $course_id, float $progress_percent ): bool {
        $data = array(
            'progress_percent'  => min( 100, max( 0, $progress_percent ) ),
            'last_activity_at'  => current_time( 'mysql' ),
        );

        // Mark as completed if 100%.
        if ( $progress_percent >= 100 ) {
            $data['status']       = self::STATUS_COMPLETED;
            $data['completed_at'] = current_time( 'mysql' );
        }

        $result = EnrollmentTable::update( $user_id, $course_id, $data );

        if ( $result && $progress_percent >= 100 ) {
            /**
             * Fires when a user completes a course.
             *
             * @param int $user_id   The user ID.
             * @param int $course_id The course ID.
             */
            do_action( 'swiftlms_course_completed', $user_id, $course_id );

            // Track analytics.
            AnalyticsTable::track(
                $user_id,
                AnalyticsTable::EVENT_COURSE_COMPLETE,
                'course',
                $course_id
            );
        }

        return $result;
    }

    /**
     * Mark a course as completed.
     *
     * @param int $user_id   The user ID.
     * @param int $course_id The course ID.
     * @return bool True on success.
     */
    public function mark_completed( int $user_id, int $course_id ): bool {
        return $this->update_progress( $user_id, $course_id, 100 );
    }

    /**
     * Update last activity timestamp.
     *
     * @param int $user_id   The user ID.
     * @param int $course_id The course ID.
     * @return bool True on success.
     */
    public function touch( int $user_id, int $course_id ): bool {
        return EnrollmentTable::update(
            $user_id,
            $course_id,
            array( 'last_activity_at' => current_time( 'mysql' ) )
        );
    }

    /**
     * Check if a course has reached its student limit.
     *
     * @param int $course_id The course ID.
     * @return bool True if the course is full.
     */
    public function is_course_full( int $course_id ): bool {
        $max_students = (int) get_post_meta( $course_id, '_swiftlms_max_students', true );

        if ( $max_students <= 0 ) {
            return false; // Unlimited.
        }

        $current_count = EnrollmentTable::count_by_course( $course_id, self::STATUS_ACTIVE );

        return $current_count >= $max_students;
    }

    /**
     * Get enrollment count for a course.
     *
     * @param int         $course_id The course ID.
     * @param string|null $status    Optional. Filter by status.
     * @return int The enrollment count.
     */
    public function get_enrollment_count( int $course_id, ?string $status = null ): int {
        return EnrollmentTable::count_by_course( $course_id, $status );
    }

    /**
     * Extend enrollment expiration.
     *
     * @param int    $user_id   The user ID.
     * @param int    $course_id The course ID.
     * @param string $new_date  The new expiration date (Y-m-d H:i:s format).
     * @return bool True on success.
     */
    public function extend_enrollment( int $user_id, int $course_id, string $new_date ): bool {
        $enrollment = $this->get_enrollment( $user_id, $course_id );

        if ( ! $enrollment ) {
            return false;
        }

        $result = EnrollmentTable::update(
            $user_id,
            $course_id,
            array(
                'expires_at' => $new_date,
                'status'     => self::STATUS_ACTIVE, // Reactivate if expired.
            )
        );

        if ( $result ) {
            /**
             * Fires when an enrollment is extended.
             *
             * @param int    $user_id   The user ID.
             * @param int    $course_id The course ID.
             * @param string $new_date  The new expiration date.
             */
            do_action( 'swiftlms_enrollment_extended', $user_id, $course_id, $new_date );
        }

        return $result;
    }

    /**
     * Process expired enrollments.
     *
     * Should be called via cron to expire enrollments that have passed their expiration date.
     *
     * @return int Number of enrollments expired.
     */
    public function process_expirations(): int {
        $count = EnrollmentTable::expire_enrollments();

        if ( $count > 0 ) {
            /**
             * Fires after enrollments have been expired.
             *
             * @param int $count The number of expired enrollments.
             */
            do_action( 'swiftlms_enrollments_expired', $count );
        }

        return $count;
    }

    /**
     * Get courses a user is enrolled in.
     *
     * @param int         $user_id The user ID.
     * @param string|null $status  Optional. Filter by status.
     * @return array<int> Array of course IDs.
     */
    public function get_user_course_ids( int $user_id, ?string $status = null ): array {
        $enrollments = $this->get_user_enrollments( $user_id, $status );
        return array_column( $enrollments, 'course_id' );
    }

    /**
     * Get enrollment statistics.
     *
     * @return object Statistics object.
     */
    public function get_stats(): object {
        return EnrollmentTable::get_stats();
    }

    /**
     * Check if user can access course content.
     *
     * @param int $user_id   The user ID.
     * @param int $course_id The course ID.
     * @return bool True if user can access.
     */
    public function can_access( int $user_id, int $course_id ): bool {
        // Admins always have access.
        if ( user_can( $user_id, 'manage_options' ) ) {
            return true;
        }

        // Check access type.
        $access_type = get_post_meta( $course_id, '_swiftlms_access_type', true );

        // Open courses are accessible to all.
        if ( $access_type === 'open' ) {
            return true;
        }

        // For other types, check enrollment.
        return $this->is_enrolled( $user_id, $course_id );
    }

    /**
     * Bulk enroll users.
     *
     * @param array<int> $user_ids  Array of user IDs.
     * @param int        $course_id The course ID.
     * @param array      $args      Optional. Enrollment arguments.
     * @return array{success: int, failed: int} Results.
     */
    public function bulk_enroll( array $user_ids, int $course_id, array $args = array() ): array {
        $results = array(
            'success' => 0,
            'failed'  => 0,
        );

        $args['enrollment_source'] = $args['enrollment_source'] ?? self::SOURCE_ADMIN;

        foreach ( $user_ids as $user_id ) {
            $enrollment_id = $this->enroll( $user_id, $course_id, $args );

            if ( $enrollment_id ) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }
}
