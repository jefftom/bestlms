/**
 * Certificate Template Builder
 *
 * @package SwiftLMS
 */

(function($) {
    'use strict';

    var CertificateBuilder = {
        selectedTemplate: null,
        customizations: {},
        previewFrame: null,
        zoomLevel: 50,
        debounceTimer: null,

        /**
         * Initialize the builder.
         */
        init: function() {
            this.bindEvents();
            this.initColorPickers();
            this.initMediaUploaders();

            // Load initial template if set.
            var initialTemplate = $('#sfls_json_template').val();
            if (initialTemplate) {
                this.selectedTemplate = initialTemplate;
                this.loadCustomizations();
                this.updatePreview();
            }
        },

        /**
         * Bind event handlers.
         */
        bindEvents: function() {
            var self = this;

            // Template selection.
            $(document).on('click', '.sfls-template-card', function() {
                self.selectTemplate($(this).data('template-id'));
            });

            // Customization inputs.
            $(document).on('change', '.sfls-customizer-input', function() {
                self.updateCustomization($(this).data('key'), $(this).val());
            });

            $(document).on('input', '.sfls-customizer-input', function() {
                self.debouncePreview();
            });

            // Color picker changes.
            $(document).on('change', '.sfls-color-swatch', function() {
                var $wrapper = $(this).closest('.sfls-color-picker-wrapper');
                var $input = $wrapper.find('.sfls-color-input');
                $input.val($(this).val()).trigger('change');
            });

            // Font selector changes.
            $(document).on('change', '.sfls-font-select', function() {
                var $preview = $(this).siblings('.sfls-font-preview');
                $preview.css('font-family', $(this).val());
                self.updateCustomization($(this).data('key'), $(this).val());
            });

            // Zoom controls.
            $(document).on('click', '.sfls-zoom-in', function() {
                self.zoom(10);
            });

            $(document).on('click', '.sfls-zoom-out', function() {
                self.zoom(-10);
            });

            // Preview button.
            $(document).on('click', '.sfls-preview-btn', function(e) {
                e.preventDefault();
                self.openPreview();
            });

            // Save button.
            $(document).on('click', '.sfls-save-customizations', function(e) {
                e.preventDefault();
                self.saveCustomizations();
            });

            // Tab switching.
            $(document).on('click', '.sfls-tab', function() {
                var target = $(this).data('tab');
                $('.sfls-tab').removeClass('active');
                $(this).addClass('active');
                $('.sfls-tab-content').removeClass('active');
                $('#' + target).addClass('active');
            });

            // Reset to defaults.
            $(document).on('click', '.sfls-reset-customizations', function(e) {
                e.preventDefault();
                if (confirm(sflsCertBuilder.strings.confirmReset)) {
                    self.resetCustomizations();
                }
            });
        },

        /**
         * Initialize color pickers.
         */
        initColorPickers: function() {
            $('.sfls-color-swatch').each(function() {
                var $input = $(this).siblings('.sfls-color-input');
                $(this).val($input.val());
            });
        },

        /**
         * Initialize media uploaders.
         */
        initMediaUploaders: function() {
            var self = this;

            $('.sfls-image-upload').each(function() {
                var $upload = $(this);
                var key = $upload.data('key');
                var frame;

                $upload.on('click', function(e) {
                    e.preventDefault();

                    if (frame) {
                        frame.open();
                        return;
                    }

                    frame = wp.media({
                        title: sflsCertBuilder.strings.selectImage,
                        button: { text: sflsCertBuilder.strings.useImage },
                        multiple: false
                    });

                    frame.on('select', function() {
                        var attachment = frame.state().get('selection').first().toJSON();
                        self.setImage($upload, key, attachment.url);
                    });

                    frame.open();
                });
            });

            // Remove image.
            $(document).on('click', '.sfls-image-upload-remove', function(e) {
                e.stopPropagation();
                var $upload = $(this).closest('.sfls-image-upload');
                var key = $upload.data('key');
                self.removeImage($upload, key);
            });
        },

        /**
         * Set image for upload field.
         */
        setImage: function($upload, key, url) {
            $upload.addClass('has-image');
            $upload.html(
                '<img src="' + url + '" class="sfls-image-upload-preview">' +
                '<div class="sfls-image-upload-actions">' +
                    '<span class="sfls-image-upload-remove">' + sflsCertBuilder.strings.removeImage + '</span>' +
                '</div>'
            );

            this.updateCustomization(key, url);
        },

        /**
         * Remove image from upload field.
         */
        removeImage: function($upload, key) {
            $upload.removeClass('has-image');
            $upload.html(
                '<span class="sfls-image-upload-icon dashicons dashicons-upload"></span>' +
                '<span class="sfls-image-upload-text">' + sflsCertBuilder.strings.uploadImage + '</span>'
            );

            this.updateCustomization(key, '');
        },

        /**
         * Select a template.
         */
        selectTemplate: function(templateId) {
            this.selectedTemplate = templateId;

            // Update UI.
            $('.sfls-template-card').removeClass('selected');
            $('.sfls-template-card[data-template-id="' + templateId + '"]').addClass('selected');

            // Update hidden input.
            $('#sfls_json_template').val(templateId);

            // Load template defaults and update preview.
            this.loadTemplateDefaults(templateId);
        },

        /**
         * Load template default customizations.
         */
        loadTemplateDefaults: function(templateId) {
            var self = this;

            $.ajax({
                url: sflsCertBuilder.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sfls_get_template_defaults',
                    template_id: templateId,
                    _wpnonce: sflsCertBuilder.nonce
                },
                success: function(response) {
                    if (response.success && response.data.customizable) {
                        // Update form fields with defaults.
                        $.each(response.data.customizable, function(key, value) {
                            var $field = $('[data-key="' + key + '"]');
                            if ($field.length) {
                                $field.val(value);
                                if ($field.hasClass('sfls-color-input')) {
                                    $field.siblings('.sfls-color-swatch').val(value);
                                }
                            }
                        });

                        self.customizations = response.data.customizable;
                        self.updatePreview();
                    }
                }
            });
        },

        /**
         * Load saved customizations.
         */
        loadCustomizations: function() {
            var saved = $('#sfls_template_customizations').val();
            if (saved) {
                try {
                    this.customizations = JSON.parse(saved);

                    // Update form fields.
                    $.each(this.customizations, function(key, value) {
                        var $field = $('[data-key="' + key + '"]');
                        if ($field.length) {
                            if ($field.hasClass('sfls-image-upload')) {
                                if (value) {
                                    $field.addClass('has-image');
                                    $field.html(
                                        '<img src="' + value + '" class="sfls-image-upload-preview">' +
                                        '<div class="sfls-image-upload-actions">' +
                                            '<span class="sfls-image-upload-remove">' + sflsCertBuilder.strings.removeImage + '</span>' +
                                        '</div>'
                                    );
                                }
                            } else {
                                $field.val(value);
                                if ($field.hasClass('sfls-color-input')) {
                                    $field.siblings('.sfls-color-swatch').val(value);
                                }
                            }
                        }
                    });
                } catch (e) {
                    console.error('Failed to parse customizations', e);
                }
            }
        },

        /**
         * Update a customization value.
         */
        updateCustomization: function(key, value) {
            this.customizations[key] = value;
            $('#sfls_template_customizations').val(JSON.stringify(this.customizations));
        },

        /**
         * Debounce preview updates.
         */
        debouncePreview: function() {
            var self = this;
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(function() {
                self.updatePreview();
            }, 300);
        },

        /**
         * Update the preview iframe.
         */
        updatePreview: function() {
            if (!this.selectedTemplate) return;

            var self = this;
            var $preview = $('.sfls-preview-frame');

            $preview.addClass('sfls-loading');

            $.ajax({
                url: sflsCertBuilder.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sfls_preview_certificate_template',
                    template_id: this.selectedTemplate,
                    customizations: JSON.stringify(this.customizations),
                    _wpnonce: sflsCertBuilder.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var $iframe = $preview.find('iframe');
                        if ($iframe.length === 0) {
                            $iframe = $('<iframe></iframe>');
                            $preview.append($iframe);
                        }

                        // Write HTML to iframe.
                        var doc = $iframe[0].contentDocument || $iframe[0].contentWindow.document;
                        doc.open();
                        doc.write(response.data.html);
                        doc.close();

                        self.applyZoom();
                    }
                },
                complete: function() {
                    $preview.removeClass('sfls-loading');
                }
            });
        },

        /**
         * Zoom the preview.
         */
        zoom: function(delta) {
            this.zoomLevel = Math.max(25, Math.min(100, this.zoomLevel + delta));
            this.applyZoom();
            $('.sfls-zoom-level').text(this.zoomLevel + '%');
        },

        /**
         * Apply current zoom level.
         */
        applyZoom: function() {
            var $frame = $('.sfls-preview-frame');
            var $iframe = $frame.find('iframe');
            var scale = this.zoomLevel / 100;

            $iframe.css('transform', 'scale(' + scale + ')');
            $frame.css({
                width: (792 * scale) + 'px',
                height: (612 * scale) + 'px'
            });
        },

        /**
         * Open full preview in new window.
         */
        openPreview: function() {
            var url = sflsCertBuilder.ajaxUrl +
                '?action=sfls_preview_certificate_fullscreen' +
                '&template_id=' + this.selectedTemplate +
                '&customizations=' + encodeURIComponent(JSON.stringify(this.customizations)) +
                '&_wpnonce=' + sflsCertBuilder.nonce;

            window.open(url, '_blank', 'width=900,height=700');
        },

        /**
         * Save customizations.
         */
        saveCustomizations: function() {
            var $btn = $('.sfls-save-customizations');
            var originalText = $btn.text();

            $btn.text(sflsCertBuilder.strings.saving).prop('disabled', true);

            // Customizations are saved via the hidden input when the post is saved.
            // This just provides visual feedback.
            setTimeout(function() {
                $btn.text(sflsCertBuilder.strings.saved);
                setTimeout(function() {
                    $btn.text(originalText).prop('disabled', false);
                }, 1500);
            }, 500);
        },

        /**
         * Reset customizations to defaults.
         */
        resetCustomizations: function() {
            this.customizations = {};
            $('#sfls_template_customizations').val('');

            if (this.selectedTemplate) {
                this.loadTemplateDefaults(this.selectedTemplate);
            }
        }
    };

    // Initialize on DOM ready.
    $(document).ready(function() {
        if ($('.sfls-certificate-builder').length) {
            CertificateBuilder.init();
        }
    });

    // Expose globally.
    window.SFLSCertificateBuilder = CertificateBuilder;

})(jQuery);
