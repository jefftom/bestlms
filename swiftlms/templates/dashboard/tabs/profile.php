<?php
/**
 * Dashboard Profile Tab
 *
 * @package SwiftLMS
 */

defined( 'ABSPATH' ) || exit;

$user = wp_get_current_user();
$profile_updated = isset( $_GET['profile_updated'] ) && '1' === $_GET['profile_updated'];
?>

<div class="sfls-dashboard-profile">
    <div class="sfls-tab-header">
        <h2 class="sfls-dashboard-title"><?php esc_html_e( 'My Profile', 'swiftlms' ); ?></h2>
    </div>

    <?php if ( $profile_updated ) : ?>
        <div class="sfls-notice sfls-notice-success">
            <span class="dashicons dashicons-yes-alt"></span>
            <?php esc_html_e( 'Your profile has been updated successfully.', 'swiftlms' ); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sfls-profile-form" enctype="multipart/form-data">
        <?php wp_nonce_field( 'sfls_update_profile', 'sfls_profile_nonce' ); ?>
        <input type="hidden" name="action" value="sfls_update_profile">

        <div class="sfls-form-section">
            <h3 class="sfls-section-heading"><?php esc_html_e( 'Profile Picture', 'swiftlms' ); ?></h3>

            <div class="sfls-avatar-upload">
                <div class="sfls-current-avatar">
                    <img src="<?php echo esc_url( $data['user']['avatar'] ); ?>" alt="<?php echo esc_attr( $data['user']['name'] ); ?>" id="sfls-avatar-preview">
                </div>
                <div class="sfls-avatar-actions">
                    <label for="sfls-avatar-input" class="sfls-secondary-btn">
                        <span class="dashicons dashicons-camera"></span>
                        <?php esc_html_e( 'Change Photo', 'swiftlms' ); ?>
                    </label>
                    <input type="file" id="sfls-avatar-input" name="profile_avatar" accept="image/*" class="sfls-hidden">
                    <p class="sfls-help-text"><?php esc_html_e( 'JPG, PNG or GIF. Max 2MB.', 'swiftlms' ); ?></p>
                </div>
            </div>
        </div>

        <div class="sfls-form-section">
            <h3 class="sfls-section-heading"><?php esc_html_e( 'Personal Information', 'swiftlms' ); ?></h3>

            <div class="sfls-form-row">
                <div class="sfls-form-group">
                    <label for="first_name"><?php esc_html_e( 'First Name', 'swiftlms' ); ?></label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo esc_attr( $user->first_name ); ?>">
                </div>
                <div class="sfls-form-group">
                    <label for="last_name"><?php esc_html_e( 'Last Name', 'swiftlms' ); ?></label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo esc_attr( $user->last_name ); ?>">
                </div>
            </div>

            <div class="sfls-form-group">
                <label for="display_name"><?php esc_html_e( 'Display Name', 'swiftlms' ); ?></label>
                <input type="text" id="display_name" name="display_name" value="<?php echo esc_attr( $user->display_name ); ?>">
            </div>

            <div class="sfls-form-group">
                <label for="user_email"><?php esc_html_e( 'Email Address', 'swiftlms' ); ?></label>
                <input type="email" id="user_email" name="user_email" value="<?php echo esc_attr( $user->user_email ); ?>" required>
            </div>

            <div class="sfls-form-group">
                <label for="description"><?php esc_html_e( 'Bio', 'swiftlms' ); ?></label>
                <textarea id="description" name="description" rows="4"><?php echo esc_textarea( $user->description ); ?></textarea>
                <p class="sfls-help-text"><?php esc_html_e( 'Brief description about yourself.', 'swiftlms' ); ?></p>
            </div>
        </div>

        <div class="sfls-form-section">
            <h3 class="sfls-section-heading"><?php esc_html_e( 'Change Password', 'swiftlms' ); ?></h3>
            <p class="sfls-section-desc"><?php esc_html_e( 'Leave blank to keep your current password.', 'swiftlms' ); ?></p>

            <div class="sfls-form-group">
                <label for="current_password"><?php esc_html_e( 'Current Password', 'swiftlms' ); ?></label>
                <div class="sfls-password-field">
                    <input type="password" id="current_password" name="current_password" autocomplete="current-password">
                    <button type="button" class="sfls-toggle-password" aria-label="<?php esc_attr_e( 'Show password', 'swiftlms' ); ?>">
                        <span class="dashicons dashicons-visibility"></span>
                    </button>
                </div>
            </div>

            <div class="sfls-form-row">
                <div class="sfls-form-group">
                    <label for="new_password"><?php esc_html_e( 'New Password', 'swiftlms' ); ?></label>
                    <div class="sfls-password-field">
                        <input type="password" id="new_password" name="new_password" autocomplete="new-password">
                        <button type="button" class="sfls-toggle-password" aria-label="<?php esc_attr_e( 'Show password', 'swiftlms' ); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                    </div>
                    <div class="sfls-password-strength" id="password-strength"></div>
                </div>
                <div class="sfls-form-group">
                    <label for="confirm_password"><?php esc_html_e( 'Confirm New Password', 'swiftlms' ); ?></label>
                    <div class="sfls-password-field">
                        <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password">
                        <button type="button" class="sfls-toggle-password" aria-label="<?php esc_attr_e( 'Show password', 'swiftlms' ); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="sfls-form-section">
            <h3 class="sfls-section-heading"><?php esc_html_e( 'Notifications', 'swiftlms' ); ?></h3>

            <div class="sfls-checkbox-group">
                <label class="sfls-checkbox">
                    <input type="checkbox" name="email_course_updates" value="1" <?php checked( get_user_meta( $user->ID, 'sfls_email_course_updates', true ), '1' ); ?>>
                    <span class="sfls-checkbox-label"><?php esc_html_e( 'Course updates and announcements', 'swiftlms' ); ?></span>
                </label>
                <label class="sfls-checkbox">
                    <input type="checkbox" name="email_new_lessons" value="1" <?php checked( get_user_meta( $user->ID, 'sfls_email_new_lessons', true ), '1' ); ?>>
                    <span class="sfls-checkbox-label"><?php esc_html_e( 'New lesson notifications', 'swiftlms' ); ?></span>
                </label>
                <label class="sfls-checkbox">
                    <input type="checkbox" name="email_reminders" value="1" <?php checked( get_user_meta( $user->ID, 'sfls_email_reminders', true ), '1' ); ?>>
                    <span class="sfls-checkbox-label"><?php esc_html_e( 'Learning reminders', 'swiftlms' ); ?></span>
                </label>
                <label class="sfls-checkbox">
                    <input type="checkbox" name="email_marketing" value="1" <?php checked( get_user_meta( $user->ID, 'sfls_email_marketing', true ), '1' ); ?>>
                    <span class="sfls-checkbox-label"><?php esc_html_e( 'Promotional emails and offers', 'swiftlms' ); ?></span>
                </label>
            </div>
        </div>

        <div class="sfls-form-actions">
            <button type="submit" class="sfls-primary-btn sfls-save-profile">
                <span class="dashicons dashicons-saved"></span>
                <?php esc_html_e( 'Save Changes', 'swiftlms' ); ?>
            </button>
        </div>
    </form>
</div>
