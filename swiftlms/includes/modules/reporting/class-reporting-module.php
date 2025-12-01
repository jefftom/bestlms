<?php
/**
 * Reporting Module
 *
 * @package SwiftLMS
 */

namespace SwiftLMS\Modules\Reporting;

use SwiftLMS\Core\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Reporting Module class.
 */
class Reporting_Module extends AbstractModule {

    /**
     * Module ID.
     *
     * @var string
     */
    protected string $id = 'reporting';

    /**
     * Module name.
     *
     * @var string
     */
    protected string $name = 'Advanced Reporting';

    /**
     * Module description.
     *
     * @var string
     */
    protected string $description = 'Analytics dashboard, reports, and data exports.';

    /**
     * Initialize module.
     */
    public function init(): void {
        // Admin pages
        add_action( 'admin_menu', array( $this, 'add_admin_pages' ), 25 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // REST API
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

        // Export handler
        add_action( 'admin_init', array( $this, 'handle_export' ) );

        // Dashboard widget
        add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
    }

    /**
     * Add admin pages.
     */
    public function add_admin_pages(): void {
        add_submenu_page(
            'swiftlms',
            __( 'Reports', 'swiftlms' ),
            __( 'Reports', 'swiftlms' ),
            'manage_options',
            'sfls-reports',
            array( $this, 'render_reports_page' )
        );
    }

    /**
     * Render reports page.
     */
    public function render_reports_page(): void {
        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview';

        $tabs = array(
            'overview' => __( 'Overview', 'swiftlms' ),
            'courses'  => __( 'Courses', 'swiftlms' ),
            'students' => __( 'Students', 'swiftlms' ),
            'quizzes'  => __( 'Quizzes', 'swiftlms' ),
            'export'   => __( 'Export', 'swiftlms' ),
        );

        // Add revenue tab if WooCommerce is active
        if ( class_exists( 'WooCommerce' ) ) {
            $tabs['revenue'] = __( 'Revenue', 'swiftlms' );
        }
        ?>
        <div class="wrap sfls-reports-wrap">
            <h1><?php esc_html_e( 'Reports & Analytics', 'swiftlms' ); ?></h1>

            <nav class="nav-tab-wrapper sfls-nav-tabs">
                <?php foreach ( $tabs as $key => $label ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'tab', $key ) ); ?>"
                       class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="sfls-reports-content">
                <?php
                switch ( $tab ) {
                    case 'courses':
                        $this->render_courses_tab();
                        break;
                    case 'students':
                        $this->render_students_tab();
                        break;
                    case 'quizzes':
                        $this->render_quizzes_tab();
                        break;
                    case 'export':
                        $this->render_export_tab();
                        break;
                    case 'revenue':
                        $this->render_revenue_tab();
                        break;
                    default:
                        $this->render_overview_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render overview tab.
     */
    private function render_overview_tab(): void {
        $period     = isset( $_GET['period'] ) ? sanitize_key( $_GET['period'] ) : '30days';
        $dates      = $this->get_period_dates( $period );
        $stats      = Analytics::get_overview_stats( $dates['start'], $dates['end'] );
        $prev_dates = $this->get_period_dates( $period, true );
        $prev_stats = Analytics::get_overview_stats( $prev_dates['start'], $prev_dates['end'] );
        ?>
        <div class="sfls-date-filter">
            <select id="sfls-period-select" onchange="location.href='<?php echo esc_url( admin_url( 'admin.php?page=sfls-reports&tab=overview&period=' ) ); ?>' + this.value">
                <option value="7days" <?php selected( $period, '7days' ); ?>><?php esc_html_e( 'Last 7 Days', 'swiftlms' ); ?></option>
                <option value="30days" <?php selected( $period, '30days' ); ?>><?php esc_html_e( 'Last 30 Days', 'swiftlms' ); ?></option>
                <option value="90days" <?php selected( $period, '90days' ); ?>><?php esc_html_e( 'Last 90 Days', 'swiftlms' ); ?></option>
                <option value="year" <?php selected( $period, 'year' ); ?>><?php esc_html_e( 'This Year', 'swiftlms' ); ?></option>
                <option value="all" <?php selected( $period, 'all' ); ?>><?php esc_html_e( 'All Time', 'swiftlms' ); ?></option>
            </select>
        </div>

        <div class="sfls-stats-grid">
            <?php
            $stat_cards = array(
                array(
                    'label'    => __( 'Total Students', 'swiftlms' ),
                    'value'    => $stats['total_students'],
                    'icon'     => 'groups',
                    'color'    => '#4f46e5',
                ),
                array(
                    'label'    => __( 'New Enrollments', 'swiftlms' ),
                    'value'    => $stats['new_enrollments'],
                    'prev'     => $prev_stats['new_enrollments'],
                    'icon'     => 'welcome-learn-more',
                    'color'    => '#0891b2',
                ),
                array(
                    'label'    => __( 'Course Completions', 'swiftlms' ),
                    'value'    => $stats['completions'],
                    'prev'     => $prev_stats['completions'],
                    'icon'     => 'yes-alt',
                    'color'    => '#059669',
                ),
                array(
                    'label'    => __( 'Avg Completion Rate', 'swiftlms' ),
                    'value'    => $stats['avg_completion_rate'] . '%',
                    'icon'     => 'chart-pie',
                    'color'    => '#d97706',
                ),
                array(
                    'label'    => __( 'Lessons Completed', 'swiftlms' ),
                    'value'    => $stats['lessons_completed'],
                    'prev'     => $prev_stats['lessons_completed'],
                    'icon'     => 'media-text',
                    'color'    => '#7c3aed',
                ),
                array(
                    'label'    => __( 'Quiz Pass Rate', 'swiftlms' ),
                    'value'    => $stats['quiz_pass_rate'] . '%',
                    'icon'     => 'editor-spellcheck',
                    'color'    => '#dc2626',
                ),
                array(
                    'label'    => __( 'Quiz Attempts', 'swiftlms' ),
                    'value'    => $stats['quiz_attempts'],
                    'prev'     => $prev_stats['quiz_attempts'],
                    'icon'     => 'clipboard',
                    'color'    => '#0284c7',
                ),
                array(
                    'label'    => __( 'Certificates Issued', 'swiftlms' ),
                    'value'    => $stats['certificates'],
                    'prev'     => $prev_stats['certificates'],
                    'icon'     => 'awards',
                    'color'    => '#ca8a04',
                ),
            );

            foreach ( $stat_cards as $card ) :
                $change = null;
                if ( isset( $card['prev'] ) && $card['prev'] > 0 ) {
                    $change = round( ( ( $card['value'] - $card['prev'] ) / $card['prev'] ) * 100, 1 );
                }
                ?>
                <div class="sfls-stat-card">
                    <div class="sfls-stat-icon" style="background-color: <?php echo esc_attr( $card['color'] ); ?>20; color: <?php echo esc_attr( $card['color'] ); ?>;">
                        <span class="dashicons dashicons-<?php echo esc_attr( $card['icon'] ); ?>"></span>
                    </div>
                    <div class="sfls-stat-content">
                        <span class="sfls-stat-value"><?php echo esc_html( $card['value'] ); ?></span>
                        <span class="sfls-stat-label"><?php echo esc_html( $card['label'] ); ?></span>
                        <?php if ( $change !== null ) : ?>
                            <span class="sfls-stat-change <?php echo $change >= 0 ? 'positive' : 'negative'; ?>">
                                <span class="dashicons dashicons-arrow-<?php echo $change >= 0 ? 'up' : 'down'; ?>-alt"></span>
                                <?php echo esc_html( abs( $change ) ); ?>%
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="sfls-charts-row">
            <div class="sfls-chart-card">
                <h3><?php esc_html_e( 'Enrollment Trends', 'swiftlms' ); ?></h3>
                <canvas id="enrollmentChart"></canvas>
            </div>
            <div class="sfls-chart-card">
                <h3><?php esc_html_e( 'Completion Trends', 'swiftlms' ); ?></h3>
                <canvas id="completionChart"></canvas>
            </div>
        </div>

        <div class="sfls-tables-row">
            <div class="sfls-table-card">
                <h3><?php esc_html_e( 'Top Courses', 'swiftlms' ); ?></h3>
                <?php
                $top_courses = array_slice( Analytics::get_course_analytics(), 0, 5 );
                if ( $top_courses ) :
                    ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Course', 'swiftlms' ); ?></th>
                                <th><?php esc_html_e( 'Enrolled', 'swiftlms' ); ?></th>
                                <th><?php esc_html_e( 'Completion', 'swiftlms' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $top_courses as $course ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $course['course_title'] ); ?></td>
                                    <td><?php echo esc_html( $course['total_enrolled'] ); ?></td>
                                    <td>
                                        <div class="sfls-mini-progress">
                                            <div class="sfls-mini-bar" style="width: <?php echo esc_attr( $course['completion_rate'] ); ?>%;"></div>
                                            <span><?php echo esc_html( $course['completion_rate'] ); ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p class="sfls-no-data"><?php esc_html_e( 'No course data yet.', 'swiftlms' ); ?></p>
                <?php endif; ?>
            </div>

            <div class="sfls-table-card">
                <h3><?php esc_html_e( 'Top Performers', 'swiftlms' ); ?></h3>
                <?php
                $top_performers = Analytics::get_top_performers( 5 );
                if ( $top_performers ) :
                    ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Student', 'swiftlms' ); ?></th>
                                <th><?php esc_html_e( 'Completions', 'swiftlms' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $top_performers as $student ) : ?>
                                <tr>
                                    <td>
                                        <?php echo get_avatar( $student['ID'], 24 ); ?>
                                        <?php echo esc_html( $student['display_name'] ); ?>
                                    </td>
                                    <td><?php echo esc_html( $student['value'] ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p class="sfls-no-data"><?php esc_html_e( 'No student data yet.', 'swiftlms' ); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <script>
        jQuery(function($) {
            var chartData = <?php echo wp_json_encode( $this->get_chart_data( $dates['start'], $dates['end'] ) ); ?>;

            // Enrollment Chart
            new Chart(document.getElementById('enrollmentChart'), {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: '<?php echo esc_js( __( 'Enrollments', 'swiftlms' ) ); ?>',
                        data: chartData.enrollments,
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });

            // Completion Chart
            new Chart(document.getElementById('completionChart'), {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: '<?php echo esc_js( __( 'Courses', 'swiftlms' ) ); ?>',
                            data: chartData.courses,
                            backgroundColor: '#059669'
                        },
                        {
                            label: '<?php echo esc_js( __( 'Lessons', 'swiftlms' ) ); ?>',
                            data: chartData.lessons,
                            backgroundColor: '#0891b2'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Render courses tab.
     */
    private function render_courses_tab(): void {
        $courses = Analytics::get_course_analytics();
        ?>
        <div class="sfls-toolbar">
            <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'sfls_export' => 'courses' ) ), 'sfls_export' ) ); ?>"
               class="button">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e( 'Export CSV', 'swiftlms' ); ?>
            </a>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Course', 'swiftlms' ); ?></th>
                    <th><?php esc_html_e( 'Enrolled', 'swiftlms' ); ?></th>
                    <th><?php esc_html_e( 'In Progress', 'swiftlms' ); ?></th>
                    <th><?php esc_html_e( 'Completed', 'swiftlms' ); ?></th>
                    <th><?php esc_html_e( 'Completion Rate', 'swiftlms' ); ?></th>
                    <th><?php esc_html_e( 'Avg Progress', 'swiftlms' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'swiftlms' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $courses ) ) : ?>
                    <tr>
                        <td colspan="7"><?php esc_html_e( 'No course data available.', 'swiftlms' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $courses as $course ) : ?>
                        <tr>
                            <td>
                                <strong>
                                    <a href="<?php echo esc_url( get_edit_post_link( $course['course_id'] ) ); ?>">
                                        <?php echo esc_html( $course['course_title'] ); ?>
                                    </a>
                                </strong>
                            </td>
                            <td><?php echo esc_html( $course['total_enrolled'] ); ?></td>
                            <td><?php echo esc_html( $course['in_progress'] ); ?></td>
                            <td><?php echo esc_html( $course['completed'] ); ?></td>
                            <td>
                                <div class="sfls-progress-cell">
                                    <div class="sfls-progress-bar-small">
                                        <div class="sfls-bar" style="width: <?php echo esc_attr( $course['completion_rate'] ); ?>%;"></div>
                                    </div>
                                    <span><?php echo esc_html( $course['completion_rate'] ); ?>%</span>
                                </div>
                            </td>
                            <td><?php echo esc_html( $course['avg_progress'] ); ?>%</td>
                            <td>
                                <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'course-detail', 'course_id' => $course['course_id'] ) ) ); ?>"
                                   class="button button-small">
                                    <?php esc_html_e( 'Details', 'swiftlms' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render students tab.
     */
    private function render_students_tab(): void {
        $students = Analytics::get_student_analytics( 0, 50 );
        ?>
        <div class="sfls-toolbar">
            <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'sfls_export' => 'students' ) ), 'sfls_export' ) ); ?>"
               class="button">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e( 'Export CSV', 'swiftlms' ); ?>
            </a>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Student', 'swiftlms' ); ?></th>
                    <th><?php esc_html_e( 'Enrolled', 'swiftlms' ); ?></th>
                    <th><?php esc_html_e( 'Completed', 'swiftlms' ); ?></th>
                    <th><?php esc_html_e( 'Lessons', 'swiftlms' ); ?></th>
                    <th><?php esc_html_e( 'Quizzes Passed', 'swiftlms' ); ?></th>
                    <th><?php esc_html_e( 'Avg Score', 'swiftlms' ); ?></th>
                    <th><?php esc_html_e( 'Time Spent', 'swiftlms' ); ?></th>
                    <th><?php esc_html_e( 'Last Active', 'swiftlms' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $students ) ) : ?>
                    <tr>
                        <td colspan="8"><?php esc_html_e( 'No student data available.', 'swiftlms' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $students as $student ) : ?>
                        <tr>
                            <td>
                                <?php echo get_avatar( $student['user_id'], 32 ); ?>
                                <strong><?php echo esc_html( $student['display_name'] ); ?></strong>
                                <br><small><?php echo esc_html( $student['user_email'] ); ?></small>
                            </td>
                            <td><?php echo esc_html( $student['enrolled_courses'] ); ?></td>
                            <td><?php echo esc_html( $student['completed_courses'] ); ?></td>
                            <td><?php echo esc_html( $student['lessons_completed'] ); ?></td>
                            <td><?php echo esc_html( $student['quizzes_passed'] ); ?></td>
                            <td><?php echo $student['avg_quiz_score'] ? esc_html( round( $student['avg_quiz_score'], 1 ) ) . '%' : '—'; ?></td>
                            <td><?php echo esc_html( $this->format_duration( $student['total_time_spent'] ) ); ?></td>
                            <td><?php echo $student['last_activity'] ? esc_html( human_time_diff( strtotime( $student['last_activity'] ) ) ) . ' ago' : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render quizzes tab.
     */
    private function render_quizzes_tab(): void {
        $quizzes = Analytics::get_quiz_analytics();
        ?>
        <div class="sfls-toolbar">
            <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'sfls_export' => 'quizzes' ) ), 'sfls_export' ) ); ?>"
               class="button">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e( 'Export CSV', 'swiftlms' ); ?>
            </a>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Quiz', 'swiftlms' ); ?></th>
                    <th><?php esc_html_e( 'Total Attempts', 'swiftlms' ); ?></th>
                    <th><?php esc_html_e( 'Unique Users', 'swiftlms' ); ?></th>
                    <th><?php esc_html_e( 'Pass Rate', 'swiftlms' ); ?></th>
                    <th><?php esc_html_e( 'Avg Score', 'swiftlms' ); ?></th>
                    <th><?php esc_html_e( 'Avg Time', 'swiftlms' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $quizzes ) ) : ?>
                    <tr>
                        <td colspan="6"><?php esc_html_e( 'No quiz data available.', 'swiftlms' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $quizzes as $quiz ) : ?>
                        <?php
                        $pass_rate = $quiz['total_attempts'] > 0
                            ? round( ( $quiz['passed'] / $quiz['total_attempts'] ) * 100, 1 )
                            : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html( $quiz['quiz_title'] ); ?></strong></td>
                            <td><?php echo esc_html( $quiz['total_attempts'] ); ?></td>
                            <td><?php echo esc_html( $quiz['unique_users'] ); ?></td>
                            <td><?php echo esc_html( $pass_rate ); ?>%</td>
                            <td><?php echo $quiz['avg_score'] ? esc_html( round( $quiz['avg_score'], 1 ) ) . '%' : '—'; ?></td>
                            <td><?php echo esc_html( $this->format_duration( $quiz['avg_time'] ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render export tab.
     */
    private function render_export_tab(): void {
        $courses = get_posts( array(
            'post_type'      => 'sfls_course',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );
        ?>
        <div class="sfls-export-section">
            <h2><?php esc_html_e( 'Export Reports', 'swiftlms' ); ?></h2>

            <form method="get" action="">
                <input type="hidden" name="page" value="sfls-reports">
                <input type="hidden" name="tab" value="export">
                <?php wp_nonce_field( 'sfls_export', '_wpnonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Report Type', 'swiftlms' ); ?></th>
                        <td>
                            <select name="sfls_export" id="export-type" class="regular-text">
                                <option value="enrollments"><?php esc_html_e( 'Enrollments', 'swiftlms' ); ?></option>
                                <option value="progress"><?php esc_html_e( 'Student Progress', 'swiftlms' ); ?></option>
                                <option value="students"><?php esc_html_e( 'Student Analytics', 'swiftlms' ); ?></option>
                                <option value="quizzes"><?php esc_html_e( 'Quiz Results', 'swiftlms' ); ?></option>
                                <option value="courses"><?php esc_html_e( 'Course Analytics', 'swiftlms' ); ?></option>
                                <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                                    <option value="revenue"><?php esc_html_e( 'Revenue Report', 'swiftlms' ); ?></option>
                                <?php endif; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Date Range', 'swiftlms' ); ?></th>
                        <td>
                            <input type="date" name="start_date" id="start-date" value="<?php echo esc_attr( date( 'Y-m-d', strtotime( '-30 days' ) ) ); ?>">
                            <?php esc_html_e( 'to', 'swiftlms' ); ?>
                            <input type="date" name="end_date" id="end-date" value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Course Filter', 'swiftlms' ); ?></th>
                        <td>
                            <select name="course_id" class="regular-text">
                                <option value=""><?php esc_html_e( 'All Courses', 'swiftlms' ); ?></option>
                                <?php foreach ( $courses as $course ) : ?>
                                    <option value="<?php echo esc_attr( $course->ID ); ?>">
                                        <?php echo esc_html( $course->post_title ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e( 'Download CSV', 'swiftlms' ); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render revenue tab.
     */
    private function render_revenue_tab(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            echo '<p>' . esc_html__( 'WooCommerce is required for revenue reports.', 'swiftlms' ) . '</p>';
            return;
        }

        $period  = isset( $_GET['period'] ) ? sanitize_key( $_GET['period'] ) : '30days';
        $dates   = $this->get_period_dates( $period );
        $revenue = Analytics::get_revenue_analytics( $dates['start'], $dates['end'] );
        ?>
        <div class="sfls-date-filter">
            <select onchange="location.href='<?php echo esc_url( admin_url( 'admin.php?page=sfls-reports&tab=revenue&period=' ) ); ?>' + this.value">
                <option value="7days" <?php selected( $period, '7days' ); ?>><?php esc_html_e( 'Last 7 Days', 'swiftlms' ); ?></option>
                <option value="30days" <?php selected( $period, '30days' ); ?>><?php esc_html_e( 'Last 30 Days', 'swiftlms' ); ?></option>
                <option value="90days" <?php selected( $period, '90days' ); ?>><?php esc_html_e( 'Last 90 Days', 'swiftlms' ); ?></option>
                <option value="year" <?php selected( $period, 'year' ); ?>><?php esc_html_e( 'This Year', 'swiftlms' ); ?></option>
            </select>
        </div>

        <div class="sfls-revenue-stats">
            <div class="sfls-stat-card sfls-revenue-card">
                <div class="sfls-stat-icon" style="background-color: #05966920; color: #059669;">
                    <span class="dashicons dashicons-chart-bar"></span>
                </div>
                <div class="sfls-stat-content">
                    <span class="sfls-stat-value"><?php echo wc_price( $revenue['total_revenue'] ); ?></span>
                    <span class="sfls-stat-label"><?php esc_html_e( 'Total Revenue', 'swiftlms' ); ?></span>
                </div>
            </div>
            <div class="sfls-stat-card">
                <div class="sfls-stat-icon" style="background-color: #4f46e520; color: #4f46e5;">
                    <span class="dashicons dashicons-cart"></span>
                </div>
                <div class="sfls-stat-content">
                    <span class="sfls-stat-value"><?php echo esc_html( $revenue['total_orders'] ); ?></span>
                    <span class="sfls-stat-label"><?php esc_html_e( 'Orders', 'swiftlms' ); ?></span>
                </div>
            </div>
        </div>

        <h3><?php esc_html_e( 'Revenue by Course', 'swiftlms' ); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Course', 'swiftlms' ); ?></th>
                    <th><?php esc_html_e( 'Orders', 'swiftlms' ); ?></th>
                    <th><?php esc_html_e( 'Revenue', 'swiftlms' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $revenue['by_course'] ) ) : ?>
                    <tr>
                        <td colspan="3"><?php esc_html_e( 'No revenue data available.', 'swiftlms' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $revenue['by_course'] as $course ) : ?>
                        <tr>
                            <td><?php echo esc_html( $course['course_title'] ); ?></td>
                            <td><?php echo esc_html( $course['orders'] ); ?></td>
                            <td><?php echo wc_price( $course['revenue'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Handle export request.
     */
    public function handle_export(): void {
        if ( ! isset( $_GET['sfls_export'] ) || ! isset( $_GET['_wpnonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'sfls_export' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $type    = sanitize_key( $_GET['sfls_export'] );
        $filters = array(
            'start_date' => isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : '',
            'end_date'   => isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : '',
            'course_id'  => isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0,
        );

        Exporter::export_csv( $type, $filters );
    }

    /**
     * Register REST routes.
     */
    public function register_rest_routes(): void {
        register_rest_route(
            'swiftlms/v1',
            '/reports/overview',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_overview_api' ),
                'permission_callback' => array( $this, 'check_permission' ),
            )
        );

        register_rest_route(
            'swiftlms/v1',
            '/reports/courses',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_courses_api' ),
                'permission_callback' => array( $this, 'check_permission' ),
            )
        );

        register_rest_route(
            'swiftlms/v1',
            '/reports/students',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_students_api' ),
                'permission_callback' => array( $this, 'check_permission' ),
            )
        );
    }

    /**
     * Check API permission.
     *
     * @return bool
     */
    public function check_permission(): bool {
        return current_user_can( 'manage_options' );
    }

    /**
     * Get overview API.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function get_overview_api( \WP_REST_Request $request ): \WP_REST_Response {
        $start = $request->get_param( 'start_date' ) ?: date( 'Y-m-d', strtotime( '-30 days' ) );
        $end   = $request->get_param( 'end_date' ) ?: date( 'Y-m-d' );

        return rest_ensure_response( Analytics::get_overview_stats( $start, $end ) );
    }

    /**
     * Get courses API.
     *
     * @return \WP_REST_Response
     */
    public function get_courses_api(): \WP_REST_Response {
        return rest_ensure_response( Analytics::get_course_analytics() );
    }

    /**
     * Get students API.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function get_students_api( \WP_REST_Request $request ): \WP_REST_Response {
        $limit = $request->get_param( 'limit' ) ?: 50;
        return rest_ensure_response( Analytics::get_student_analytics( 0, $limit ) );
    }

    /**
     * Add dashboard widget.
     */
    public function add_dashboard_widget(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        wp_add_dashboard_widget(
            'sfls_dashboard_stats',
            __( 'SwiftLMS Overview', 'swiftlms' ),
            array( $this, 'render_dashboard_widget' )
        );
    }

    /**
     * Render dashboard widget.
     */
    public function render_dashboard_widget(): void {
        $stats = Analytics::get_overview_stats(
            date( 'Y-m-d', strtotime( '-30 days' ) ),
            date( 'Y-m-d' )
        );
        ?>
        <div class="sfls-dashboard-widget">
            <div class="sfls-widget-stats">
                <div class="sfls-widget-stat">
                    <span class="value"><?php echo esc_html( $stats['total_students'] ); ?></span>
                    <span class="label"><?php esc_html_e( 'Students', 'swiftlms' ); ?></span>
                </div>
                <div class="sfls-widget-stat">
                    <span class="value"><?php echo esc_html( $stats['new_enrollments'] ); ?></span>
                    <span class="label"><?php esc_html_e( 'Enrollments (30d)', 'swiftlms' ); ?></span>
                </div>
                <div class="sfls-widget-stat">
                    <span class="value"><?php echo esc_html( $stats['completions'] ); ?></span>
                    <span class="label"><?php esc_html_e( 'Completions (30d)', 'swiftlms' ); ?></span>
                </div>
                <div class="sfls-widget-stat">
                    <span class="value"><?php echo esc_html( $stats['avg_completion_rate'] ); ?>%</span>
                    <span class="label"><?php esc_html_e( 'Completion Rate', 'swiftlms' ); ?></span>
                </div>
            </div>
            <p class="sfls-widget-footer">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=sfls-reports' ) ); ?>">
                    <?php esc_html_e( 'View Full Reports →', 'swiftlms' ); ?>
                </a>
            </p>
        </div>
        <style>
            .sfls-widget-stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 15px; }
            .sfls-widget-stat { text-align: center; padding: 10px; background: #f5f5f5; border-radius: 6px; }
            .sfls-widget-stat .value { display: block; font-size: 24px; font-weight: 700; color: #1d2327; }
            .sfls-widget-stat .label { font-size: 12px; color: #646970; }
            .sfls-widget-footer { margin: 0; text-align: right; }
        </style>
        <?php
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current page hook.
     */
    public function enqueue_admin_assets( string $hook ): void {
        if ( 'swiftlms_page_sfls-reports' !== $hook ) {
            return;
        }

        // Chart.js
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            array(),
            '4.4.1',
            true
        );

        wp_enqueue_style(
            'sfls-reports',
            SWIFTLMS_PLUGIN_URL . 'includes/modules/reporting/assets/css/reports.css',
            array(),
            SWIFTLMS_VERSION
        );

        wp_enqueue_script(
            'sfls-reports',
            SWIFTLMS_PLUGIN_URL . 'includes/modules/reporting/assets/js/reports.js',
            array( 'jquery', 'chartjs' ),
            SWIFTLMS_VERSION,
            true
        );
    }

    /**
     * Get period dates.
     *
     * @param string $period   Period key.
     * @param bool   $previous Get previous period.
     * @return array
     */
    private function get_period_dates( string $period, bool $previous = false ): array {
        $end   = date( 'Y-m-d' );
        $start = $end;

        switch ( $period ) {
            case '7days':
                $start = date( 'Y-m-d', strtotime( '-7 days' ) );
                break;
            case '30days':
                $start = date( 'Y-m-d', strtotime( '-30 days' ) );
                break;
            case '90days':
                $start = date( 'Y-m-d', strtotime( '-90 days' ) );
                break;
            case 'year':
                $start = date( 'Y-01-01' );
                break;
            case 'all':
                $start = '2000-01-01';
                break;
        }

        if ( $previous ) {
            $days  = ( strtotime( $end ) - strtotime( $start ) ) / 86400;
            $end   = date( 'Y-m-d', strtotime( $start ) - 86400 );
            $start = date( 'Y-m-d', strtotime( "-{$days} days", strtotime( $end ) ) );
        }

        return array( 'start' => $start, 'end' => $end );
    }

    /**
     * Get chart data.
     *
     * @param string $start_date Start date.
     * @param string $end_date   End date.
     * @return array
     */
    private function get_chart_data( string $start_date, string $end_date ): array {
        $days     = ( strtotime( $end_date ) - strtotime( $start_date ) ) / 86400;
        $interval = $days > 60 ? 'week' : 'day';

        $enrollments = Analytics::get_enrollment_trends( $start_date, $end_date, $interval );
        $completions = Analytics::get_completion_trends( $start_date, $end_date, $interval );

        $labels      = array();
        $enroll_data = array();
        $course_data = array();
        $lesson_data = array();

        foreach ( $enrollments as $row ) {
            $labels[]      = $row['period'];
            $enroll_data[] = (int) $row['enrollments'];
        }

        foreach ( $completions as $row ) {
            $course_data[] = (int) $row['courses'];
            $lesson_data[] = (int) $row['lessons'];
        }

        return array(
            'labels'      => $labels,
            'enrollments' => $enroll_data,
            'courses'     => $course_data,
            'lessons'     => $lesson_data,
        );
    }

    /**
     * Format duration.
     *
     * @param int $seconds Seconds.
     * @return string
     */
    private function format_duration( ?int $seconds ): string {
        if ( ! $seconds ) {
            return '0m';
        }

        $hours   = floor( $seconds / 3600 );
        $minutes = floor( ( $seconds % 3600 ) / 60 );

        if ( $hours > 0 ) {
            return sprintf( '%dh %dm', $hours, $minutes );
        }

        return sprintf( '%dm', $minutes );
    }
}
