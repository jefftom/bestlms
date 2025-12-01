/**
 * SwiftLMS Google Docs Import JavaScript
 *
 * @package SwiftLMS
 */

(function($) {
    'use strict';

    var GoogleDocsImport = {
        currentDocId: null,
        previewData: null,

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

            // Tab switching
            $('.sfls-tab-btn').on('click', function() {
                var tab = $(this).data('tab');
                self.switchTab(tab);
            });

            // Search
            $('#sfls-search-btn').on('click', function() {
                self.searchDocs();
            });

            $('#sfls-doc-search').on('keypress', function(e) {
                if (e.which === 13) {
                    self.searchDocs();
                }
            });

            // Load on page load (recent docs)
            if ($('#sfls-search-results').length) {
                self.searchDocs();
            }

            // Load URL
            $('#sfls-load-url-btn').on('click', function() {
                self.loadFromUrl();
            });

            // Document selection
            $(document).on('click', '.sfls-doc-item', function() {
                var docId = $(this).data('doc-id');
                self.selectDocument(docId);
            });

            // Import
            $('#sfls-import-btn').on('click', function() {
                self.importDocument();
            });

            // Cancel
            $('#sfls-cancel-btn').on('click', function() {
                self.cancelImport();
            });

            // Disconnect
            $('#sfls-disconnect-btn').on('click', function() {
                self.disconnect();
            });

            // Save credentials
            $('#sfls-gdocs-credentials-form').on('submit', function(e) {
                e.preventDefault();
                self.saveCredentials();
            });

            // Reconfigure
            $('#sfls-reconfigure-btn').on('click', function(e) {
                e.preventDefault();
                // Clear credentials and reload
                self.ajax({
                    action: 'sfls_gdocs_disconnect'
                }).then(function() {
                    location.reload();
                });
            });

            // Copy redirect URI
            $('.sfls-copy-btn').on('click', function() {
                var text = $(this).data('copy');
                self.copyToClipboard(text);
                $(this).text('Copied!');
                setTimeout(function() {
                    $('.sfls-copy-btn').text('Copy');
                }, 2000);
            });

            // Split by change - refresh preview
            $('#split_by').on('change', function() {
                if (self.currentDocId) {
                    self.previewDocument(self.currentDocId);
                }
            });
        },

        /**
         * Switch tabs
         */
        switchTab: function(tab) {
            $('.sfls-tab-btn').removeClass('active');
            $('.sfls-tab-btn[data-tab="' + tab + '"]').addClass('active');
            $('.sfls-tab-content').removeClass('active');
            $('#tab-' + tab).addClass('active');
        },

        /**
         * Search documents
         */
        searchDocs: function() {
            var self = this;
            var query = $('#sfls-doc-search').val();
            var $results = $('#sfls-search-results');

            $results.html('<div class="sfls-loading"><div class="sfls-loading-spinner"></div></div>');

            this.ajax({
                action: 'sfls_gdocs_search',
                query: query
            }).then(function(response) {
                if (response.docs && response.docs.length > 0) {
                    self.renderDocList(response.docs);
                } else {
                    $results.html('<div class="sfls-no-results">' + swiftlms_gdocs.i18n.no_results + '</div>');
                }
            }).catch(function(error) {
                $results.html('<div class="sfls-no-results">' + error + '</div>');
            });
        },

        /**
         * Render document list
         */
        renderDocList: function(docs) {
            var html = '<div class="sfls-doc-list">';

            docs.forEach(function(doc) {
                var thumbnail = doc.thumbnail
                    ? '<img src="' + doc.thumbnail + '" alt="">'
                    : '<span class="dashicons dashicons-media-document"></span>';

                var date = doc.modified
                    ? new Date(doc.modified).toLocaleDateString()
                    : '';

                html += '<div class="sfls-doc-item" data-doc-id="' + doc.id + '">';
                html += '<div class="sfls-doc-thumbnail">' + thumbnail + '</div>';
                html += '<div class="sfls-doc-info">';
                html += '<div class="sfls-doc-name">' + doc.name + '</div>';
                html += '<div class="sfls-doc-date">' + date + '</div>';
                html += '</div>';
                html += '</div>';
            });

            html += '</div>';

            $('#sfls-search-results').html(html);
        },

        /**
         * Load from URL
         */
        loadFromUrl: function() {
            var url = $('#sfls-doc-url').val().trim();

            if (!url) {
                alert('Please enter a Google Docs URL');
                return;
            }

            this.previewDocument(null, url);
        },

        /**
         * Select document
         */
        selectDocument: function(docId) {
            $('.sfls-doc-item').removeClass('selected');
            $('.sfls-doc-item[data-doc-id="' + docId + '"]').addClass('selected');
            this.previewDocument(docId);
        },

        /**
         * Preview document
         */
        previewDocument: function(docId, docUrl) {
            var self = this;
            var $preview = $('#sfls-preview-section');
            var $content = $('#sfls-preview-content');

            this.currentDocId = docId;

            $preview.show();
            $content.html('<div class="sfls-loading"><div class="sfls-loading-spinner"></div><p>' + swiftlms_gdocs.i18n.loading + '</p></div>');

            this.ajax({
                action: 'sfls_gdocs_preview',
                doc_id: docId,
                doc_url: docUrl,
                split_by: $('#split_by').val()
            }).then(function(response) {
                self.currentDocId = response.doc_id;
                self.previewData = response;
                self.renderPreview(response);
            }).catch(function(error) {
                $content.html('<div class="sfls-import-error"><span class="dashicons dashicons-warning"></span><p>' + error + '</p></div>');
            });
        },

        /**
         * Render preview
         */
        renderPreview: function(data) {
            var html = '';

            // Header
            html += '<div class="sfls-preview-header">';
            html += '<div class="sfls-preview-title">';
            html += '<h4>' + data.title + '</h4>';
            if (data.preview.course.description) {
                html += '<p>' + data.preview.course.description + '</p>';
            }
            html += '</div>';
            html += '<div class="sfls-preview-stats">';
            html += '<div class="sfls-preview-stat"><span class="sfls-preview-stat-value">' + data.preview.totals.lessons + '</span><span class="sfls-preview-stat-label">Lessons</span></div>';
            html += '<div class="sfls-preview-stat"><span class="sfls-preview-stat-value">' + data.preview.totals.quizzes + '</span><span class="sfls-preview-stat-label">Quizzes</span></div>';
            html += '<div class="sfls-preview-stat"><span class="sfls-preview-stat-value">' + data.stats.word_count.toLocaleString() + '</span><span class="sfls-preview-stat-label">Words</span></div>';
            html += '</div>';
            html += '</div>';

            // Structure preview
            if (data.structure && data.structure.length > 0) {
                html += '<div class="sfls-structure-preview">';
                html += '<h4>Document Structure (Lessons)</h4>';
                html += '<ul class="sfls-structure-list">';

                data.structure.forEach(function(item, index) {
                    var level = item.level.replace('HEADING_', 'H');
                    var levelClass = 'sfls-structure-level-' + level.toLowerCase();
                    html += '<li class="sfls-structure-item">';
                    html += '<span class="sfls-structure-level ' + levelClass + '">' + level + '</span>';
                    html += '<span class="sfls-structure-title">' + item.title + '</span>';
                    html += '</li>';
                });

                html += '</ul>';
                html += '</div>';
            }

            // Lessons preview
            if (data.preview.lessons && data.preview.lessons.length > 0) {
                html += '<div class="sfls-lessons-preview">';
                html += '<h4>Lessons to Create</h4>';
                html += '<table class="widefat striped">';
                html += '<thead><tr><th>#</th><th>Title</th><th>Type</th><th>Words</th><th>Media</th></tr></thead>';
                html += '<tbody>';

                data.preview.lessons.forEach(function(lesson, index) {
                    var media = [];
                    if (lesson.has_images) media.push('Images');
                    if (lesson.has_video) media.push('Video');

                    html += '<tr>';
                    html += '<td>' + (index + 1) + '</td>';
                    html += '<td>' + lesson.title + '</td>';
                    html += '<td>' + lesson.type + '</td>';
                    html += '<td>' + lesson.word_count + '</td>';
                    html += '<td>' + (media.length > 0 ? media.join(', ') : '-') + '</td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';
                html += '</div>';
            }

            // Quizzes preview
            if (data.preview.quizzes && data.preview.quizzes.length > 0) {
                html += '<div class="sfls-quizzes-preview" style="margin-top: 20px;">';
                html += '<h4>Quizzes to Create</h4>';
                html += '<ul>';

                data.preview.quizzes.forEach(function(quiz) {
                    html += '<li><strong>' + quiz.title + '</strong> - ' + quiz.question_count + ' questions</li>';
                });

                html += '</ul>';
                html += '</div>';
            }

            $('#sfls-preview-content').html(html);
        },

        /**
         * Import document
         */
        importDocument: function() {
            var self = this;

            if (!this.currentDocId) {
                alert('Please select a document first');
                return;
            }

            if (!confirm(swiftlms_gdocs.i18n.confirm_import)) {
                return;
            }

            var $preview = $('#sfls-preview-section');
            var $progress = $('#sfls-import-progress');
            var $result = $('#sfls-import-result');

            $preview.hide();
            $progress.show();
            $result.hide();

            this.ajax({
                action: 'sfls_gdocs_import',
                doc_id: this.currentDocId,
                split_by: $('#split_by').val(),
                course_status: $('#course_status').val(),
                import_images: $('#import_images').is(':checked') ? 1 : 0,
                create_quizzes: $('#create_quizzes').is(':checked') ? 1 : 0
            }).then(function(response) {
                $progress.hide();
                self.renderResult(response, true);
            }).catch(function(error) {
                $progress.hide();
                self.renderResult({ message: error }, false);
            });
        },

        /**
         * Render import result
         */
        renderResult: function(response, success) {
            var $result = $('#sfls-import-result');
            var html = '';

            if (success) {
                html += '<div class="sfls-import-success">';
                html += '<span class="dashicons dashicons-yes-alt"></span>';
                html += '<h3>' + swiftlms_gdocs.i18n.success + '</h3>';

                if (response.result) {
                    html += '<div class="sfls-import-stats">';
                    html += '<div class="sfls-import-stat"><span class="sfls-import-stat-value">' + response.result.lessons + '</span><span class="sfls-import-stat-label">Lessons</span></div>';
                    html += '<div class="sfls-import-stat"><span class="sfls-import-stat-value">' + response.result.quizzes + '</span><span class="sfls-import-stat-label">Quizzes</span></div>';
                    html += '</div>';
                }

                html += '<div class="sfls-import-actions-result">';
                html += '<a href="' + response.course_url + '" class="button button-primary">Edit Course</a>';
                html += '<button type="button" class="button" onclick="location.reload()">Import Another</button>';
                html += '</div>';
                html += '</div>';
            } else {
                html += '<div class="sfls-import-error">';
                html += '<span class="dashicons dashicons-dismiss"></span>';
                html += '<h3>Import Failed</h3>';
                html += '<p>' + response.message + '</p>';

                if (response.errors && response.errors.length > 0) {
                    html += '<div class="sfls-error-list"><ul>';
                    response.errors.forEach(function(err) {
                        html += '<li>' + err + '</li>';
                    });
                    html += '</ul></div>';
                }

                html += '<button type="button" class="button" onclick="location.reload()">Try Again</button>';
                html += '</div>';
            }

            $result.html(html).show();
        },

        /**
         * Cancel import
         */
        cancelImport: function() {
            this.currentDocId = null;
            this.previewData = null;
            $('#sfls-preview-section').hide();
            $('#sfls-preview-content').empty();
            $('.sfls-doc-item').removeClass('selected');
            $('#sfls-doc-url').val('');
        },

        /**
         * Disconnect
         */
        disconnect: function() {
            if (!confirm('Are you sure you want to disconnect from Google?')) {
                return;
            }

            this.ajax({
                action: 'sfls_gdocs_disconnect'
            }).then(function() {
                location.reload();
            });
        },

        /**
         * Save credentials
         */
        saveCredentials: function() {
            var self = this;
            var $form = $('#sfls-gdocs-credentials-form');
            var $btn = $form.find('button[type="submit"]');

            $btn.prop('disabled', true).text(swiftlms_gdocs.i18n.loading);

            this.ajax({
                action: 'sfls_gdocs_save_credentials',
                client_id: $('#client_id').val(),
                client_secret: $('#client_secret').val()
            }).then(function() {
                location.reload();
            }).catch(function(error) {
                alert(error);
                $btn.prop('disabled', false).text('Save Credentials');
            });
        },

        /**
         * Copy to clipboard
         */
        copyToClipboard: function(text) {
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
        },

        /**
         * AJAX helper
         */
        ajax: function(data) {
            data.nonce = swiftlms_gdocs.nonce;

            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: swiftlms_gdocs.ajax_url,
                    type: 'POST',
                    data: data,
                    success: function(response) {
                        if (response.success) {
                            resolve(response.data);
                        } else {
                            reject(response.data.message || swiftlms_gdocs.i18n.error);
                        }
                    },
                    error: function() {
                        reject(swiftlms_gdocs.i18n.error);
                    }
                });
            });
        }
    };

    // Initialize on ready
    $(document).ready(function() {
        GoogleDocsImport.init();
    });

})(jQuery);
