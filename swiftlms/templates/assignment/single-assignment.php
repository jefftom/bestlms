<?php
/**
 * Single Assignment Template
 *
 * @package SwiftLMS
 */

defined( 'ABSPATH' ) || exit;

get_header();

$assignment_id = get_the_ID();
$settings      = \SwiftLMS\Modules\Assignments\Assignment::get_settings( $assignment_id );
$user_id       = get_current_user_id();
$is_logged_in  = is_user_logged_in();
$submissions   = $is_logged_in ? \SwiftLMS\Modules\Assignments\Submissions_Table::get_user_submissions( $assignment_id, $user_id ) : array();
$attempt_count = count( $submissions );
$can_submit    = $attempt_count < $settings['max_attempts'];
$latest        = ! empty( $submissions ) ? $submissions[0] : null;

// Due date info
$due_date      = $settings['due_date'];
$is_past_due   = $due_date && strtotime( $due_date ) < time();
$can_late      = $is_past_due && $settings['late_allowed'];
?>

<div class="sfls-assignment-single">
    <div class="sfls-assignment-container">
        <header class="sfls-assignment-header">
            <?php if ( $settings['course_id'] ) : ?>
                <div class="sfls-assignment-breadcrumb">
                    <a href="<?php echo esc_url( get_permalink( $settings['course_id'] ) ); ?>">
                        <?php echo esc_html( get_the_title( $settings['course_id'] ) ); ?>
                    </a>
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                    <span><?php esc_html_e( 'Assignment', 'swiftlms' ); ?></span>
                </div>
            <?php endif; ?>

            <h1 class="sfls-assignment-title"><?php the_title(); ?></h1>

            <div class="sfls-assignment-meta">
                <div class="sfls-meta-item">
                    <span class="dashicons dashicons-star-filled"></span>
                    <span><?php printf( esc_html__( '%d Points', 'swiftlms' ), $settings['points'] ); ?></span>
                </div>
                <?php if ( $due_date ) : ?>
                    <div class="sfls-meta-item <?php echo $is_past_due ? 'sfls-past-due' : ''; ?>">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <span>
                            <?php
                            printf(
                                esc_html__( 'Due: %s', 'swiftlms' ),
                                date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $due_date ) )
                            );
                            ?>
                        </span>
                        <?php if ( $is_past_due ) : ?>
                            <span class="sfls-past-due-badge"><?php esc_html_e( 'Past Due', 'swiftlms' ); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="sfls-meta-item">
                    <span class="dashicons dashicons-upload"></span>
                    <span><?php printf( esc_html__( 'Submission Type: %s', 'swiftlms' ), esc_html( \SwiftLMS\Modules\Assignments\Assignment::ASSIGNMENT_TYPES[ $settings['type'] ] ?? $settings['type'] ) ); ?></span>
                </div>
                <div class="sfls-meta-item">
                    <span class="dashicons dashicons-backup"></span>
                    <span><?php printf( esc_html__( 'Attempts: %d/%d', 'swiftlms' ), $attempt_count, $settings['max_attempts'] ); ?></span>
                </div>
            </div>
        </header>

        <div class="sfls-assignment-content">
            <div class="sfls-assignment-main">
                <div class="sfls-content-section">
                    <h2><?php esc_html_e( 'Instructions', 'swiftlms' ); ?></h2>
                    <div class="sfls-assignment-description">
                        <?php the_content(); ?>
                    </div>

                    <?php if ( $settings['instructions'] ) : ?>
                        <div class="sfls-special-instructions">
                            <h3><?php esc_html_e( 'Special Instructions', 'swiftlms' ); ?></h3>
                            <?php echo wp_kses_post( wpautop( $settings['instructions'] ) ); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ( ! $is_logged_in ) : ?>
                    <div class="sfls-login-notice">
                        <span class="dashicons dashicons-lock"></span>
                        <p><?php esc_html_e( 'Please log in to submit this assignment.', 'swiftlms' ); ?></p>
                        <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="sfls-primary-btn">
                            <?php esc_html_e( 'Log In', 'swiftlms' ); ?>
                        </a>
                    </div>
                <?php elseif ( ! $can_submit && $latest && $latest->status !== 'graded' ) : ?>
                    <div class="sfls-max-attempts-notice">
                        <span class="dashicons dashicons-info"></span>
                        <p><?php esc_html_e( 'You have reached the maximum number of attempts. Your submission is pending review.', 'swiftlms' ); ?></p>
                    </div>
                <?php elseif ( $is_past_due && ! $can_late ) : ?>
                    <div class="sfls-past-due-notice">
                        <span class="dashicons dashicons-warning"></span>
                        <p><?php esc_html_e( 'This assignment is past due and no longer accepting submissions.', 'swiftlms' ); ?></p>
                    </div>
                <?php elseif ( $can_submit ) : ?>
                    <div class="sfls-submission-form-section">
                        <h2><?php esc_html_e( 'Submit Your Work', 'swiftlms' ); ?></h2>

                        <?php if ( $can_late ) : ?>
                            <div class="sfls-late-warning">
                                <span class="dashicons dashicons-warning"></span>
                                <p>
                                    <?php
                                    printf(
                                        esc_html__( 'This assignment is past due. Late submissions will receive a %d%% penalty.', 'swiftlms' ),
                                        $settings['late_penalty']
                                    );
                                    ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <form id="sfls-assignment-form" class="sfls-assignment-form" enctype="multipart/form-data">
                            <input type="hidden" name="assignment_id" value="<?php echo esc_attr( $assignment_id ); ?>">

                            <?php if ( in_array( $settings['type'], array( 'file_upload', 'mixed' ), true ) ) : ?>
                                <div class="sfls-form-group sfls-file-upload-group">
                                    <label for="assignment_file"><?php esc_html_e( 'Upload File', 'swiftlms' ); ?></label>
                                    <div class="sfls-file-dropzone" id="file-dropzone">
                                        <span class="dashicons dashicons-cloud-upload"></span>
                                        <p><?php esc_html_e( 'Drag & drop your file here or click to browse', 'swiftlms' ); ?></p>
                                        <span class="sfls-file-info">
                                            <?php
                                            printf(
                                                esc_html__( 'Allowed: %s (Max %dMB)', 'swiftlms' ),
                                                esc_html( $settings['allowed_files'] ),
                                                $settings['max_file_size']
                                            );
                                            ?>
                                        </span>
                                        <input type="file" name="file" id="assignment_file" class="sfls-file-input">
                                    </div>
                                    <div class="sfls-selected-file" id="selected-file" style="display: none;">
                                        <span class="dashicons dashicons-media-default"></span>
                                        <span class="sfls-file-name"></span>
                                        <button type="button" class="sfls-remove-file" title="<?php esc_attr_e( 'Remove', 'swiftlms' ); ?>">
                                            <span class="dashicons dashicons-no-alt"></span>
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ( in_array( $settings['type'], array( 'text_entry', 'mixed' ), true ) ) : ?>
                                <div class="sfls-form-group sfls-text-entry-group">
                                    <label for="text_content"><?php esc_html_e( 'Text Submission', 'swiftlms' ); ?></label>
                                    <?php
                                    wp_editor(
                                        '',
                                        'text_content',
                                        array(
                                            'media_buttons' => false,
                                            'textarea_rows' => 10,
                                            'teeny'         => true,
                                        )
                                    );
                                    ?>
                                    <?php if ( $settings['min_words'] || $settings['max_words'] ) : ?>
                                        <div class="sfls-word-count-info">
                                            <?php if ( $settings['min_words'] ) : ?>
                                                <span><?php printf( esc_html__( 'Minimum: %d words', 'swiftlms' ), $settings['min_words'] ); ?></span>
                                            <?php endif; ?>
                                            <?php if ( $settings['max_words'] ) : ?>
                                                <span><?php printf( esc_html__( 'Maximum: %d words', 'swiftlms' ), $settings['max_words'] ); ?></span>
                                            <?php endif; ?>
                                            <span class="sfls-current-word-count"><?php esc_html_e( 'Current: 0 words', 'swiftlms' ); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ( in_array( $settings['type'], array( 'url_submission', 'mixed' ), true ) ) : ?>
                                <div class="sfls-form-group sfls-url-group">
                                    <label for="submission_url"><?php esc_html_e( 'URL / Link', 'swiftlms' ); ?></label>
                                    <input type="url" name="url" id="submission_url" placeholder="https://example.com/your-work">
                                    <p class="sfls-field-description"><?php esc_html_e( 'Enter the URL to your submitted work.', 'swiftlms' ); ?></p>
                                </div>
                            <?php endif; ?>

                            <div class="sfls-form-actions">
                                <button type="submit" class="sfls-primary-btn sfls-submit-btn">
                                    <span class="dashicons dashicons-upload"></span>
                                    <?php esc_html_e( 'Submit Assignment', 'swiftlms' ); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ( $is_logged_in && ! empty( $submissions ) ) : ?>
                <aside class="sfls-assignment-sidebar">
                    <div class="sfls-submissions-history">
                        <h3><?php esc_html_e( 'Your Submissions', 'swiftlms' ); ?></h3>

                        <?php foreach ( $submissions as $submission ) : ?>
                            <div class="sfls-submission-card sfls-status-<?php echo esc_attr( $submission->status ); ?>">
                                <div class="sfls-submission-header">
                                    <span class="sfls-attempt-number">
                                        <?php printf( esc_html__( 'Attempt #%d', 'swiftlms' ), $submission->attempt_number ); ?>
                                    </span>
                                    <span class="sfls-submission-status">
                                        <?php echo esc_html( ucfirst( $submission->status ) ); ?>
                                    </span>
                                </div>

                                <div class="sfls-submission-date">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                    <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $submission->submitted_at ) ) ); ?>
                                    <?php if ( $submission->is_late ) : ?>
                                        <span class="sfls-late-badge"><?php esc_html_e( 'Late', 'swiftlms' ); ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if ( $submission->file_name ) : ?>
                                    <div class="sfls-submission-file">
                                        <span class="dashicons dashicons-media-default"></span>
                                        <a href="<?php echo esc_url( $submission->file_url ); ?>" target="_blank">
                                            <?php echo esc_html( $submission->file_name ); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <?php if ( $submission->status === 'graded' ) : ?>
                                    <div class="sfls-submission-grade">
                                        <div class="sfls-grade-display">
                                            <span class="sfls-grade-value"><?php echo esc_html( number_format( $submission->grade, 1 ) ); ?></span>
                                            <span class="sfls-grade-total">/ <?php echo esc_html( $settings['points'] ); ?></span>
                                        </div>
                                        <div class="sfls-grade-percentage">
                                            <?php echo esc_html( number_format( $submission->grade_percentage, 1 ) ); ?>%
                                        </div>
                                    </div>

                                    <?php if ( $submission->feedback ) : ?>
                                        <div class="sfls-submission-feedback">
                                            <h4><?php esc_html_e( 'Instructor Feedback', 'swiftlms' ); ?></h4>
                                            <?php echo wp_kses_post( wpautop( $submission->feedback ) ); ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </aside>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
get_footer();
