<?php
/**
 * Dashboard view.
 *
 * @package SwiftLMS\Admin\Views
 * @var array $stats Dashboard statistics.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap swiftlms-admin-page swiftlms-dashboard">
    <h1><?php esc_html_e( 'SwiftLMS Dashboard', 'swiftlms' ); ?></h1>

    <div class="swiftlms-dashboard-widgets">
        <!-- Stats Cards -->
        <div class="swiftlms-stats-grid">
            <div class="swiftlms-stat-card">
                <div class="swiftlms-stat-icon dashicons dashicons-welcome-learn-more"></div>
                <div class="swiftlms-stat-content">
                    <span class="swiftlms-stat-value"><?php echo esc_html( $stats['total_courses'] ); ?></span>
                    <span class="swiftlms-stat-label"><?php esc_html_e( 'Published Courses', 'swiftlms' ); ?></span>
                </div>
            </div>

            <div class="swiftlms-stat-card">
                <div class="swiftlms-stat-icon dashicons dashicons-media-document"></div>
                <div class="swiftlms-stat-content">
                    <span class="swiftlms-stat-value"><?php echo esc_html( $stats['total_lessons'] ); ?></span>
                    <span class="swiftlms-stat-label"><?php esc_html_e( 'Lessons', 'swiftlms' ); ?></span>
                </div>
            </div>

            <div class="swiftlms-stat-card">
                <div class="swiftlms-stat-icon dashicons dashicons-groups"></div>
                <div class="swiftlms-stat-content">
                    <span class="swiftlms-stat-value"><?php echo esc_html( $stats['active_enrollments'] ); ?></span>
                    <span class="swiftlms-stat-label"><?php esc_html_e( 'Active Enrollments', 'swiftlms' ); ?></span>
                </div>
            </div>

            <div class="swiftlms-stat-card">
                <div class="swiftlms-stat-icon dashicons dashicons-yes-alt"></div>
                <div class="swiftlms-stat-content">
                    <span class="swiftlms-stat-value"><?php echo esc_html( $stats['completed_courses'] ); ?></span>
                    <span class="swiftlms-stat-label"><?php esc_html_e( 'Completions', 'swiftlms' ); ?></span>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="swiftlms-quick-actions">
            <h2><?php esc_html_e( 'Quick Actions', 'swiftlms' ); ?></h2>
            <div class="swiftlms-action-buttons">
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=sfls_course' ) ); ?>" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e( 'Create Course', 'swiftlms' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=sfls_lesson' ) ); ?>" class="button">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e( 'Add Lesson', 'swiftlms' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=swiftlms-enrollments' ) ); ?>" class="button">
                    <span class="dashicons dashicons-admin-users"></span>
                    <?php esc_html_e( 'Manage Enrollments', 'swiftlms' ); ?>
                </a>
            </div>
        </div>

        <!-- Recent Enrollments -->
        <div class="swiftlms-recent-enrollments">
            <h2><?php esc_html_e( 'Recent Enrollments', 'swiftlms' ); ?></h2>
            <?php if ( empty( $stats['recent_enrollments'] ) ) : ?>
                <p class="swiftlms-no-data"><?php esc_html_e( 'No enrollments yet.', 'swiftlms' ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Student', 'swiftlms' ); ?></th>
                            <th><?php esc_html_e( 'Course', 'swiftlms' ); ?></th>
                            <th><?php esc_html_e( 'Progress', 'swiftlms' ); ?></th>
                            <th><?php esc_html_e( 'Enrolled', 'swiftlms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $stats['recent_enrollments'] as $enrollment ) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $enrollment->display_name ); ?></strong>
                                    <br>
                                    <small><?php echo esc_html( $enrollment->user_email ); ?></small>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url( get_edit_post_link( $enrollment->course_id ) ); ?>">
                                        <?php echo esc_html( $enrollment->course_title ); ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="swiftlms-progress-bar">
                                        <div class="swiftlms-progress-fill" style="width: <?php echo esc_attr( $enrollment->progress_percent ); ?>%;"></div>
                                    </div>
                                    <span class="swiftlms-progress-text"><?php echo esc_html( round( $enrollment->progress_percent, 1 ) ); ?>%</span>
                                </td>
                                <td>
                                    <?php echo esc_html( human_time_diff( strtotime( $enrollment->enrolled_at ), current_time( 'timestamp' ) ) ); ?>
                                    <?php esc_html_e( 'ago', 'swiftlms' ); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- System Info -->
        <div class="swiftlms-system-info">
            <h2><?php esc_html_e( 'System Information', 'swiftlms' ); ?></h2>
            <table class="widefat">
                <tbody>
                    <tr>
                        <td><?php esc_html_e( 'SwiftLMS Version', 'swiftlms' ); ?></td>
                        <td><?php echo esc_html( SWIFTLMS_VERSION ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'WordPress Version', 'swiftlms' ); ?></td>
                        <td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'PHP Version', 'swiftlms' ); ?></td>
                        <td><?php echo esc_html( PHP_VERSION ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'Database Version', 'swiftlms' ); ?></td>
                        <td><?php echo esc_html( get_option( 'swiftlms_db_version', 'N/A' ) ); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
