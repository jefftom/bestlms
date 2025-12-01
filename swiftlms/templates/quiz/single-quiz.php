<?php
/**
 * Single Quiz Template
 *
 * @package SwiftLMS
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="sfls-quiz-page">
    <div class="sfls-quiz-wrapper">
        <?php if ( ! is_user_logged_in() ) : ?>
            <div class="sfls-notice sfls-notice-warning">
                <p><?php esc_html_e( 'Please log in to take this quiz.', 'swiftlms' ); ?></p>
                <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="sfls-btn sfls-btn-primary">
                    <?php esc_html_e( 'Log In', 'swiftlms' ); ?>
                </a>
            </div>
        <?php else : ?>
            <?php
            // Check enrollment if course is associated.
            $course_id = get_post_meta( get_the_ID(), '_sfls_course_id', true );
            $enrolled  = true;

            if ( $course_id ) {
                $enrolled = \SwiftLMS\Core\Enrollment::is_enrolled( get_current_user_id(), $course_id );
            }

            if ( ! $enrolled ) :
                ?>
                <div class="sfls-notice sfls-notice-warning">
                    <p><?php esc_html_e( 'You must be enrolled in the course to take this quiz.', 'swiftlms' ); ?></p>
                    <?php if ( $course_id ) : ?>
                        <a href="<?php echo esc_url( get_permalink( $course_id ) ); ?>" class="sfls-btn sfls-btn-primary">
                            <?php esc_html_e( 'View Course', 'swiftlms' ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <div id="sfls-quiz-container">
                    <div class="sfls-quiz-loading">
                        <div class="sfls-spinner"></div>
                        <p><?php esc_html_e( 'Loading quiz...', 'swiftlms' ); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.sfls-quiz-page {
    padding: 40px 20px;
    background: #f5f5f5;
    min-height: 80vh;
}

.sfls-quiz-wrapper {
    max-width: 900px;
    margin: 0 auto;
}

.sfls-quiz-loading {
    text-align: center;
    padding: 60px;
}

.sfls-spinner {
    width: 50px;
    height: 50px;
    margin: 0 auto 20px;
    border: 4px solid #e0e0e0;
    border-top-color: #0073aa;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<?php
get_footer();
