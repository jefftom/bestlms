<?php
/**
 * REST API controller.
 *
 * @package SwiftLMS\Api
 */

namespace SwiftLMS\Api;

use SwiftLMS\Api\Endpoints\CoursesEndpoint;
use SwiftLMS\Api\Endpoints\ProgressEndpoint;
use SwiftLMS\Api\Endpoints\EnrollmentEndpoint;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main REST API controller.
 *
 * Registers and manages all SwiftLMS REST API endpoints.
 */
class RestApi {

    /**
     * API namespace.
     *
     * @var string
     */
    public const NAMESPACE = 'swiftlms/v1';

    /**
     * Registered endpoints.
     *
     * @var array<object>
     */
    protected array $endpoints = array();

    /**
     * Constructor.
     */
    public function __construct() {
        $this->init_endpoints();
    }

    /**
     * Initialize endpoint classes.
     *
     * @return void
     */
    protected function init_endpoints(): void {
        $this->endpoints = array(
            new CoursesEndpoint(),
            new ProgressEndpoint(),
            new EnrollmentEndpoint(),
        );

        /**
         * Filter the registered API endpoints.
         *
         * @param array $endpoints Array of endpoint objects.
         */
        $this->endpoints = apply_filters( 'swiftlms_api_endpoints', $this->endpoints );
    }

    /**
     * Register all routes.
     *
     * @return void
     */
    public function register_routes(): void {
        foreach ( $this->endpoints as $endpoint ) {
            if ( method_exists( $endpoint, 'register_routes' ) ) {
                $endpoint->register_routes();
            }
        }

        /**
         * Fires after SwiftLMS API routes are registered.
         *
         * Use this hook to register custom API routes.
         */
        do_action( 'swiftlms_api_routes_registered' );
    }

    /**
     * Get the API namespace.
     *
     * @return string
     */
    public static function get_namespace(): string {
        return self::NAMESPACE;
    }

    /**
     * Check if the current user is authenticated.
     *
     * @return bool|\WP_Error True if authenticated, WP_Error if not.
     */
    public static function check_authentication() {
        if ( ! is_user_logged_in() ) {
            return new \WP_Error(
                'rest_not_logged_in',
                __( 'You must be logged in to access this endpoint.', 'swiftlms' ),
                array( 'status' => 401 )
            );
        }

        return true;
    }

    /**
     * Check if the current user can manage courses.
     *
     * @return bool|\WP_Error True if allowed, WP_Error if not.
     */
    public static function check_admin_permission() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to perform this action.', 'swiftlms' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Sanitize and validate a user ID parameter.
     *
     * @param mixed            $param   The parameter value.
     * @param \WP_REST_Request $request The request object.
     * @param string           $key     The parameter key.
     * @return int|false The sanitized user ID or false.
     */
    public static function sanitize_user_id( $param, \WP_REST_Request $request, string $key ) {
        $user_id = absint( $param );

        if ( $user_id === 0 ) {
            return false;
        }

        return $user_id;
    }

    /**
     * Validate a user ID exists.
     *
     * @param mixed            $param   The parameter value.
     * @param \WP_REST_Request $request The request object.
     * @param string           $key     The parameter key.
     * @return bool True if valid.
     */
    public static function validate_user_id( $param, \WP_REST_Request $request, string $key ): bool {
        $user_id = absint( $param );
        return $user_id > 0 && get_userdata( $user_id ) !== false;
    }

    /**
     * Validate a course ID exists.
     *
     * @param mixed            $param   The parameter value.
     * @param \WP_REST_Request $request The request object.
     * @param string           $key     The parameter key.
     * @return bool True if valid.
     */
    public static function validate_course_id( $param, \WP_REST_Request $request, string $key ): bool {
        $course_id = absint( $param );
        $course    = get_post( $course_id );

        return $course && $course->post_type === 'sfls_course';
    }

    /**
     * Validate a lesson ID exists.
     *
     * @param mixed            $param   The parameter value.
     * @param \WP_REST_Request $request The request object.
     * @param string           $key     The parameter key.
     * @return bool True if valid.
     */
    public static function validate_lesson_id( $param, \WP_REST_Request $request, string $key ): bool {
        $lesson_id = absint( $param );
        $lesson    = get_post( $lesson_id );

        return $lesson && $lesson->post_type === 'sfls_lesson';
    }

    /**
     * Format a course for API response.
     *
     * @param \WP_Post $course The course post object.
     * @return array Formatted course data.
     */
    public static function format_course( \WP_Post $course ): array {
        $course_component = swiftlms()->get_component( 'course' );
        $meta             = $course_component->get_meta( $course->ID );

        return array(
            'id'           => $course->ID,
            'title'        => $course->post_title,
            'slug'         => $course->post_name,
            'excerpt'      => $course->post_excerpt,
            'content'      => apply_filters( 'the_content', $course->post_content ),
            'status'       => $course->post_status,
            'url'          => get_permalink( $course->ID ),
            'thumbnail'    => get_the_post_thumbnail_url( $course->ID, 'large' ),
            'author'       => array(
                'id'   => (int) $course->post_author,
                'name' => get_the_author_meta( 'display_name', $course->post_author ),
            ),
            'duration'     => $meta['duration'],
            'price'        => $meta['price'],
            'access_type'  => $meta['access_type'],
            'lesson_count' => $meta['lesson_count'],
            'video_url'    => $meta['video_url'],
            'categories'   => self::get_terms( $course->ID, 'sfls_course_category' ),
            'tags'         => self::get_terms( $course->ID, 'sfls_course_tag' ),
            'difficulty'   => self::get_terms( $course->ID, 'sfls_difficulty' ),
            'created_at'   => $course->post_date,
            'updated_at'   => $course->post_modified,
        );
    }

    /**
     * Format a lesson for API response.
     *
     * @param \WP_Post $lesson The lesson post object.
     * @return array Formatted lesson data.
     */
    public static function format_lesson( \WP_Post $lesson ): array {
        $lesson_component = swiftlms()->get_component( 'lesson' );
        $meta             = $lesson_component->get_meta( $lesson->ID );

        return array(
            'id'                 => $lesson->ID,
            'title'              => $lesson->post_title,
            'slug'               => $lesson->post_name,
            'excerpt'            => $lesson->post_excerpt,
            'content'            => apply_filters( 'the_content', $lesson->post_content ),
            'status'             => $lesson->post_status,
            'url'                => get_permalink( $lesson->ID ),
            'thumbnail'          => get_the_post_thumbnail_url( $lesson->ID, 'large' ),
            'course_id'          => $meta['course_id'],
            'order'              => $meta['order'],
            'duration'           => $meta['duration'],
            'completion_type'    => $meta['completion_type'],
            'is_preview'         => $meta['is_preview'],
            'force_sequential'   => $meta['force_sequential'],
            'video'              => array(
                'url'                => $meta['video_url'],
                'type'               => $meta['video_type'],
                'duration'           => $meta['video_duration'],
                'completion_percent' => $meta['completion_percent'],
            ),
            'created_at'         => $lesson->post_date,
            'updated_at'         => $lesson->post_modified,
        );
    }

    /**
     * Get taxonomy terms for a post.
     *
     * @param int    $post_id  The post ID.
     * @param string $taxonomy The taxonomy name.
     * @return array Array of term data.
     */
    protected static function get_terms( int $post_id, string $taxonomy ): array {
        $terms = get_the_terms( $post_id, $taxonomy );

        if ( ! $terms || is_wp_error( $terms ) ) {
            return array();
        }

        return array_map(
            function ( $term ) {
                return array(
                    'id'   => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                );
            },
            $terms
        );
    }
}
