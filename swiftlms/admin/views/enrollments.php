<?php
/**
 * Enrollments view.
 *
 * @package SwiftLMS\Admin\Views
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap swiftlms-admin-page swiftlms-enrollments">
    <h1>
        <?php esc_html_e( 'Enrollments', 'swiftlms' ); ?>
        <button type="button" class="page-title-action" id="swiftlms-add-enrollment">
            <?php esc_html_e( 'Add Enrollment', 'swiftlms' ); ?>
        </button>
    </h1>

    <div id="swiftlms-enrollments-app">
        <!-- Filters -->
        <div class="swiftlms-filters">
            <select id="swiftlms-course-filter">
                <option value=""><?php esc_html_e( 'All Courses', 'swiftlms' ); ?></option>
                <?php
                $courses = get_posts(
                    array(
                        'post_type'      => 'sfls_course',
                        'posts_per_page' => -1,
                        'post_status'    => 'publish',
                        'orderby'        => 'title',
                        'order'          => 'ASC',
                    )
                );
                foreach ( $courses as $course ) :
                    ?>
                    <option value="<?php echo esc_attr( $course->ID ); ?>"><?php echo esc_html( $course->post_title ); ?></option>
                <?php endforeach; ?>
            </select>

            <select id="swiftlms-status-filter">
                <option value=""><?php esc_html_e( 'All Statuses', 'swiftlms' ); ?></option>
                <option value="active"><?php esc_html_e( 'Active', 'swiftlms' ); ?></option>
                <option value="completed"><?php esc_html_e( 'Completed', 'swiftlms' ); ?></option>
                <option value="expired"><?php esc_html_e( 'Expired', 'swiftlms' ); ?></option>
                <option value="cancelled"><?php esc_html_e( 'Cancelled', 'swiftlms' ); ?></option>
            </select>

            <button type="button" class="button" id="swiftlms-apply-filters">
                <?php esc_html_e( 'Filter', 'swiftlms' ); ?>
            </button>
        </div>

        <!-- Enrollments Table -->
        <table class="wp-list-table widefat fixed striped" id="swiftlms-enrollments-table">
            <thead>
                <tr>
                    <th class="column-student"><?php esc_html_e( 'Student', 'swiftlms' ); ?></th>
                    <th class="column-course"><?php esc_html_e( 'Course', 'swiftlms' ); ?></th>
                    <th class="column-status"><?php esc_html_e( 'Status', 'swiftlms' ); ?></th>
                    <th class="column-progress"><?php esc_html_e( 'Progress', 'swiftlms' ); ?></th>
                    <th class="column-enrolled"><?php esc_html_e( 'Enrolled', 'swiftlms' ); ?></th>
                    <th class="column-actions"><?php esc_html_e( 'Actions', 'swiftlms' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr class="swiftlms-loading">
                    <td colspan="6"><?php esc_html_e( 'Loading enrollments...', 'swiftlms' ); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="swiftlms-pagination" id="swiftlms-pagination"></div>
    </div>

    <!-- Add Enrollment Modal -->
    <div id="swiftlms-enrollment-modal" class="swiftlms-modal" style="display: none;">
        <div class="swiftlms-modal-content">
            <span class="swiftlms-modal-close">&times;</span>
            <h2><?php esc_html_e( 'Add Enrollment', 'swiftlms' ); ?></h2>

            <form id="swiftlms-enrollment-form">
                <table class="form-table">
                    <tr>
                        <th><label for="enrollment-user"><?php esc_html_e( 'Student', 'swiftlms' ); ?></label></th>
                        <td>
                            <select id="enrollment-user" name="user_id" required class="swiftlms-user-select">
                                <option value=""><?php esc_html_e( 'Search for a user...', 'swiftlms' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="enrollment-course"><?php esc_html_e( 'Course', 'swiftlms' ); ?></label></th>
                        <td>
                            <select id="enrollment-course" name="course_id" required>
                                <option value=""><?php esc_html_e( 'Select a course', 'swiftlms' ); ?></option>
                                <?php foreach ( $courses as $course ) : ?>
                                    <option value="<?php echo esc_attr( $course->ID ); ?>"><?php echo esc_html( $course->post_title ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="enrollment-expires"><?php esc_html_e( 'Expires', 'swiftlms' ); ?></label></th>
                        <td>
                            <input type="date" id="enrollment-expires" name="expires_at">
                            <p class="description"><?php esc_html_e( 'Leave blank for no expiration.', 'swiftlms' ); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Add Enrollment', 'swiftlms' ); ?></button>
                    <button type="button" class="button swiftlms-modal-close"><?php esc_html_e( 'Cancel', 'swiftlms' ); ?></button>
                </p>
            </form>
        </div>
    </div>
</div>
