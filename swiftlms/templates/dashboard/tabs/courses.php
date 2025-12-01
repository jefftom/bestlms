<?php
/**
 * Dashboard Courses Tab
 *
 * @package SwiftLMS
 */

defined( 'ABSPATH' ) || exit;

$filter = isset( $_GET['filter'] ) ? sanitize_key( $_GET['filter'] ) : 'all';
?>

<div class="sfls-dashboard-courses">
    <div class="sfls-tab-header">
        <h2 class="sfls-dashboard-title"><?php esc_html_e( 'My Courses', 'swiftlms' ); ?></h2>
        <div class="sfls-course-filters">
            <a href="<?php echo esc_url( add_query_arg( 'filter', 'all' ) ); ?>"
               class="sfls-filter-btn <?php echo 'all' === $filter ? 'active' : ''; ?>">
                <?php esc_html_e( 'All', 'swiftlms' ); ?>
                <span class="sfls-filter-count"><?php echo esc_html( count( $data['courses']['all'] ) ); ?></span>
            </a>
            <a href="<?php echo esc_url( add_query_arg( 'filter', 'in_progress' ) ); ?>"
               class="sfls-filter-btn <?php echo 'in_progress' === $filter ? 'active' : ''; ?>">
                <?php esc_html_e( 'In Progress', 'swiftlms' ); ?>
                <span class="sfls-filter-count"><?php echo esc_html( count( $data['courses']['in_progress'] ) ); ?></span>
            </a>
            <a href="<?php echo esc_url( add_query_arg( 'filter', 'completed' ) ); ?>"
               class="sfls-filter-btn <?php echo 'completed' === $filter ? 'active' : ''; ?>">
                <?php esc_html_e( 'Completed', 'swiftlms' ); ?>
                <span class="sfls-filter-count"><?php echo esc_html( count( $data['courses']['completed'] ) ); ?></span>
            </a>
            <a href="<?php echo esc_url( add_query_arg( 'filter', 'not_started' ) ); ?>"
               class="sfls-filter-btn <?php echo 'not_started' === $filter ? 'active' : ''; ?>">
                <?php esc_html_e( 'Not Started', 'swiftlms' ); ?>
                <span class="sfls-filter-count"><?php echo esc_html( count( $data['courses']['not_started'] ) ); ?></span>
            </a>
        </div>
    </div>

    <?php
    $courses = 'all' === $filter ? $data['courses']['all'] : $data['courses'][ $filter ];

    if ( empty( $courses ) ) :
    ?>
        <div class="sfls-empty-state">
            <span class="dashicons dashicons-welcome-learn-more"></span>
            <h3><?php esc_html_e( 'No courses found', 'swiftlms' ); ?></h3>
            <?php if ( 'all' === $filter ) : ?>
                <p><?php esc_html_e( 'You haven\'t enrolled in any courses yet.', 'swiftlms' ); ?></p>
                <a href="<?php echo esc_url( get_post_type_archive_link( 'sfls_course' ) ); ?>" class="sfls-primary-btn">
                    <?php esc_html_e( 'Browse Courses', 'swiftlms' ); ?>
                </a>
            <?php else : ?>
                <p><?php esc_html_e( 'No courses match this filter.', 'swiftlms' ); ?></p>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="sfls-courses-list">
            <?php foreach ( $courses as $course ) : ?>
                <div class="sfls-course-row">
                    <div class="sfls-course-thumb">
                        <?php if ( $course['thumbnail'] ) : ?>
                            <img src="<?php echo esc_url( $course['thumbnail'] ); ?>" alt="<?php echo esc_attr( $course['title'] ); ?>">
                        <?php else : ?>
                            <div class="sfls-course-placeholder">
                                <span class="dashicons dashicons-welcome-learn-more"></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="sfls-course-details">
                        <h3 class="sfls-course-name">
                            <a href="<?php echo esc_url( $course['url'] ); ?>"><?php echo esc_html( $course['title'] ); ?></a>
                        </h3>
                        <div class="sfls-course-stats">
                            <span class="sfls-stat">
                                <span class="dashicons dashicons-media-text"></span>
                                <?php printf( esc_html__( '%d Lessons', 'swiftlms' ), $course['total_lessons'] ); ?>
                            </span>
                            <?php if ( ! empty( $course['instructor'] ) ) : ?>
                                <span class="sfls-stat">
                                    <span class="dashicons dashicons-admin-users"></span>
                                    <?php echo esc_html( $course['instructor'] ); ?>
                                </span>
                            <?php endif; ?>
                            <span class="sfls-stat">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                <?php printf( esc_html__( 'Enrolled %s', 'swiftlms' ), esc_html( $course['enrolled_date'] ) ); ?>
                            </span>
                        </div>
                    </div>

                    <div class="sfls-course-progress-col">
                        <div class="sfls-progress-ring" data-progress="<?php echo esc_attr( $course['progress'] ); ?>">
                            <svg viewBox="0 0 36 36">
                                <path class="sfls-progress-bg"
                                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                <path class="sfls-progress-fill"
                                    stroke-dasharray="<?php echo esc_attr( $course['progress'] ); ?>, 100"
                                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                            </svg>
                            <span class="sfls-progress-value"><?php echo esc_html( $course['progress'] ); ?>%</span>
                        </div>
                        <span class="sfls-lessons-progress">
                            <?php printf( esc_html__( '%d/%d lessons', 'swiftlms' ), $course['completed_lessons'], $course['total_lessons'] ); ?>
                        </span>
                    </div>

                    <div class="sfls-course-actions-col">
                        <?php if ( $course['progress'] >= 100 ) : ?>
                            <span class="sfls-status-badge sfls-completed">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php esc_html_e( 'Completed', 'swiftlms' ); ?>
                            </span>
                            <?php if ( ! empty( $course['certificate_url'] ) ) : ?>
                                <a href="<?php echo esc_url( $course['certificate_url'] ); ?>" class="sfls-secondary-btn">
                                    <span class="dashicons dashicons-download"></span>
                                    <?php esc_html_e( 'Certificate', 'swiftlms' ); ?>
                                </a>
                            <?php endif; ?>
                        <?php elseif ( $course['progress'] > 0 ) : ?>
                            <a href="<?php echo esc_url( $course['next_lesson']['url'] ?? $course['url'] ); ?>" class="sfls-primary-btn">
                                <?php esc_html_e( 'Continue', 'swiftlms' ); ?>
                            </a>
                        <?php else : ?>
                            <a href="<?php echo esc_url( $course['url'] ); ?>" class="sfls-primary-btn">
                                <?php esc_html_e( 'Start Course', 'swiftlms' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
