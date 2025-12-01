<?php
/**
 * Admin class.
 *
 * @package SwiftLMS\Admin
 */

namespace SwiftLMS\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles admin functionality.
 */
class Admin {

    /**
     * Admin menu slug.
     *
     * @var string
     */
    public const MENU_SLUG = 'swiftlms';

    /**
     * Add menu pages.
     *
     * @return void
     */
    public function add_menu_pages(): void {
        // Main menu.
        add_menu_page(
            __( 'SwiftLMS', 'swiftlms' ),
            __( 'SwiftLMS', 'swiftlms' ),
            'edit_posts',
            self::MENU_SLUG,
            array( $this, 'render_dashboard' ),
            'dashicons-welcome-learn-more',
            25
        );

        // Dashboard submenu.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Dashboard', 'swiftlms' ),
            __( 'Dashboard', 'swiftlms' ),
            'edit_posts',
            self::MENU_SLUG,
            array( $this, 'render_dashboard' )
        );

        // Courses submenu (already registered as CPT).
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Courses', 'swiftlms' ),
            __( 'Courses', 'swiftlms' ),
            'edit_posts',
            'edit.php?post_type=sfls_course'
        );

        // Lessons submenu.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Lessons', 'swiftlms' ),
            __( 'Lessons', 'swiftlms' ),
            'edit_posts',
            'edit.php?post_type=sfls_lesson'
        );

        // Topics submenu.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Topics', 'swiftlms' ),
            __( 'Topics', 'swiftlms' ),
            'edit_posts',
            'edit.php?post_type=sfls_topic'
        );

        // Enrollments submenu.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Enrollments', 'swiftlms' ),
            __( 'Enrollments', 'swiftlms' ),
            'manage_options',
            self::MENU_SLUG . '-enrollments',
            array( $this, 'render_enrollments' )
        );

        // Reports submenu.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Reports', 'swiftlms' ),
            __( 'Reports', 'swiftlms' ),
            'manage_options',
            self::MENU_SLUG . '-reports',
            array( $this, 'render_reports' )
        );

        // Settings submenu.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Settings', 'swiftlms' ),
            __( 'Settings', 'swiftlms' ),
            'manage_options',
            self::MENU_SLUG . '-settings',
            array( $this, 'render_settings' )
        );

        // Modules submenu.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Modules', 'swiftlms' ),
            __( 'Modules', 'swiftlms' ),
            'manage_options',
            self::MENU_SLUG . '-modules',
            array( $this, 'render_modules' )
        );
    }

    /**
     * Enqueue admin styles.
     *
     * @param string $hook_suffix The current admin page.
     * @return void
     */
    public function enqueue_styles( string $hook_suffix ): void {
        // Global admin styles.
        wp_enqueue_style(
            'swiftlms-admin',
            SWIFTLMS_PLUGIN_URL . 'admin/assets/css/admin.css',
            array(),
            SWIFTLMS_VERSION
        );

        // Page-specific styles.
        if ( $this->is_swiftlms_page( $hook_suffix ) ) {
            wp_enqueue_style(
                'swiftlms-admin-pages',
                SWIFTLMS_PLUGIN_URL . 'admin/assets/css/admin-pages.css',
                array( 'swiftlms-admin' ),
                SWIFTLMS_VERSION
            );
        }
    }

    /**
     * Enqueue admin scripts.
     *
     * @param string $hook_suffix The current admin page.
     * @return void
     */
    public function enqueue_scripts( string $hook_suffix ): void {
        // jQuery UI for sortable.
        wp_enqueue_script( 'jquery-ui-sortable' );

        // Global admin script.
        wp_enqueue_script(
            'swiftlms-admin',
            SWIFTLMS_PLUGIN_URL . 'admin/assets/js/admin.js',
            array( 'jquery', 'jquery-ui-sortable', 'wp-util' ),
            SWIFTLMS_VERSION,
            true
        );

        wp_localize_script(
            'swiftlms-admin',
            'swiftlmsAdmin',
            array(
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'restUrl'   => rest_url( 'swiftlms/v1/' ),
                'nonce'     => wp_create_nonce( 'wp_rest' ),
                'i18n'      => array(
                    'confirmDelete' => __( 'Are you sure you want to delete this?', 'swiftlms' ),
                    'saving'        => __( 'Saving...', 'swiftlms' ),
                    'saved'         => __( 'Saved!', 'swiftlms' ),
                    'error'         => __( 'An error occurred.', 'swiftlms' ),
                ),
            )
        );

        // Course builder script.
        if ( $this->is_course_edit_page( $hook_suffix ) ) {
            wp_enqueue_script(
                'swiftlms-course-builder',
                SWIFTLMS_PLUGIN_URL . 'admin/assets/js/course-builder.js',
                array( 'swiftlms-admin' ),
                SWIFTLMS_VERSION,
                true
            );
        }
    }

    /**
     * Check if on a SwiftLMS admin page.
     *
     * @param string $hook_suffix The hook suffix.
     * @return bool
     */
    protected function is_swiftlms_page( string $hook_suffix ): bool {
        $swiftlms_pages = array(
            'toplevel_page_swiftlms',
            'swiftlms_page_swiftlms-enrollments',
            'swiftlms_page_swiftlms-reports',
            'swiftlms_page_swiftlms-settings',
            'swiftlms_page_swiftlms-modules',
        );

        if ( in_array( $hook_suffix, $swiftlms_pages, true ) ) {
            return true;
        }

        // Check for CPT pages.
        global $post_type;
        $swiftlms_post_types = array( 'sfls_course', 'sfls_lesson', 'sfls_topic' );

        return in_array( $post_type, $swiftlms_post_types, true );
    }

    /**
     * Check if on course edit page.
     *
     * @param string $hook_suffix The hook suffix.
     * @return bool
     */
    protected function is_course_edit_page( string $hook_suffix ): bool {
        global $post_type;
        return $hook_suffix === 'post.php' && $post_type === 'sfls_course';
    }

    /**
     * Render dashboard page.
     *
     * @return void
     */
    public function render_dashboard(): void {
        $stats = $this->get_dashboard_stats();
        include SWIFTLMS_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Render enrollments page.
     *
     * @return void
     */
    public function render_enrollments(): void {
        include SWIFTLMS_PLUGIN_DIR . 'admin/views/enrollments.php';
    }

    /**
     * Render reports page.
     *
     * @return void
     */
    public function render_reports(): void {
        include SWIFTLMS_PLUGIN_DIR . 'admin/views/reports.php';
    }

    /**
     * Render settings page.
     *
     * @return void
     */
    public function render_settings(): void {
        // Handle form submission.
        if ( isset( $_POST['swiftlms_settings_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['swiftlms_settings_nonce'] ), 'swiftlms_save_settings' ) ) {
            $this->save_settings();
        }

        $settings = $this->get_settings();
        include SWIFTLMS_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Render modules page.
     *
     * @return void
     */
    public function render_modules(): void {
        $modules           = swiftlms()->get_modules();
        $available_modules = $this->get_available_modules();
        include SWIFTLMS_PLUGIN_DIR . 'admin/views/modules.php';
    }

    /**
     * Get dashboard statistics.
     *
     * @return array
     */
    protected function get_dashboard_stats(): array {
        global $wpdb;

        // Course count.
        $courses = wp_count_posts( 'sfls_course' );

        // Lesson count.
        $lessons = wp_count_posts( 'sfls_lesson' );

        // Enrollment stats.
        $enrollment_stats = swiftlms()->enrollment()->get_stats();

        // Recent enrollments.
        $enrollments_table = \SwiftLMS\Database\Schema::enrollments_table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $recent_enrollments = $wpdb->get_results(
            "SELECT e.*, u.display_name, u.user_email, p.post_title as course_title
            FROM {$enrollments_table} e
            LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
            LEFT JOIN {$wpdb->posts} p ON e.course_id = p.ID
            ORDER BY e.enrolled_at DESC
            LIMIT 10"
        );

        return array(
            'total_courses'       => $courses->publish ?? 0,
            'draft_courses'       => $courses->draft ?? 0,
            'total_lessons'       => $lessons->publish ?? 0,
            'total_enrollments'   => $enrollment_stats->total ?? 0,
            'active_enrollments'  => $enrollment_stats->active ?? 0,
            'completed_courses'   => $enrollment_stats->completed ?? 0,
            'average_progress'    => round( $enrollment_stats->avg_progress ?? 0, 1 ),
            'recent_enrollments'  => $recent_enrollments,
        );
    }

    /**
     * Get plugin settings.
     *
     * @return array
     */
    protected function get_settings(): array {
        return array(
            'course_slug'          => get_option( 'swiftlms_course_slug', 'courses' ),
            'lesson_slug'          => get_option( 'swiftlms_lesson_slug', 'lessons' ),
            'topic_slug'           => get_option( 'swiftlms_topic_slug', 'topics' ),
            'enable_focus_mode'    => get_option( 'swiftlms_enable_focus_mode', true ),
            'video_sync_interval'  => get_option( 'swiftlms_video_sync_interval', 5 ),
            'completion_threshold' => get_option( 'swiftlms_completion_threshold', 90 ),
            'enable_resume'        => get_option( 'swiftlms_enable_resume', true ),
        );
    }

    /**
     * Save settings.
     *
     * @return void
     */
    protected function save_settings(): void {
        $settings = array(
            'swiftlms_course_slug'          => isset( $_POST['course_slug'] ) ? sanitize_title( wp_unslash( $_POST['course_slug'] ) ) : 'courses',
            'swiftlms_lesson_slug'          => isset( $_POST['lesson_slug'] ) ? sanitize_title( wp_unslash( $_POST['lesson_slug'] ) ) : 'lessons',
            'swiftlms_topic_slug'           => isset( $_POST['topic_slug'] ) ? sanitize_title( wp_unslash( $_POST['topic_slug'] ) ) : 'topics',
            'swiftlms_enable_focus_mode'    => isset( $_POST['enable_focus_mode'] ),
            'swiftlms_video_sync_interval'  => isset( $_POST['video_sync_interval'] ) ? absint( $_POST['video_sync_interval'] ) : 5,
            'swiftlms_completion_threshold' => isset( $_POST['completion_threshold'] ) ? absint( $_POST['completion_threshold'] ) : 90,
            'swiftlms_enable_resume'        => isset( $_POST['enable_resume'] ),
        );

        foreach ( $settings as $key => $value ) {
            update_option( $key, $value );
        }

        // Flush rewrite rules.
        set_transient( 'swiftlms_flush_rewrite_rules', true, 30 );

        add_settings_error(
            'swiftlms_settings',
            'settings_saved',
            __( 'Settings saved.', 'swiftlms' ),
            'success'
        );
    }

    /**
     * Get available (installable) modules.
     *
     * @return array
     */
    protected function get_available_modules(): array {
        return array(
            'quizzes'      => array(
                'name'        => __( 'Quizzes', 'swiftlms' ),
                'description' => __( 'Add quizzes with multiple question types.', 'swiftlms' ),
                'installed'   => swiftlms()->has_module( 'quizzes' ),
            ),
            'certificates' => array(
                'name'        => __( 'Certificates', 'swiftlms' ),
                'description' => __( 'Generate PDF certificates on course completion.', 'swiftlms' ),
                'installed'   => swiftlms()->has_module( 'certificates' ),
            ),
            'gamification' => array(
                'name'        => __( 'Gamification', 'swiftlms' ),
                'description' => __( 'Points, badges, and leaderboards.', 'swiftlms' ),
                'installed'   => swiftlms()->has_module( 'gamification' ),
            ),
            'groups'       => array(
                'name'        => __( 'Groups', 'swiftlms' ),
                'description' => __( 'Organization and group management.', 'swiftlms' ),
                'installed'   => swiftlms()->has_module( 'groups' ),
            ),
            'video'        => array(
                'name'        => __( 'Video', 'swiftlms' ),
                'description' => __( 'Enhanced video player with advanced tracking.', 'swiftlms' ),
                'installed'   => swiftlms()->has_module( 'video' ),
            ),
            'analytics'    => array(
                'name'        => __( 'Analytics', 'swiftlms' ),
                'description' => __( 'Advanced reporting and analytics dashboard.', 'swiftlms' ),
                'installed'   => swiftlms()->has_module( 'analytics' ),
            ),
        );
    }
}
