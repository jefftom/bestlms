<?php
/**
 * Email Notifications Module
 *
 * Handles email notifications for course events.
 *
 * @package SwiftLMS\Modules\Notifications
 * @since 1.0.0
 */

namespace SwiftLMS\Modules\Notifications;

use SwiftLMS\Abstracts\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Notifications Module class.
 */
class NotificationsModule extends AbstractModule {

    /**
     * Email templates.
     *
     * @var array
     */
    private $templates = array();

    /**
     * Get module ID.
     *
     * @return string
     */
    public function get_id(): string {
        return 'notifications';
    }

    /**
     * Get module name.
     *
     * @return string
     */
    public function get_name(): string {
        return __( 'Email Notifications', 'swiftlms' );
    }

    /**
     * Get module description.
     *
     * @return string
     */
    public function get_description(): string {
        return __( 'Send automated email notifications for enrollment, progress, and completion events.', 'swiftlms' );
    }

    /**
     * Get module version.
     *
     * @return string
     */
    public function get_version(): string {
        return '1.0.0';
    }

    /**
     * Initialize module.
     *
     * @return void
     */
    public function init(): void {
        $this->register_templates();

        // Event triggers.
        add_action( 'swiftlms_user_enrolled', array( $this, 'send_enrollment_email' ), 10, 3 );
        add_action( 'swiftlms_lesson_completed', array( $this, 'send_lesson_completed_email' ), 10, 3 );
        add_action( 'swiftlms_course_completed', array( $this, 'send_course_completed_email' ), 10, 2 );
        add_action( 'swiftlms_quiz_attempt_submitted', array( $this, 'send_quiz_result_email' ), 10, 2 );
        add_action( 'swiftlms_certificate_issued', array( $this, 'send_certificate_email' ), 10, 4 );

        // Drip content release.
        add_action( 'swiftlms_drip_release_check', array( $this, 'check_drip_notifications' ) );

        // Admin settings.
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Email styling.
        add_filter( 'swiftlms_email_header', array( $this, 'get_email_header' ) );
        add_filter( 'swiftlms_email_footer', array( $this, 'get_email_footer' ) );
    }

    /**
     * Register email templates.
     *
     * @return void
     */
    private function register_templates(): void {
        $this->templates = array(
            'enrollment' => array(
                'label'       => __( 'Enrollment Confirmation', 'swiftlms' ),
                'description' => __( 'Sent when a user enrolls in a course.', 'swiftlms' ),
                'subject'     => __( 'Welcome to {course_title}!', 'swiftlms' ),
                'body'        => $this->get_default_enrollment_body(),
                'enabled'     => true,
            ),
            'lesson_completed' => array(
                'label'       => __( 'Lesson Completed', 'swiftlms' ),
                'description' => __( 'Sent when a user completes a lesson.', 'swiftlms' ),
                'subject'     => __( 'Great progress in {course_title}!', 'swiftlms' ),
                'body'        => $this->get_default_lesson_completed_body(),
                'enabled'     => false, // Off by default to avoid spam.
            ),
            'course_completed' => array(
                'label'       => __( 'Course Completed', 'swiftlms' ),
                'description' => __( 'Sent when a user completes a course.', 'swiftlms' ),
                'subject'     => __( 'Congratulations! You\'ve completed {course_title}', 'swiftlms' ),
                'body'        => $this->get_default_course_completed_body(),
                'enabled'     => true,
            ),
            'quiz_passed' => array(
                'label'       => __( 'Quiz Passed', 'swiftlms' ),
                'description' => __( 'Sent when a user passes a quiz.', 'swiftlms' ),
                'subject'     => __( 'You passed the quiz in {course_title}!', 'swiftlms' ),
                'body'        => $this->get_default_quiz_passed_body(),
                'enabled'     => true,
            ),
            'quiz_failed' => array(
                'label'       => __( 'Quiz Failed', 'swiftlms' ),
                'description' => __( 'Sent when a user fails a quiz.', 'swiftlms' ),
                'subject'     => __( 'Quiz results for {course_title}', 'swiftlms' ),
                'body'        => $this->get_default_quiz_failed_body(),
                'enabled'     => true,
            ),
            'certificate_issued' => array(
                'label'       => __( 'Certificate Issued', 'swiftlms' ),
                'description' => __( 'Sent when a certificate is issued.', 'swiftlms' ),
                'subject'     => __( 'Your certificate for {course_title} is ready!', 'swiftlms' ),
                'body'        => $this->get_default_certificate_body(),
                'enabled'     => true,
            ),
            'drip_content_available' => array(
                'label'       => __( 'New Content Available', 'swiftlms' ),
                'description' => __( 'Sent when drip content becomes available.', 'swiftlms' ),
                'subject'     => __( 'New lesson available in {course_title}', 'swiftlms' ),
                'body'        => $this->get_default_drip_body(),
                'enabled'     => true,
            ),
            'progress_reminder' => array(
                'label'       => __( 'Progress Reminder', 'swiftlms' ),
                'description' => __( 'Sent to inactive students as a reminder.', 'swiftlms' ),
                'subject'     => __( 'Continue your learning in {course_title}', 'swiftlms' ),
                'body'        => $this->get_default_reminder_body(),
                'enabled'     => true,
            ),
        );

        $this->templates = apply_filters( 'swiftlms_email_templates', $this->templates );
    }

    /**
     * Send enrollment confirmation email.
     *
     * @param int   $user_id   User ID.
     * @param int   $course_id Course ID.
     * @param array $meta      Enrollment meta.
     * @return void
     */
    public function send_enrollment_email( int $user_id, int $course_id, array $meta = array() ): void {
        if ( ! $this->is_template_enabled( 'enrollment' ) ) {
            return;
        }

        $user   = get_userdata( $user_id );
        $course = get_post( $course_id );

        if ( ! $user || ! $course ) {
            return;
        }

        $placeholders = $this->get_placeholders( $user, $course );
        $placeholders['{course_url}'] = get_permalink( $course_id );

        $this->send_email( 'enrollment', $user->user_email, $placeholders );
    }

    /**
     * Send lesson completed email.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course ID.
     * @param int $lesson_id Lesson ID.
     * @return void
     */
    public function send_lesson_completed_email( int $user_id, int $course_id, int $lesson_id ): void {
        if ( ! $this->is_template_enabled( 'lesson_completed' ) ) {
            return;
        }

        $user   = get_userdata( $user_id );
        $course = get_post( $course_id );
        $lesson = get_post( $lesson_id );

        if ( ! $user || ! $course || ! $lesson ) {
            return;
        }

        $progress     = \SwiftLMS\Core\Progress::get_course_progress( $user_id, $course_id );
        $placeholders = $this->get_placeholders( $user, $course );
        $placeholders['{lesson_title}']   = $lesson->post_title;
        $placeholders['{progress}']       = $progress['percentage'] . '%';
        $placeholders['{lessons_remaining}'] = $progress['total'] - $progress['completed'];

        $this->send_email( 'lesson_completed', $user->user_email, $placeholders );
    }

    /**
     * Send course completed email.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course ID.
     * @return void
     */
    public function send_course_completed_email( int $user_id, int $course_id ): void {
        if ( ! $this->is_template_enabled( 'course_completed' ) ) {
            return;
        }

        $user   = get_userdata( $user_id );
        $course = get_post( $course_id );

        if ( ! $user || ! $course ) {
            return;
        }

        $placeholders = $this->get_placeholders( $user, $course );
        $placeholders['{completion_date}'] = wp_date( get_option( 'date_format' ) );

        $this->send_email( 'course_completed', $user->user_email, $placeholders );
    }

    /**
     * Send quiz result email.
     *
     * @param int   $attempt_id Attempt ID.
     * @param array $result     Quiz result.
     * @return void
     */
    public function send_quiz_result_email( int $attempt_id, array $result ): void {
        $template = $result['passed'] ? 'quiz_passed' : 'quiz_failed';

        if ( ! $this->is_template_enabled( $template ) ) {
            return;
        }

        $attempt = \SwiftLMS\Modules\Quiz\AttemptsTable::get( $attempt_id );
        if ( ! $attempt ) {
            return;
        }

        $user   = get_userdata( $attempt->user_id );
        $course = get_post( $attempt->course_id );
        $quiz   = get_post( $attempt->quiz_id );

        if ( ! $user ) {
            return;
        }

        $placeholders = $this->get_placeholders( $user, $course );
        $placeholders['{quiz_title}']    = $quiz ? $quiz->post_title : '';
        $placeholders['{score}']         = $result['percentage'] . '%';
        $placeholders['{passing_score}'] = $result['passing_score'] . '%';
        $placeholders['{points_earned}'] = $result['earned_points'];
        $placeholders['{points_total}']  = $result['total_points'];

        $this->send_email( $template, $user->user_email, $placeholders );
    }

    /**
     * Send certificate issued email.
     *
     * @param int    $cert_id   Certificate ID.
     * @param string $code      Certificate code.
     * @param int    $user_id   User ID.
     * @param int    $course_id Course ID.
     * @return void
     */
    public function send_certificate_email( int $cert_id, string $code, int $user_id, int $course_id ): void {
        if ( ! $this->is_template_enabled( 'certificate_issued' ) ) {
            return;
        }

        $user   = get_userdata( $user_id );
        $course = get_post( $course_id );

        if ( ! $user || ! $course ) {
            return;
        }

        $download_url = admin_url( "admin-ajax.php?action=sfls_download_certificate&id={$cert_id}" );
        $verify_url   = home_url( "?swiftlms-verify={$code}" );

        $placeholders = $this->get_placeholders( $user, $course );
        $placeholders['{certificate_code}'] = $code;
        $placeholders['{download_url}']     = $download_url;
        $placeholders['{verify_url}']       = $verify_url;

        $this->send_email( 'certificate_issued', $user->user_email, $placeholders );
    }

    /**
     * Check and send drip content notifications.
     *
     * @return void
     */
    public function check_drip_notifications(): void {
        // This would check for recently released content and notify users.
        // Implementation depends on tracking which notifications have been sent.
    }

    /**
     * Get base placeholders.
     *
     * @param \WP_User $user   User object.
     * @param \WP_Post $course Course post (optional).
     * @return array
     */
    private function get_placeholders( \WP_User $user, ?\WP_Post $course = null ): array {
        $placeholders = array(
            '{student_name}'  => $user->display_name,
            '{student_email}' => $user->user_email,
            '{first_name}'    => $user->first_name ?: $user->display_name,
            '{site_name}'     => get_bloginfo( 'name' ),
            '{site_url}'      => home_url(),
            '{login_url}'     => wp_login_url(),
            '{dashboard_url}' => home_url( '/dashboard/' ),
        );

        if ( $course ) {
            $placeholders['{course_title}'] = $course->post_title;
            $placeholders['{course_url}']   = get_permalink( $course->ID );
        }

        return apply_filters( 'swiftlms_email_placeholders', $placeholders, $user, $course );
    }

    /**
     * Check if template is enabled.
     *
     * @param string $template Template key.
     * @return bool
     */
    private function is_template_enabled( string $template ): bool {
        $settings = get_option( 'swiftlms_email_settings', array() );
        $key      = "enabled_{$template}";

        if ( isset( $settings[ $key ] ) ) {
            return (bool) $settings[ $key ];
        }

        // Default from template definition.
        return $this->templates[ $template ]['enabled'] ?? false;
    }

    /**
     * Get template content.
     *
     * @param string $template Template key.
     * @param string $field    Field (subject or body).
     * @return string
     */
    private function get_template_content( string $template, string $field ): string {
        $settings = get_option( 'swiftlms_email_settings', array() );
        $key      = "{$template}_{$field}";

        if ( ! empty( $settings[ $key ] ) ) {
            return $settings[ $key ];
        }

        return $this->templates[ $template ][ $field ] ?? '';
    }

    /**
     * Send email.
     *
     * @param string $template     Template key.
     * @param string $to           Recipient email.
     * @param array  $placeholders Placeholders to replace.
     * @return bool
     */
    private function send_email( string $template, string $to, array $placeholders = array() ): bool {
        $subject = $this->get_template_content( $template, 'subject' );
        $body    = $this->get_template_content( $template, 'body' );

        // Replace placeholders.
        $subject = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $subject );
        $body    = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $body );

        // Wrap in HTML template.
        $html = $this->wrap_email_html( $body );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
        );

        $sent = wp_mail( $to, $subject, $html, $headers );

        /**
         * Fires after an email is sent.
         *
         * @param string $template Template key.
         * @param string $to       Recipient.
         * @param bool   $sent     Whether email was sent.
         */
        do_action( 'swiftlms_email_sent', $template, $to, $sent );

        return $sent;
    }

    /**
     * Wrap email body in HTML template.
     *
     * @param string $body Email body content.
     * @return string
     */
    private function wrap_email_html( string $body ): string {
        $header = apply_filters( 'swiftlms_email_header', '' );
        $footer = apply_filters( 'swiftlms_email_footer', '' );

        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; font-size: 16px; line-height: 1.6; color: #333; background-color: #f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    ' . $header . '
                    <tr>
                        <td style="padding: 40px;">
                            ' . nl2br( $body ) . '
                        </td>
                    </tr>
                    ' . $footer . '
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Get email header.
     *
     * @return string
     */
    public function get_email_header(): string {
        $logo_url = get_option( 'swiftlms_email_logo' );
        $bg_color = get_option( 'swiftlms_email_header_color', '#0073aa' );

        $logo_html = '';
        if ( $logo_url ) {
            $logo_html = '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" style="max-width: 200px; max-height: 60px;">';
        } else {
            $logo_html = '<span style="font-size: 24px; font-weight: bold; color: #fff;">' . esc_html( get_bloginfo( 'name' ) ) . '</span>';
        }

        return '
        <tr>
            <td style="background-color: ' . esc_attr( $bg_color ) . '; padding: 30px; text-align: center;">
                ' . $logo_html . '
            </td>
        </tr>';
    }

    /**
     * Get email footer.
     *
     * @return string
     */
    public function get_email_footer(): string {
        $footer_text = get_option( 'swiftlms_email_footer_text', '' );

        if ( ! $footer_text ) {
            $footer_text = sprintf(
                /* translators: %s: site name */
                __( '© %1$s %2$s. All rights reserved.', 'swiftlms' ),
                gmdate( 'Y' ),
                get_bloginfo( 'name' )
            );
        }

        return '
        <tr>
            <td style="background-color: #f9f9f9; padding: 20px; text-align: center; font-size: 13px; color: #666; border-top: 1px solid #eee;">
                ' . wp_kses_post( $footer_text ) . '
            </td>
        </tr>';
    }

    /**
     * Add settings page.
     *
     * @return void
     */
    public function add_settings_page(): void {
        add_submenu_page(
            'swiftlms',
            __( 'Email Settings', 'swiftlms' ),
            __( 'Emails', 'swiftlms' ),
            'manage_options',
            'swiftlms-emails',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings.
     *
     * @return void
     */
    public function register_settings(): void {
        register_setting( 'swiftlms_email_settings', 'swiftlms_email_settings' );
        register_setting( 'swiftlms_email_settings', 'swiftlms_email_logo' );
        register_setting( 'swiftlms_email_settings', 'swiftlms_email_header_color' );
        register_setting( 'swiftlms_email_settings', 'swiftlms_email_footer_text' );
    }

    /**
     * Render settings page.
     *
     * @return void
     */
    public function render_settings_page(): void {
        $settings     = get_option( 'swiftlms_email_settings', array() );
        $current_tab  = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
        $template_keys = array_keys( $this->templates );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Email Settings', 'swiftlms' ); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=swiftlms-emails&tab=general" class="nav-tab <?php echo 'general' === $current_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'General', 'swiftlms' ); ?>
                </a>
                <?php foreach ( $this->templates as $key => $template ) : ?>
                    <a href="?page=swiftlms-emails&tab=<?php echo esc_attr( $key ); ?>" class="nav-tab <?php echo $key === $current_tab ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $template['label'] ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form method="post" action="options.php">
                <?php settings_fields( 'swiftlms_email_settings' ); ?>

                <?php if ( 'general' === $current_tab ) : ?>
                    <?php $this->render_general_settings(); ?>
                <?php elseif ( isset( $this->templates[ $current_tab ] ) ) : ?>
                    <?php $this->render_template_settings( $current_tab, $this->templates[ $current_tab ], $settings ); ?>
                <?php endif; ?>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render general email settings.
     *
     * @return void
     */
    private function render_general_settings(): void {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="swiftlms_email_logo"><?php esc_html_e( 'Email Logo URL', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="url" name="swiftlms_email_logo" id="swiftlms_email_logo"
                           value="<?php echo esc_attr( get_option( 'swiftlms_email_logo' ) ); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e( 'URL to logo image for email header.', 'swiftlms' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="swiftlms_email_header_color"><?php esc_html_e( 'Header Color', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="color" name="swiftlms_email_header_color" id="swiftlms_email_header_color"
                           value="<?php echo esc_attr( get_option( 'swiftlms_email_header_color', '#0073aa' ) ); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="swiftlms_email_footer_text"><?php esc_html_e( 'Footer Text', 'swiftlms' ); ?></label></th>
                <td>
                    <textarea name="swiftlms_email_footer_text" id="swiftlms_email_footer_text" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'swiftlms_email_footer_text' ) ); ?></textarea>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Available Placeholders', 'swiftlms' ); ?></h2>
        <p><?php esc_html_e( 'Use these placeholders in email subjects and bodies:', 'swiftlms' ); ?></p>
        <code>{student_name}</code>, <code>{first_name}</code>, <code>{student_email}</code>,
        <code>{course_title}</code>, <code>{course_url}</code>, <code>{site_name}</code>,
        <code>{site_url}</code>, <code>{login_url}</code>, <code>{dashboard_url}</code>
        <?php
    }

    /**
     * Render template-specific settings.
     *
     * @param string $key      Template key.
     * @param array  $template Template config.
     * @param array  $settings Saved settings.
     * @return void
     */
    private function render_template_settings( string $key, array $template, array $settings ): void {
        $enabled = isset( $settings["enabled_{$key}"] ) ? (bool) $settings["enabled_{$key}"] : $template['enabled'];
        $subject = $settings["{$key}_subject"] ?? $template['subject'];
        $body    = $settings["{$key}_body"] ?? $template['body'];
        ?>
        <h2><?php echo esc_html( $template['label'] ); ?></h2>
        <p><?php echo esc_html( $template['description'] ); ?></p>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Enable', 'swiftlms' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="swiftlms_email_settings[enabled_<?php echo esc_attr( $key ); ?>]"
                               value="1" <?php checked( $enabled ); ?>>
                        <?php esc_html_e( 'Send this email notification', 'swiftlms' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="<?php echo esc_attr( $key ); ?>_subject"><?php esc_html_e( 'Subject', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="text" name="swiftlms_email_settings[<?php echo esc_attr( $key ); ?>_subject]"
                           id="<?php echo esc_attr( $key ); ?>_subject" value="<?php echo esc_attr( $subject ); ?>" class="large-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="<?php echo esc_attr( $key ); ?>_body"><?php esc_html_e( 'Body', 'swiftlms' ); ?></label></th>
                <td>
                    <textarea name="swiftlms_email_settings[<?php echo esc_attr( $key ); ?>_body]"
                              id="<?php echo esc_attr( $key ); ?>_body" rows="12" class="large-text code"><?php echo esc_textarea( $body ); ?></textarea>
                </td>
            </tr>
        </table>
        <?php
    }

    // Default email bodies.
    private function get_default_enrollment_body(): string {
        return __( 'Hi {first_name},

Welcome to {course_title}! You\'re now enrolled and ready to start learning.

Click the button below to access your course:

{course_url}

Happy learning!

{site_name}', 'swiftlms' );
    }

    private function get_default_lesson_completed_body(): string {
        return __( 'Hi {first_name},

Great job! You\'ve completed "{lesson_title}" in {course_title}.

Your progress: {progress}
Lessons remaining: {lessons_remaining}

Keep up the great work!

{site_name}', 'swiftlms' );
    }

    private function get_default_course_completed_body(): string {
        return __( 'Hi {first_name},

Congratulations! You\'ve completed {course_title}!

This is a fantastic achievement. You should be proud of your dedication to learning.

Check your dashboard for any certificates or next steps:
{dashboard_url}

Thank you for learning with us!

{site_name}', 'swiftlms' );
    }

    private function get_default_quiz_passed_body(): string {
        return __( 'Hi {first_name},

Congratulations! You passed the quiz "{quiz_title}" in {course_title}!

Your score: {score}
Passing score: {passing_score}
Points earned: {points_earned} / {points_total}

Keep up the excellent work!

{site_name}', 'swiftlms' );
    }

    private function get_default_quiz_failed_body(): string {
        return __( 'Hi {first_name},

You\'ve completed the quiz "{quiz_title}" in {course_title}.

Your score: {score}
Passing score: {passing_score}

Don\'t worry! You can review the material and try again. Learning takes practice.

Continue your course:
{course_url}

{site_name}', 'swiftlms' );
    }

    private function get_default_certificate_body(): string {
        return __( 'Hi {first_name},

Your certificate for {course_title} is ready!

Certificate ID: {certificate_code}

Download your certificate:
{download_url}

You can verify your certificate anytime at:
{verify_url}

Congratulations on your achievement!

{site_name}', 'swiftlms' );
    }

    private function get_default_drip_body(): string {
        return __( 'Hi {first_name},

New content is now available in {course_title}!

A new lesson has been unlocked for you. Continue your learning journey now:

{course_url}

{site_name}', 'swiftlms' );
    }

    private function get_default_reminder_body(): string {
        return __( 'Hi {first_name},

We noticed you haven\'t visited {course_title} in a while.

Your progress: {progress}

Don\'t lose momentum! Continue where you left off:
{course_url}

We\'re here to help you succeed!

{site_name}', 'swiftlms' );
    }
}
