<?php
/**
 * Progress class.
 *
 * @package SwiftLMS\Core
 */

namespace SwiftLMS\Core;

use SwiftLMS\Database\ProgressTable;
use SwiftLMS\Database\AnalyticsTable;
use SwiftLMS\Interfaces\ProgressTrackerInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles progress tracking for lessons and topics.
 *
 * Implements the secondsWatchedVector pattern for accurate video tracking
 * and prevents completion by scrubbing.
 */
class Progress implements ProgressTrackerInterface {

    /**
     * Progress statuses.
     */
    public const STATUS_NOT_STARTED = 'not_started';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';

    /**
     * Video milestones to track.
     */
    protected const VIDEO_MILESTONES = array( 25, 50, 75, 95 );

    /**
     * Get progress for a user and content item.
     *
     * @param int $user_id    The user ID.
     * @param int $content_id The content (lesson/topic) ID.
     * @return array|null The progress data or null if not found.
     */
    public function get_progress( int $user_id, int $content_id ): ?array {
        $record = ProgressTable::get( $user_id, $content_id );

        if ( ! $record ) {
            return null;
        }

        return array(
            'id'                     => (int) $record->id,
            'status'                 => $record->status,
            'progress_percent'       => (float) $record->progress_percent,
            'video_position'         => (int) $record->video_position,
            'video_duration'         => (int) $record->video_duration,
            'seconds_watched_vector' => $record->seconds_watched_vector ? json_decode( $record->seconds_watched_vector, true ) : array(),
            'started_at'             => $record->started_at,
            'completed_at'           => $record->completed_at,
            'updated_at'             => $record->updated_at,
        );
    }

    /**
     * Update progress for a user and content item.
     *
     * @param int   $user_id    The user ID.
     * @param int   $content_id The content ID.
     * @param array $data       The progress data to update.
     * @return bool True on success, false on failure.
     */
    public function update_progress( int $user_id, int $content_id, array $data ): bool {
        // Get the course ID for this content.
        $course_id = $this->get_course_id_for_content( $content_id );

        if ( ! $course_id ) {
            return false;
        }

        // Prepare data for database.
        $db_data = array(
            'course_id' => $course_id,
        );

        // Map allowed fields.
        $allowed = array(
            'status',
            'progress_percent',
            'video_position',
            'video_duration',
            'started_at',
            'completed_at',
            'topic_id',
        );

        foreach ( $allowed as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $db_data[ $field ] = $data[ $field ];
            }
        }

        // Handle seconds_watched_vector.
        if ( isset( $data['seconds_watched_vector'] ) ) {
            $db_data['seconds_watched_vector'] = is_array( $data['seconds_watched_vector'] )
                ? wp_json_encode( $data['seconds_watched_vector'] )
                : $data['seconds_watched_vector'];
        }

        // Upsert the record.
        $result = ProgressTable::upsert( $user_id, $content_id, $db_data );

        if ( $result ) {
            // Update enrollment progress.
            $this->sync_enrollment_progress( $user_id, $course_id );

            // Update last activity.
            swiftlms()->enrollment()->touch( $user_id, $course_id );
        }

        return $result !== false;
    }

    /**
     * Mark content as started.
     *
     * @param int $user_id    The user ID.
     * @param int $content_id The content ID.
     * @return bool True on success, false on failure.
     */
    public function mark_started( int $user_id, int $content_id ): bool {
        $existing = $this->get_progress( $user_id, $content_id );

        // Don't overwrite if already started or completed.
        if ( $existing && $existing['status'] !== self::STATUS_NOT_STARTED ) {
            return true;
        }

        $result = $this->update_progress(
            $user_id,
            $content_id,
            array(
                'status'     => self::STATUS_IN_PROGRESS,
                'started_at' => current_time( 'mysql' ),
            )
        );

        if ( $result ) {
            $course_id = $this->get_course_id_for_content( $content_id );

            AnalyticsTable::track(
                $user_id,
                AnalyticsTable::EVENT_LESSON_START,
                'lesson',
                $content_id,
                array( 'course_id' => $course_id )
            );

            /**
             * Fires when a user starts a lesson.
             *
             * @param int $user_id    The user ID.
             * @param int $content_id The lesson ID.
             * @param int $course_id  The course ID.
             */
            do_action( 'swiftlms_lesson_started', $user_id, $content_id, $course_id );
        }

        return $result;
    }

    /**
     * Mark content as completed.
     *
     * @param int $user_id    The user ID.
     * @param int $content_id The content ID.
     * @return bool True on success, false on failure.
     */
    public function mark_completed( int $user_id, int $content_id ): bool {
        $result = $this->update_progress(
            $user_id,
            $content_id,
            array(
                'status'           => self::STATUS_COMPLETED,
                'progress_percent' => 100,
                'completed_at'     => current_time( 'mysql' ),
            )
        );

        if ( $result ) {
            $course_id = $this->get_course_id_for_content( $content_id );

            AnalyticsTable::track(
                $user_id,
                AnalyticsTable::EVENT_LESSON_COMPLETE,
                'lesson',
                $content_id,
                array( 'course_id' => $course_id )
            );

            /**
             * Fires when a user completes a lesson.
             *
             * @param int $user_id    The user ID.
             * @param int $content_id The lesson ID.
             * @param int $course_id  The course ID.
             */
            do_action( 'swiftlms_lesson_completed', $user_id, $content_id, $course_id );

            // Check if course is now complete.
            $this->check_course_completion( $user_id, $course_id );
        }

        return $result;
    }

    /**
     * Reset progress for a user and content item.
     *
     * @param int $user_id    The user ID.
     * @param int $content_id The content ID.
     * @return bool True on success, false on failure.
     */
    public function reset_progress( int $user_id, int $content_id ): bool {
        $result = ProgressTable::delete( $user_id, $content_id );

        if ( $result ) {
            $course_id = $this->get_course_id_for_content( $content_id );
            $this->sync_enrollment_progress( $user_id, $course_id );
        }

        return $result;
    }

    /**
     * Get all progress for a user in a course.
     *
     * @param int $user_id   The user ID.
     * @param int $course_id The course ID.
     * @return array Array of progress records indexed by lesson ID.
     */
    public function get_course_progress( int $user_id, int $course_id ): array {
        $records = ProgressTable::get_by_course( $user_id, $course_id );
        $indexed = array();

        foreach ( $records as $record ) {
            $indexed[ $record->lesson_id ] = array(
                'lesson_id'        => (int) $record->lesson_id,
                'status'           => $record->status,
                'progress_percent' => (float) $record->progress_percent,
                'video_position'   => (int) $record->video_position,
                'completed_at'     => $record->completed_at,
            );
        }

        return $indexed;
    }

    /**
     * Calculate overall course completion percentage.
     *
     * @param int $user_id   The user ID.
     * @param int $course_id The course ID.
     * @return float The completion percentage (0-100).
     */
    public function calculate_course_completion( int $user_id, int $course_id ): float {
        // Get total lessons in course.
        $lessons = get_posts(
            array(
                'post_type'      => 'sfls_lesson',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'meta_key'       => '_swiftlms_course_id',
                'meta_value'     => $course_id,
                'fields'         => 'ids',
            )
        );

        if ( empty( $lessons ) ) {
            return 0;
        }

        $total_lessons     = count( $lessons );
        $completed_lessons = ProgressTable::count_completed( $user_id, $course_id );

        return round( ( $completed_lessons / $total_lessons ) * 100, 2 );
    }

    /**
     * Check if a lesson is completed.
     *
     * @param int $user_id   The user ID.
     * @param int $lesson_id The lesson ID.
     * @return bool True if completed.
     */
    public function is_completed( int $user_id, int $lesson_id ): bool {
        $progress = $this->get_progress( $user_id, $lesson_id );
        return $progress && $progress['status'] === self::STATUS_COMPLETED;
    }

    /**
     * Update video progress.
     *
     * Implements the secondsWatchedVector pattern for accurate tracking.
     *
     * @param int   $user_id        The user ID.
     * @param int   $content_id     The content ID.
     * @param int   $position       Current video position in seconds.
     * @param int   $duration       Total video duration in seconds.
     * @param array $watched_vector Array of watched seconds (sparse or full).
     * @return bool True on success.
     */
    public function update_video_progress( int $user_id, int $content_id, int $position, int $duration, array $watched_vector = array() ): bool {
        $existing = $this->get_progress( $user_id, $content_id );

        // Merge watched vectors.
        $current_vector = $existing ? $existing['seconds_watched_vector'] : array();

        if ( ! empty( $watched_vector ) ) {
            $current_vector = $this->merge_watched_vectors( $current_vector, $watched_vector );
        }

        // Calculate unique seconds watched.
        $unique_seconds = $this->count_unique_watched_seconds( $current_vector );

        // Calculate progress percentage based on unique seconds.
        $progress_percent = $duration > 0 ? ( $unique_seconds / $duration ) * 100 : 0;
        $progress_percent = min( 100, round( $progress_percent, 2 ) );

        // Check for milestones.
        $this->check_video_milestones( $user_id, $content_id, $progress_percent, $existing );

        // Get completion threshold.
        $threshold = (int) get_post_meta( $content_id, '_swiftlms_video_completion_percent', true ) ?: 90;

        // Determine status.
        $status = self::STATUS_IN_PROGRESS;
        $completed_at = null;

        if ( $progress_percent >= $threshold ) {
            $status       = self::STATUS_COMPLETED;
            $completed_at = current_time( 'mysql' );

            // Track video completion.
            AnalyticsTable::track(
                $user_id,
                AnalyticsTable::EVENT_VIDEO_COMPLETE,
                'lesson',
                $content_id,
                array(
                    'duration'        => $duration,
                    'unique_seconds'  => $unique_seconds,
                    'final_position'  => $position,
                )
            );
        }

        // Update progress.
        return $this->update_progress(
            $user_id,
            $content_id,
            array(
                'status'                 => $status,
                'progress_percent'       => $progress_percent,
                'video_position'         => $position,
                'video_duration'         => $duration,
                'seconds_watched_vector' => $current_vector,
                'completed_at'           => $completed_at,
            )
        );
    }

    /**
     * Merge two watched vectors.
     *
     * @param array $existing The existing vector.
     * @param array $new      The new vector to merge.
     * @return array The merged vector.
     */
    protected function merge_watched_vectors( array $existing, array $new ): array {
        foreach ( $new as $second => $count ) {
            $second = (int) $second;
            if ( ! isset( $existing[ $second ] ) ) {
                $existing[ $second ] = 0;
            }
            $existing[ $second ] += (int) $count;
        }

        return $existing;
    }

    /**
     * Count unique seconds watched.
     *
     * @param array $vector The watched vector.
     * @return int Number of unique seconds watched at least once.
     */
    protected function count_unique_watched_seconds( array $vector ): int {
        $count = 0;

        foreach ( $vector as $times_watched ) {
            if ( $times_watched > 0 ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Check and track video milestones.
     *
     * @param int        $user_id    The user ID.
     * @param int        $content_id The content ID.
     * @param float      $percent    Current progress percentage.
     * @param array|null $existing   Existing progress data.
     * @return void
     */
    protected function check_video_milestones( int $user_id, int $content_id, float $percent, ?array $existing ): void {
        $previous_percent = $existing ? $existing['progress_percent'] : 0;

        foreach ( self::VIDEO_MILESTONES as $milestone ) {
            if ( $percent >= $milestone && $previous_percent < $milestone ) {
                AnalyticsTable::track(
                    $user_id,
                    AnalyticsTable::EVENT_VIDEO_MILESTONE,
                    'lesson',
                    $content_id,
                    array( 'milestone' => $milestone )
                );

                /**
                 * Fires when a user reaches a video milestone.
                 *
                 * @param int $user_id    The user ID.
                 * @param int $content_id The content ID.
                 * @param int $milestone  The milestone percentage.
                 */
                do_action( 'swiftlms_video_milestone', $user_id, $content_id, $milestone );
            }
        }
    }

    /**
     * Sync enrollment progress with lesson completion.
     *
     * @param int $user_id   The user ID.
     * @param int $course_id The course ID.
     * @return void
     */
    protected function sync_enrollment_progress( int $user_id, int $course_id ): void {
        $completion = $this->calculate_course_completion( $user_id, $course_id );
        swiftlms()->enrollment()->update_progress( $user_id, $course_id, $completion );
    }

    /**
     * Check if course is now complete.
     *
     * @param int $user_id   The user ID.
     * @param int $course_id The course ID.
     * @return bool True if course is complete.
     */
    protected function check_course_completion( int $user_id, int $course_id ): bool {
        $completion = $this->calculate_course_completion( $user_id, $course_id );

        if ( $completion >= 100 ) {
            swiftlms()->enrollment()->mark_completed( $user_id, $course_id );
            return true;
        }

        return false;
    }

    /**
     * Get the course ID for a content item.
     *
     * @param int $content_id The content (lesson/topic) ID.
     * @return int|null The course ID or null.
     */
    protected function get_course_id_for_content( int $content_id ): ?int {
        $post = get_post( $content_id );

        if ( ! $post ) {
            return null;
        }

        // Direct course ID lookup.
        $course_id = get_post_meta( $content_id, '_swiftlms_course_id', true );

        if ( $course_id ) {
            return (int) $course_id;
        }

        // For topics, get via lesson.
        if ( $post->post_type === 'sfls_topic' ) {
            $lesson_id = get_post_meta( $content_id, '_swiftlms_lesson_id', true );
            if ( $lesson_id ) {
                return (int) get_post_meta( $lesson_id, '_swiftlms_course_id', true );
            }
        }

        return null;
    }

    /**
     * Get recent activity for a user.
     *
     * @param int $user_id The user ID.
     * @param int $limit   Number of items to return.
     * @return array Array of recent progress records with post data.
     */
    public function get_recent_activity( int $user_id, int $limit = 10 ): array {
        $records  = ProgressTable::get_recent_activity( $user_id, $limit );
        $activity = array();

        foreach ( $records as $record ) {
            $lesson = get_post( $record->lesson_id );
            $course = get_post( $record->course_id );

            if ( $lesson && $course ) {
                $activity[] = array(
                    'lesson_id'        => (int) $record->lesson_id,
                    'lesson_title'     => $lesson->post_title,
                    'course_id'        => (int) $record->course_id,
                    'course_title'     => $course->post_title,
                    'status'           => $record->status,
                    'progress_percent' => (float) $record->progress_percent,
                    'updated_at'       => $record->updated_at,
                );
            }
        }

        return $activity;
    }

    /**
     * Reset all progress for a user in a course.
     *
     * @param int $user_id   The user ID.
     * @param int $course_id The course ID.
     * @return int Number of records deleted.
     */
    public function reset_course_progress( int $user_id, int $course_id ): int {
        $count = ProgressTable::delete_by_course( $user_id, $course_id );

        if ( $count > 0 ) {
            // Reset enrollment progress too.
            swiftlms()->enrollment()->update_progress( $user_id, $course_id, 0 );

            /**
             * Fires when course progress is reset.
             *
             * @param int $user_id   The user ID.
             * @param int $course_id The course ID.
             * @param int $count     Number of lessons reset.
             */
            do_action( 'swiftlms_course_progress_reset', $user_id, $course_id, $count );
        }

        return $count;
    }
}
