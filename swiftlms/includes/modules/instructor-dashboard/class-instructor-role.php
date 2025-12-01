<?php
/**
 * Instructor Role and Capabilities
 *
 * @package SwiftLMS\Modules\InstructorDashboard
 */

namespace SwiftLMS\Modules\InstructorDashboard;

defined( 'ABSPATH' ) || exit;

/**
 * Instructor_Role class.
 *
 * Handles the custom instructor role and capabilities.
 */
class Instructor_Role {

    /**
     * Role name.
     */
    const ROLE_NAME = 'sfls_instructor';

    /**
     * Role display name.
     */
    const ROLE_DISPLAY = 'Instructor';

    /**
     * Instructor capabilities.
     *
     * @var array
     */
    private static $capabilities = array(
        // Core capabilities
        'read'                           => true,
        'upload_files'                   => true,
        'edit_posts'                     => false,
        'delete_posts'                   => false,

        // Course capabilities
        'sfls_create_courses'            => true,
        'sfls_edit_own_courses'          => true,
        'sfls_delete_own_courses'        => true,
        'sfls_publish_courses'           => false, // Requires approval
        'sfls_view_own_course_reports'   => true,

        // Lesson capabilities
        'sfls_create_lessons'            => true,
        'sfls_edit_own_lessons'          => true,
        'sfls_delete_own_lessons'        => true,

        // Quiz capabilities
        'sfls_create_quizzes'            => true,
        'sfls_edit_own_quizzes'          => true,
        'sfls_delete_own_quizzes'        => true,
        'sfls_view_quiz_attempts'        => true,

        // Assignment capabilities
        'sfls_create_assignments'        => true,
        'sfls_edit_own_assignments'      => true,
        'sfls_grade_assignments'         => true,
        'sfls_view_submissions'          => true,

        // Student capabilities
        'sfls_view_enrolled_students'    => true,
        'sfls_message_students'          => true,
        'sfls_manage_enrollments'        => false,

        // Certificate capabilities
        'sfls_create_certificates'       => true,
        'sfls_edit_own_certificates'     => true,

        // Earnings capabilities
        'sfls_view_own_earnings'         => true,
        'sfls_request_payout'            => true,

        // Dashboard access
        'sfls_access_instructor_dashboard' => true,
    );

    /**
     * Initialize the role.
     */
    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'register_role' ) );
        add_filter( 'map_meta_cap', array( __CLASS__, 'map_meta_capabilities' ), 10, 4 );
        add_action( 'set_user_role', array( __CLASS__, 'on_role_change' ), 10, 3 );
    }

    /**
     * Register the instructor role.
     */
    public static function register_role(): void {
        if ( ! get_role( self::ROLE_NAME ) ) {
            add_role( self::ROLE_NAME, self::ROLE_DISPLAY, self::$capabilities );
        }
    }

    /**
     * Get all instructor capabilities.
     *
     * @return array
     */
    public static function get_capabilities(): array {
        return self::$capabilities;
    }

    /**
     * Add instructor role to existing user.
     *
     * @param int $user_id User ID.
     * @return bool
     */
    public static function make_instructor( int $user_id ): bool {
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return false;
        }

        $user->add_role( self::ROLE_NAME );

        do_action( 'swiftlms_user_became_instructor', $user_id );

        return true;
    }

    /**
     * Remove instructor role from user.
     *
     * @param int $user_id User ID.
     * @return bool
     */
    public static function remove_instructor( int $user_id ): bool {
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return false;
        }

        $user->remove_role( self::ROLE_NAME );

        do_action( 'swiftlms_user_removed_instructor', $user_id );

        return true;
    }

    /**
     * Check if user is an instructor.
     *
     * @param int $user_id User ID (0 for current user).
     * @return bool
     */
    public static function is_instructor( int $user_id = 0 ): bool {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return false;
        }

        return in_array( self::ROLE_NAME, (array) $user->roles, true );
    }

    /**
     * Check if user can manage specific course.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course ID.
     * @return bool
     */
    public static function can_manage_course( int $user_id, int $course_id ): bool {
        // Admins can manage all courses.
        if ( user_can( $user_id, 'manage_options' ) ) {
            return true;
        }

        // Check if user is the course author.
        $course = get_post( $course_id );
        if ( ! $course || 'sfls_course' !== $course->post_type ) {
            return false;
        }

        if ( (int) $course->post_author === $user_id ) {
            return true;
        }

        // Check if user is a co-instructor.
        $co_instructors = get_post_meta( $course_id, '_sfls_co_instructors', true );
        if ( is_array( $co_instructors ) && in_array( $user_id, array_map( 'intval', $co_instructors ), true ) ) {
            return true;
        }

        return false;
    }

    /**
     * Get courses by instructor.
     *
     * @param int   $user_id User ID.
     * @param array $args    Additional query args.
     * @return array
     */
    public static function get_instructor_courses( int $user_id, array $args = array() ): array {
        $default_args = array(
            'post_type'      => 'sfls_course',
            'posts_per_page' => -1,
            'post_status'    => array( 'publish', 'draft', 'pending' ),
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'     => '_sfls_co_instructors',
                    'value'   => sprintf( '"%d"', $user_id ),
                    'compare' => 'LIKE',
                ),
            ),
            'author'         => $user_id,
        );

        // Merge with custom args.
        $query_args = wp_parse_args( $args, $default_args );

        // Handle author + co-instructor query.
        $author_query = new \WP_Query( array_merge( $query_args, array( 'fields' => 'ids' ) ) );

        $co_instructor_query = new \WP_Query( array(
            'post_type'      => 'sfls_course',
            'posts_per_page' => -1,
            'post_status'    => $query_args['post_status'],
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => '_sfls_co_instructors',
                    'value'   => sprintf( '"%d"', $user_id ),
                    'compare' => 'LIKE',
                ),
            ),
        ) );

        $course_ids = array_unique( array_merge( $author_query->posts, $co_instructor_query->posts ) );

        if ( empty( $course_ids ) ) {
            return array();
        }

        return get_posts( array(
            'post_type'      => 'sfls_course',
            'posts_per_page' => -1,
            'post__in'       => $course_ids,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );
    }

    /**
     * Map meta capabilities for instructor-specific checks.
     *
     * @param array  $caps    Required capabilities.
     * @param string $cap     Capability being checked.
     * @param int    $user_id User ID.
     * @param array  $args    Additional arguments.
     * @return array
     */
    public static function map_meta_capabilities( array $caps, string $cap, int $user_id, array $args ): array {
        switch ( $cap ) {
            case 'sfls_edit_course':
            case 'sfls_delete_course':
                if ( isset( $args[0] ) ) {
                    $course = get_post( $args[0] );
                    if ( $course && self::can_manage_course( $user_id, $course->ID ) ) {
                        $caps = array( 'sfls_edit_own_courses' );
                    } else {
                        $caps = array( 'do_not_allow' );
                    }
                }
                break;

            case 'sfls_view_course_submissions':
                if ( isset( $args[0] ) ) {
                    if ( self::can_manage_course( $user_id, $args[0] ) ) {
                        $caps = array( 'sfls_view_submissions' );
                    } else {
                        $caps = array( 'do_not_allow' );
                    }
                }
                break;
        }

        return $caps;
    }

    /**
     * Handle role change event.
     *
     * @param int    $user_id   User ID.
     * @param string $role      New role.
     * @param array  $old_roles Previous roles.
     */
    public static function on_role_change( int $user_id, string $role, array $old_roles ): void {
        if ( self::ROLE_NAME === $role && ! in_array( self::ROLE_NAME, $old_roles, true ) ) {
            // User just became an instructor.
            self::setup_new_instructor( $user_id );
        }
    }

    /**
     * Setup new instructor.
     *
     * @param int $user_id User ID.
     */
    private static function setup_new_instructor( int $user_id ): void {
        // Set default instructor settings.
        update_user_meta( $user_id, '_sfls_instructor_since', current_time( 'mysql' ) );
        update_user_meta( $user_id, '_sfls_instructor_status', 'active' );
        update_user_meta( $user_id, '_sfls_commission_rate', 70 ); // Default 70% commission.
        update_user_meta( $user_id, '_sfls_total_earnings', 0 );
        update_user_meta( $user_id, '_sfls_withdrawn_earnings', 0 );

        do_action( 'swiftlms_instructor_setup_complete', $user_id );
    }

    /**
     * Get all instructors.
     *
     * @param array $args Query arguments.
     * @return array
     */
    public static function get_all_instructors( array $args = array() ): array {
        $default_args = array(
            'role'    => self::ROLE_NAME,
            'orderby' => 'display_name',
            'order'   => 'ASC',
        );

        $query_args = wp_parse_args( $args, $default_args );

        return get_users( $query_args );
    }

    /**
     * Update role capabilities.
     *
     * Used during plugin updates.
     */
    public static function update_capabilities(): void {
        $role = get_role( self::ROLE_NAME );
        if ( ! $role ) {
            self::register_role();
            return;
        }

        foreach ( self::$capabilities as $cap => $grant ) {
            if ( $grant ) {
                $role->add_cap( $cap );
            } else {
                $role->remove_cap( $cap );
            }
        }
    }

    /**
     * Remove role on plugin deactivation.
     */
    public static function remove_role(): void {
        remove_role( self::ROLE_NAME );
    }
}
