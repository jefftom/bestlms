/**
 * SwiftLMS Admin Submissions JavaScript
 *
 * @package SwiftLMS
 */

(function($) {
    'use strict';

    /**
     * Admin Submissions Module
     */
    const SubmissionsAdmin = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;

            // View submission
            $(document).on('click', '.sfls-view-submission', function(e) {
                e.preventDefault();
                var submissionId = $(this).data('id');
                self.viewSubmission(submissionId);
            });

            // Grade submission
            $(document).on('click', '.sfls-grade-submission', function(e) {
                e.preventDefault();
                var submissionId = $(this).data('id');
                self.openGradeModal(submissionId);
            });

            // Close modal
            $(document).on('click', '.sfls-modal-close, .sfls-modal-overlay', function() {
                self.closeModal();
            });

            // Submit grade
            $(document).on('submit', '#sfls-grade-form', function(e) {
                e.preventDefault();
                self.submitGrade($(this));
            });
        },

        /**
         * View submission details
         */
        viewSubmission: function(submissionId) {
            var self = this;

            // For now, show a simple modal with submission data
            // In production, this would fetch submission details via AJAX
            this.showModal(
                'Submission Details',
                '<p>Loading submission #' + submissionId + '...</p>',
                'view'
            );

            // Fetch submission data
            $.ajax({
                url: ajaxurl,
                data: {
                    action: 'sfls_get_submission',
                    submission_id: submissionId,
                    nonce: swiftlms_admin.nonce || ''
                },
                success: function(response) {
                    if (response.success) {
                        self.updateModalContent(self.buildSubmissionView(response.data));
                    } else {
                        self.updateModalContent('<p class="error">Failed to load submission.</p>');
                    }
                },
                error: function() {
                    self.updateModalContent('<p class="error">Failed to load submission.</p>');
                }
            });
        },

        /**
         * Build submission view HTML
         */
        buildSubmissionView: function(submission) {
            var html = '<div class="sfls-submission-view">';

            html += '<div class="sfls-view-row">';
            html += '<label>Student:</label>';
            html += '<span>' + (submission.display_name || 'Unknown') + '</span>';
            html += '</div>';

            html += '<div class="sfls-view-row">';
            html += '<label>Submitted:</label>';
            html += '<span>' + submission.submitted_at + '</span>';
            html += '</div>';

            if (submission.file_url) {
                html += '<div class="sfls-view-row">';
                html += '<label>File:</label>';
                html += '<a href="' + submission.file_url + '" target="_blank">' + submission.file_name + '</a>';
                html += '</div>';
            }

            if (submission.text_content) {
                html += '<div class="sfls-view-row">';
                html += '<label>Text Content:</label>';
                html += '<div class="sfls-text-content">' + submission.text_content + '</div>';
                html += '</div>';
            }

            if (submission.url_submission) {
                html += '<div class="sfls-view-row">';
                html += '<label>URL:</label>';
                html += '<a href="' + submission.url_submission + '" target="_blank">' + submission.url_submission + '</a>';
                html += '</div>';
            }

            if (submission.grade !== null) {
                html += '<div class="sfls-view-row">';
                html += '<label>Grade:</label>';
                html += '<span>' + submission.grade + ' (' + submission.grade_percentage + '%)</span>';
                html += '</div>';

                if (submission.feedback) {
                    html += '<div class="sfls-view-row">';
                    html += '<label>Feedback:</label>';
                    html += '<div class="sfls-feedback">' + submission.feedback + '</div>';
                    html += '</div>';
                }
            }

            html += '</div>';

            return html;
        },

        /**
         * Open grade modal
         */
        openGradeModal: function(submissionId) {
            var html = '<form id="sfls-grade-form" method="post">';
            html += '<input type="hidden" name="submission_id" value="' + submissionId + '">';
            html += '<input type="hidden" name="action" value="sfls_grade_submission">';
            html += wp.template('sfls-grade-nonce')({});

            html += '<div class="sfls-form-group">';
            html += '<label for="grade">Grade (Points)</label>';
            html += '<input type="number" name="grade" id="grade" min="0" step="0.1" required>';
            html += '</div>';

            html += '<div class="sfls-form-group">';
            html += '<label for="feedback">Feedback</label>';
            html += '<textarea name="feedback" id="feedback" rows="5"></textarea>';
            html += '</div>';

            html += '<div class="sfls-form-actions">';
            html += '<button type="button" class="button sfls-modal-close">Cancel</button>';
            html += '<button type="submit" class="button button-primary">Save Grade</button>';
            html += '</div>';

            html += '</form>';

            this.showModal('Grade Submission', html, 'grade');
        },

        /**
         * Submit grade
         */
        submitGrade: function($form) {
            var self = this;
            var $submitBtn = $form.find('button[type="submit"]');

            $submitBtn.prop('disabled', true).text('Saving...');

            // Submit form directly (form has action attribute)
            $form[0].submit();
        },

        /**
         * Show modal
         */
        showModal: function(title, content, type) {
            // Remove existing modal
            this.closeModal();

            var modal = '<div class="sfls-modal-overlay"></div>';
            modal += '<div class="sfls-modal sfls-modal-' + type + '">';
            modal += '<div class="sfls-modal-header">';
            modal += '<h2>' + title + '</h2>';
            modal += '<button type="button" class="sfls-modal-close">&times;</button>';
            modal += '</div>';
            modal += '<div class="sfls-modal-content">' + content + '</div>';
            modal += '</div>';

            $('body').append(modal);
        },

        /**
         * Update modal content
         */
        updateModalContent: function(content) {
            $('.sfls-modal-content').html(content);
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('.sfls-modal, .sfls-modal-overlay').remove();
        }
    };

    // Add modal styles
    var styles = `
        .sfls-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 100000;
        }
        .sfls-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            z-index: 100001;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow: auto;
        }
        .sfls-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid #ddd;
        }
        .sfls-modal-header h2 {
            margin: 0;
            font-size: 18px;
        }
        .sfls-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            line-height: 1;
        }
        .sfls-modal-close:hover {
            color: #000;
        }
        .sfls-modal-content {
            padding: 20px;
        }
        .sfls-view-row {
            margin-bottom: 16px;
        }
        .sfls-view-row label {
            display: block;
            font-weight: 600;
            margin-bottom: 4px;
            color: #23282d;
        }
        .sfls-text-content,
        .sfls-feedback {
            background: #f5f5f5;
            padding: 12px;
            border-radius: 4px;
            max-height: 200px;
            overflow-y: auto;
        }
        .sfls-form-group {
            margin-bottom: 16px;
        }
        .sfls-form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
        }
        .sfls-form-group input,
        .sfls-form-group textarea {
            width: 100%;
        }
        .sfls-form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 16px;
            border-top: 1px solid #ddd;
        }
    `;

    $('<style>').text(styles).appendTo('head');

    // Initialize on ready
    $(document).ready(function() {
        SubmissionsAdmin.init();
    });

})(jQuery);
