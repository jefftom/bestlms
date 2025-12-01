<?php
/**
 * Courses REST API endpoint.
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
 * Handles courses API endpoints.
 */
class CoursesEndpoint {

    /**
     * Register routes.
     *
     * @return void
     */
    public function register_routes(): void {
        // Get all courses.
        register_rest_route(
            RestApi::NAMESPACE,
            '/courses',
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_courses' ),
                    'permission_callback' => '__return_true',
                    'args'                => $this->get_collection_params(),
                ),
            )
        );

        // Get single course.
        register_rest_route(
            RestApi::NAMESPACE,
            '/courses/(?P<id>\d+)',
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_course' ),
                    'permission_callback' => '__return_true',
                    'args'                => array(
                        'id' => array(
                            'description' => __( 'Course ID.', 'swiftlms' ),
                            'type'        => 'integer',
                            'required'    => true,
                        ),
                    ),
                ),
            )
        );

        // Get course lessons.
        register_rest_route(
            RestApi::NAMESPACE,
            '/courses/(?P<id>\d+)/lessons',
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_course_lessons' ),
                    'permission_callback' => '__return_true',
                    'args'                => array(
                        'id' => array(
                            'description'       => __( 'Course ID.', 'swiftlms' ),
                            'type'              => 'integer',
                            'required'          => true,
                            'validate_callback' => array( RestApi::class, 'validate_course_id' ),
                        ),
                    ),
                ),
            )
        );

        // Get single lesson.
        register_rest_route(
            RestApi::NAMESPACE,
            '/lessons/(?P<id>\d+)',
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_lesson' ),
                    'permission_callback' => array( $this, 'check_lesson_access' ),
                    'args'                => array(
                        'id' => array(
                            'description'       => __( 'Lesson ID.', 'swiftlms' ),
                            'type'              => 'integer',
                            'required'          => true,
                            'validate_callback' => array( RestApi::class, 'validate_lesson_id' ),
                        ),
                    ),
                ),
            )
        );

        // Get user's courses.
        register_rest_route(
            RestApi::NAMESPACE,
            '/users/(?P<user_id>\d+)/courses',
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_user_courses' ),
                    'permission_callback' => array( $this, 'check_user_permission' ),
                    'args'                => array(
                        'user_id' => array(
                            'description'       => __( 'User ID.', 'swiftlms' ),
                            'type'              => 'integer',
                            'required'          => true,
                            'validate_callback' => array( RestApi::class, 'validate_user_id' ),
                        ),
                        'status'  => array(
                            'description' => __( 'Enrollment status filter.', 'swiftlms' ),
                            'type'        => 'string',
                            'enum'        => array( 'active', 'completed', 'expired', 'cancelled' ),
                            'default'     => 'active',
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Get collection parameters.
     *
     * @return array
     */
    protected function get_collection_params(): array {
        return array(
            'page'     => array(
                'description'       => __( 'Current page of the collection.', 'swiftlms' ),
                'type'              => 'integer',
                'default'           => 1,
                'sanitize_callback' => 'absint',
            ),
            'per_page' => array(
                'description'       => __( 'Maximum number of items to return.', 'swiftlms' ),
                'type'              => 'integer',
                'default'           => 10,
                'sanitize_callback' => 'absint',
            ),
            'search'   => array(
                'description'       => __( 'Search term.', 'swiftlms' ),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'category' => array(
                'description'       => __( 'Category slug or ID.', 'swiftlms' ),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'orderby'  => array(
                'description' => __( 'Order by field.', 'swiftlms' ),
                'type'        => 'string',
                'enum'        => array( 'date', 'title', 'popularity', 'menu_order' ),
                'default'     => 'date',
            ),
            'order'    => array(
                'description' => __( 'Order direction.', 'swiftlms' ),
                'type'        => 'string',
                'enum'        => array( 'asc', 'desc' ),
                'default'     => 'desc',
            ),
        );
    }

    /**
     * Get courses.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response
     */
    public function get_courses( \WP_REST_Request $request ): \WP_REST_Response {
        $args = array(
            'post_type'      => 'sfls_course',
            'post_status'    => 'publish',
            'posts_per_page' => $request->get_param( 'per_page' ),
            'paged'          => $request->get_param( 'page' ),
            'orderby'        => $request->get_param( 'orderby' ),
            'order'          => strtoupper( $request->get_param( 'order' ) ),
        );

        // Handle search.
        $search = $request->get_param( 'search' );
        if ( $search ) {
            $args['s'] = $search;
        }

        // Handle category filter.
        $category = $request->get_param( 'category' );
        if ( $category ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'sfls_course_category',
                    'field'    => is_numeric( $category ) ? 'term_id' : 'slug',
                    'terms'    => $category,
                ),
            );
        }

        $query   = new \WP_Query( $args );
        $courses = array();

        foreach ( $query->posts as $course ) {
            $courses[] = RestApi::format_course( $course );
        }

        $response = new \WP_REST_Response( $courses );

        // Add pagination headers.
        $response->header( 'X-WP-Total', $query->found_posts );
        $response->header( 'X-WP-TotalPages', $query->max_num_pages );

        return $response;
    }

    /**
     * Get a single course.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_course( \WP_REST_Request $request ) {
        $course_id = $request->get_param( 'id' );
        $course    = get_post( $course_id );

        if ( ! $course || $course->post_type !== 'sfls_course' ) {
            return new \WP_Error(
                'course_not_found',
                __( 'Course not found.', 'swiftlms' ),
                array( 'status' => 404 )
            );
        }

        if ( $course->post_status !== 'publish' && ! current_user_can( 'edit_post', $course_id ) ) {
            return new \WP_Error(
                'course_not_found',
                __( 'Course not found.', 'swiftlms' ),
                array( 'status' => 404 )
            );
        }

        $data = RestApi::format_course( $course );

        // Add user-specific data if logged in.
        if ( is_user_logged_in() ) {
            $user_id    = get_current_user_id();
            $enrollment = swiftlms()->enrollment()->get_enrollment( $user_id, $course_id );

            $data['user_data'] = array(
                'is_enrolled'      => swiftlms()->enrollment()->is_enrolled( $user_id, $course_id ),
                'enrollment'       => $enrollment ? array(
                    'status'           => $enrollment->status,
                    'progress_percent' => (float) $enrollment->progress_percent,
                    'enrolled_at'      => $enrollment->enrolled_at,
                    'expires_at'       => $enrollment->expires_at,
                ) : null,
                'resume'           => \SwiftLMS\Core\User::get_resume_data( $user_id, $course_id ),
            );
        }

        return new \WP_REST_Response( $data );
    }

    /**
     * Get course lessons.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response
     */
    public function get_course_lessons( \WP_REST_Request $request ): \WP_REST_Response {
        $course_id = $request->get_param( 'id' );
        $user_id   = get_current_user_id();

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

        $data = array();

        foreach ( $lessons as $lesson ) {
            $lesson_data = RestApi::format_lesson( $lesson );

            // Add user progress if logged in.
            if ( $user_id ) {
                $progress = swiftlms()->progress()->get_progress( $user_id, $lesson->ID );
                $lesson_component = swiftlms()->get_component( 'lesson' );

                $lesson_data['user_progress'] = array(
                    'status'           => $progress ? $progress['status'] : 'not_started',
                    'progress_percent' => $progress ? $progress['progress_percent'] : 0,
                    'video_position'   => $progress ? $progress['video_position'] : 0,
                    'is_available'     => $lesson_component->is_available( $lesson->ID, $user_id ),
                );
            }

            $data[] = $lesson_data;
        }

        return new \WP_REST_Response( $data );
    }

    /**
     * Get a single lesson.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_lesson( \WP_REST_Request $request ) {
        $lesson_id = $request->get_param( 'id' );
        $lesson    = get_post( $lesson_id );

        if ( ! $lesson || $lesson->post_type !== 'sfls_lesson' ) {
            return new \WP_Error(
                'lesson_not_found',
                __( 'Lesson not found.', 'swiftlms' ),
                array( 'status' => 404 )
            );
        }

        $data    = RestApi::format_lesson( $lesson );
        $user_id = get_current_user_id();

        if ( $user_id ) {
            $progress = swiftlms()->progress()->get_progress( $user_id, $lesson_id );

            $data['user_progress'] = array(
                'status'                 => $progress ? $progress['status'] : 'not_started',
                'progress_percent'       => $progress ? $progress['progress_percent'] : 0,
                'video_position'         => $progress ? $progress['video_position'] : 0,
                'seconds_watched_vector' => $progress ? $progress['seconds_watched_vector'] : array(),
            );
        }

        // Get topics.
        $topics = get_posts(
            array(
                'post_type'      => 'sfls_topic',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'meta_key'       => '_swiftlms_lesson_id',
                'meta_value'     => $lesson_id,
                'orderby'        => 'meta_value_num',
                'meta_key'       => '_swiftlms_order',
                'order'          => 'ASC',
            )
        );

        $data['topics'] = array_map(
            function ( $topic ) {
                return array(
                    'id'       => $topic->ID,
                    'title'    => $topic->post_title,
                    'excerpt'  => $topic->post_excerpt,
                    'order'    => get_post_meta( $topic->ID, '_swiftlms_order', true ),
                    'duration' => get_post_meta( $topic->ID, '_swiftlms_duration', true ),
                );
            },
            $topics
        );

        return new \WP_REST_Response( $data );
    }

    /**
     * Get user's enrolled courses.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response
     */
    public function get_user_courses( \WP_REST_Request $request ): \WP_REST_Response {
        $user_id = $request->get_param( 'user_id' );
        $status  = $request->get_param( 'status' );

        $courses = \SwiftLMS\Core\User::get_courses( $user_id, $status );

        return new \WP_REST_Response( $courses );
    }

    /**
     * Check if user can access a lesson.
     *
     * @param \WP_REST_Request $request The request object.
     * @return bool|\WP_Error
     */
    public function check_lesson_access( \WP_REST_Request $request ) {
        $lesson_id = $request->get_param( 'id' );
        $lesson    = get_post( $lesson_id );

        if ( ! $lesson ) {
            return true; // Will be handled in callback.
        }

        // Preview lessons are accessible.
        if ( get_post_meta( $lesson_id, '_swiftlms_is_preview', true ) === '1' ) {
            return true;
        }

        // Check enrollment.
        $course_id = get_post_meta( $lesson_id, '_swiftlms_course_id', true );
        $access_type = get_post_meta( $course_id, '_swiftlms_access_type', true );

        // Open courses are accessible.
        if ( $access_type === 'open' ) {
            return true;
        }

        // For other types, user must be logged in and enrolled.
        if ( ! is_user_logged_in() ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'You must be enrolled in this course to access this lesson.', 'swiftlms' ),
                array( 'status' => 403 )
            );
        }

        $user_id = get_current_user_id();

        if ( ! swiftlms()->enrollment()->is_enrolled( $user_id, $course_id ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'You must be enrolled in this course to access this lesson.', 'swiftlms' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Check if user can access their own data.
     *
     * @param \WP_REST_Request $request The request object.
     * @return bool|\WP_Error
     */
    public function check_user_permission( \WP_REST_Request $request ) {
        if ( ! is_user_logged_in() ) {
            return new \WP_Error(
                'rest_not_logged_in',
                __( 'You must be logged in.', 'swiftlms' ),
                array( 'status' => 401 )
            );
        }

        $user_id = $request->get_param( 'user_id' );

        // Users can only access their own data unless admin.
        if ( get_current_user_id() !== $user_id && ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'You cannot access another user\'s data.', 'swiftlms' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }
}
