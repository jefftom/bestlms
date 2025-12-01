<?php
/**
 * Instructor Dashboard - Analytics View
 *
 * @package SwiftLMS\Modules\InstructorDashboard
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap sfls-instructor-wrap">
    <h1><?php esc_html_e( 'Analytics', 'swiftlms' ); ?></h1>

    <!-- Period Filter -->
    <div class="sfls-analytics-filters">
        <form method="get" class="sfls-period-form">
            <input type="hidden" name="page" value="sfls-instructor-analytics">
            <select name="period" onchange="this.form.submit()">
                <option value="week" <?php selected( $period, 'week' ); ?>><?php esc_html_e( 'Last 7 Days', 'swiftlms' ); ?></option>
                <option value="month" <?php selected( $period, 'month' ); ?>><?php esc_html_e( 'This Month', 'swiftlms' ); ?></option>
                <option value="year" <?php selected( $period, 'year' ); ?>><?php esc_html_e( 'This Year', 'swiftlms' ); ?></option>
                <option value="all" <?php selected( $period, 'all' ); ?>><?php esc_html_e( 'All Time', 'swiftlms' ); ?></option>
            </select>
        </form>
    </div>

    <!-- Stats Overview -->
    <div class="sfls-analytics-stats">
        <div class="sfls-stat-card">
            <div class="sfls-stat-icon sfls-icon-primary">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="sfls-stat-content">
                <span class="sfls-stat-value"><?php echo esc_html( $stats['total_enrollments'] ); ?></span>
                <span class="sfls-stat-label"><?php esc_html_e( 'Total Enrollments', 'swiftlms' ); ?></span>
            </div>
        </div>

        <div class="sfls-stat-card">
            <div class="sfls-stat-icon sfls-icon-success">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="sfls-stat-content">
                <span class="sfls-stat-value"><?php echo esc_html( $stats['completions'] ); ?></span>
                <span class="sfls-stat-label"><?php esc_html_e( 'Completions', 'swiftlms' ); ?></span>
            </div>
        </div>

        <div class="sfls-stat-card">
            <div class="sfls-stat-icon sfls-icon-info">
                <span class="dashicons dashicons-chart-line"></span>
            </div>
            <div class="sfls-stat-content">
                <span class="sfls-stat-value"><?php echo esc_html( $stats['completion_rate'] ); ?>%</span>
                <span class="sfls-stat-label"><?php esc_html_e( 'Completion Rate', 'swiftlms' ); ?></span>
            </div>
        </div>

        <div class="sfls-stat-card">
            <div class="sfls-stat-icon sfls-icon-warning">
                <span class="dashicons dashicons-performance"></span>
            </div>
            <div class="sfls-stat-content">
                <span class="sfls-stat-value"><?php echo esc_html( $stats['avg_progress'] ); ?>%</span>
                <span class="sfls-stat-label"><?php esc_html_e( 'Avg. Progress', 'swiftlms' ); ?></span>
            </div>
        </div>

        <div class="sfls-stat-card">
            <div class="sfls-stat-icon sfls-icon-quiz">
                <span class="dashicons dashicons-welcome-write-blog"></span>
            </div>
            <div class="sfls-stat-content">
                <span class="sfls-stat-value"><?php echo esc_html( $stats['avg_quiz_score'] ); ?>%</span>
                <span class="sfls-stat-label"><?php esc_html_e( 'Avg. Quiz Score', 'swiftlms' ); ?></span>
            </div>
        </div>

        <div class="sfls-stat-card">
            <div class="sfls-stat-icon">
                <span class="dashicons dashicons-star-filled"></span>
            </div>
            <div class="sfls-stat-content">
                <span class="sfls-stat-value"><?php echo esc_html( $stats['avg_rating'] ); ?>/5</span>
                <span class="sfls-stat-label"><?php esc_html_e( 'Avg. Rating', 'swiftlms' ); ?> (<?php echo esc_html( $stats['total_reviews'] ); ?>)</span>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="sfls-analytics-charts">
        <div class="sfls-chart-card sfls-chart-wide">
            <h3><?php esc_html_e( 'Enrollment Trends (Last 30 Days)', 'swiftlms' ); ?></h3>
            <canvas id="enrollmentTrendChart" height="300"></canvas>
        </div>
    </div>

    <!-- Course Performance Table -->
    <div class="sfls-card">
        <div class="sfls-card-header">
            <h3><?php esc_html_e( 'Course Performance', 'swiftlms' ); ?></h3>
        </div>
        <div class="sfls-card-body">
            <?php if ( empty( $course_performance ) ) : ?>
                <p class="sfls-no-data"><?php esc_html_e( 'No course data available.', 'swiftlms' ); ?></p>
            <?php else : ?>
                <table class="sfls-table sfls-performance-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Course', 'swiftlms' ); ?></th>
                            <th><?php esc_html_e( 'Enrollments', 'swiftlms' ); ?></th>
                            <th><?php esc_html_e( 'Completions', 'swiftlms' ); ?></th>
                            <th><?php esc_html_e( 'Completion Rate', 'swiftlms' ); ?></th>
                            <th><?php esc_html_e( 'Avg. Progress', 'swiftlms' ); ?></th>
                            <th><?php esc_html_e( 'Rating', 'swiftlms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $course_performance as $course ) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $course['title'] ); ?></strong>
                                <?php if ( 'draft' === $course['status'] ) : ?>
                                    <span class="sfls-badge sfls-badge-draft"><?php esc_html_e( 'Draft', 'swiftlms' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $course['enrollments'] ); ?></td>
                            <td><?php echo esc_html( $course['completions'] ); ?></td>
                            <td>
                                <div class="sfls-progress-cell">
                                    <div class="sfls-progress-bar-small">
                                        <div class="sfls-bar" style="width: <?php echo esc_attr( $course['completion_rate'] ); ?>%"></div>
                                    </div>
                                    <span><?php echo esc_html( $course['completion_rate'] ); ?>%</span>
                                </div>
                            </td>
                            <td>
                                <div class="sfls-progress-cell">
                                    <div class="sfls-progress-bar-small sfls-bar-info">
                                        <div class="sfls-bar" style="width: <?php echo esc_attr( $course['avg_progress'] ); ?>%"></div>
                                    </div>
                                    <span><?php echo esc_html( $course['avg_progress'] ); ?>%</span>
                                </div>
                            </td>
                            <td>
                                <?php if ( $course['reviews'] > 0 ) : ?>
                                    <div class="sfls-rating-cell">
                                        <span class="dashicons dashicons-star-filled"></span>
                                        <?php echo esc_html( $course['rating'] ); ?>
                                        <span class="sfls-review-count">(<?php echo esc_html( $course['reviews'] ); ?>)</span>
                                    </div>
                                <?php else : ?>
                                    <span class="sfls-no-rating"><?php esc_html_e( 'No reviews', 'swiftlms' ); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Additional Metrics -->
    <div class="sfls-analytics-row">
        <div class="sfls-card sfls-half">
            <div class="sfls-card-header">
                <h3><?php esc_html_e( 'Quiz Statistics', 'swiftlms' ); ?></h3>
            </div>
            <div class="sfls-card-body">
                <div class="sfls-metrics-grid">
                    <div class="sfls-metric">
                        <span class="sfls-metric-value"><?php echo esc_html( $stats['total_quizzes'] ); ?></span>
                        <span class="sfls-metric-label"><?php esc_html_e( 'Total Quizzes', 'swiftlms' ); ?></span>
                    </div>
                    <div class="sfls-metric">
                        <span class="sfls-metric-value"><?php echo esc_html( $stats['quiz_attempts'] ); ?></span>
                        <span class="sfls-metric-label"><?php esc_html_e( 'Total Attempts', 'swiftlms' ); ?></span>
                    </div>
                    <div class="sfls-metric">
                        <span class="sfls-metric-value"><?php echo esc_html( $stats['avg_quiz_score'] ); ?>%</span>
                        <span class="sfls-metric-label"><?php esc_html_e( 'Avg. Score', 'swiftlms' ); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="sfls-card sfls-half">
            <div class="sfls-card-header">
                <h3><?php esc_html_e( 'Content Overview', 'swiftlms' ); ?></h3>
            </div>
            <div class="sfls-card-body">
                <div class="sfls-metrics-grid">
                    <div class="sfls-metric">
                        <span class="sfls-metric-value"><?php echo esc_html( $stats['total_courses'] ); ?></span>
                        <span class="sfls-metric-label"><?php esc_html_e( 'Courses', 'swiftlms' ); ?></span>
                    </div>
                    <div class="sfls-metric">
                        <span class="sfls-metric-value"><?php echo esc_html( $stats['total_lessons'] ); ?></span>
                        <span class="sfls-metric-label"><?php esc_html_e( 'Lessons', 'swiftlms' ); ?></span>
                    </div>
                    <div class="sfls-metric">
                        <span class="sfls-metric-value"><?php echo esc_html( $stats['active_students'] ); ?></span>
                        <span class="sfls-metric-label"><?php esc_html_e( 'Active Students', 'swiftlms' ); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Enrollment Trend Chart
    var trendData = <?php echo wp_json_encode( $trends ); ?>;
    var ctx = document.getElementById('enrollmentTrendChart');

    if (ctx && trendData.length > 0) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: trendData.map(function(item) {
                    return new Date(item.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                }),
                datasets: [
                    {
                        label: '<?php esc_html_e( 'Enrollments', 'swiftlms' ); ?>',
                        data: trendData.map(function(item) { return parseInt(item.enrollments); }),
                        backgroundColor: 'rgba(79, 70, 229, 0.8)',
                        borderRadius: 4
                    },
                    {
                        label: '<?php esc_html_e( 'Completions', 'swiftlms' ); ?>',
                        data: trendData.map(function(item) { return parseInt(item.completions); }),
                        backgroundColor: 'rgba(5, 150, 105, 0.8)',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }
});
</script>
