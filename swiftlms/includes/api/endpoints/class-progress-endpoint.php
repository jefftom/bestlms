<?php
/**
 * Progress REST API endpoint.
 *
 * @package SwiftLMS\Api\Endpoints
 */

namespace SwiftLMS\Api\Endpoints;

use SwiftLMS\Api\RestApi;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles progress tracking API endpoints.
 */
class ProgressEndpoint {

    /**
     * Register routes.
     *
     * @return void
     */
    public function register_routes(): void {
        // Get progress for a lesson.
        register_rest_route(
            RestApi::NAMESPACE,
            '/progress/(?P<user_id>\d+)/(?P<lesson_id>\d+)',
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_progress' ),
                    'permission_callback' => array( $this, 'check_permission' ),
                    'args'                => array(
                        'user_id'   => array(
                            'description'       => __( 'User ID.', 'swiftlms' ),
                            'type'              => 'integer',
                            'required'          => true,
                            'validate_callback' => array( RestApi::class, 'validate_user_id' ),
                        ),
                        'lesson_id' => array(
                            'description'       => __( 'Lesson ID.', 'swiftlms' ),
                            'type'              => 'integer',
                            'required'          => true,
                            'validate_callback' => array( RestApi::class, 'validate_lesson_id' ),
                        ),
                    ),
                ),
                array(
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'update_progress' ),
                    'permission_callback' => array( $this, 'check_permission' ),
                    'args'                => array(
                        'user_id'          => array(
                            'description'       => __( 'User ID.', 'swiftlms' ),
                            'type'              => 'integer',
                            'required'          => true,
                            'validate_callback' => array( RestApi::class, 'validate_user_id' ),
                        ),
                        'lesson_id'        => array(
                            'description'       => __( 'Lesson ID.', 'swiftlms' ),
                            'type'              => 'integer',
                            'required'          => true,
                            'validate_callback' => array( RestApi::class, 'validate_lesson_id' ),
                        ),
                        'status'           => array(
                            'description' => __( 'Progress status.', 'swiftlms' ),
                            'type'        => 'string',
                            'enum'        => array( 'not_started', 'in_progress', 'completed' ),
                        ),
                        'progress_percent' => array(
                            'description'       => __( 'Progress percentage.', 'swiftlms' ),
                            'type'              => 'number',
                            'minimum'           => 0,
                            'maximum'           => 100,
                            'sanitize_callback' => function ( $value ) {
                                return max( 0, min( 100, floatval( $value ) ) );
                            },
                        ),
                    ),
                ),
            )
        );

        // Video progress update (throttled endpoint).
        register_rest_route(
            RestApi::NAMESPACE,
            '/progress/video-update',
            array(
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'update_video_progress' ),
                    'permission_callback' => array( RestApi::class, 'check_authentication' ),
                    'args'                => array(
                        'lesson_id'              => array(
                            'description'       => __( 'Lesson ID.', 'swiftlms' ),
                            'type'              => 'integer',
                            'required'          => true,
                            'validate_callback' => array( RestApi::class, 'validate_lesson_id' ),
                        ),
                        'position'               => array(
                            'description'       => __( 'Current video position in seconds.', 'swiftlms' ),
                            'type'              => 'integer',
                            'required'          => true,
                            'minimum'           => 0,
                            'sanitize_callback' => 'absint',
                        ),
                        'duration'               => array(
                            'description'       => __( 'Video duration in seconds.', 'swiftlms' ),
                            'type'              => 'integer',
                            'required'          => true,
                            'minimum'           => 1,
                            'sanitize_callback' => 'absint',
                        ),
                        'seconds_watched_vector' => array(
                            'description' => __( 'Array of watched seconds.', 'swiftlms' ),
                            'type'        => 'object',
                            'default'     => array(),
                        ),
                    ),
                ),
            )
        );

        // Mark lesson complete.
        register_rest_route(
            RestApi::NAMESPACE,
            '/progress/(?P<lesson_id>\d+)/complete',
            array(
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'mark_complete' ),
                    'permission_callback' => array( RestApi::class, 'check_authentication' ),
                    'args'                => array(
                        'lesson_id' => array(
                            'description'       => __( 'Lesson ID.', 'swiftlms' ),
                            'type'              => 'integer',
                            'required'          => true,
                            'validate_callback' => array( RestApi::class, 'validate_lesson_id' ),
                        ),
                    ),
                ),
            )
        );

        // Get course progress summary.
        register_rest_route(
            RestApi::NAMESPACE,
            '/progress/course/(?P<course_id>\d+)',
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_course_progress' ),
                    'permission_callback' => array( RestApi::class, 'check_authentication' ),
                    'args'                => array(
                        'course_id' => array(
                            'description'       => __( 'Course ID.', 'swiftlms' ),
                            'type'              => 'integer',
                            'required'          => true,
                            'validate_callback' => array( RestApi::class, 'validate_course_id' ),
                        ),
                    ),
                ),
            )
        );

        // Reset progress.
        register_rest_route(
            RestApi::NAMESPACE,
            '/progress/(?P<lesson_id>\d+)/reset',
            array(
                array(
                    'methods'             => \WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'reset_progress' ),
                    'permission_callback' => array( RestApi::class, 'check_authentication' ),
                    'args'                => array(
                        'lesson_id' => array(
                            'description'       => __( 'Lesson ID.', 'swiftlms' ),
                            'type'              => 'integer',
                            'required'          => true,
                            'validate_callback' => array( RestApi::class, 'validate_lesson_id' ),
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Get progress for a lesson.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response
     */
    public function get_progress( \WP_REST_Request $request ): \WP_REST_Response {
        $user_id   = $request->get_param( 'user_id' );
        $lesson_id = $request->get_param( 'lesson_id' );

        $progress = swiftlms()->progress()->get_progress( $user_id, $lesson_id );

        if ( ! $progress ) {
            $progress = array(
                'status'                 => 'not_started',
                'progress_percent'       => 0,
                'video_position'         => 0,
                'video_duration'         => 0,
                'seconds_watched_vector' => array(),
                'started_at'             => null,
                'completed_at'           => null,
            );
        }

        return new \WP_REST_Response( $progress );
    }

    /**
     * Update progress for a lesson.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function update_progress( \WP_REST_Request $request ) {
        $user_id   = $request->get_param( 'user_id' );
        $lesson_id = $request->get_param( 'lesson_id' );

        $data = array();

        if ( $request->has_param( 'status' ) ) {
            $data['status'] = $request->get_param( 'status' );
        }

        if ( $request->has_param( 'progress_percent' ) ) {
            $data['progress_percent'] = $request->get_param( 'progress_percent' );
        }

        if ( empty( $data ) ) {
            return new \WP_Error(
                'no_data',
                __( 'No data provided to update.', 'swiftlms' ),
                array( 'status' => 400 )
            );
        }

        // Mark as started if first update.
        $existing = swiftlms()->progress()->get_progress( $user_id, $lesson_id );
        if ( ! $existing ) {
            swiftlms()->progress()->mark_started( $user_id, $lesson_id );
        }

        $result = swiftlms()->progress()->update_progress( $user_id, $lesson_id, $data );

        if ( ! $result ) {
            return new \WP_Error(
                'update_failed',
                __( 'Failed to update progress.', 'swiftlms' ),
                array( 'status' => 500 )
            );
        }

        $progress = swiftlms()->progress()->get_progress( $user_id, $lesson_id );

        return new \WP_REST_Response( $progress );
    }

    /**
     * Update video progress.
     *
     * Handles frequent updates from video player with throttling.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function update_video_progress( \WP_REST_Request $request ) {
        $user_id   = get_current_user_id();
        $lesson_id = $request->get_param( 'lesson_id' );
        $position  = $request->get_param( 'position' );
        $duration  = $request->get_param( 'duration' );
        $vector    = $request->get_param( 'seconds_watched_vector' );

        // Verify user has access to this lesson.
        $lesson_component = swiftlms()->get_component( 'lesson' );
        if ( ! $lesson_component->is_available( $lesson_id, $user_id ) ) {
            return new \WP_Error(
                'no_access',
                __( 'You do not have access to this lesson.', 'swiftlms' ),
                array( 'status' => 403 )
            );
        }

        // Mark as started if needed.
        $existing = swiftlms()->progress()->get_progress( $user_id, $lesson_id );
        if ( ! $existing ) {
            swiftlms()->progress()->mark_started( $user_id, $lesson_id );
        }

        // Update video progress.
        $result = swiftlms()->progress()->update_video_progress(
            $user_id,
            $lesson_id,
            $position,
            $duration,
            is_array( $vector ) ? $vector : array()
        );

        if ( ! $result ) {
            return new \WP_Error(
                'update_failed',
                __( 'Failed to update video progress.', 'swiftlms' ),
                array( 'status' => 500 )
            );
        }

        $progress = swiftlms()->progress()->get_progress( $user_id, $lesson_id );

        return new \WP_REST_Response(
            array(
                'success'  => true,
                'progress' => $progress,
            )
        );
    }

    /**
     * Mark a lesson as complete.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function mark_complete( \WP_REST_Request $request ) {
        $user_id   = get_current_user_id();
        $lesson_id = $request->get_param( 'lesson_id' );

        // Verify access.
        $lesson_component = swiftlms()->get_component( 'lesson' );
        if ( ! $lesson_component->is_available( $lesson_id, $user_id ) ) {
            return new \WP_Error(
                'no_access',
                __( 'You do not have access to this lesson.', 'swiftlms' ),
                array( 'status' => 403 )
            );
        }

        // Check completion type.
        $completion_type = get_post_meta( $lesson_id, '_swiftlms_completion_type', true ) ?: 'manual';

        if ( $completion_type === 'video' ) {
            // Check if video progress meets threshold.
            $progress  = swiftlms()->progress()->get_progress( $user_id, $lesson_id );
            $threshold = (int) get_post_meta( $lesson_id, '_swiftlms_video_completion_percent', true ) ?: 90;

            if ( ! $progress || $progress['progress_percent'] < $threshold ) {
                return new \WP_Error(
                    'video_incomplete',
                    sprintf(
                        /* translators: %d: required percentage */
                        __( 'You must watch at least %d%% of the video to complete this lesson.', 'swiftlms' ),
                        $threshold
                    ),
                    array( 'status' => 400 )
                );
            }
        }

        $result = swiftlms()->progress()->mark_completed( $user_id, $lesson_id );

        if ( ! $result ) {
            return new \WP_Error(
                'completion_failed',
                __( 'Failed to mark lesson as complete.', 'swiftlms' ),
                array( 'status' => 500 )
            );
        }

        // Get updated progress and course completion.
        $course_id  = get_post_meta( $lesson_id, '_swiftlms_course_id', true );
        $enrollment = swiftlms()->enrollment()->get_enrollment( $user_id, $course_id );

        return new \WP_REST_Response(
            array(
                'success'            => true,
                'lesson_completed'   => true,
                'course_progress'    => $enrollment ? (float) $enrollment->progress_percent : 0,
                'course_completed'   => $enrollment && $enrollment->status === 'completed',
            )
        );
    }

    /**
     * Get course progress summary.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response
     */
    public function get_course_progress( \WP_REST_Request $request ): \WP_REST_Response {
        $user_id   = get_current_user_id();
        $course_id = $request->get_param( 'course_id' );

        $progress   = swiftlms()->progress()->get_course_progress( $user_id, $course_id );
        $completion = swiftlms()->progress()->calculate_course_completion( $user_id, $course_id );
        $enrollment = swiftlms()->enrollment()->get_enrollment( $user_id, $course_id );

        // Get lesson list with status.
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
                'fields'         => 'ids',
            )
        );

        $lesson_progress = array();
        foreach ( $lessons as $lesson_id ) {
            $lesson_progress[ $lesson_id ] = isset( $progress[ $lesson_id ] )
                ? $progress[ $lesson_id ]
                : array(
                    'status'           => 'not_started',
                    'progress_percent' => 0,
                );
        }

        return new \WP_REST_Response(
            array(
                'completion_percent' => $completion,
                'status'             => $enrollment ? $enrollment->status : null,
                'enrolled_at'        => $enrollment ? $enrollment->enrolled_at : null,
                'lessons'            => $lesson_progress,
            )
        );
    }

    /**
     * Reset lesson progress.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function reset_progress( \WP_REST_Request $request ) {
        $user_id   = get_current_user_id();
        $lesson_id = $request->get_param( 'lesson_id' );

        $result = swiftlms()->progress()->reset_progress( $user_id, $lesson_id );

        if ( ! $result ) {
            return new \WP_Error(
                'reset_failed',
                __( 'Failed to reset progress.', 'swiftlms' ),
                array( 'status' => 500 )
            );
        }

        return new \WP_REST_Response(
            array(
                'success' => true,
                'message' => __( 'Progress has been reset.', 'swiftlms' ),
            )
        );
    }

    /**
     * Check permission for progress access.
     *
     * @param \WP_REST_Request $request The request object.
     * @return bool|\WP_Error
     */
    public function check_permission( \WP_REST_Request $request ) {
        if ( ! is_user_logged_in() ) {
            return new \WP_Error(
                'rest_not_logged_in',
                __( 'You must be logged in.', 'swiftlms' ),
                array( 'status' => 401 )
            );
        }

        $user_id = $request->get_param( 'user_id' );

        // Users can only access their own progress unless admin.
        if ( get_current_user_id() !== $user_id && ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'You cannot access another user\'s progress.', 'swiftlms' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }
}
