<?php
/**
 * Progress Tracker interface.
 *
 * @package SwiftLMS\Interfaces
 */

namespace SwiftLMS\Interfaces;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Interface for progress tracking implementations.
 *
 * Defines the contract for any class that tracks user progress
 * through course content.
 */
interface ProgressTrackerInterface {

    /**
     * Get progress for a user and content item.
     *
     * @param int $user_id    The user ID.
     * @param int $content_id The content (lesson/topic) ID.
     * @return array{
     *     status: string,
     *     progress_percent: float,
     *     video_position: int,
     *     started_at: string|null,
     *     completed_at: string|null
     * }|null The progress data or null if not found.
     */
    public function get_progress( int $user_id, int $content_id ): ?array;

    /**
     * Update progress for a user and content item.
     *
     * @param int   $user_id    The user ID.
     * @param int   $content_id The content ID.
     * @param array $data       The progress data to update.
     * @return bool True on success, false on failure.
     */
    public function update_progress( int $user_id, int $content_id, array $data ): bool;

    /**
     * Mark content as started.
     *
     * @param int $user_id    The user ID.
     * @param int $content_id The content ID.
     * @return bool True on success, false on failure.
     */
    public function mark_started( int $user_id, int $content_id ): bool;

    /**
     * Mark content as completed.
     *
     * @param int $user_id    The user ID.
     * @param int $content_id The content ID.
     * @return bool True on success, false on failure.
     */
    public function mark_completed( int $user_id, int $content_id ): bool;

    /**
     * Reset progress for a user and content item.
     *
     * @param int $user_id    The user ID.
     * @param int $content_id The content ID.
     * @return bool True on success, false on failure.
     */
    public function reset_progress( int $user_id, int $content_id ): bool;

    /**
     * Get all progress for a user in a course.
     *
     * @param int $user_id   The user ID.
     * @param int $course_id The course ID.
     * @return array<int, array{
     *     lesson_id: int,
     *     status: string,
     *     progress_percent: float
     * }> Array of progress records indexed by lesson ID.
     */
    public function get_course_progress( int $user_id, int $course_id ): array;

    /**
     * Calculate overall course completion percentage.
     *
     * @param int $user_id   The user ID.
     * @param int $course_id The course ID.
     * @return float The completion percentage (0-100).
     */
    public function calculate_course_completion( int $user_id, int $course_id ): float;
}
