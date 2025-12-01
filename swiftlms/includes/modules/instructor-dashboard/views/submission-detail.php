<?php
/**
 * Instructor Dashboard - Submission Detail View
 *
 * @package SwiftLMS\Modules\InstructorDashboard
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap sfls-instructor-wrap">
    <h1>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=sfls-instructor-submissions' ) ); ?>" class="sfls-back-link">
            &larr; <?php esc_html_e( 'Back to Submissions', 'swiftlms' ); ?>
        </a>
    </h1>

    <div class="sfls-submission-detail">
        <!-- Student Info -->
        <div class="sfls-detail-header">
            <div class="sfls-student-profile">
                <?php echo get_avatar( $submission->student_id, 64 ); ?>
                <div class="sfls-student-details">
                    <h2><?php echo esc_html( $submission->student_name ); ?></h2>
                    <p><?php echo esc_html( $submission->user_email ); ?></p>
                </div>
            </div>
            <div class="sfls-submission-meta">
                <div class="sfls-meta-item">
                    <span class="sfls-meta-label"><?php esc_html_e( 'Submitted', 'swiftlms' ); ?></span>
                    <span class="sfls-meta-value"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $submission->submitted_at ) ) ); ?></span>
                </div>
                <div class="sfls-meta-item">
                    <span class="sfls-meta-label"><?php esc_html_e( 'Status', 'swiftlms' ); ?></span>
                    <span class="sfls-status-badge sfls-status-<?php echo esc_attr( $submission->status ); ?>">
                        <?php echo esc_html( ucfirst( $submission->status ) ); ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="sfls-detail-columns">
            <!-- Left: Submission Content -->
            <div class="sfls-detail-main">
                <div class="sfls-card">
                    <div class="sfls-card-header">
                        <h3><?php echo esc_html( $submission->assignment_title ); ?></h3>
                    </div>
                    <div class="sfls-card-body">
                        <?php
                        // Show assignment instructions.
                        $instructions = get_post_meta( $submission->assignment_id, '_sfls_instructions', true );
                        if ( $instructions ) :
                        ?>
                        <div class="sfls-assignment-instructions">
                            <h4><?php esc_html_e( 'Assignment Instructions', 'swiftlms' ); ?></h4>
                            <?php echo wp_kses_post( $instructions ); ?>
                        </div>
                        <hr>
                        <?php endif; ?>

                        <div class="sfls-submission-content">
                            <h4><?php esc_html_e( 'Student Submission', 'swiftlms' ); ?></h4>

                            <?php if ( $submission->submission_type === 'text' || ! empty( $submission->content ) ) : ?>
                                <div class="sfls-text-submission">
                                    <?php echo wp_kses_post( $submission->content ); ?>
                                </div>
                            <?php endif; ?>

                            <?php if ( $submission->submission_type === 'url' || ! empty( $submission->url ) ) : ?>
                                <div class="sfls-url-submission">
                                    <strong><?php esc_html_e( 'Submitted URL:', 'swiftlms' ); ?></strong>
                                    <a href="<?php echo esc_url( $submission->url ); ?>" target="_blank" rel="noopener">
                                        <?php echo esc_html( $submission->url ); ?>
                                    </a>
                                </div>
                            <?php endif; ?>

                            <?php
                            $files = maybe_unserialize( $submission->files );
                            if ( ! empty( $files ) && is_array( $files ) ) :
                            ?>
                                <div class="sfls-file-submission">
                                    <strong><?php esc_html_e( 'Submitted Files:', 'swiftlms' ); ?></strong>
                                    <ul class="sfls-file-list">
                                        <?php foreach ( $files as $file ) : ?>
                                            <li>
                                                <span class="dashicons dashicons-media-default"></span>
                                                <a href="<?php echo esc_url( $file['url'] ); ?>" target="_blank">
                                                    <?php echo esc_html( $file['name'] ); ?>
                                                </a>
                                                <span class="sfls-file-size">(<?php echo esc_html( size_format( $file['size'] ?? 0 ) ); ?>)</span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Grading Form -->
            <div class="sfls-detail-sidebar">
                <div class="sfls-card sfls-grading-card">
                    <div class="sfls-card-header">
                        <h3><?php esc_html_e( 'Grade Submission', 'swiftlms' ); ?></h3>
                    </div>
                    <div class="sfls-card-body">
                        <form id="sfls-grading-form" class="sfls-grading-form">
                            <input type="hidden" name="submission_id" value="<?php echo esc_attr( $submission->id ); ?>">
                            <?php wp_nonce_field( 'sfls_instructor_nonce', 'nonce' ); ?>

                            <div class="sfls-form-field">
                                <label for="grade"><?php esc_html_e( 'Grade', 'swiftlms' ); ?></label>
                                <div class="sfls-grade-input">
                                    <input type="number" id="grade" name="grade" min="0" max="<?php echo esc_attr( $max_points ); ?>" step="0.5" value="<?php echo esc_attr( $submission->grade ?: '' ); ?>" required>
                                    <span class="sfls-grade-max">/ <?php echo esc_html( $max_points ); ?></span>
                                </div>
                                <div class="sfls-grade-percentage" id="grade-percentage"></div>
                            </div>

                            <div class="sfls-form-field">
                                <label for="status"><?php esc_html_e( 'Status', 'swiftlms' ); ?></label>
                                <select id="status" name="status">
                                    <option value="graded" <?php selected( $submission->status, 'graded' ); ?>><?php esc_html_e( 'Graded', 'swiftlms' ); ?></option>
                                    <option value="needs_revision" <?php selected( $submission->status, 'needs_revision' ); ?>><?php esc_html_e( 'Needs Revision', 'swiftlms' ); ?></option>
                                    <option value="rejected" <?php selected( $submission->status, 'rejected' ); ?>><?php esc_html_e( 'Rejected', 'swiftlms' ); ?></option>
                                </select>
                            </div>

                            <div class="sfls-form-field">
                                <label for="feedback"><?php esc_html_e( 'Feedback', 'swiftlms' ); ?></label>
                                <textarea id="feedback" name="feedback" rows="6" placeholder="<?php esc_attr_e( 'Provide feedback to the student...', 'swiftlms' ); ?>"><?php echo esc_textarea( $submission->feedback ?: '' ); ?></textarea>
                            </div>

                            <div class="sfls-form-actions">
                                <button type="submit" class="button button-primary button-large">
                                    <?php esc_html_e( 'Submit Grade', 'swiftlms' ); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Quick Feedback Templates -->
                <div class="sfls-card">
                    <div class="sfls-card-header">
                        <h3><?php esc_html_e( 'Quick Feedback', 'swiftlms' ); ?></h3>
                    </div>
                    <div class="sfls-card-body">
                        <div class="sfls-quick-feedback">
                            <button type="button" class="sfls-feedback-btn" data-feedback="<?php esc_attr_e( 'Excellent work! Your submission demonstrates a thorough understanding of the material.', 'swiftlms' ); ?>">
                                <?php esc_html_e( 'Excellent', 'swiftlms' ); ?>
                            </button>
                            <button type="button" class="sfls-feedback-btn" data-feedback="<?php esc_attr_e( 'Good effort! Your work shows solid understanding with some room for improvement.', 'swiftlms' ); ?>">
                                <?php esc_html_e( 'Good', 'swiftlms' ); ?>
                            </button>
                            <button type="button" class="sfls-feedback-btn" data-feedback="<?php esc_attr_e( 'Your submission needs more detail. Please review the instructions and consider resubmitting.', 'swiftlms' ); ?>">
                                <?php esc_html_e( 'Needs Work', 'swiftlms' ); ?>
                            </button>
                            <button type="button" class="sfls-feedback-btn" data-feedback="<?php esc_attr_e( 'Please review the assignment requirements and resubmit your work.', 'swiftlms' ); ?>">
                                <?php esc_html_e( 'Resubmit', 'swiftlms' ); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var maxPoints = <?php echo (int) $max_points; ?>;

    // Calculate percentage on grade input
    $('#grade').on('input', function() {
        var grade = parseFloat($(this).val()) || 0;
        var percentage = Math.round((grade / maxPoints) * 100);
        $('#grade-percentage').text(percentage + '%');
    }).trigger('input');

    // Quick feedback buttons
    $('.sfls-feedback-btn').on('click', function() {
        var feedback = $(this).data('feedback');
        var $textarea = $('#feedback');
        var current = $textarea.val();
        $textarea.val(current ? current + '\n\n' + feedback : feedback);
    });

    // Form submission
    $('#sfls-grading-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');

        if (!confirm(swiftlms_instructor.i18n.confirm_grade)) {
            return;
        }

        $btn.prop('disabled', true).text(swiftlms_instructor.i18n.loading);

        $.ajax({
            url: swiftlms_instructor.ajax_url,
            type: 'POST',
            data: {
                action: 'sfls_instructor_grade_submission',
                nonce: $form.find('[name="nonce"]').val(),
                submission_id: $form.find('[name="submission_id"]').val(),
                grade: $form.find('[name="grade"]').val(),
                status: $form.find('[name="status"]').val(),
                feedback: $form.find('[name="feedback"]').val()
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=sfls-instructor-submissions' ) ); ?>';
                } else {
                    alert(response.data.message || swiftlms_instructor.i18n.error);
                    $btn.prop('disabled', false).text('<?php esc_html_e( 'Submit Grade', 'swiftlms' ); ?>');
                }
            },
            error: function() {
                alert(swiftlms_instructor.i18n.error);
                $btn.prop('disabled', false).text('<?php esc_html_e( 'Submit Grade', 'swiftlms' ); ?>');
            }
        });
    });
});
</script>
