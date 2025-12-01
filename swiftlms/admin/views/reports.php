<?php
/**
 * Reports view.
 *
 * @package SwiftLMS\Admin\Views
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap swiftlms-admin-page swiftlms-reports">
    <h1><?php esc_html_e( 'Reports', 'swiftlms' ); ?></h1>

    <div class="swiftlms-reports-nav">
        <a href="#overview" class="swiftlms-report-tab active" data-tab="overview">
            <?php esc_html_e( 'Overview', 'swiftlms' ); ?>
        </a>
        <a href="#courses" class="swiftlms-report-tab" data-tab="courses">
            <?php esc_html_e( 'Courses', 'swiftlms' ); ?>
        </a>
        <a href="#students" class="swiftlms-report-tab" data-tab="students">
            <?php esc_html_e( 'Students', 'swiftlms' ); ?>
        </a>
    </div>

    <div class="swiftlms-reports-content">
        <!-- Overview Tab -->
        <div id="swiftlms-report-overview" class="swiftlms-report-panel active">
            <div class="swiftlms-date-range">
                <label>
                    <?php esc_html_e( 'Date Range:', 'swiftlms' ); ?>
                    <select id="swiftlms-date-range">
                        <option value="7"><?php esc_html_e( 'Last 7 days', 'swiftlms' ); ?></option>
                        <option value="30" selected><?php esc_html_e( 'Last 30 days', 'swiftlms' ); ?></option>
                        <option value="90"><?php esc_html_e( 'Last 90 days', 'swiftlms' ); ?></option>
                        <option value="365"><?php esc_html_e( 'Last year', 'swiftlms' ); ?></option>
                    </select>
                </label>
            </div>

            <div class="swiftlms-overview-stats">
                <div class="swiftlms-stat-box">
                    <h3><?php esc_html_e( 'New Enrollments', 'swiftlms' ); ?></h3>
                    <span class="swiftlms-stat-number" id="stat-new-enrollments">-</span>
                </div>
                <div class="swiftlms-stat-box">
                    <h3><?php esc_html_e( 'Course Completions', 'swiftlms' ); ?></h3>
                    <span class="swiftlms-stat-number" id="stat-completions">-</span>
                </div>
                <div class="swiftlms-stat-box">
                    <h3><?php esc_html_e( 'Lessons Completed', 'swiftlms' ); ?></h3>
                    <span class="swiftlms-stat-number" id="stat-lessons">-</span>
                </div>
                <div class="swiftlms-stat-box">
                    <h3><?php esc_html_e( 'Active Users', 'swiftlms' ); ?></h3>
                    <span class="swiftlms-stat-number" id="stat-active-users">-</span>
                </div>
            </div>

            <div class="swiftlms-chart-container">
                <h3><?php esc_html_e( 'Enrollment Trend', 'swiftlms' ); ?></h3>
                <canvas id="swiftlms-enrollment-chart"></canvas>
            </div>
        </div>

        <!-- Courses Tab -->
        <div id="swiftlms-report-courses" class="swiftlms-report-panel">
            <h2><?php esc_html_e( 'Course Performance', 'swiftlms' ); ?></h2>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Course', 'swiftlms' ); ?></th>
                        <th><?php esc_html_e( 'Enrollments', 'swiftlms' ); ?></th>
                        <th><?php esc_html_e( 'Completions', 'swiftlms' ); ?></th>
                        <th><?php esc_html_e( 'Completion Rate', 'swiftlms' ); ?></th>
                        <th><?php esc_html_e( 'Avg. Progress', 'swiftlms' ); ?></th>
                    </tr>
                </thead>
                <tbody id="swiftlms-course-stats">
                    <tr>
                        <td colspan="5"><?php esc_html_e( 'Loading...', 'swiftlms' ); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Students Tab -->
        <div id="swiftlms-report-students" class="swiftlms-report-panel">
            <h2><?php esc_html_e( 'Student Activity', 'swiftlms' ); ?></h2>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Student', 'swiftlms' ); ?></th>
                        <th><?php esc_html_e( 'Courses Enrolled', 'swiftlms' ); ?></th>
                        <th><?php esc_html_e( 'Courses Completed', 'swiftlms' ); ?></th>
                        <th><?php esc_html_e( 'Last Activity', 'swiftlms' ); ?></th>
                    </tr>
                </thead>
                <tbody id="swiftlms-student-stats">
                    <tr>
                        <td colspan="4"><?php esc_html_e( 'Loading...', 'swiftlms' ); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
