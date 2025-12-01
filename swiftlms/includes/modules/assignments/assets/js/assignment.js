/**
 * SwiftLMS Assignment JavaScript
 *
 * @package SwiftLMS
 */

(function($) {
    'use strict';

    /**
     * Assignment Module
     */
    const SwiftLMSAssignment = {
        /**
         * Initialize
         */
        init: function() {
            this.form = $('#sfls-assignment-form');
            this.submitBtn = this.form.find('.sfls-submit-btn');
            this.fileInput = this.form.find('#assignment_file');
            this.dropzone = this.form.find('#file-dropzone');
            this.selectedFile = this.form.find('#selected-file');

            if (this.form.length) {
                this.bindEvents();
                this.initWordCount();
            }
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;

            // Form submission
            this.form.on('submit', function(e) {
                e.preventDefault();
                self.submitAssignment();
            });

            // File input change
            this.fileInput.on('change', function() {
                self.handleFileSelect(this.files[0]);
            });

            // Drag and drop
            this.dropzone
                .on('dragover dragenter', function(e) {
                    e.preventDefault();
                    $(this).addClass('dragover');
                })
                .on('dragleave dragend drop', function(e) {
                    e.preventDefault();
                    $(this).removeClass('dragover');
                })
                .on('drop', function(e) {
                    var files = e.originalEvent.dataTransfer.files;
                    if (files.length) {
                        self.fileInput[0].files = files;
                        self.handleFileSelect(files[0]);
                    }
                });

            // Remove file
            this.form.on('click', '.sfls-remove-file', function() {
                self.fileInput.val('');
                self.selectedFile.hide();
                self.dropzone.show();
            });
        },

        /**
         * Handle file selection
         */
        handleFileSelect: function(file) {
            if (!file) return;

            this.selectedFile.find('.sfls-file-name').text(file.name);
            this.selectedFile.show();
            this.dropzone.hide();
        },

        /**
         * Initialize word count
         */
        initWordCount: function() {
            var self = this;
            var $counter = this.form.find('.sfls-current-word-count');

            if (!$counter.length) return;

            // Check if TinyMCE is available
            if (typeof tinyMCE !== 'undefined') {
                // Wait for TinyMCE to initialize
                setTimeout(function() {
                    var editor = tinyMCE.get('text_content');
                    if (editor) {
                        editor.on('keyup', function() {
                            self.updateWordCount($counter, editor.getContent({ format: 'text' }));
                        });
                    }
                }, 1000);
            }

            // Also track textarea (for text mode)
            $('#text_content').on('keyup', function() {
                self.updateWordCount($counter, $(this).val());
            });
        },

        /**
         * Update word count display
         */
        updateWordCount: function($counter, text) {
            var words = text.trim().split(/\s+/).filter(function(word) {
                return word.length > 0;
            }).length;

            $counter.text(swiftlms_assignment.i18n.current || 'Current: ' + words + ' words');
        },

        /**
         * Submit assignment
         */
        submitAssignment: function() {
            var self = this;

            // Validate
            if (!this.validateForm()) {
                return;
            }

            // Confirm submission
            if (!confirm(swiftlms_assignment.i18n.confirm_submit)) {
                return;
            }

            // Prepare form data
            var formData = new FormData(this.form[0]);
            formData.append('action', 'sfls_submit_assignment');
            formData.append('nonce', swiftlms_assignment.nonce);

            // Add TinyMCE content if available
            if (typeof tinyMCE !== 'undefined') {
                var editor = tinyMCE.get('text_content');
                if (editor) {
                    formData.set('text_content', editor.getContent());
                }
            }

            // Disable button
            this.submitBtn
                .prop('disabled', true)
                .html('<span class="dashicons dashicons-update sfls-spin"></span> ' + swiftlms_assignment.i18n.submitting);

            // Submit
            $.ajax({
                url: swiftlms_assignment.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        self.showNotice('success', response.data.message);
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        self.showNotice('error', response.data.message);
                        self.resetButton();
                    }
                },
                error: function() {
                    self.showNotice('error', 'An error occurred. Please try again.');
                    self.resetButton();
                }
            });
        },

        /**
         * Validate form
         */
        validateForm: function() {
            var type = this.form.data('type') || 'mixed';
            var hasFile = this.fileInput.length && this.fileInput[0].files.length > 0;
            var hasText = false;
            var hasUrl = false;

            // Check text content
            if (typeof tinyMCE !== 'undefined') {
                var editor = tinyMCE.get('text_content');
                if (editor) {
                    hasText = editor.getContent({ format: 'text' }).trim().length > 0;
                }
            }
            if (!hasText) {
                hasText = $('#text_content').val() && $('#text_content').val().trim().length > 0;
            }

            // Check URL
            hasUrl = $('#submission_url').val() && $('#submission_url').val().trim().length > 0;

            // Validate based on type
            if (type === 'file_upload' && !hasFile) {
                this.showNotice('error', swiftlms_assignment.i18n.file_required);
                return false;
            }

            if (type === 'text_entry' && !hasText) {
                this.showNotice('error', swiftlms_assignment.i18n.text_required);
                return false;
            }

            if (type === 'url_submission' && !hasUrl) {
                this.showNotice('error', swiftlms_assignment.i18n.url_required);
                return false;
            }

            // For mixed type, at least one should be filled
            if (type === 'mixed' && !hasFile && !hasText && !hasUrl) {
                this.showNotice('error', 'Please provide at least one form of submission.');
                return false;
            }

            return true;
        },

        /**
         * Reset submit button
         */
        resetButton: function() {
            this.submitBtn
                .prop('disabled', false)
                .html('<span class="dashicons dashicons-upload"></span> ' + swiftlms_assignment.i18n.submit);
        },

        /**
         * Show notice
         */
        showNotice: function(type, message) {
            // Remove existing notices
            $('.sfls-form-notice').remove();

            var $notice = $('<div class="sfls-form-notice sfls-notice-' + type + '">' +
                '<span class="dashicons dashicons-' + (type === 'success' ? 'yes-alt' : 'warning') + '"></span>' +
                '<span>' + message + '</span>' +
                '</div>');

            this.form.before($notice);

            // Scroll to notice
            $('html, body').animate({
                scrollTop: $notice.offset().top - 100
            }, 300);

            // Auto-remove success notices
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut(function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        }
    };

    // Add notice styles
    var styles = `
        .sfls-form-notice {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .sfls-notice-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .sfls-notice-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .sfls-form-notice .dashicons {
            font-size: 20px;
            width: 20px;
            height: 20px;
        }
        .sfls-spin {
            animation: sfls-spin 1s linear infinite;
        }
        @keyframes sfls-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    `;

    $('<style>').text(styles).appendTo('head');

    // Initialize on ready
    $(document).ready(function() {
        SwiftLMSAssignment.init();
    });

})(jQuery);
