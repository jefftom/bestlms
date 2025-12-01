<?php
/**
 * Settings view.
 *
 * @package SwiftLMS\Admin\Views
 * @var array $settings Current settings.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap swiftlms-admin-page swiftlms-settings">
    <h1><?php esc_html_e( 'SwiftLMS Settings', 'swiftlms' ); ?></h1>

    <?php settings_errors( 'swiftlms_settings' ); ?>

    <form method="post" action="">
        <?php wp_nonce_field( 'swiftlms_save_settings', 'swiftlms_settings_nonce' ); ?>

        <div class="swiftlms-settings-sections">
            <!-- Permalinks Section -->
            <div class="swiftlms-settings-section">
                <h2><?php esc_html_e( 'Permalinks', 'swiftlms' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Customize the URL structure for your learning content.', 'swiftlms' ); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="course_slug"><?php esc_html_e( 'Course Slug', 'swiftlms' ); ?></label>
                        </th>
                        <td>
                            <code><?php echo esc_url( home_url( '/' ) ); ?></code>
                            <input type="text" id="course_slug" name="course_slug" value="<?php echo esc_attr( $settings['course_slug'] ); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="lesson_slug"><?php esc_html_e( 'Lesson Slug', 'swiftlms' ); ?></label>
                        </th>
                        <td>
                            <code><?php echo esc_url( home_url( '/' ) ); ?></code>
                            <input type="text" id="lesson_slug" name="lesson_slug" value="<?php echo esc_attr( $settings['lesson_slug'] ); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="topic_slug"><?php esc_html_e( 'Topic Slug', 'swiftlms' ); ?></label>
                        </th>
                        <td>
                            <code><?php echo esc_url( home_url( '/' ) ); ?></code>
                            <input type="text" id="topic_slug" name="topic_slug" value="<?php echo esc_attr( $settings['topic_slug'] ); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Learning Experience Section -->
            <div class="swiftlms-settings-section">
                <h2><?php esc_html_e( 'Learning Experience', 'swiftlms' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Configure how students experience your courses.', 'swiftlms' ); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Focus Mode', 'swiftlms' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_focus_mode" value="1" <?php checked( $settings['enable_focus_mode'] ); ?>>
                                <?php esc_html_e( 'Enable distraction-free learning mode', 'swiftlms' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Hides site header and sidebar during lessons.', 'swiftlms' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Auto-Resume', 'swiftlms' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_resume" value="1" <?php checked( $settings['enable_resume'] ); ?>>
                                <?php esc_html_e( 'Enable video auto-resume', 'swiftlms' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Automatically resume video playback from where the student left off.', 'swiftlms' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Video Tracking Section -->
            <div class="swiftlms-settings-section">
                <h2><?php esc_html_e( 'Video Tracking', 'swiftlms' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Configure video progress tracking settings.', 'swiftlms' ); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="video_sync_interval"><?php esc_html_e( 'Sync Interval', 'swiftlms' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="video_sync_interval" name="video_sync_interval" value="<?php echo esc_attr( $settings['video_sync_interval'] ); ?>" class="small-text" min="3" max="30">
                            <?php esc_html_e( 'seconds', 'swiftlms' ); ?>
                            <p class="description"><?php esc_html_e( 'How often video progress syncs to the server (3-30 seconds).', 'swiftlms' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="completion_threshold"><?php esc_html_e( 'Completion Threshold', 'swiftlms' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="completion_threshold" name="completion_threshold" value="<?php echo esc_attr( $settings['completion_threshold'] ); ?>" class="small-text" min="50" max="100">%
                            <p class="description"><?php esc_html_e( 'Default percentage of unique seconds that must be watched to complete a video lesson.', 'swiftlms' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'swiftlms' ); ?>">
        </p>
    </form>
</div>
