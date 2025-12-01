<?php
/**
 * Student Dashboard
 *
 * Handles the frontend student dashboard page.
 *
 * @package SwiftLMS\Core
 * @since 1.0.0
 */

namespace SwiftLMS\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard class.
 */
class Dashboard {

    /**
     * Dashboard page ID.
     *
     * @var int
     */
    private static $page_id = 0;

    /**
     * Initialize dashboard.
     *
     * @return void
     */
    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
        add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ) );
        add_action( 'template_redirect', array( __CLASS__, 'handle_dashboard' ) );
        add_shortcode( 'swiftlms_dashboard', array( __CLASS__, 'dashboard_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );

        // AJAX endpoints.
        add_action( 'wp_ajax_sfls_dashboard_data', array( __CLASS__, 'ajax_get_dashboard_data' ) );
    }

    /**
     * Add rewrite rules.
     *
     * @return void
     */
    public static function add_rewrite_rules(): void {
        add_rewrite_rule( '^dashboard/?$', 'index.php?sfls_dashboard=1', 'top' );
        add_rewrite_rule( '^dashboard/([^/]+)/?$', 'index.php?sfls_dashboard=1&sfls_dashboard_tab=$matches[1]', 'top' );
    }

    /**
     * Add query vars.
     *
     * @param array $vars Query vars.
     * @return array
     */
    public static function add_query_vars( array $vars ): array {
        $vars[] = 'sfls_dashboard';
        $vars[] = 'sfls_dashboard_tab';
        return $vars;
    }

    /**
     * Handle dashboard page.
     *
     * @return void
     */
    public static function handle_dashboard(): void {
        if ( ! get_query_var( 'sfls_dashboard' ) ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url( home_url( '/dashboard/' ) ) );
            exit;
        }

        // Load dashboard template.
        include SWIFTLMS_PLUGIN_DIR . 'templates/dashboard/dashboard.php';
        exit;
    }

    /**
     * Dashboard shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function dashboard_shortcode( array $atts = array() ): string {
        if ( ! is_user_logged_in() ) {
            return '<p>' . esc_html__( 'Please log in to view your dashboard.', 'swiftlms' ) . '</p>';
        }

        ob_start();
        include SWIFTLMS_PLUGIN_DIR . 'templates/dashboard/dashboard-content.php';
        return ob_get_clean();
    }

    /**
     * Enqueue scripts.
     *
     * @return void
     */
    public static function enqueue_scripts(): void {
        if ( ! get_query_var( 'sfls_dashboard' ) && ! has_shortcode( get_post()->post_content ?? '', 'swiftlms_dashboard' ) ) {
            return;
        }

        wp_enqueue_style(
            'swiftlms-dashboard',
            SWIFTLMS_PLUGIN_URL . 'public/assets/css/dashboard.css',
            array(),
            SWIFTLMS_VERSION
        );

        wp_enqueue_script(
            'swiftlms-dashboard',
            SWIFTLMS_PLUGIN_URL . 'public/assets/js/dashboard.js',
            array( 'jquery' ),
            SWIFTLMS_VERSION,
            true
        );

        wp_localize_script(
            'swiftlms-dashboard',
            'swiftlmsDashboard',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'sfls_dashboard' ),
            )
        );
    }

    /**
     * Get dashboard data for user.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public static function get_dashboard_data( int $user_id ): array {
        $enrollments = Enrollment::get_user_enrollments( $user_id );

        $data = array(
            'user'         => self::get_user_data( $user_id ),
            'stats'        => self::get_user_stats( $user_id, $enrollments ),
            'courses'      => self::get_courses_data( $user_id, $enrollments ),
            'certificates' => self::get_certificates_data( $user_id ),
            'activity'     => self::get_recent_activity( $user_id ),
        );

        return apply_filters( 'swiftlms_dashboard_data', $data, $user_id );
    }

    /**
     * Get user data.
     *
     * @param int $user_id User ID.
     * @return array
     */
    private static function get_user_data( int $user_id ): array {
        $user = get_userdata( $user_id );

        return array(
            'id'           => $user_id,
            'name'         => $user->display_name,
            'email'        => $user->user_email,
            'avatar'       => get_avatar_url( $user_id, array( 'size' => 150 ) ),
            'registered'   => $user->user_registered,
            'member_since' => human_time_diff( strtotime( $user->user_registered ) ),
        );
    }

    /**
     * Get user stats.
     *
     * @param int   $user_id     User ID.
     * @param array $enrollments User enrollments.
     * @return array
     */
    private static function get_user_stats( int $user_id, array $enrollments ): array {
        $active_courses    = 0;
        $completed_courses = 0;
        $total_lessons     = 0;
        $completed_lessons = 0;

        foreach ( $enrollments as $enrollment ) {
            if ( 'active' === $enrollment->status ) {
                $active_courses++;
            } elseif ( 'completed' === $enrollment->status ) {
                $completed_courses++;
            }

            $progress           = Progress::get_course_progress( $user_id, $enrollment->course_id );
            $total_lessons     += $progress['total'];
            $completed_lessons += $progress['completed'];
        }

        // Get certificates count.
        $certificates = 0;
        if ( class_exists( '\SwiftLMS\Modules\Certificates\CertificatesTable' ) ) {
            $certs = \SwiftLMS\Modules\Certificates\CertificatesTable::get_user_certificates( $user_id, 'active' );
            $certificates = count( $certs );
        }

        return array(
            'enrolled_courses'  => count( $enrollments ),
            'active_courses'    => $active_courses,
            'completed_courses' => $completed_courses,
            'total_lessons'     => $total_lessons,
            'completed_lessons' => $completed_lessons,
            'certificates'      => $certificates,
            'overall_progress'  => $total_lessons > 0 ? round( ( $completed_lessons / $total_lessons ) * 100 ) : 0,
        );
    }

    /**
     * Get courses data.
     *
     * @param int   $user_id     User ID.
     * @param array $enrollments User enrollments.
     * @return array
     */
    private static function get_courses_data( int $user_id, array $enrollments ): array {
        $courses = array(
            'in_progress' => array(),
            'completed'   => array(),
        );

        foreach ( $enrollments as $enrollment ) {
            $course = get_post( $enrollment->course_id );
            if ( ! $course ) {
                continue;
            }

            $progress    = Progress::get_course_progress( $user_id, $enrollment->course_id );
            $last_lesson = Progress::get_last_accessed_lesson( $user_id, $enrollment->course_id );

            $course_data = array(
                'id'              => $course->ID,
                'title'           => $course->post_title,
                'thumbnail'       => get_the_post_thumbnail_url( $course->ID, 'medium' ) ?: SWIFTLMS_PLUGIN_URL . 'assets/img/course-placeholder.png',
                'url'             => get_permalink( $course->ID ),
                'progress'        => $progress['percentage'],
                'completed'       => $progress['completed'],
                'total'           => $progress['total'],
                'status'          => $enrollment->status,
                'enrolled_at'     => $enrollment->enrolled_at,
                'last_lesson'     => $last_lesson,
                'last_lesson_url' => $last_lesson ? get_permalink( $last_lesson ) : get_permalink( $course->ID ),
            );

            if ( 'completed' === $enrollment->status || 100 === $progress['percentage'] ) {
                $courses['completed'][] = $course_data;
            } else {
                $courses['in_progress'][] = $course_data;
            }
        }

        // Sort by last activity.
        usort( $courses['in_progress'], function( $a, $b ) {
            return strtotime( $b['enrolled_at'] ) - strtotime( $a['enrolled_at'] );
        } );

        return $courses;
    }

    /**
     * Get certificates data.
     *
     * @param int $user_id User ID.
     * @return array
     */
    private static function get_certificates_data( int $user_id ): array {
        if ( ! class_exists( '\SwiftLMS\Modules\Certificates\CertificatesTable' ) ) {
            return array();
        }

        $certs = \SwiftLMS\Modules\Certificates\CertificatesTable::get_user_certificates( $user_id, 'active' );
        $data  = array();

        foreach ( $certs as $cert ) {
            $course = get_post( $cert->course_id );

            $data[] = array(
                'id'           => $cert->id,
                'code'         => $cert->certificate_code,
                'course_title' => $course ? $course->post_title : '',
                'issued_at'    => $cert->issued_at,
                'download_url' => admin_url( "admin-ajax.php?action=sfls_download_certificate&id={$cert->id}" ),
                'verify_url'   => home_url( "?swiftlms-verify={$cert->certificate_code}" ),
            );
        }

        return $data;
    }

    /**
     * Get recent activity.
     *
     * @param int $user_id User ID.
     * @return array
     */
    private static function get_recent_activity( int $user_id ): array {
        global $wpdb;

        $table = $wpdb->prefix . 'swiftlms_progress';

        $activities = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.*, c.post_title as course_title, l.post_title as lesson_title
                FROM {$table} p
                LEFT JOIN {$wpdb->posts} c ON p.course_id = c.ID
                LEFT JOIN {$wpdb->posts} l ON p.lesson_id = l.ID
                WHERE p.user_id = %d
                ORDER BY p.updated_at DESC
                LIMIT 10",
                $user_id
            )
        );

        $data = array();

        foreach ( $activities as $activity ) {
            $action = 'viewed';
            if ( 'completed' === $activity->status ) {
                $action = 'completed';
            } elseif ( 'in_progress' === $activity->status ) {
                $action = 'started';
            }

            $data[] = array(
                'type'         => 'lesson',
                'action'       => $action,
                'course_title' => $activity->course_title,
                'lesson_title' => $activity->lesson_title,
                'lesson_url'   => get_permalink( $activity->lesson_id ),
                'progress'     => $activity->progress_percent,
                'date'         => $activity->updated_at,
                'time_ago'     => human_time_diff( strtotime( $activity->updated_at ) ),
            );
        }

        return $data;
    }

    /**
     * AJAX: Get dashboard data.
     *
     * @return void
     */
    public static function ajax_get_dashboard_data(): void {
        check_ajax_referer( 'sfls_dashboard', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'Not logged in' );
        }

        $data = self::get_dashboard_data( get_current_user_id() );
        wp_send_json_success( $data );
    }

    /**
     * Get dashboard tabs.
     *
     * @return array
     */
    public static function get_tabs(): array {
        $tabs = array(
            'overview'     => array(
                'label' => __( 'Overview', 'swiftlms' ),
                'icon'  => 'dashicons-dashboard',
            ),
            'courses'      => array(
                'label' => __( 'My Courses', 'swiftlms' ),
                'icon'  => 'dashicons-welcome-learn-more',
            ),
            'certificates' => array(
                'label' => __( 'Certificates', 'swiftlms' ),
                'icon'  => 'dashicons-awards',
            ),
            'profile'      => array(
                'label' => __( 'Profile', 'swiftlms' ),
                'icon'  => 'dashicons-admin-users',
            ),
        );

        return apply_filters( 'swiftlms_dashboard_tabs', $tabs );
    }
}
