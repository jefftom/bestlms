<?php
/**
 * User class.
 *
 * @package SwiftLMS\Core
 */

namespace SwiftLMS\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles user-related LMS functionality.
 *
 * Provides methods for getting user courses, progress summaries,
 * and other learner-specific data.
 */
class User {

    /**
     * Get a user's enrolled courses.
     *
     * @param int         $user_id The user ID.
     * @param string|null $status  Optional. Filter by enrollment status.
     * @return array Array of course data.
     */
    public static function get_courses( int $user_id, ?string $status = 'active' ): array {
        $enrollments = swiftlms()->enrollment()->get_user_enrollments( $user_id, $status );
        $courses     = array();

        foreach ( $enrollments as $enrollment ) {
            $course = get_post( $enrollment->course_id );

            if ( ! $course || $course->post_status !== 'publish' ) {
                continue;
            }

            $courses[] = array(
                'id'               => (int) $course->ID,
                'title'            => $course->post_title,
                'excerpt'          => $course->post_excerpt,
                'url'              => get_permalink( $course->ID ),
                'thumbnail'        => get_the_post_thumbnail_url( $course->ID, 'medium' ),
                'enrolled_at'      => $enrollment->enrolled_at,
                'expires_at'       => $enrollment->expires_at,
                'progress_percent' => (float) $enrollment->progress_percent,
                'status'           => $enrollment->status,
                'last_activity'    => $enrollment->last_activity_at,
            );
        }

        return $courses;
    }

    /**
     * Get a user's progress summary.
     *
     * @param int $user_id The user ID.
     * @return array Summary statistics.
     */
    public static function get_progress_summary( int $user_id ): array {
        $enrollments = swiftlms()->enrollment()->get_user_enrollments( $user_id );

        $summary = array(
            'total_courses'     => 0,
            'active_courses'    => 0,
            'completed_courses' => 0,
            'total_lessons'     => 0,
            'completed_lessons' => 0,
            'average_progress'  => 0,
            'total_time_spent'  => 0, // Future: track time spent.
        );

        if ( empty( $enrollments ) ) {
            return $summary;
        }

        $total_progress = 0;

        foreach ( $enrollments as $enrollment ) {
            $summary['total_courses']++;

            if ( $enrollment->status === Enrollment::STATUS_ACTIVE ) {
                $summary['active_courses']++;
            }

            if ( $enrollment->status === Enrollment::STATUS_COMPLETED ) {
                $summary['completed_courses']++;
            }

            $total_progress += (float) $enrollment->progress_percent;

            // Count lessons in this course.
            $lessons = get_posts(
                array(
                    'post_type'      => 'sfls_lesson',
                    'posts_per_page' => -1,
                    'post_status'    => 'publish',
                    'meta_key'       => '_swiftlms_course_id',
                    'meta_value'     => $enrollment->course_id,
                    'fields'         => 'ids',
                )
            );

            $summary['total_lessons'] += count( $lessons );

            // Count completed lessons.
            foreach ( $lessons as $lesson_id ) {
                if ( swiftlms()->progress()->is_completed( $user_id, $lesson_id ) ) {
                    $summary['completed_lessons']++;
                }
            }
        }

        if ( $summary['total_courses'] > 0 ) {
            $summary['average_progress'] = round( $total_progress / $summary['total_courses'], 2 );
        }

        return $summary;
    }

    /**
     * Get a user's recent activity.
     *
     * @param int $user_id The user ID.
     * @param int $limit   Number of items to return.
     * @return array Array of activity items.
     */
    public static function get_recent_activity( int $user_id, int $limit = 10 ): array {
        return swiftlms()->progress()->get_recent_activity( $user_id, $limit );
    }

    /**
     * Get a user's certificates.
     *
     * @param int $user_id The user ID.
     * @return array Array of certificate data.
     */
    public static function get_certificates( int $user_id ): array {
        // Check if certificates module is active.
        if ( ! swiftlms()->has_module( 'certificates' ) ) {
            return array();
        }

        /**
         * Filter to get user certificates.
         *
         * @param array $certificates The certificates array.
         * @param int   $user_id      The user ID.
         */
        return apply_filters( 'swiftlms_user_certificates', array(), $user_id );
    }

    /**
     * Get a user's quiz attempts.
     *
     * @param int      $user_id   The user ID.
     * @param int|null $course_id Optional. Filter by course.
     * @param int      $limit     Number of items to return.
     * @return array Array of quiz attempt data.
     */
    public static function get_quiz_attempts( int $user_id, ?int $course_id = null, int $limit = 20 ): array {
        // Check if quizzes module is active.
        if ( ! swiftlms()->has_module( 'quizzes' ) ) {
            return array();
        }

        /**
         * Filter to get user quiz attempts.
         *
         * @param array    $attempts  The attempts array.
         * @param int      $user_id   The user ID.
         * @param int|null $course_id The course ID filter.
         * @param int      $limit     The limit.
         */
        return apply_filters( 'swiftlms_user_quiz_attempts', array(), $user_id, $course_id, $limit );
    }

    /**
     * Check if a user has completed a course.
     *
     * @param int $user_id   The user ID.
     * @param int $course_id The course ID.
     * @return bool True if the course is completed.
     */
    public static function has_completed_course( int $user_id, int $course_id ): bool {
        $enrollment = swiftlms()->enrollment()->get_enrollment( $user_id, $course_id );

        if ( ! $enrollment ) {
            return false;
        }

        return $enrollment->status === Enrollment::STATUS_COMPLETED;
    }

    /**
     * Get the next lesson for a user in a course.
     *
     * @param int $user_id   The user ID.
     * @param int $course_id The course ID.
     * @return \WP_Post|null The next lesson post or null.
     */
    public static function get_next_lesson( int $user_id, int $course_id ): ?\WP_Post {
        $lessons = get_posts(
            array(
                'post_type'      => 'sfls_lesson',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'meta_key'       => '_swiftlms_course_id',
                'meta_value'     => $course_id,
                'orderby'        => 'meta_value_num',
                'meta_key'       => '_swiftlms_order',
                'order'          => 'ASC',
            )
        );

        if ( empty( $lessons ) ) {
            return null;
        }

        // Find first incomplete lesson.
        foreach ( $lessons as $lesson ) {
            if ( ! swiftlms()->progress()->is_completed( $user_id, $lesson->ID ) ) {
                return $lesson;
            }
        }

        // All completed, return the last one.
        return end( $lessons ) ?: null;
    }

    /**
     * Get resume data for a course.
     *
     * Returns the lesson and position where the user should resume.
     *
     * @param int $user_id   The user ID.
     * @param int $course_id The course ID.
     * @return array{lesson_id: int|null, video_position: int, url: string|null} Resume data.
     */
    public static function get_resume_data( int $user_id, int $course_id ): array {
        $default = array(
            'lesson_id'      => null,
            'video_position' => 0,
            'url'            => null,
        );

        // Get most recent in-progress lesson.
        $progress_records = swiftlms()->progress()->get_course_progress( $user_id, $course_id );

        // Find the most recently updated in-progress lesson.
        $resume_lesson = null;
        $latest_update = null;

        foreach ( $progress_records as $lesson_id => $progress ) {
            if ( $progress['status'] === Progress::STATUS_IN_PROGRESS ) {
                $lesson = get_post( $lesson_id );
                if ( $lesson && $lesson->post_status === 'publish' ) {
                    // Get full progress data to check updated_at.
                    $full_progress = swiftlms()->progress()->get_progress( $user_id, $lesson_id );
                    if ( $full_progress ) {
                        $updated = strtotime( $full_progress['updated_at'] );
                        if ( ! $latest_update || $updated > $latest_update ) {
                            $latest_update = $updated;
                            $resume_lesson = $lesson_id;
                        }
                    }
                }
            }
        }

        if ( $resume_lesson ) {
            $progress = swiftlms()->progress()->get_progress( $user_id, $resume_lesson );

            return array(
                'lesson_id'      => $resume_lesson,
                'video_position' => $progress ? $progress['video_position'] : 0,
                'url'            => get_permalink( $resume_lesson ),
            );
        }

        // No in-progress lesson, find next incomplete lesson.
        $next_lesson = self::get_next_lesson( $user_id, $course_id );

        if ( $next_lesson ) {
            return array(
                'lesson_id'      => $next_lesson->ID,
                'video_position' => 0,
                'url'            => get_permalink( $next_lesson->ID ),
            );
        }

        return $default;
    }

    /**
     * Get gamification data for a user.
     *
     * @param int $user_id The user ID.
     * @return array Gamification data (points, badges, etc.).
     */
    public static function get_gamification_data( int $user_id ): array {
        // Check if gamification module is active.
        if ( ! swiftlms()->has_module( 'gamification' ) ) {
            return array(
                'points'       => 0,
                'badges'       => array(),
                'level'        => 1,
                'achievements' => array(),
            );
        }

        /**
         * Filter to get user gamification data.
         *
         * @param array $data    The gamification data.
         * @param int   $user_id The user ID.
         */
        return apply_filters( 'swiftlms_user_gamification', array(), $user_id );
    }
}
