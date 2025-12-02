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

    /**
     * Google Sheets Import Module
     */
    var SheetsImport = {
        spreadsheetId: null,
        sheets: [],
        columnMap: {},
        typeConfig: null,
        headers: [],

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

            // Mode switching
            $('.sfls-primary-tab').on('click', function() {
                var mode = $(this).data('mode');
                self.switchMode(mode);
            });

            // Load spreadsheet
            $('#sfls-load-sheets-btn').on('click', function() {
                self.loadSpreadsheet();
            });

            $('#sfls-sheets-url').on('keypress', function(e) {
                if (e.which === 13) {
                    self.loadSpreadsheet();
                }
            });

            // Change spreadsheet
            $('#sfls-sheets-change').on('click', function() {
                self.resetSheets();
            });

            // Import type change
            $('#sfls-import-type').on('change', function() {
                var type = $(this).val();
                self.onTypeChange(type);
            });

            // Preview
            $('#sfls-sheets-preview-btn').on('click', function() {
                self.previewData();
            });

            // Import
            $('#sfls-sheets-import-btn').on('click', function() {
                self.importData();
            });

            // Cancel
            $('#sfls-sheets-cancel-btn').on('click', function() {
                self.cancelPreview();
            });
        },

        /**
         * Switch between Docs and Sheets mode
         */
        switchMode: function(mode) {
            $('.sfls-primary-tab').removeClass('active');
            $('.sfls-primary-tab[data-mode="' + mode + '"]').addClass('active');
            $('.sfls-mode-content').removeClass('active');
            $('#sfls-mode-' + mode).addClass('active');
        },

        /**
         * Load spreadsheet metadata
         */
        loadSpreadsheet: function() {
            var self = this;
            var url = $('#sfls-sheets-url').val().trim();

            if (!url) {
                alert('Please enter a Google Sheets URL');
                return;
            }

            var $btn = $('#sfls-load-sheets-btn');
            $btn.prop('disabled', true).text(swiftlms_gdocs.i18n.loading);

            this.ajax({
                action: 'sfls_sheets_load',
                url: url
            }).then(function(response) {
                self.spreadsheetId = response.spreadsheet_id;
                self.sheets = response.sheets;
                self.renderSheetsConfig(response);
                $btn.prop('disabled', false).text('Load Spreadsheet');
            }).catch(function(error) {
                alert(error);
                $btn.prop('disabled', false).text('Load Spreadsheet');
            });
        },

        /**
         * Render sheets configuration
         */
        renderSheetsConfig: function(data) {
            $('#sfls-sheets-title').text(data.title);

            // Populate sheets dropdown
            var $select = $('#sfls-sheet-select');
            $select.empty();
            data.sheets.forEach(function(sheet) {
                $select.append('<option value="' + sheet.title + '">' + sheet.title + '</option>');
            });

            // Show config, hide URL input
            $('.sfls-sheets-url-input').hide();
            $('#sfls-sheets-config').show();
        },

        /**
         * Reset sheets selection
         */
        resetSheets: function() {
            this.spreadsheetId = null;
            this.sheets = [];
            this.columnMap = {};
            $('#sfls-sheets-url').val('');
            $('#sfls-sheets-config').hide();
            $('#sfls-column-mapping').hide();
            $('#sfls-sheets-preview').hide();
            $('#sfls-sheets-result').hide();
            $('.sfls-sheets-url-input').show();
        },

        /**
         * Handle import type change
         */
        onTypeChange: function(type) {
            if (!type) {
                $('#sfls-sheets-preview-btn').prop('disabled', true);
                $('#sfls-download-template').hide();
                return;
            }

            $('#sfls-sheets-preview-btn').prop('disabled', false);

            // Update template download link
            var templateUrl = swiftlms_gdocs.ajax_url + '?action=sfls_sheets_template&type=' + type + '&nonce=' + swiftlms_gdocs.nonce;
            $('#sfls-download-template').attr('href', templateUrl).show();
        },

        /**
         * Preview sheet data
         */
        previewData: function() {
            var self = this;
            var importType = $('#sfls-import-type').val();
            var sheetName = $('#sfls-sheet-select').val();

            if (!importType) {
                alert('Please select an import type');
                return;
            }

            var $btn = $('#sfls-sheets-preview-btn');
            $btn.prop('disabled', true).text(swiftlms_gdocs.i18n.loading);

            this.ajax({
                action: 'sfls_sheets_preview',
                spreadsheet_id: this.spreadsheetId,
                sheet_name: sheetName,
                import_type: importType
            }).then(function(response) {
                self.headers = response.headers;
                self.columnMap = response.column_map;
                self.typeConfig = response.type_config;
                self.renderColumnMapping(response);
                self.renderPreview(response);
                $btn.prop('disabled', false).text('Preview Data');
            }).catch(function(error) {
                alert(error);
                $btn.prop('disabled', false).text('Preview Data');
            });
        },

        /**
         * Render column mapping UI
         */
        renderColumnMapping: function(data) {
            var self = this;
            var $fields = $('#sfls-mapping-fields');
            $fields.empty();

            var requiredFields = data.type_config.required || [];
            var optionalFields = data.type_config.optional || [];
            var allFields = requiredFields.concat(optionalFields);

            allFields.forEach(function(field) {
                var isRequired = requiredFields.indexOf(field) !== -1;
                var mappedColumn = data.column_map[field] || '';
                var isMapped = mappedColumn !== '';

                var html = '<div class="sfls-mapping-field ' + (isMapped ? 'mapped' : (isRequired ? 'missing' : '')) + '">';
                html += '<label>' + self.formatFieldName(field);
                if (isRequired) {
                    html += ' <span class="required">*</span>';
                }
                html += '</label>';
                html += '<select data-field="' + field + '">';
                html += '<option value="">— Not mapped —</option>';

                data.headers.forEach(function(header, index) {
                    var selected = mappedColumn === header ? ' selected' : '';
                    html += '<option value="' + header + '"' + selected + '>' + header + '</option>';
                });

                html += '</select>';
                html += '</div>';

                $fields.append(html);
            });

            // Handle mapping changes
            $fields.find('select').on('change', function() {
                var field = $(this).data('field');
                var value = $(this).val();
                self.columnMap[field] = value;

                var $parent = $(this).closest('.sfls-mapping-field');
                $parent.removeClass('mapped missing');
                if (value) {
                    $parent.addClass('mapped');
                } else if (requiredFields.indexOf(field) !== -1) {
                    $parent.addClass('missing');
                }
            });

            $('#sfls-column-mapping').show();
        },

        /**
         * Format field name for display
         */
        formatFieldName: function(field) {
            return field.split('_').map(function(word) {
                return word.charAt(0).toUpperCase() + word.slice(1);
            }).join(' ');
        },

        /**
         * Render data preview
         */
        renderPreview: function(data) {
            var validation = data.validation;
            var $summary = $('#sfls-sheets-preview .sfls-validation-summary');

            // Validation summary
            var validCount = validation.valid_rows.length;
            var errorCount = validation.errors.length;
            var totalCount = validCount + errorCount;

            if (errorCount === 0) {
                $summary.removeClass('has-errors').addClass('valid');
                $summary.html('<span class="dashicons dashicons-yes-alt sfls-summary-icon"></span> All ' + validCount + ' rows are valid and ready to import.');
            } else {
                $summary.removeClass('valid').addClass('has-errors');
                var summaryHtml = '<span class="dashicons dashicons-warning sfls-summary-icon"></span> ';
                summaryHtml += validCount + ' valid rows, ' + errorCount + ' rows with errors.';
                summaryHtml += '<div class="sfls-validation-details"><ul>';
                validation.errors.slice(0, 5).forEach(function(err) {
                    summaryHtml += '<li>Row ' + err.row + ': ' + err.message + '</li>';
                });
                if (validation.errors.length > 5) {
                    summaryHtml += '<li>... and ' + (validation.errors.length - 5) + ' more errors</li>';
                }
                summaryHtml += '</ul></div>';
                $summary.html(summaryHtml);
            }

            // Preview table
            var $table = $('#sfls-preview-table');
            var tableHtml = '<thead><tr><th>Status</th>';

            // Use mapped fields as columns
            Object.keys(this.columnMap).forEach(function(field) {
                if (this.columnMap[field]) {
                    tableHtml += '<th>' + this.formatFieldName(field) + '</th>';
                }
            }.bind(this));
            tableHtml += '</tr></thead><tbody>';

            // Sample rows
            data.sample_rows.forEach(function(row, index) {
                var isValid = !validation.errors.find(function(e) { return e.row === (index + 2); });
                tableHtml += '<tr class="' + (isValid ? 'valid' : 'invalid') + '">';
                tableHtml += '<td><span class="sfls-row-status ' + (isValid ? 'valid' : 'invalid') + '">' + (isValid ? 'Valid' : 'Error') + '</span></td>';

                Object.keys(this.columnMap).forEach(function(field) {
                    if (this.columnMap[field]) {
                        tableHtml += '<td>' + (row[field] || '-') + '</td>';
                    }
                }.bind(this));

                tableHtml += '</tr>';
            }.bind(this));

            tableHtml += '</tbody>';
            $table.html(tableHtml);

            // Import options based on type
            this.renderImportOptions();

            $('#sfls-sheets-preview').show();
        },

        /**
         * Render import options based on type
         */
        renderImportOptions: function() {
            var type = $('#sfls-import-type').val();
            var $options = $('#sfls-sheets-options');
            $options.empty();

            var optionsHtml = '';

            if (type === 'students') {
                optionsHtml += '<div class="sfls-option-field sfls-checkbox-field"><label><input type="checkbox" id="opt-send-welcome" checked> Send welcome email</label></div>';
                optionsHtml += '<div class="sfls-option-field sfls-checkbox-field"><label><input type="checkbox" id="opt-generate-password" checked> Auto-generate passwords</label></div>';
            } else if (type === 'enrollments') {
                optionsHtml += '<div class="sfls-option-field sfls-checkbox-field"><label><input type="checkbox" id="opt-send-notification" checked> Send enrollment notification</label></div>';
            } else if (type === 'courses' || type === 'lessons') {
                optionsHtml += '<div class="sfls-option-field"><label>Status</label><select id="opt-status"><option value="draft">Draft</option><option value="publish">Published</option></select></div>';
            } else if (type === 'quiz_questions') {
                optionsHtml += '<div class="sfls-option-field sfls-checkbox-field"><label><input type="checkbox" id="opt-randomize"> Randomize answers</label></div>';
            }

            optionsHtml += '<div class="sfls-option-field sfls-checkbox-field"><label><input type="checkbox" id="opt-skip-existing" checked> Skip existing records</label></div>';

            $options.html(optionsHtml);
        },

        /**
         * Cancel preview
         */
        cancelPreview: function() {
            $('#sfls-column-mapping').hide();
            $('#sfls-sheets-preview').hide();
        },

        /**
         * Import data
         */
        importData: function() {
            var self = this;
            var importType = $('#sfls-import-type').val();
            var sheetName = $('#sfls-sheet-select').val();

            if (!confirm('Are you sure you want to import this data?')) {
                return;
            }

            // Gather options
            var options = {
                skip_existing: $('#opt-skip-existing').is(':checked'),
                send_welcome: $('#opt-send-welcome').is(':checked'),
                generate_password: $('#opt-generate-password').is(':checked'),
                send_notification: $('#opt-send-notification').is(':checked'),
                status: $('#opt-status').val(),
                randomize: $('#opt-randomize').is(':checked')
            };

            $('#sfls-sheets-preview').hide();
            $('#sfls-sheets-progress').show();

            this.ajax({
                action: 'sfls_sheets_import',
                spreadsheet_id: this.spreadsheetId,
                sheet_name: sheetName,
                import_type: importType,
                column_map: JSON.stringify(this.columnMap),
                options: JSON.stringify(options)
            }).then(function(response) {
                $('#sfls-sheets-progress').hide();
                self.renderResult(response);
            }).catch(function(error) {
                $('#sfls-sheets-progress').hide();
                self.renderResult({ message: error, stats: { created: 0, updated: 0, skipped: 0, errors: 1 } }, true);
            });
        },

        /**
         * Render import result
         */
        renderResult: function(response, isError) {
            var $result = $('#sfls-sheets-result');
            var html = '';

            if (!isError) {
                html += '<div class="sfls-import-success">';
                html += '<span class="dashicons dashicons-yes-alt"></span>';
                html += '<h3>Import Complete!</h3>';
                html += '<p>' + response.message + '</p>';

                if (response.stats) {
                    html += '<div class="sfls-sheets-result-stats">';
                    html += '<div class="sfls-result-stat"><span class="sfls-result-stat-value created">' + response.stats.created + '</span><span class="sfls-result-stat-label">Created</span></div>';
                    html += '<div class="sfls-result-stat"><span class="sfls-result-stat-value updated">' + response.stats.updated + '</span><span class="sfls-result-stat-label">Updated</span></div>';
                    html += '<div class="sfls-result-stat"><span class="sfls-result-stat-value skipped">' + response.stats.skipped + '</span><span class="sfls-result-stat-label">Skipped</span></div>';
                    html += '<div class="sfls-result-stat"><span class="sfls-result-stat-value errors">' + response.stats.errors + '</span><span class="sfls-result-stat-label">Errors</span></div>';
                    html += '</div>';
                }

                if (response.log && response.log.length > 0) {
                    html += '<div class="sfls-import-log">';
                    response.log.slice(-20).forEach(function(entry) {
                        var logClass = entry.type === 'created' ? 'log-success' : (entry.type === 'error' ? 'log-error' : 'log-skip');
                        html += '<div class="log-entry ' + logClass + '">' + entry.message + '</div>';
                    });
                    html += '</div>';
                }

                html += '<div class="sfls-import-actions-result">';
                html += '<button type="button" class="button button-primary" onclick="location.reload()">Import More</button>';
                html += '</div>';
                html += '</div>';
            } else {
                html += '<div class="sfls-import-error">';
                html += '<span class="dashicons dashicons-dismiss"></span>';
                html += '<h3>Import Failed</h3>';
                html += '<p>' + response.message + '</p>';
                html += '<button type="button" class="button" onclick="location.reload()">Try Again</button>';
                html += '</div>';
            }

            $result.html(html).show();
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

    // Initialize Sheets Import on ready
    $(document).ready(function() {
        SheetsImport.init();
    });

    /**
     * File Upload Module
     */
    var FileUpload = {
        wordFileKey: null,
        excelFileKey: null,
        excelSheets: [],
        excelColumnMap: {},

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

            // File type tabs
            $('.sfls-file-type-tab').on('click', function() {
                var type = $(this).data('type');
                self.switchFileType(type);
            });

            // Word dropzone
            this.setupDropzone('#sfls-word-dropzone', '#sfls-word-file', 'word');

            // Excel dropzone
            this.setupDropzone('#sfls-excel-dropzone', '#sfls-excel-file', 'excel');

            // Word file remove
            $('#sfls-word-info .sfls-file-remove').on('click', function() {
                self.removeFile('word');
            });

            // Excel file remove
            $('#sfls-excel-info .sfls-file-remove').on('click', function() {
                self.removeFile('excel');
            });

            // Word import
            $('#sfls-word-import-btn').on('click', function() {
                self.importWord();
            });

            // Word cancel
            $('#sfls-word-cancel-btn').on('click', function() {
                self.cancelWord();
            });

            // Excel import type change
            $('#sfls-excel-import-type').on('change', function() {
                var type = $(this).val();
                $('#sfls-excel-preview-btn').prop('disabled', !type);
            });

            // Excel preview
            $('#sfls-excel-preview-btn').on('click', function() {
                self.previewExcel();
            });

            // Excel import
            $('#sfls-excel-import-btn').on('click', function() {
                self.importExcel();
            });

            // Excel cancel
            $('#sfls-excel-cancel-btn').on('click', function() {
                self.cancelExcel();
            });

            // Split by change for Word
            $('#word_split_by').on('change', function() {
                if (self.wordFileKey) {
                    self.previewWord();
                }
            });
        },

        /**
         * Switch file type tab
         */
        switchFileType: function(type) {
            $('.sfls-file-type-tab').removeClass('active');
            $('.sfls-file-type-tab[data-type="' + type + '"]').addClass('active');
            $('.sfls-upload-type-content').removeClass('active');
            $('#sfls-upload-' + type).addClass('active');
        },

        /**
         * Setup dropzone
         */
        setupDropzone: function(dropzoneSelector, inputSelector, type) {
            var self = this;
            var $dropzone = $(dropzoneSelector);
            var $input = $(inputSelector);

            // Click to open file dialog
            $dropzone.on('click', function() {
                $input.click();
            });

            // Drag events
            $dropzone.on('dragover', function(e) {
                e.preventDefault();
                $(this).addClass('dragover');
            });

            $dropzone.on('dragleave', function(e) {
                e.preventDefault();
                $(this).removeClass('dragover');
            });

            $dropzone.on('drop', function(e) {
                e.preventDefault();
                $(this).removeClass('dragover');
                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    self.uploadFile(files[0], type);
                }
            });

            // File input change
            $input.on('change', function() {
                if (this.files.length > 0) {
                    self.uploadFile(this.files[0], type);
                    this.value = '';
                }
            });
        },

        /**
         * Upload file
         */
        uploadFile: function(file, type) {
            var self = this;
            var $dropzone = $('#sfls-' + type + '-dropzone');
            var $content = $dropzone.find('.sfls-dropzone-content');
            var $loading = $dropzone.find('.sfls-dropzone-loading');

            // Show loading
            $content.hide();
            $loading.show();

            var formData = new FormData();
            formData.append('file', file);
            formData.append('file_type', type);
            formData.append('action', 'sfls_file_upload');
            formData.append('nonce', swiftlms_gdocs.nonce);

            $.ajax({
                url: swiftlms_gdocs.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $content.show();
                    $loading.hide();

                    if (response.success) {
                        if (type === 'word') {
                            self.wordFileKey = response.data.file_key;
                            self.onWordFileLoaded(response.data);
                        } else {
                            self.excelFileKey = response.data.file_key;
                            self.onExcelFileLoaded(response.data);
                        }
                    } else {
                        alert(response.data.message || swiftlms_gdocs.i18n.error);
                    }
                },
                error: function() {
                    $content.show();
                    $loading.hide();
                    alert(swiftlms_gdocs.i18n.error);
                }
            });
        },

        /**
         * Word file loaded
         */
        onWordFileLoaded: function(data) {
            // Show file info
            $('#sfls-word-info .sfls-file-name').text(data.file_name);
            $('#sfls-word-dropzone').hide();
            $('#sfls-word-info').show();

            // Auto preview
            this.previewWord();
        },

        /**
         * Preview Word file
         */
        previewWord: function() {
            var self = this;
            var $preview = $('#sfls-word-preview');
            var $content = $('#sfls-word-preview-content');

            $preview.show();
            $content.html('<div class="sfls-loading"><div class="sfls-loading-spinner"></div><p>' + swiftlms_gdocs.i18n.loading + '</p></div>');

            this.ajax({
                action: 'sfls_file_preview',
                file_key: this.wordFileKey,
                split_by: $('#word_split_by').val()
            }).then(function(response) {
                self.renderWordPreview(response);
            }).catch(function(error) {
                $content.html('<div class="sfls-import-error"><span class="dashicons dashicons-warning"></span><p>' + error + '</p></div>');
            });
        },

        /**
         * Render Word preview
         */
        renderWordPreview: function(data) {
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

            // Lessons preview
            if (data.preview.lessons && data.preview.lessons.length > 0) {
                html += '<div class="sfls-lessons-preview">';
                html += '<h4>Lessons to Create</h4>';
                html += '<table class="widefat striped">';
                html += '<thead><tr><th>#</th><th>Title</th><th>Words</th><th>Media</th></tr></thead>';
                html += '<tbody>';

                data.preview.lessons.forEach(function(lesson, index) {
                    var media = [];
                    if (lesson.has_images) media.push('Images');
                    if (lesson.has_video) media.push('Video');

                    html += '<tr>';
                    html += '<td>' + (index + 1) + '</td>';
                    html += '<td>' + lesson.title + '</td>';
                    html += '<td>' + lesson.word_count + '</td>';
                    html += '<td>' + (media.length > 0 ? media.join(', ') : '-') + '</td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';
                html += '</div>';
            }

            $('#sfls-word-preview-content').html(html);
        },

        /**
         * Import Word file
         */
        importWord: function() {
            var self = this;

            if (!this.wordFileKey) {
                alert('Please upload a file first');
                return;
            }

            if (!confirm(swiftlms_gdocs.i18n.confirm_import)) {
                return;
            }

            $('#sfls-word-preview').hide();
            $('#sfls-word-progress').show();

            this.ajax({
                action: 'sfls_file_import',
                file_key: this.wordFileKey,
                split_by: $('#word_split_by').val(),
                course_status: $('#word_course_status').val(),
                import_images: $('#word_import_images').is(':checked') ? 1 : 0,
                create_quizzes: $('#word_create_quizzes').is(':checked') ? 1 : 0
            }).then(function(response) {
                $('#sfls-word-progress').hide();
                self.renderWordResult(response, false);
            }).catch(function(error) {
                $('#sfls-word-progress').hide();
                self.renderWordResult({ message: error }, true);
            });
        },

        /**
         * Render Word import result
         */
        renderWordResult: function(response, isError) {
            var $result = $('#sfls-word-result');
            var html = '';

            if (!isError) {
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
                html += '<button type="button" class="button" onclick="location.reload()">Try Again</button>';
                html += '</div>';
            }

            $result.html(html).show();
        },

        /**
         * Cancel Word import
         */
        cancelWord: function() {
            this.removeFile('word');
        },

        /**
         * Excel file loaded
         */
        onExcelFileLoaded: function(data) {
            // Show file info
            $('#sfls-excel-info .sfls-file-name').text(data.file_name);
            $('#sfls-excel-dropzone').hide();
            $('#sfls-excel-info').show();
            $('#sfls-excel-config').show();

            // Hide sheet select for CSV
            if (data.extension === 'csv') {
                $('#sfls-excel-sheet-field').hide();
            }
        },

        /**
         * Preview Excel file
         */
        previewExcel: function() {
            var self = this;
            var importType = $('#sfls-excel-import-type').val();

            if (!importType) {
                alert('Please select an import type');
                return;
            }

            var $btn = $('#sfls-excel-preview-btn');
            $btn.prop('disabled', true).text(swiftlms_gdocs.i18n.loading);

            this.ajax({
                action: 'sfls_file_preview',
                file_key: this.excelFileKey,
                import_type: importType,
                sheet_index: $('#sfls-excel-sheet').val() || 0
            }).then(function(response) {
                self.excelColumnMap = response.column_map;
                self.renderExcelMapping(response);
                self.renderExcelPreview(response);
                $btn.prop('disabled', false).text('Preview Data');
            }).catch(function(error) {
                alert(error);
                $btn.prop('disabled', false).text('Preview Data');
            });
        },

        /**
         * Render Excel column mapping
         */
        renderExcelMapping: function(data) {
            var self = this;
            var $fields = $('#sfls-excel-mapping-fields');
            $fields.empty();

            var requiredFields = data.type_config.required || [];
            var optionalFields = data.type_config.optional || [];
            var allFields = requiredFields.concat(optionalFields);

            allFields.forEach(function(field) {
                var isRequired = requiredFields.indexOf(field) !== -1;
                var mappedColumn = data.column_map[field] || '';
                var isMapped = mappedColumn !== '';

                var html = '<div class="sfls-mapping-field ' + (isMapped ? 'mapped' : (isRequired ? 'missing' : '')) + '">';
                html += '<label>' + self.formatFieldName(field);
                if (isRequired) {
                    html += ' <span class="required">*</span>';
                }
                html += '</label>';
                html += '<select data-field="' + field + '">';
                html += '<option value="">— Not mapped —</option>';

                data.headers.forEach(function(header) {
                    var selected = mappedColumn === header ? ' selected' : '';
                    html += '<option value="' + header + '"' + selected + '>' + header + '</option>';
                });

                html += '</select>';
                html += '</div>';

                $fields.append(html);
            });

            $fields.find('select').on('change', function() {
                var field = $(this).data('field');
                var value = $(this).val();
                self.excelColumnMap[field] = value;
            });

            $('#sfls-excel-mapping').show();
        },

        /**
         * Format field name
         */
        formatFieldName: function(field) {
            return field.split('_').map(function(word) {
                return word.charAt(0).toUpperCase() + word.slice(1);
            }).join(' ');
        },

        /**
         * Render Excel preview
         */
        renderExcelPreview: function(data) {
            var self = this;
            var validation = data.validation;
            var $summary = $('#sfls-excel-preview .sfls-validation-summary');

            // Validation summary
            var validCount = validation.valid_rows.length;
            var errorCount = validation.errors.length;

            if (errorCount === 0) {
                $summary.removeClass('has-errors').addClass('valid');
                $summary.html('<span class="dashicons dashicons-yes-alt sfls-summary-icon"></span> All ' + validCount + ' rows are valid and ready to import.');
            } else {
                $summary.removeClass('valid').addClass('has-errors');
                var summaryHtml = '<span class="dashicons dashicons-warning sfls-summary-icon"></span> ';
                summaryHtml += validCount + ' valid rows, ' + errorCount + ' rows with errors.';
                $summary.html(summaryHtml);
            }

            // Preview table
            var $table = $('#sfls-excel-preview-table');
            var tableHtml = '<thead><tr><th>Status</th>';

            Object.keys(this.excelColumnMap).forEach(function(field) {
                if (self.excelColumnMap[field]) {
                    tableHtml += '<th>' + self.formatFieldName(field) + '</th>';
                }
            });
            tableHtml += '</tr></thead><tbody>';

            data.sample_rows.forEach(function(row, index) {
                var isValid = !validation.errors.find(function(e) { return e.row === (index + 2); });
                tableHtml += '<tr class="' + (isValid ? 'valid' : 'invalid') + '">';
                tableHtml += '<td><span class="sfls-row-status ' + (isValid ? 'valid' : 'invalid') + '">' + (isValid ? 'Valid' : 'Error') + '</span></td>';

                Object.keys(self.excelColumnMap).forEach(function(field) {
                    if (self.excelColumnMap[field]) {
                        tableHtml += '<td>' + (row[field] || '-') + '</td>';
                    }
                });

                tableHtml += '</tr>';
            });

            tableHtml += '</tbody>';
            $table.html(tableHtml);

            // Import options
            this.renderExcelOptions();

            $('#sfls-excel-preview').show();
        },

        /**
         * Render Excel import options
         */
        renderExcelOptions: function() {
            var type = $('#sfls-excel-import-type').val();
            var $options = $('#sfls-excel-options');
            $options.empty();

            var optionsHtml = '';

            if (type === 'students') {
                optionsHtml += '<div class="sfls-option-field sfls-checkbox-field"><label><input type="checkbox" id="excel-opt-send-welcome" checked> Send welcome email</label></div>';
            } else if (type === 'enrollments') {
                optionsHtml += '<div class="sfls-option-field sfls-checkbox-field"><label><input type="checkbox" id="excel-opt-send-notification" checked> Send enrollment notification</label></div>';
            }

            optionsHtml += '<div class="sfls-option-field sfls-checkbox-field"><label><input type="checkbox" id="excel-opt-skip-existing" checked> Skip existing records</label></div>';

            $options.html(optionsHtml);
        },

        /**
         * Import Excel file
         */
        importExcel: function() {
            var self = this;
            var importType = $('#sfls-excel-import-type').val();

            if (!confirm('Are you sure you want to import this data?')) {
                return;
            }

            var options = {
                skip_existing: $('#excel-opt-skip-existing').is(':checked'),
                send_welcome: $('#excel-opt-send-welcome').is(':checked'),
                send_notification: $('#excel-opt-send-notification').is(':checked')
            };

            $('#sfls-excel-preview').hide();
            $('#sfls-excel-progress').show();

            this.ajax({
                action: 'sfls_file_import',
                file_key: this.excelFileKey,
                import_type: importType,
                sheet_index: $('#sfls-excel-sheet').val() || 0,
                column_map: JSON.stringify(this.excelColumnMap),
                options: JSON.stringify(options)
            }).then(function(response) {
                $('#sfls-excel-progress').hide();
                self.renderExcelResult(response, false);
            }).catch(function(error) {
                $('#sfls-excel-progress').hide();
                self.renderExcelResult({ message: error }, true);
            });
        },

        /**
         * Render Excel import result
         */
        renderExcelResult: function(response, isError) {
            var $result = $('#sfls-excel-result');
            var html = '';

            if (!isError) {
                html += '<div class="sfls-import-success">';
                html += '<span class="dashicons dashicons-yes-alt"></span>';
                html += '<h3>Import Complete!</h3>';
                html += '<p>' + response.message + '</p>';

                if (response.stats) {
                    html += '<div class="sfls-sheets-result-stats">';
                    html += '<div class="sfls-result-stat"><span class="sfls-result-stat-value created">' + response.stats.created + '</span><span class="sfls-result-stat-label">Created</span></div>';
                    html += '<div class="sfls-result-stat"><span class="sfls-result-stat-value updated">' + response.stats.updated + '</span><span class="sfls-result-stat-label">Updated</span></div>';
                    html += '<div class="sfls-result-stat"><span class="sfls-result-stat-value skipped">' + response.stats.skipped + '</span><span class="sfls-result-stat-label">Skipped</span></div>';
                    html += '<div class="sfls-result-stat"><span class="sfls-result-stat-value errors">' + response.stats.errors + '</span><span class="sfls-result-stat-label">Errors</span></div>';
                    html += '</div>';
                }

                html += '<div class="sfls-import-actions-result">';
                html += '<button type="button" class="button button-primary" onclick="location.reload()">Import More</button>';
                html += '</div>';
                html += '</div>';
            } else {
                html += '<div class="sfls-import-error">';
                html += '<span class="dashicons dashicons-dismiss"></span>';
                html += '<h3>Import Failed</h3>';
                html += '<p>' + response.message + '</p>';
                html += '<button type="button" class="button" onclick="location.reload()">Try Again</button>';
                html += '</div>';
            }

            $result.html(html).show();
        },

        /**
         * Cancel Excel import
         */
        cancelExcel: function() {
            $('#sfls-excel-mapping').hide();
            $('#sfls-excel-preview').hide();
        },

        /**
         * Remove file
         */
        removeFile: function(type) {
            if (type === 'word') {
                this.wordFileKey = null;
                $('#sfls-word-info').hide();
                $('#sfls-word-preview').hide();
                $('#sfls-word-result').hide();
                $('#sfls-word-dropzone').show();
            } else {
                this.excelFileKey = null;
                this.excelColumnMap = {};
                $('#sfls-excel-info').hide();
                $('#sfls-excel-config').hide();
                $('#sfls-excel-mapping').hide();
                $('#sfls-excel-preview').hide();
                $('#sfls-excel-result').hide();
                $('#sfls-excel-dropzone').show();
            }
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

    // Initialize File Upload on ready
    $(document).ready(function() {
        FileUpload.init();
    });

})(jQuery);
