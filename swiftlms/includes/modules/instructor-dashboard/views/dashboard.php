<?php
/**
 * Instructor Dashboard - Main Dashboard View
 *
 * @package SwiftLMS\Modules\InstructorDashboard
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap sfls-instructor-wrap">
    <h1><?php esc_html_e( 'Instructor Dashboard', 'swiftlms' ); ?></h1>

    <div class="sfls-instructor-welcome">
        <div class="sfls-welcome-avatar">
            <?php echo get_avatar( $instructor_id, 60 ); ?>
        </div>
        <div class="sfls-welcome-text">
            <h2><?php printf( esc_html__( 'Welcome back, %s!', 'swiftlms' ), esc_html( wp_get_current_user()->display_name ) ); ?></h2>
            <p><?php esc_html_e( 'Here\'s an overview of your courses and students this month.', 'swiftlms' ); ?></p>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="sfls-instructor-stats">
        <div class="sfls-stat-card sfls-stat-primary">
            <div class="sfls-stat-icon">
                <span class="dashicons dashicons-welcome-learn-more"></span>
            </div>
            <div class="sfls-stat-content">
                <span class="sfls-stat-value"><?php echo esc_html( $stats['total_courses'] ); ?></span>
                <span class="sfls-stat-label"><?php esc_html_e( 'Courses', 'swiftlms' ); ?></span>
            </div>
        </div>

        <div class="sfls-stat-card sfls-stat-info">
            <div class="sfls-stat-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="sfls-stat-content">
                <span class="sfls-stat-value"><?php echo esc_html( $stats['active_students'] ); ?></span>
                <span class="sfls-stat-label"><?php esc_html_e( 'Active Students', 'swiftlms' ); ?></span>
            </div>
        </div>

        <div class="sfls-stat-card sfls-stat-success">
            <div class="sfls-stat-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="sfls-stat-content">
                <span class="sfls-stat-value"><?php echo esc_html( $stats['completions'] ); ?></span>
                <span class="sfls-stat-label"><?php esc_html_e( 'Completions', 'swiftlms' ); ?></span>
            </div>
        </div>

        <div class="sfls-stat-card sfls-stat-warning">
            <div class="sfls-stat-icon">
                <span class="dashicons dashicons-edit"></span>
            </div>
            <div class="sfls-stat-content">
                <span class="sfls-stat-value"><?php echo esc_html( $stats['pending_assignments'] ); ?></span>
                <span class="sfls-stat-label"><?php esc_html_e( 'Pending Grades', 'swiftlms' ); ?></span>
            </div>
        </div>

        <div class="sfls-stat-card sfls-stat-revenue">
            <div class="sfls-stat-icon">
                <span class="dashicons dashicons-chart-area"></span>
            </div>
            <div class="sfls-stat-content">
                <span class="sfls-stat-value"><?php echo wc_price( $earnings['total_earnings'] ); ?></span>
                <span class="sfls-stat-label"><?php esc_html_e( 'Earnings (Month)', 'swiftlms' ); ?></span>
            </div>
        </div>

        <div class="sfls-stat-card">
            <div class="sfls-stat-icon">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <div class="sfls-stat-content">
                <span class="sfls-stat-value"><?php echo wc_price( $earnings['balance'] ); ?></span>
                <span class="sfls-stat-label"><?php esc_html_e( 'Available Balance', 'swiftlms' ); ?></span>
            </div>
        </div>
    </div>

    <!-- Two Column Layout -->
    <div class="sfls-instructor-columns">
        <!-- Left Column -->
        <div class="sfls-instructor-main">
            <!-- Pending Submissions -->
            <?php if ( ! empty( $pending['submissions'] ) ) : ?>
            <div class="sfls-card">
                <div class="sfls-card-header">
                    <h3><?php esc_html_e( 'Pending Submissions', 'swiftlms' ); ?></h3>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=sfls-instructor-submissions' ) ); ?>" class="sfls-view-all">
                        <?php esc_html_e( 'View All', 'swiftlms' ); ?> &rarr;
                    </a>
                </div>
                <div class="sfls-card-body">
                    <table class="sfls-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Student', 'swiftlms' ); ?></th>
                                <th><?php esc_html_e( 'Assignment', 'swiftlms' ); ?></th>
                                <th><?php esc_html_e( 'Submitted', 'swiftlms' ); ?></th>
                                <th><?php esc_html_e( 'Action', 'swiftlms' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $pending['submissions'] as $submission ) : ?>
                            <tr>
                                <td>
                                    <div class="sfls-user-cell">
                                        <img src="<?php echo esc_url( $submission['student_avatar'] ); ?>" alt="" class="sfls-avatar-small">
                                        <?php echo esc_html( $submission['student_name'] ); ?>
                                    </div>
                                </td>
                                <td><?php echo esc_html( $submission['assignment_title'] ); ?></td>
                                <td><?php echo esc_html( human_time_diff( strtotime( $submission['submitted_at'] ), current_time( 'timestamp' ) ) ); ?> ago</td>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=sfls-instructor-submissions&submission_id=' . $submission['id'] ) ); ?>" class="button button-small">
                                        <?php esc_html_e( 'Grade', 'swiftlms' ); ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Stats -->
            <div class="sfls-card">
                <div class="sfls-card-header">
                    <h3><?php esc_html_e( 'Performance Overview', 'swiftlms' ); ?></h3>
                </div>
                <div class="sfls-card-body">
                    <div class="sfls-performance-grid">
                        <div class="sfls-performance-item">
                            <div class="sfls-performance-label"><?php esc_html_e( 'Completion Rate', 'swiftlms' ); ?></div>
                            <div class="sfls-performance-bar">
                                <div class="sfls-bar-fill" style="width: <?php echo esc_attr( $stats['completion_rate'] ); ?>%"></div>
                            </div>
                            <div class="sfls-performance-value"><?php echo esc_html( $stats['completion_rate'] ); ?>%</div>
                        </div>
                        <div class="sfls-performance-item">
                            <div class="sfls-performance-label"><?php esc_html_e( 'Avg. Progress', 'swiftlms' ); ?></div>
                            <div class="sfls-performance-bar">
                                <div class="sfls-bar-fill sfls-bar-info" style="width: <?php echo esc_attr( $stats['avg_progress'] ); ?>%"></div>
                            </div>
                            <div class="sfls-performance-value"><?php echo esc_html( $stats['avg_progress'] ); ?>%</div>
                        </div>
                        <div class="sfls-performance-item">
                            <div class="sfls-performance-label"><?php esc_html_e( 'Avg. Quiz Score', 'swiftlms' ); ?></div>
                            <div class="sfls-performance-bar">
                                <div class="sfls-bar-fill sfls-bar-warning" style="width: <?php echo esc_attr( $stats['avg_quiz_score'] ); ?>%"></div>
                            </div>
                            <div class="sfls-performance-value"><?php echo esc_html( $stats['avg_quiz_score'] ); ?>%</div>
                        </div>
                        <div class="sfls-performance-item">
                            <div class="sfls-performance-label"><?php esc_html_e( 'Avg. Rating', 'swiftlms' ); ?></div>
                            <div class="sfls-rating-stars">
                                <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                                    <span class="dashicons dashicons-star-<?php echo $i <= round( $stats['avg_rating'] ) ? 'filled' : 'empty'; ?>"></span>
                                <?php endfor; ?>
                            </div>
                            <div class="sfls-performance-value"><?php echo esc_html( $stats['avg_rating'] ); ?>/5</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="sfls-instructor-sidebar">
            <!-- Recent Activity -->
            <div class="sfls-card">
                <div class="sfls-card-header">
                    <h3><?php esc_html_e( 'Recent Activity', 'swiftlms' ); ?></h3>
                </div>
                <div class="sfls-card-body sfls-activity-feed">
                    <?php if ( ! empty( $recent_activity ) ) : ?>
                        <?php foreach ( $recent_activity as $activity ) : ?>
                        <div class="sfls-activity-item">
                            <img src="<?php echo esc_url( $activity['avatar'] ); ?>" alt="" class="sfls-activity-avatar">
                            <div class="sfls-activity-content">
                                <?php
                                switch ( $activity['type'] ) {
                                    case 'enrollment':
                                        printf(
                                            esc_html__( '%s enrolled in %s', 'swiftlms' ),
                                            '<strong>' . esc_html( $activity['display_name'] ) . '</strong>',
                                            '<em>' . esc_html( $activity['course_title'] ) . '</em>'
                                        );
                                        break;
                                    case 'completion':
                                        printf(
                                            esc_html__( '%s completed %s', 'swiftlms' ),
                                            '<strong>' . esc_html( $activity['display_name'] ) . '</strong>',
                                            '<em>' . esc_html( $activity['course_title'] ) . '</em>'
                                        );
                                        break;
                                    case 'submission':
                                        printf(
                                            esc_html__( '%s submitted %s', 'swiftlms' ),
                                            '<strong>' . esc_html( $activity['display_name'] ) . '</strong>',
                                            '<em>' . esc_html( $activity['assignment_title'] ) . '</em>'
                                        );
                                        break;
                                    case 'review':
                                        printf(
                                            esc_html__( '%s left a %s-star review on %s', 'swiftlms' ),
                                            '<strong>' . esc_html( $activity['display_name'] ) . '</strong>',
                                            esc_html( $activity['rating'] ),
                                            '<em>' . esc_html( $activity['course_title'] ) . '</em>'
                                        );
                                        break;
                                }
                                ?>
                                <span class="sfls-activity-time"><?php echo esc_html( $activity['time_ago'] ); ?> ago</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p class="sfls-no-data"><?php esc_html_e( 'No recent activity.', 'swiftlms' ); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="sfls-card">
                <div class="sfls-card-header">
                    <h3><?php esc_html_e( 'Quick Actions', 'swiftlms' ); ?></h3>
                </div>
                <div class="sfls-card-body sfls-quick-actions">
                    <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=sfls_course' ) ); ?>" class="sfls-action-btn">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e( 'New Course', 'swiftlms' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=sfls-instructor-students' ) ); ?>" class="sfls-action-btn">
                        <span class="dashicons dashicons-groups"></span>
                        <?php esc_html_e( 'View Students', 'swiftlms' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=sfls-instructor-earnings' ) ); ?>" class="sfls-action-btn">
                        <span class="dashicons dashicons-chart-bar"></span>
                        <?php esc_html_e( 'View Earnings', 'swiftlms' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=sfls-instructor-analytics' ) ); ?>" class="sfls-action-btn">
                        <span class="dashicons dashicons-analytics"></span>
                        <?php esc_html_e( 'Analytics', 'swiftlms' ); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
