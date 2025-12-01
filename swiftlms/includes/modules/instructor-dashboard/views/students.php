<?php
/**
 * Instructor Dashboard - Students View
 *
 * @package SwiftLMS\Modules\InstructorDashboard
 */

defined( 'ABSPATH' ) || exit;

use SwiftLMS\Modules\InstructorDashboard\Instructor_Role;

$current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$total_pages = ceil( $result['total'] / 20 );
?>

<div class="wrap sfls-instructor-wrap">
    <h1><?php esc_html_e( 'Students', 'swiftlms' ); ?></h1>

    <!-- Filters -->
    <div class="sfls-filters-bar">
        <form method="get" class="sfls-filters-form">
            <input type="hidden" name="page" value="sfls-instructor-students">

            <select name="course_id" class="sfls-filter-select">
                <option value=""><?php esc_html_e( 'All Courses', 'swiftlms' ); ?></option>
                <?php foreach ( $courses as $course ) : ?>
                    <option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( $args['course_id'], $course->ID ); ?>>
                        <?php echo esc_html( $course->post_title ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status" class="sfls-filter-select">
                <option value=""><?php esc_html_e( 'All Statuses', 'swiftlms' ); ?></option>
                <option value="enrolled" <?php selected( $args['status'], 'enrolled' ); ?>><?php esc_html_e( 'Enrolled', 'swiftlms' ); ?></option>
                <option value="in_progress" <?php selected( $args['status'], 'in_progress' ); ?>><?php esc_html_e( 'In Progress', 'swiftlms' ); ?></option>
                <option value="completed" <?php selected( $args['status'], 'completed' ); ?>><?php esc_html_e( 'Completed', 'swiftlms' ); ?></option>
            </select>

            <input type="search" name="s" value="<?php echo esc_attr( $args['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search students...', 'swiftlms' ); ?>" class="sfls-search-input">

            <button type="submit" class="button"><?php esc_html_e( 'Filter', 'swiftlms' ); ?></button>
        </form>

        <div class="sfls-filters-info">
            <?php printf( esc_html__( 'Showing %d students', 'swiftlms' ), $result['total'] ); ?>
        </div>
    </div>

    <?php if ( empty( $result['students'] ) ) : ?>
        <div class="sfls-empty-state">
            <div class="sfls-empty-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <h2><?php esc_html_e( 'No Students Found', 'swiftlms' ); ?></h2>
            <p><?php esc_html_e( 'No students match your current filters.', 'swiftlms' ); ?></p>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped sfls-students-table">
            <thead>
                <tr>
                    <th class="column-avatar" style="width: 50px;"></th>
                    <th class="column-name"><?php esc_html_e( 'Student', 'swiftlms' ); ?></th>
                    <th class="column-courses"><?php esc_html_e( 'Courses', 'swiftlms' ); ?></th>
                    <th class="column-progress"><?php esc_html_e( 'Avg. Progress', 'swiftlms' ); ?></th>
                    <th class="column-completed"><?php esc_html_e( 'Completed', 'swiftlms' ); ?></th>
                    <th class="column-activity"><?php esc_html_e( 'Last Activity', 'swiftlms' ); ?></th>
                    <th class="column-actions"><?php esc_html_e( 'Actions', 'swiftlms' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $result['students'] as $student ) : ?>
                <tr>
                    <td class="column-avatar">
                        <img src="<?php echo esc_url( $student['avatar'] ); ?>" alt="" class="sfls-student-avatar">
                    </td>
                    <td class="column-name">
                        <strong><?php echo esc_html( $student['display_name'] ); ?></strong>
                        <br>
                        <span class="sfls-student-email"><?php echo esc_html( $student['user_email'] ); ?></span>
                    </td>
                    <td class="column-courses">
                        <?php echo esc_html( $student['enrolled_courses'] ); ?>
                    </td>
                    <td class="column-progress">
                        <div class="sfls-progress-cell">
                            <div class="sfls-progress-bar-small">
                                <div class="sfls-bar" style="width: <?php echo esc_attr( $student['avg_progress'] ); ?>%"></div>
                            </div>
                            <span><?php echo esc_html( $student['avg_progress'] ); ?>%</span>
                        </div>
                    </td>
                    <td class="column-completed">
                        <?php echo esc_html( $student['completed_courses'] ); ?> / <?php echo esc_html( $student['enrolled_courses'] ); ?>
                    </td>
                    <td class="column-activity">
                        <?php
                        if ( $student['last_activity'] ) {
                            echo esc_html( human_time_diff( strtotime( $student['last_activity'] ), current_time( 'timestamp' ) ) ) . ' ago';
                        } else {
                            esc_html_e( 'Never', 'swiftlms' );
                        }
                        ?>
                    </td>
                    <td class="column-actions">
                        <a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $student['user_id'] ) ); ?>" class="button button-small">
                            <?php esc_html_e( 'View', 'swiftlms' ); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

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
