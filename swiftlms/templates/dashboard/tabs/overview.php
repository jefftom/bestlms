<?php
/**
 * Dashboard Overview Tab
 *
 * @package SwiftLMS
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="sfls-dashboard-overview">
    <h2 class="sfls-dashboard-title"><?php esc_html_e( 'Welcome back,', 'swiftlms' ); ?> <?php echo esc_html( $data['user']['first_name'] ); ?>!</h2>

    <div class="sfls-stats-grid">
        <div class="sfls-stat-card">
            <div class="sfls-stat-icon">
                <span class="dashicons dashicons-welcome-learn-more"></span>
            </div>
            <div class="sfls-stat-content">
                <span class="sfls-stat-value"><?php echo esc_html( $data['stats']['enrolled_courses'] ); ?></span>
                <span class="sfls-stat-label"><?php esc_html_e( 'Enrolled Courses', 'swiftlms' ); ?></span>
            </div>
        </div>

        <div class="sfls-stat-card">
            <div class="sfls-stat-icon sfls-stat-completed">
                <span class="dashicons dashicons-awards"></span>
            </div>
            <div class="sfls-stat-content">
                <span class="sfls-stat-value"><?php echo esc_html( $data['stats']['completed_courses'] ); ?></span>
                <span class="sfls-stat-label"><?php esc_html_e( 'Completed Courses', 'swiftlms' ); ?></span>
            </div>
        </div>

        <div class="sfls-stat-card">
            <div class="sfls-stat-icon sfls-stat-lessons">
                <span class="dashicons dashicons-media-text"></span>
            </div>
            <div class="sfls-stat-content">
                <span class="sfls-stat-value"><?php echo esc_html( $data['stats']['completed_lessons'] ); ?></span>
                <span class="sfls-stat-label"><?php esc_html_e( 'Lessons Completed', 'swiftlms' ); ?></span>
            </div>
        </div>

        <div class="sfls-stat-card">
            <div class="sfls-stat-icon sfls-stat-certificates">
                <span class="dashicons dashicons-id"></span>
            </div>
            <div class="sfls-stat-content">
                <span class="sfls-stat-value"><?php echo esc_html( $data['stats']['certificates_earned'] ); ?></span>
                <span class="sfls-stat-label"><?php esc_html_e( 'Certificates Earned', 'swiftlms' ); ?></span>
            </div>
        </div>
    </div>

    <?php if ( ! empty( $data['courses']['in_progress'] ) ) : ?>
        <div class="sfls-section">
            <h3 class="sfls-section-title"><?php esc_html_e( 'Continue Learning', 'swiftlms' ); ?></h3>
            <div class="sfls-course-grid">
                <?php foreach ( array_slice( $data['courses']['in_progress'], 0, 3 ) as $course ) : ?>
                    <div class="sfls-course-card">
                        <?php if ( $course['thumbnail'] ) : ?>
                            <div class="sfls-course-thumbnail">
                                <img src="<?php echo esc_url( $course['thumbnail'] ); ?>" alt="<?php echo esc_attr( $course['title'] ); ?>">
                                <div class="sfls-progress-overlay">
                                    <span class="sfls-progress-text"><?php echo esc_html( $course['progress'] ); ?>%</span>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="sfls-course-content">
                            <h4 class="sfls-course-title">
                                <a href="<?php echo esc_url( $course['url'] ); ?>"><?php echo esc_html( $course['title'] ); ?></a>
                            </h4>
                            <div class="sfls-course-meta">
                                <span class="sfls-lessons-count">
                                    <span class="dashicons dashicons-media-text"></span>
                                    <?php printf( esc_html__( '%d/%d lessons', 'swiftlms' ), $course['completed_lessons'], $course['total_lessons'] ); ?>
                                </span>
                            </div>
                            <div class="sfls-progress-bar">
                                <div class="sfls-progress-fill" style="width: <?php echo esc_attr( $course['progress'] ); ?>%;"></div>
                            </div>
                            <?php if ( ! empty( $course['next_lesson'] ) ) : ?>
                                <a href="<?php echo esc_url( $course['next_lesson']['url'] ); ?>" class="sfls-continue-btn">
                                    <?php esc_html_e( 'Continue', 'swiftlms' ); ?>
                                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $data['activity'] ) ) : ?>
        <div class="sfls-section">
            <h3 class="sfls-section-title"><?php esc_html_e( 'Recent Activity', 'swiftlms' ); ?></h3>
            <div class="sfls-activity-list">
                <?php foreach ( $data['activity'] as $activity ) : ?>
                    <div class="sfls-activity-item">
                        <div class="sfls-activity-icon <?php echo esc_attr( 'sfls-activity-' . $activity['type'] ); ?>">
                            <span class="dashicons <?php echo esc_attr( $activity['icon'] ); ?>"></span>
                        </div>
                        <div class="sfls-activity-content">
                            <p class="sfls-activity-text"><?php echo wp_kses_post( $activity['message'] ); ?></p>
                            <span class="sfls-activity-time"><?php echo esc_html( $activity['time_ago'] ); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $data['certificates'] ) ) : ?>
        <div class="sfls-section">
            <h3 class="sfls-section-title"><?php esc_html_e( 'Recent Certificates', 'swiftlms' ); ?></h3>
            <div class="sfls-certificates-mini">
                <?php foreach ( array_slice( $data['certificates'], 0, 3 ) as $cert ) : ?>
                    <div class="sfls-certificate-mini">
                        <div class="sfls-certificate-icon">
                            <span class="dashicons dashicons-awards"></span>
                        </div>
                        <div class="sfls-certificate-info">
                            <h5><?php echo esc_html( $cert['course_title'] ); ?></h5>
                            <span class="sfls-certificate-date"><?php echo esc_html( $cert['date'] ); ?></span>
                        </div>
                        <a href="<?php echo esc_url( $cert['download_url'] ); ?>" class="sfls-download-btn" title="<?php esc_attr_e( 'Download', 'swiftlms' ); ?>">
                            <span class="dashicons dashicons-download"></span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
