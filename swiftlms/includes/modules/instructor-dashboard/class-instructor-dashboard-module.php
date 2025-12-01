<?php
/**
 * Instructor Dashboard Module
 *
 * @package SwiftLMS\Modules\InstructorDashboard
 */

namespace SwiftLMS\Modules\InstructorDashboard;

use SwiftLMS\Abstracts\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Instructor_Dashboard_Module class.
 *
 * Main module for instructor dashboard functionality.
 */
class Instructor_Dashboard_Module extends AbstractModule {

    /**
     * Module ID.
     *
     * @var string
     */
    protected $id = 'instructor-dashboard';

    /**
     * Module name.
     *
     * @var string
     */
    protected $name = 'Instructor Dashboard';

    /**
     * Module description.
     *
     * @var string
     */
    protected $description = 'Dedicated dashboard for instructors to manage courses, students, and earnings.';

    /**
     * Module dependencies.
     *
     * @var array
     */
    protected $dependencies = array();

    /**
     * Dashboard page slug.
     */
    const DASHBOARD_SLUG = 'sfls-instructor';

    /**
     * Initialize the module.
     */
    public function init(): void {
        // Initialize components.
        Instructor_Role::init();
        Earnings::init();

        // Admin menu.
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );

        // Frontend dashboard.
        add_shortcode( 'swiftlms_instructor_dashboard', array( $this, 'render_dashboard_shortcode' ) );

        // Enqueue assets.
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

        // AJAX handlers.
        add_action( 'wp_ajax_sfls_instructor_get_students', array( $this, 'ajax_get_students' ) );
        add_action( 'wp_ajax_sfls_instructor_grade_submission', array( $this, 'ajax_grade_submission' ) );
        add_action( 'wp_ajax_sfls_instructor_request_withdrawal', array( $this, 'ajax_request_withdrawal' ) );
        add_action( 'wp_ajax_sfls_instructor_update_payment_settings', array( $this, 'ajax_update_payment_settings' ) );

        // REST API.
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

        // Dashboard widget.
        add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );

        // Admin bar.
        add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu' ), 100 );
    }

    /**
     * Register activation hook.
     */
    public function activate(): void {
        Instructor_Role::register_role();
        Earnings::create_tables();
        flush_rewrite_rules();
    }

    /**
     * Register deactivation hook.
     */
    public function deactivate(): void {
        // Don't remove role on deactivation to preserve data.
        flush_rewrite_rules();
    }

    /**
     * Register admin menu.
     */
    public function register_admin_menu(): void {
        // Only show for instructors and admins.
        if ( ! current_user_can( 'sfls_access_instructor_dashboard' ) && ! current_user_can( 'manage_options' ) ) {
            return;
        }

        add_menu_page(
            __( 'Instructor Dashboard', 'swiftlms' ),
            __( 'Instructor', 'swiftlms' ),
            'sfls_access_instructor_dashboard',
            self::DASHBOARD_SLUG,
            array( $this, 'render_dashboard_page' ),
            'dashicons-welcome-learn-more',
            3
        );

        add_submenu_page(
            self::DASHBOARD_SLUG,
            __( 'Dashboard', 'swiftlms' ),
            __( 'Dashboard', 'swiftlms' ),
            'sfls_access_instructor_dashboard',
            self::DASHBOARD_SLUG,
            array( $this, 'render_dashboard_page' )
        );

        add_submenu_page(
            self::DASHBOARD_SLUG,
            __( 'My Courses', 'swiftlms' ),
            __( 'My Courses', 'swiftlms' ),
            'sfls_access_instructor_dashboard',
            self::DASHBOARD_SLUG . '-courses',
            array( $this, 'render_courses_page' )
        );

        add_submenu_page(
            self::DASHBOARD_SLUG,
            __( 'Students', 'swiftlms' ),
            __( 'Students', 'swiftlms' ),
            'sfls_access_instructor_dashboard',
            self::DASHBOARD_SLUG . '-students',
            array( $this, 'render_students_page' )
        );

        add_submenu_page(
            self::DASHBOARD_SLUG,
            __( 'Submissions', 'swiftlms' ),
            __( 'Submissions', 'swiftlms' ),
            'sfls_grade_assignments',
            self::DASHBOARD_SLUG . '-submissions',
            array( $this, 'render_submissions_page' )
        );

        add_submenu_page(
            self::DASHBOARD_SLUG,
            __( 'Earnings', 'swiftlms' ),
            __( 'Earnings', 'swiftlms' ),
            'sfls_view_own_earnings',
            self::DASHBOARD_SLUG . '-earnings',
            array( $this, 'render_earnings_page' )
        );

        add_submenu_page(
            self::DASHBOARD_SLUG,
            __( 'Analytics', 'swiftlms' ),
            __( 'Analytics', 'swiftlms' ),
            'sfls_view_own_course_reports',
            self::DASHBOARD_SLUG . '-analytics',
            array( $this, 'render_analytics_page' )
        );
    }

    /**
     * Render dashboard page.
     */
    public function render_dashboard_page(): void {
        $instructor_id = get_current_user_id();
        $stats = Instructor_Analytics::get_overview_stats( $instructor_id, 'month' );
        $earnings = Earnings::get_earnings_summary( $instructor_id, 'month' );
        $recent_activity = Instructor_Analytics::get_recent_activity( $instructor_id, 10 );
        $pending = Instructor_Analytics::get_pending_submissions( $instructor_id, array( 'limit' => 5 ) );

        include __DIR__ . '/views/dashboard.php';
    }

    /**
     * Render courses page.
     */
    public function render_courses_page(): void {
        $instructor_id = get_current_user_id();
        $courses = Instructor_Analytics::get_course_performance( $instructor_id );

        include __DIR__ . '/views/courses.php';
    }

    /**
     * Render students page.
     */
    public function render_students_page(): void {
        $instructor_id = get_current_user_id();

        $args = array(
            'course_id' => isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0,
            'status'    => isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '',
            'search'    => isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '',
            'limit'     => 20,
            'offset'    => isset( $_GET['paged'] ) ? ( absint( $_GET['paged'] ) - 1 ) * 20 : 0,
        );

        $result = Instructor_Analytics::get_students( $instructor_id, $args );
        $courses = Instructor_Role::get_instructor_courses( $instructor_id );

        include __DIR__ . '/views/students.php';
    }

    /**
     * Render submissions page.
     */
    public function render_submissions_page(): void {
        $instructor_id = get_current_user_id();

        // Check if viewing single submission.
        if ( isset( $_GET['submission_id'] ) ) {
            $submission_id = absint( $_GET['submission_id'] );
            $this->render_submission_detail( $submission_id );
            return;
        }

        $args = array(
            'course_id' => isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0,
            'limit'     => 20,
            'offset'    => isset( $_GET['paged'] ) ? ( absint( $_GET['paged'] ) - 1 ) * 20 : 0,
        );

        $result = Instructor_Analytics::get_pending_submissions( $instructor_id, $args );
        $courses = Instructor_Role::get_instructor_courses( $instructor_id );

        include __DIR__ . '/views/submissions.php';
    }

    /**
     * Render single submission detail.
     *
     * @param int $submission_id Submission ID.
     */
    private function render_submission_detail( int $submission_id ): void {
        global $wpdb;

        $submission = $wpdb->get_row( $wpdb->prepare(
            "SELECT s.*, a.post_title as assignment_title, u.display_name as student_name, u.user_email
             FROM {$wpdb->prefix}swiftlms_submissions s
             INNER JOIN {$wpdb->posts} a ON s.assignment_id = a.ID
             INNER JOIN {$wpdb->users} u ON s.student_id = u.ID
             WHERE s.id = %d",
            $submission_id
        ) );

        if ( ! $submission ) {
            wp_die( __( 'Submission not found.', 'swiftlms' ) );
        }

        // Verify instructor can grade this.
        $course_id = get_post_meta( $submission->assignment_id, '_sfls_course_id', true );
        if ( ! Instructor_Role::can_manage_course( get_current_user_id(), $course_id ) ) {
            wp_die( __( 'You do not have permission to grade this submission.', 'swiftlms' ) );
        }

        $assignment = get_post( $submission->assignment_id );
        $max_points = get_post_meta( $submission->assignment_id, '_sfls_max_points', true ) ?: 100;

        include __DIR__ . '/views/submission-detail.php';
    }

    /**
     * Render earnings page.
     */
    public function render_earnings_page(): void {
        $instructor_id = get_current_user_id();

        $summary = Earnings::get_earnings_summary( $instructor_id );
        $by_course = Earnings::get_earnings_by_course( $instructor_id );
        $monthly_trend = Earnings::get_monthly_trend( $instructor_id, 12 );
        $recent_earnings = Earnings::get_instructor_earnings( $instructor_id, array( 'limit' => 20 ) );
        $withdrawals = Earnings::get_instructor_withdrawals( $instructor_id, array( 'limit' => 10 ) );
        $payment_settings = Earnings::get_payment_settings( $instructor_id );

        include __DIR__ . '/views/earnings.php';
    }

    /**
     * Render analytics page.
     */
    public function render_analytics_page(): void {
        $instructor_id = get_current_user_id();

        $period = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : 'month';
        $stats = Instructor_Analytics::get_overview_stats( $instructor_id, $period );
        $trends = Instructor_Analytics::get_enrollment_trends( $instructor_id, 30 );
        $course_performance = Instructor_Analytics::get_course_performance( $instructor_id );

        include __DIR__ . '/views/analytics.php';
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Page hook.
     */
    public function enqueue_admin_assets( string $hook ): void {
        if ( strpos( $hook, self::DASHBOARD_SLUG ) === false ) {
            return;
        }

        wp_enqueue_style(
            'sfls-instructor-dashboard',
            plugin_dir_url( __FILE__ ) . 'assets/css/instructor-dashboard.css',
            array(),
            SWIFTLMS_VERSION
        );

        wp_enqueue_script(
            'sfls-instructor-dashboard',
            plugin_dir_url( __FILE__ ) . 'assets/js/instructor-dashboard.js',
            array( 'jquery', 'chart-js' ),
            SWIFTLMS_VERSION,
            true
        );

        // Chart.js.
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            array(),
            '4.4.0',
            true
        );

        wp_localize_script( 'sfls-instructor-dashboard', 'swiftlms_instructor', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'rest_url' => rest_url( 'swiftlms/v1/instructor/' ),
            'nonce'    => wp_create_nonce( 'sfls_instructor_nonce' ),
            'i18n'     => array(
                'confirm_grade'    => __( 'Are you sure you want to submit this grade?', 'swiftlms' ),
                'confirm_withdraw' => __( 'Are you sure you want to request this withdrawal?', 'swiftlms' ),
                'loading'          => __( 'Loading...', 'swiftlms' ),
                'error'            => __( 'An error occurred. Please try again.', 'swiftlms' ),
                'success'          => __( 'Success!', 'swiftlms' ),
            ),
        ) );
    }

    /**
     * Enqueue frontend assets.
     */
    public function enqueue_frontend_assets(): void {
        if ( ! is_page() || ! has_shortcode( get_post()->post_content, 'swiftlms_instructor_dashboard' ) ) {
            return;
        }

        wp_enqueue_style(
            'sfls-instructor-frontend',
            plugin_dir_url( __FILE__ ) . 'assets/css/instructor-frontend.css',
            array(),
            SWIFTLMS_VERSION
        );

        wp_enqueue_script(
            'sfls-instructor-frontend',
            plugin_dir_url( __FILE__ ) . 'assets/js/instructor-dashboard.js',
            array( 'jquery' ),
            SWIFTLMS_VERSION,
            true
        );
    }

    /**
     * AJAX: Get students.
     */
    public function ajax_get_students(): void {
        check_ajax_referer( 'sfls_instructor_nonce', 'nonce' );

        if ( ! current_user_can( 'sfls_view_enrolled_students' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'swiftlms' ) ) );
        }

        $args = array(
            'course_id' => isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0,
            'status'    => isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '',
            'search'    => isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '',
            'limit'     => 20,
            'offset'    => isset( $_POST['page'] ) ? ( absint( $_POST['page'] ) - 1 ) * 20 : 0,
        );

        $result = Instructor_Analytics::get_students( get_current_user_id(), $args );

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Grade submission.
     */
    public function ajax_grade_submission(): void {
        check_ajax_referer( 'sfls_instructor_nonce', 'nonce' );

        if ( ! current_user_can( 'sfls_grade_assignments' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'swiftlms' ) ) );
        }

        $submission_id = isset( $_POST['submission_id'] ) ? absint( $_POST['submission_id'] ) : 0;
        $grade = isset( $_POST['grade'] ) ? floatval( $_POST['grade'] ) : 0;
        $feedback = isset( $_POST['feedback'] ) ? wp_kses_post( $_POST['feedback'] ) : '';
        $status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'graded';

        if ( ! $submission_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid submission.', 'swiftlms' ) ) );
        }

        global $wpdb;

        // Verify permission.
        $submission = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}swiftlms_submissions WHERE id = %d",
            $submission_id
        ) );

        if ( ! $submission ) {
            wp_send_json_error( array( 'message' => __( 'Submission not found.', 'swiftlms' ) ) );
        }

        $course_id = get_post_meta( $submission->assignment_id, '_sfls_course_id', true );
        if ( ! Instructor_Role::can_manage_course( get_current_user_id(), $course_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You cannot grade this submission.', 'swiftlms' ) ) );
        }

        // Update submission.
        $result = $wpdb->update(
            $wpdb->prefix . 'swiftlms_submissions',
            array(
                'grade'      => $grade,
                'feedback'   => $feedback,
                'status'     => $status,
                'graded_by'  => get_current_user_id(),
                'graded_at'  => current_time( 'mysql' ),
            ),
            array( 'id' => $submission_id ),
            array( '%f', '%s', '%s', '%d', '%s' ),
            array( '%d' )
        );

        if ( $result !== false ) {
            do_action( 'swiftlms_submission_graded', $submission_id, $grade, $feedback );
            wp_send_json_success( array( 'message' => __( 'Submission graded successfully.', 'swiftlms' ) ) );
        }

        wp_send_json_error( array( 'message' => __( 'Failed to grade submission.', 'swiftlms' ) ) );
    }

    /**
     * AJAX: Request withdrawal.
     */
    public function ajax_request_withdrawal(): void {
        check_ajax_referer( 'sfls_instructor_nonce', 'nonce' );

        if ( ! current_user_can( 'sfls_request_payout' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'swiftlms' ) ) );
        }

        $amount = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 0;
        $payment_info = Earnings::get_payment_settings( get_current_user_id() );

        if ( $amount <= 0 ) {
            wp_send_json_error( array( 'message' => __( 'Invalid amount.', 'swiftlms' ) ) );
        }

        $result = Earnings::request_withdrawal( get_current_user_id(), $amount, $payment_info );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message'       => __( 'Withdrawal request submitted successfully.', 'swiftlms' ),
            'withdrawal_id' => $result,
        ) );
    }

    /**
     * AJAX: Update payment settings.
     */
    public function ajax_update_payment_settings(): void {
        check_ajax_referer( 'sfls_instructor_nonce', 'nonce' );

        if ( ! current_user_can( 'sfls_view_own_earnings' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'swiftlms' ) ) );
        }

        $settings = array(
            'payment_method' => isset( $_POST['payment_method'] ) ? sanitize_text_field( $_POST['payment_method'] ) : '',
            'paypal_email'   => isset( $_POST['paypal_email'] ) ? sanitize_email( $_POST['paypal_email'] ) : '',
            'bank_name'      => isset( $_POST['bank_name'] ) ? sanitize_text_field( $_POST['bank_name'] ) : '',
            'bank_account'   => isset( $_POST['bank_account'] ) ? sanitize_text_field( $_POST['bank_account'] ) : '',
            'bank_routing'   => isset( $_POST['bank_routing'] ) ? sanitize_text_field( $_POST['bank_routing'] ) : '',
            'bank_swift'     => isset( $_POST['bank_swift'] ) ? sanitize_text_field( $_POST['bank_swift'] ) : '',
        );

        Earnings::update_payment_settings( get_current_user_id(), $settings );

        wp_send_json_success( array( 'message' => __( 'Payment settings updated.', 'swiftlms' ) ) );
    }

    /**
     * Register REST routes.
     */
    public function register_rest_routes(): void {
        register_rest_route( 'swiftlms/v1', '/instructor/stats', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'rest_get_stats' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        register_rest_route( 'swiftlms/v1', '/instructor/courses', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'rest_get_courses' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        register_rest_route( 'swiftlms/v1', '/instructor/students', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'rest_get_students' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        register_rest_route( 'swiftlms/v1', '/instructor/earnings', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'rest_get_earnings' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );
    }

    /**
     * REST permission check.
     *
     * @return bool
     */
    public function rest_permission_check(): bool {
        return current_user_can( 'sfls_access_instructor_dashboard' );
    }

    /**
     * REST: Get stats.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function rest_get_stats( \WP_REST_Request $request ): \WP_REST_Response {
        $period = $request->get_param( 'period' ) ?: 'month';
        $stats = Instructor_Analytics::get_overview_stats( get_current_user_id(), $period );

        return rest_ensure_response( $stats );
    }

    /**
     * REST: Get courses.
     *
     * @return \WP_REST_Response
     */
    public function rest_get_courses(): \WP_REST_Response {
        $courses = Instructor_Analytics::get_course_performance( get_current_user_id() );
        return rest_ensure_response( $courses );
    }

    /**
     * REST: Get students.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function rest_get_students( \WP_REST_Request $request ): \WP_REST_Response {
        $args = array(
            'course_id' => $request->get_param( 'course_id' ) ?: 0,
            'status'    => $request->get_param( 'status' ) ?: '',
            'search'    => $request->get_param( 'search' ) ?: '',
            'limit'     => $request->get_param( 'per_page' ) ?: 20,
            'offset'    => ( ( $request->get_param( 'page' ) ?: 1 ) - 1 ) * 20,
        );

        $result = Instructor_Analytics::get_students( get_current_user_id(), $args );

        return rest_ensure_response( $result );
    }

    /**
     * REST: Get earnings.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function rest_get_earnings( \WP_REST_Request $request ): \WP_REST_Response {
        $period = $request->get_param( 'period' ) ?: 'all';
        $summary = Earnings::get_earnings_summary( get_current_user_id(), $period );

        return rest_ensure_response( $summary );
    }

    /**
     * Add dashboard widget.
     */
    public function add_dashboard_widget(): void {
        if ( ! Instructor_Role::is_instructor() && ! current_user_can( 'manage_options' ) ) {
            return;
        }

        wp_add_dashboard_widget(
            'sfls_instructor_overview',
            __( 'Instructor Overview', 'swiftlms' ),
            array( $this, 'render_dashboard_widget' )
        );
    }

    /**
     * Render dashboard widget.
     */
    public function render_dashboard_widget(): void {
        $stats = Instructor_Analytics::get_overview_stats( get_current_user_id(), 'month' );
        $earnings = Earnings::get_earnings_summary( get_current_user_id(), 'month' );
        ?>
        <div class="sfls-widget-stats">
            <div class="sfls-widget-stat">
                <span class="sfls-widget-value"><?php echo esc_html( $stats['active_students'] ); ?></span>
                <span class="sfls-widget-label"><?php esc_html_e( 'Active Students', 'swiftlms' ); ?></span>
            </div>
            <div class="sfls-widget-stat">
                <span class="sfls-widget-value"><?php echo esc_html( $stats['completions'] ); ?></span>
                <span class="sfls-widget-label"><?php esc_html_e( 'Completions', 'swiftlms' ); ?></span>
            </div>
            <div class="sfls-widget-stat">
                <span class="sfls-widget-value"><?php echo esc_html( $stats['pending_assignments'] ); ?></span>
                <span class="sfls-widget-label"><?php esc_html_e( 'Pending Grades', 'swiftlms' ); ?></span>
            </div>
            <div class="sfls-widget-stat">
                <span class="sfls-widget-value"><?php echo wc_price( $earnings['total_earnings'] ); ?></span>
                <span class="sfls-widget-label"><?php esc_html_e( 'This Month', 'swiftlms' ); ?></span>
            </div>
        </div>
        <p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::DASHBOARD_SLUG ) ); ?>" class="button">
                <?php esc_html_e( 'View Full Dashboard', 'swiftlms' ); ?>
            </a>
        </p>
        <?php
    }

    /**
     * Add admin bar menu.
     *
     * @param \WP_Admin_Bar $admin_bar Admin bar.
     */
    public function add_admin_bar_menu( \WP_Admin_Bar $admin_bar ): void {
        if ( ! Instructor_Role::is_instructor() || is_admin() ) {
            return;
        }

        $admin_bar->add_node( array(
            'id'    => 'sfls-instructor',
            'title' => '<span class="ab-icon dashicons dashicons-welcome-learn-more"></span>' . __( 'Instructor', 'swiftlms' ),
            'href'  => admin_url( 'admin.php?page=' . self::DASHBOARD_SLUG ),
        ) );

        $pending = Instructor_Analytics::get_pending_submissions( get_current_user_id(), array( 'limit' => 1 ) );

        if ( $pending['total'] > 0 ) {
            $admin_bar->add_node( array(
                'parent' => 'sfls-instructor',
                'id'     => 'sfls-instructor-submissions',
                'title'  => sprintf( __( 'Submissions (%d)', 'swiftlms' ), $pending['total'] ),
                'href'   => admin_url( 'admin.php?page=' . self::DASHBOARD_SLUG . '-submissions' ),
            ) );
        }

        $admin_bar->add_node( array(
            'parent' => 'sfls-instructor',
            'id'     => 'sfls-instructor-courses',
            'title'  => __( 'My Courses', 'swiftlms' ),
            'href'   => admin_url( 'admin.php?page=' . self::DASHBOARD_SLUG . '-courses' ),
        ) );

        $admin_bar->add_node( array(
            'parent' => 'sfls-instructor',
            'id'     => 'sfls-instructor-earnings',
            'title'  => __( 'Earnings', 'swiftlms' ),
            'href'   => admin_url( 'admin.php?page=' . self::DASHBOARD_SLUG . '-earnings' ),
        ) );
    }

    /**
     * Render dashboard shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_dashboard_shortcode( array $atts = array() ): string {
        if ( ! is_user_logged_in() ) {
            return '<p>' . __( 'Please log in to access the instructor dashboard.', 'swiftlms' ) . '</p>';
        }

        if ( ! Instructor_Role::is_instructor() ) {
            return '<p>' . __( 'You must be an instructor to access this dashboard.', 'swiftlms' ) . '</p>';
        }

        ob_start();

        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'overview';
        $instructor_id = get_current_user_id();

        include __DIR__ . '/views/frontend-dashboard.php';

        return ob_get_clean();
    }
}
