/**
 * SwiftLMS Admin JavaScript
 *
 * @package SwiftLMS
 */

(function($) {
    'use strict';

    const SwiftLMSAdmin = {
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.initSortable();
            this.initModals();
            this.initEnrollments();
        },

        /**
         * Initialize sortable lists
         */
        initSortable: function() {
            $('.swiftlms-sortable-lessons, .swiftlms-sortable-topics').sortable({
                handle: '.swiftlms-drag-handle',
                placeholder: 'swiftlms-sortable-placeholder',
                update: this.handleSortUpdate.bind(this),
            });
        },

        /**
         * Handle sort order update
         */
        handleSortUpdate: function(event, ui) {
            const $list = $(event.target);
            const items = [];

            $list.find('li').each(function(index) {
                items.push({
                    id: $(this).data('lesson-id') || $(this).data('topic-id'),
                    order: index + 1,
                });
            });

            // Save order via AJAX
            $.ajax({
                url: swiftlmsAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'swiftlms_update_order',
                    nonce: swiftlmsAdmin.nonce,
                    items: items,
                },
            })
            .done(function(response) {
                if (response.success) {
                    console.log('Order updated');
                }
            })
            .fail(function() {
                alert(swiftlmsAdmin.i18n.error);
            });
        },

        /**
         * Initialize modals
         */
        initModals: function() {
            // Close modal on overlay click or close button
            $('.swiftlms-modal').on('click', function(e) {
                if ($(e.target).hasClass('swiftlms-modal') || $(e.target).hasClass('swiftlms-modal-close')) {
                    $(this).hide();
                }
            });

            // Close on escape
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('.swiftlms-modal').hide();
                }
            });
        },

        /**
         * Initialize enrollments page
         */
        initEnrollments: function() {
            if (!$('#swiftlms-enrollments-app').length) {
                return;
            }

            // Add enrollment button
            $('#swiftlms-add-enrollment').on('click', function() {
                $('#swiftlms-enrollment-modal').show();
            });

            // Filter button
            $('#swiftlms-apply-filters').on('click', this.loadEnrollments.bind(this));

            // Enrollment form
            $('#swiftlms-enrollment-form').on('submit', this.handleAddEnrollment.bind(this));

            // Initial load
            this.loadEnrollments();
        },

        /**
         * Load enrollments list
         */
        loadEnrollments: function() {
            const courseId = $('#swiftlms-course-filter').val();
            const status = $('#swiftlms-status-filter').val();
            const $tbody = $('#swiftlms-enrollments-table tbody');

            $tbody.html('<tr><td colspan="6">' + swiftlmsAdmin.i18n.loading + '</td></tr>');

            let url = swiftlmsAdmin.restUrl + 'admin/courses/';

            if (courseId) {
                url += courseId + '/enrollments';
            } else {
                // Need to get all enrollments - simplified for demo
                $tbody.html('<tr><td colspan="6">Please select a course to view enrollments.</td></tr>');
                return;
            }

            if (status) {
                url += '?status=' + status;
            }

            $.ajax({
                url: url,
                method: 'GET',
                headers: {
                    'X-WP-Nonce': swiftlmsAdmin.nonce,
                },
            })
            .done(function(response) {
                if (!response.length) {
                    $tbody.html('<tr><td colspan="6">No enrollments found.</td></tr>');
                    return;
                }

                let html = '';
                response.forEach(function(enrollment) {
                    html += '<tr>';
                    html += '<td><strong>' + enrollment.user_name + '</strong><br><small>' + enrollment.user_email + '</small></td>';
                    html += '<td>' + (enrollment.course_title || 'Course #' + enrollment.course_id) + '</td>';
                    html += '<td><span class="swiftlms-status-badge swiftlms-status-' + enrollment.status + '">' + enrollment.status + '</span></td>';
                    html += '<td>';
                    html += '<div class="swiftlms-progress-bar"><div class="swiftlms-progress-fill" style="width:' + enrollment.progress_percent + '%;"></div></div>';
                    html += '<span>' + Math.round(enrollment.progress_percent) + '%</span>';
                    html += '</td>';
                    html += '<td>' + enrollment.enrolled_at + '</td>';
                    html += '<td><button class="button button-small swiftlms-delete-enrollment" data-user="' + enrollment.user_id + '" data-course="' + courseId + '">Delete</button></td>';
                    html += '</tr>';
                });

                $tbody.html(html);

                // Bind delete buttons
                $tbody.find('.swiftlms-delete-enrollment').on('click', function() {
                    if (confirm(swiftlmsAdmin.i18n.confirmDelete)) {
                        const userId = $(this).data('user');
                        const courseId = $(this).data('course');
                        SwiftLMSAdmin.deleteEnrollment(userId, courseId, $(this).closest('tr'));
                    }
                });
            })
            .fail(function() {
                $tbody.html('<tr><td colspan="6">' + swiftlmsAdmin.i18n.error + '</td></tr>');
            });
        },

        /**
         * Handle add enrollment form
         */
        handleAddEnrollment: function(e) {
            e.preventDefault();

            const $form = $(e.target);
            const userId = $form.find('#enrollment-user').val();
            const courseId = $form.find('#enrollment-course').val();
            const expiresAt = $form.find('#enrollment-expires').val();

            if (!userId || !courseId) {
                alert('Please select a user and course.');
                return;
            }

            $.ajax({
                url: swiftlmsAdmin.restUrl + 'admin/enrollments',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': swiftlmsAdmin.nonce,
                },
                contentType: 'application/json',
                data: JSON.stringify({
                    user_id: parseInt(userId),
                    course_id: parseInt(courseId),
                    expires_at: expiresAt || null,
                }),
            })
            .done(function(response) {
                $('#swiftlms-enrollment-modal').hide();
                $form[0].reset();
                SwiftLMSAdmin.loadEnrollments();
            })
            .fail(function(xhr) {
                let message = swiftlmsAdmin.i18n.error;
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                alert(message);
            });
        },

        /**
         * Delete enrollment
         */
        deleteEnrollment: function(userId, courseId, $row) {
            $.ajax({
                url: swiftlmsAdmin.restUrl + 'admin/enrollments/' + userId + '/' + courseId,
                method: 'DELETE',
                headers: {
                    'X-WP-Nonce': swiftlmsAdmin.nonce,
                },
            })
            .done(function() {
                $row.fadeOut(function() {
                    $(this).remove();
                });
            })
            .fail(function(xhr) {
                let message = swiftlmsAdmin.i18n.error;
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                alert(message);
            });
        },
    };

    // Initialize on document ready
    $(document).ready(function() {
        SwiftLMSAdmin.init();
    });

    // Expose globally
    window.SwiftLMSAdmin = SwiftLMSAdmin;

})(jQuery);
