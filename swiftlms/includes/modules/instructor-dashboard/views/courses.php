<?php
/**
 * Instructor Dashboard - Courses View
 *
 * @package SwiftLMS\Modules\InstructorDashboard
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap sfls-instructor-wrap">
    <h1>
        <?php esc_html_e( 'My Courses', 'swiftlms' ); ?>
        <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=sfls_course' ) ); ?>" class="page-title-action">
            <?php esc_html_e( 'Add New Course', 'swiftlms' ); ?>
        </a>
    </h1>

    <?php if ( empty( $courses ) ) : ?>
        <div class="sfls-empty-state">
            <div class="sfls-empty-icon">
                <span class="dashicons dashicons-welcome-learn-more"></span>
            </div>
            <h2><?php esc_html_e( 'No Courses Yet', 'swiftlms' ); ?></h2>
            <p><?php esc_html_e( 'You haven\'t created any courses yet. Get started by creating your first course!', 'swiftlms' ); ?></p>
            <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=sfls_course' ) ); ?>" class="button button-primary button-hero">
                <?php esc_html_e( 'Create Your First Course', 'swiftlms' ); ?>
            </a>
        </div>
    <?php else : ?>
        <div class="sfls-courses-grid">
            <?php foreach ( $courses as $course ) : ?>
            <div class="sfls-course-card">
                <div class="sfls-course-header">
                    <?php
                    $thumbnail = get_the_post_thumbnail_url( $course['course_id'], 'medium' );
                    if ( $thumbnail ) :
                    ?>
                        <img src="<?php echo esc_url( $thumbnail ); ?>" alt="" class="sfls-course-thumbnail">
                    <?php else : ?>
                        <div class="sfls-course-thumbnail sfls-no-thumbnail">
                            <span class="dashicons dashicons-format-video"></span>
                        </div>
                    <?php endif; ?>
                    <span class="sfls-course-status sfls-status-<?php echo esc_attr( $course['status'] ); ?>">
                        <?php echo esc_html( ucfirst( $course['status'] ) ); ?>
                    </span>
                </div>
                <div class="sfls-course-body">
                    <h3 class="sfls-course-title">
                        <a href="<?php echo esc_url( get_edit_post_link( $course['course_id'] ) ); ?>">
                            <?php echo esc_html( $course['title'] ); ?>
                        </a>
                    </h3>
                    <div class="sfls-course-meta">
                        <span class="sfls-course-meta-item">
                            <span class="dashicons dashicons-groups"></span>
                            <?php echo esc_html( $course['enrollments'] ); ?> <?php esc_html_e( 'students', 'swiftlms' ); ?>
                        </span>
                        <span class="sfls-course-meta-item">
                            <span class="dashicons dashicons-yes"></span>
                            <?php echo esc_html( $course['completions'] ); ?> <?php esc_html_e( 'completions', 'swiftlms' ); ?>
                        </span>
                    </div>
                    <div class="sfls-course-stats">
                        <div class="sfls-course-stat">
                            <span class="sfls-stat-label"><?php esc_html_e( 'Completion Rate', 'swiftlms' ); ?></span>
                            <div class="sfls-mini-progress">
                                <div class="sfls-mini-bar">
                                    <div style="width: <?php echo esc_attr( $course['completion_rate'] ); ?>%"></div>
                                </div>
                                <span><?php echo esc_html( $course['completion_rate'] ); ?>%</span>
                            </div>
                        </div>
                        <div class="sfls-course-stat">
                            <span class="sfls-stat-label"><?php esc_html_e( 'Avg. Progress', 'swiftlms' ); ?></span>
                            <div class="sfls-mini-progress">
                                <div class="sfls-mini-bar sfls-bar-info">
                                    <div style="width: <?php echo esc_attr( $course['avg_progress'] ); ?>%"></div>
                                </div>
                                <span><?php echo esc_html( $course['avg_progress'] ); ?>%</span>
                            </div>
                        </div>
                    </div>
                    <?php if ( $course['reviews'] > 0 ) : ?>
                    <div class="sfls-course-rating">
                        <div class="sfls-stars">
                            <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                                <span class="dashicons dashicons-star-<?php echo $i <= round( $course['rating'] ) ? 'filled' : 'empty'; ?>"></span>
                            <?php endfor; ?>
                        </div>
                        <span class="sfls-rating-text">
                            <?php echo esc_html( $course['rating'] ); ?> (<?php echo esc_html( $course['reviews'] ); ?> <?php esc_html_e( 'reviews', 'swiftlms' ); ?>)
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="sfls-course-footer">
                    <a href="<?php echo esc_url( get_edit_post_link( $course['course_id'] ) ); ?>" class="button">
                        <span class="dashicons dashicons-edit"></span>
                        <?php esc_html_e( 'Edit', 'swiftlms' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=sfls-instructor-students&course_id=' . $course['course_id'] ) ); ?>" class="button">
                        <span class="dashicons dashicons-groups"></span>
                        <?php esc_html_e( 'Students', 'swiftlms' ); ?>
                    </a>
                    <a href="<?php echo esc_url( get_permalink( $course['course_id'] ) ); ?>" class="button" target="_blank">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php esc_html_e( 'View', 'swiftlms' ); ?>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
