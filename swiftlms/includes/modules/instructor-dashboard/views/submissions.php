<?php
/**
 * Instructor Dashboard - Submissions View
 *
 * @package SwiftLMS\Modules\InstructorDashboard
 */

defined( 'ABSPATH' ) || exit;

$current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$total_pages = ceil( $result['total'] / 20 );
?>

<div class="wrap sfls-instructor-wrap">
    <h1><?php esc_html_e( 'Pending Submissions', 'swiftlms' ); ?></h1>

    <!-- Filters -->
    <div class="sfls-filters-bar">
        <form method="get" class="sfls-filters-form">
            <input type="hidden" name="page" value="sfls-instructor-submissions">

            <select name="course_id" class="sfls-filter-select">
                <option value=""><?php esc_html_e( 'All Courses', 'swiftlms' ); ?></option>
                <?php foreach ( $courses as $course ) : ?>
                    <option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( $args['course_id'], $course->ID ); ?>>
                        <?php echo esc_html( $course->post_title ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="button"><?php esc_html_e( 'Filter', 'swiftlms' ); ?></button>
        </form>

        <div class="sfls-filters-info">
            <span class="sfls-pending-count"><?php echo esc_html( $result['total'] ); ?></span>
            <?php esc_html_e( 'submissions pending review', 'swiftlms' ); ?>
        </div>
    </div>

    <?php if ( empty( $result['submissions'] ) ) : ?>
        <div class="sfls-empty-state sfls-empty-success">
            <div class="sfls-empty-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <h2><?php esc_html_e( 'All Caught Up!', 'swiftlms' ); ?></h2>
            <p><?php esc_html_e( 'There are no pending submissions to review.', 'swiftlms' ); ?></p>
        </div>
    <?php else : ?>
        <div class="sfls-submissions-list">
            <?php foreach ( $result['submissions'] as $submission ) : ?>
            <div class="sfls-submission-card">
                <div class="sfls-submission-header">
                    <div class="sfls-submission-student">
                        <img src="<?php echo esc_url( $submission['student_avatar'] ); ?>" alt="" class="sfls-avatar">
                        <div class="sfls-student-info">
                            <strong><?php echo esc_html( $submission['student_name'] ); ?></strong>
                            <span class="sfls-submission-course"><?php echo esc_html( $submission['course_title'] ); ?></span>
                        </div>
                    </div>
                    <div class="sfls-submission-time">
                        <span class="dashicons dashicons-clock"></span>
                        <?php echo esc_html( human_time_diff( strtotime( $submission['submitted_at'] ), current_time( 'timestamp' ) ) ); ?> ago
                    </div>
                </div>
                <div class="sfls-submission-body">
                    <h4 class="sfls-assignment-title"><?php echo esc_html( $submission['assignment_title'] ); ?></h4>
                    <?php if ( ! empty( $submission['content'] ) ) : ?>
                        <p class="sfls-submission-preview">
                            <?php echo esc_html( wp_trim_words( $submission['content'], 30 ) ); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="sfls-submission-footer">
                    <span class="sfls-submission-type">
                        <?php
                        $types = array(
                            'file'    => __( 'File Upload', 'swiftlms' ),
                            'text'    => __( 'Text Entry', 'swiftlms' ),
                            'url'     => __( 'URL', 'swiftlms' ),
                            'mixed'   => __( 'Mixed', 'swiftlms' ),
                        );
                        echo esc_html( $types[ $submission['submission_type'] ] ?? $submission['submission_type'] );
                        ?>
                    </span>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=sfls-instructor-submissions&submission_id=' . $submission['id'] ) ); ?>" class="button button-primary">
                        <?php esc_html_e( 'Review & Grade', 'swiftlms' ); ?>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ( $total_pages > 1 ) : ?>
        <div class="sfls-pagination">
            <?php
            echo paginate_links( array(
                'base'      => add_query_arg( 'paged', '%#%' ),
                'format'    => '',
                'current'   => $current_page,
                'total'     => $total_pages,
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
            ) );
            ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
