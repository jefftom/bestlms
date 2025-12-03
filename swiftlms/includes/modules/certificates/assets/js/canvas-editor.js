/**
 * SwiftLMS Certificate Canvas Editor
 * Full drag-and-drop certificate designer using Fabric.js
 */

(function($) {
    'use strict';

    window.CertificateCanvasEditor = {
        canvas: null,
        selectedObject: null,
        clipboard: null,
        history: [],
        historyIndex: -1,
        maxHistory: 50,
        isDirty: false,

        // Canvas settings
        settings: {
            width: 792,  // 11" at 72dpi (Letter landscape)
            height: 612, // 8.5" at 72dpi
            backgroundColor: '#ffffff'
        },

        // Default fonts available
        fonts: [
            'Arial', 'Times New Roman', 'Georgia', 'Verdana',
            'Playfair Display', 'Open Sans', 'Roboto', 'Lato',
            'Montserrat', 'Raleway', 'Oswald', 'Great Vibes',
            'Dancing Script', 'Pacifico', 'Merriweather', 'PT Serif',
            'Source Sans Pro', 'Nunito', 'Poppins'
        ],

        // Dynamic field placeholders
        dynamicFields: {
            '{student_name}': 'John Doe',
            '{course_title}': 'Sample Course Title',
            '{completion_date}': 'December 3, 2025',
            '{certificate_id}': 'CERT-2025-001234',
            '{instructor_name}': 'Jane Smith',
            '{organization_name}': 'Your Organization',
            '{issue_date}': 'December 3, 2025',
            '{expiry_date}': 'December 3, 2026',
            '{course_duration}': '40 hours',
            '{grade}': 'A',
            '{score}': '95%'
        },

        /**
         * Initialize the canvas editor
         */
        init: function() {
            var self = this;

            // Check if we're on the right page
            if ($('#certificate-canvas').length === 0) {
                return;
            }

            // Load Google Fonts
            this.loadGoogleFonts();

            // Initialize Fabric canvas
            this.canvas = new fabric.Canvas('certificate-canvas', {
                width: this.settings.width,
                height: this.settings.height,
                backgroundColor: this.settings.backgroundColor,
                preserveObjectStacking: true,
                selection: true
            });

            // Set up event listeners
            this.setupEventListeners();

            // Set up keyboard shortcuts
            this.setupKeyboardShortcuts();

            // Initialize UI components
            this.initToolbox();
            this.initPropertiesPanel();
            this.initLayersPanel();

            // Load existing template if editing
            if (typeof certificateCanvasData !== 'undefined' && certificateCanvasData.templateData) {
                this.loadTemplate(certificateCanvasData.templateData);
            }

            // Save initial state
            this.saveHistory();

            console.log('Certificate Canvas Editor initialized');
        },

        /**
         * Load Google Fonts
         */
        loadGoogleFonts: function() {
            var googleFonts = [
                'Playfair+Display:400,700',
                'Open+Sans:400,600,700',
                'Roboto:400,500,700',
                'Lato:400,700',
                'Montserrat:400,600,700',
                'Raleway:400,600,700',
                'Oswald:400,600,700',
                'Great+Vibes',
                'Dancing+Script:400,700',
                'Pacifico',
                'Merriweather:400,700',
                'PT+Serif:400,700',
                'Source+Sans+Pro:400,600,700',
                'Nunito:400,600,700',
                'Poppins:400,500,600,700'
            ];

            var link = document.createElement('link');
            link.href = 'https://fonts.googleapis.com/css2?family=' + googleFonts.join('&family=') + '&display=swap';
            link.rel = 'stylesheet';
            document.head.appendChild(link);
        },

        /**
         * Set up canvas event listeners
         */
        setupEventListeners: function() {
            var self = this;

            // Object selection
            this.canvas.on('selection:created', function(e) {
                self.onObjectSelected(e.selected[0]);
            });

            this.canvas.on('selection:updated', function(e) {
                self.onObjectSelected(e.selected[0]);
            });

            this.canvas.on('selection:cleared', function() {
                self.onSelectionCleared();
            });

            // Object modification
            this.canvas.on('object:modified', function(e) {
                self.onObjectModified(e.target);
                self.saveHistory();
                self.isDirty = true;
            });

            this.canvas.on('object:added', function() {
                self.updateLayersPanel();
                self.isDirty = true;
            });

            this.canvas.on('object:removed', function() {
                self.updateLayersPanel();
                self.isDirty = true;
            });

            // Canvas background color
            $('#canvas-bg-color').on('change', function() {
                self.canvas.setBackgroundColor($(this).val(), function() {
                    self.canvas.renderAll();
                });
                self.saveHistory();
            });

            // Canvas size preset
            $('#canvas-size-preset').on('change', function() {
                var preset = $(this).val();
                self.setCanvasSize(preset);
            });

            // Zoom controls
            $('#zoom-in').on('click', function() {
                self.zoom(1.1);
            });

            $('#zoom-out').on('click', function() {
                self.zoom(0.9);
            });

            $('#zoom-fit').on('click', function() {
                self.zoomToFit();
            });

            $('#zoom-100').on('click', function() {
                self.setZoom(1);
            });

            // Toolbar buttons
            $('#btn-undo').on('click', function() {
                self.undo();
            });

            $('#btn-redo').on('click', function() {
                self.redo();
            });

            $('#btn-delete').on('click', function() {
                self.deleteSelected();
            });

            $('#btn-duplicate').on('click', function() {
                self.duplicateSelected();
            });

            $('#btn-bring-front').on('click', function() {
                self.bringToFront();
            });

            $('#btn-send-back').on('click', function() {
                self.sendToBack();
            });

            // Grid toggle
            $('#toggle-grid').on('change', function() {
                self.toggleGrid($(this).is(':checked'));
            });

            // Snap to grid
            $('#toggle-snap').on('change', function() {
                self.toggleSnap($(this).is(':checked'));
            });

            // Save template
            $('#btn-save-template').on('click', function() {
                self.saveTemplate();
            });

            // Preview
            $('#btn-preview').on('click', function() {
                self.preview();
            });

            // Export
            $('#btn-export-png').on('click', function() {
                self.exportPNG();
            });

            $('#btn-export-pdf').on('click', function() {
                self.exportPDF();
            });
        },

        /**
         * Set up keyboard shortcuts
         */
        setupKeyboardShortcuts: function() {
            var self = this;

            $(document).on('keydown', function(e) {
                // Only handle if canvas is focused
                if (!$('#certificate-canvas-container').is(':hover')) {
                    return;
                }

                // Delete
                if (e.key === 'Delete' || e.key === 'Backspace') {
                    if (!$(e.target).is('input, textarea')) {
                        e.preventDefault();
                        self.deleteSelected();
                    }
                }

                // Ctrl/Cmd + C - Copy
                if ((e.ctrlKey || e.metaKey) && e.key === 'c') {
                    e.preventDefault();
                    self.copy();
                }

                // Ctrl/Cmd + V - Paste
                if ((e.ctrlKey || e.metaKey) && e.key === 'v') {
                    e.preventDefault();
                    self.paste();
                }

                // Ctrl/Cmd + D - Duplicate
                if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
                    e.preventDefault();
                    self.duplicateSelected();
                }

                // Ctrl/Cmd + Z - Undo
                if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.key === 'z') {
                    e.preventDefault();
                    self.undo();
                }

                // Ctrl/Cmd + Shift + Z or Ctrl + Y - Redo
                if ((e.ctrlKey || e.metaKey) && (e.shiftKey && e.key === 'z' || e.key === 'y')) {
                    e.preventDefault();
                    self.redo();
                }

                // Ctrl/Cmd + A - Select all
                if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
                    e.preventDefault();
                    self.selectAll();
                }

                // Arrow keys - nudge
                if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key)) {
                    if (!$(e.target).is('input, textarea') && self.selectedObject) {
                        e.preventDefault();
                        var delta = e.shiftKey ? 10 : 1;
                        self.nudge(e.key, delta);
                    }
                }
            });
        },

        /**
         * Initialize toolbox
         */
        initToolbox: function() {
            var self = this;

            // Add text
            $('#tool-add-text').on('click', function() {
                self.addText();
            });

            // Add heading
            $('#tool-add-heading').on('click', function() {
                self.addHeading();
            });

            // Add dynamic field
            $('#tool-add-dynamic').on('click', function() {
                self.showDynamicFieldModal();
            });

            // Add rectangle
            $('#tool-add-rect').on('click', function() {
                self.addRectangle();
            });

            // Add circle
            $('#tool-add-circle').on('click', function() {
                self.addCircle();
            });

            // Add line
            $('#tool-add-line').on('click', function() {
                self.addLine();
            });

            // Add image
            $('#tool-add-image').on('click', function() {
                self.openMediaLibrary();
            });

            // Add QR code
            $('#tool-add-qr').on('click', function() {
                self.addQRCode();
            });

            // Add border frame
            $('#tool-add-border').on('click', function() {
                self.addBorderFrame();
            });

            // Add signature line
            $('#tool-add-signature').on('click', function() {
                self.addSignatureLine();
            });
        },

        /**
         * Initialize properties panel
         */
        initPropertiesPanel: function() {
            var self = this;

            // Text properties
            $('#prop-font-family').on('change', function() {
                self.updateSelectedProperty('fontFamily', $(this).val());
            });

            $('#prop-font-size').on('change input', function() {
                self.updateSelectedProperty('fontSize', parseInt($(this).val()));
            });

            $('#prop-font-color').on('change', function() {
                self.updateSelectedProperty('fill', $(this).val());
            });

            $('#prop-bold').on('click', function() {
                var current = self.selectedObject.fontWeight;
                self.updateSelectedProperty('fontWeight', current === 'bold' ? 'normal' : 'bold');
                $(this).toggleClass('active');
            });

            $('#prop-italic').on('click', function() {
                var current = self.selectedObject.fontStyle;
                self.updateSelectedProperty('fontStyle', current === 'italic' ? 'normal' : 'italic');
                $(this).toggleClass('active');
            });

            $('#prop-underline').on('click', function() {
                var current = self.selectedObject.underline;
                self.updateSelectedProperty('underline', !current);
                $(this).toggleClass('active');
            });

            $('#prop-align-left').on('click', function() {
                self.updateSelectedProperty('textAlign', 'left');
                self.setAlignActive('left');
            });

            $('#prop-align-center').on('click', function() {
                self.updateSelectedProperty('textAlign', 'center');
                self.setAlignActive('center');
            });

            $('#prop-align-right').on('click', function() {
                self.updateSelectedProperty('textAlign', 'right');
                self.setAlignActive('right');
            });

            // Shape properties
            $('#prop-fill-color').on('change', function() {
                self.updateSelectedProperty('fill', $(this).val());
            });

            $('#prop-stroke-color').on('change', function() {
                self.updateSelectedProperty('stroke', $(this).val());
            });

            $('#prop-stroke-width').on('change input', function() {
                self.updateSelectedProperty('strokeWidth', parseInt($(this).val()));
            });

            $('#prop-opacity').on('change input', function() {
                self.updateSelectedProperty('opacity', parseFloat($(this).val()));
            });

            // Position properties
            $('#prop-pos-x').on('change', function() {
                self.updateSelectedProperty('left', parseInt($(this).val()));
            });

            $('#prop-pos-y').on('change', function() {
                self.updateSelectedProperty('top', parseInt($(this).val()));
            });

            $('#prop-width').on('change', function() {
                self.setSelectedWidth(parseInt($(this).val()));
            });

            $('#prop-height').on('change', function() {
                self.setSelectedHeight(parseInt($(this).val()));
            });

            $('#prop-rotation').on('change input', function() {
                self.updateSelectedProperty('angle', parseInt($(this).val()));
            });

            // Lock aspect ratio
            $('#prop-lock-aspect').on('change', function() {
                if (self.selectedObject) {
                    self.selectedObject.lockUniScaling = $(this).is(':checked');
                }
            });
        },

        /**
         * Initialize layers panel
         */
        initLayersPanel: function() {
            var self = this;

            // Layer click to select
            $(document).on('click', '.layer-item', function() {
                var index = $(this).data('index');
                var objects = self.canvas.getObjects();
                if (objects[index]) {
                    self.canvas.setActiveObject(objects[index]);
                    self.canvas.renderAll();
                }
            });

            // Layer visibility toggle
            $(document).on('click', '.layer-visibility', function(e) {
                e.stopPropagation();
                var index = $(this).closest('.layer-item').data('index');
                var objects = self.canvas.getObjects();
                if (objects[index]) {
                    objects[index].visible = !objects[index].visible;
                    self.canvas.renderAll();
                    self.updateLayersPanel();
                }
            });

            // Layer lock toggle
            $(document).on('click', '.layer-lock', function(e) {
                e.stopPropagation();
                var index = $(this).closest('.layer-item').data('index');
                var objects = self.canvas.getObjects();
                if (objects[index]) {
                    var isLocked = !objects[index].selectable;
                    objects[index].selectable = isLocked;
                    objects[index].evented = isLocked;
                    self.canvas.renderAll();
                    self.updateLayersPanel();
                }
            });
        },

        /**
         * Add text element
         */
        addText: function(options) {
            options = options || {};

            var text = new fabric.IText(options.text || 'Double-click to edit', {
                left: options.left || this.settings.width / 2,
                top: options.top || this.settings.height / 2,
                fontFamily: options.fontFamily || 'Open Sans',
                fontSize: options.fontSize || 24,
                fill: options.fill || '#333333',
                originX: 'center',
                originY: 'center',
                textAlign: options.textAlign || 'center',
                customType: 'text'
            });

            this.canvas.add(text);
            this.canvas.setActiveObject(text);
            this.canvas.renderAll();
            this.saveHistory();

            return text;
        },

        /**
         * Add heading element
         */
        addHeading: function() {
            return this.addText({
                text: 'CERTIFICATE',
                fontFamily: 'Playfair Display',
                fontSize: 48,
                fill: '#1a1a1a'
            });
        },

        /**
         * Show dynamic field modal
         */
        showDynamicFieldModal: function() {
            var self = this;
            var html = '<div class="dynamic-field-modal">';
            html += '<h3>Insert Dynamic Field</h3>';
            html += '<p>Select a field to insert. It will be replaced with actual data when the certificate is generated.</p>';
            html += '<div class="dynamic-fields-list">';

            $.each(this.dynamicFields, function(field, preview) {
                html += '<div class="dynamic-field-option" data-field="' + field + '">';
                html += '<span class="field-name">' + field + '</span>';
                html += '<span class="field-preview">Preview: ' + preview + '</span>';
                html += '</div>';
            });

            html += '</div></div>';

            // Simple modal
            var $modal = $('<div class="canvas-modal-overlay">' + html + '</div>');
            $('body').append($modal);

            $modal.on('click', '.dynamic-field-option', function() {
                var field = $(this).data('field');
                self.addDynamicField(field);
                $modal.remove();
            });

            $modal.on('click', function(e) {
                if ($(e.target).hasClass('canvas-modal-overlay')) {
                    $modal.remove();
                }
            });
        },

        /**
         * Add dynamic field
         */
        addDynamicField: function(field) {
            var preview = this.dynamicFields[field] || field;

            var text = new fabric.IText(preview, {
                left: this.settings.width / 2,
                top: this.settings.height / 2,
                fontFamily: field === '{student_name}' ? 'Great Vibes' : 'Open Sans',
                fontSize: field === '{student_name}' ? 48 : 24,
                fill: '#333333',
                originX: 'center',
                originY: 'center',
                textAlign: 'center',
                customType: 'dynamic_field',
                dynamicField: field
            });

            this.canvas.add(text);
            this.canvas.setActiveObject(text);
            this.canvas.renderAll();
            this.saveHistory();

            return text;
        },

        /**
         * Add rectangle
         */
        addRectangle: function(options) {
            options = options || {};

            var rect = new fabric.Rect({
                left: options.left || this.settings.width / 2,
                top: options.top || this.settings.height / 2,
                width: options.width || 200,
                height: options.height || 100,
                fill: options.fill || 'transparent',
                stroke: options.stroke || '#333333',
                strokeWidth: options.strokeWidth || 2,
                originX: 'center',
                originY: 'center',
                rx: options.rx || 0,
                ry: options.ry || 0,
                customType: 'rectangle'
            });

            this.canvas.add(rect);
            this.canvas.setActiveObject(rect);
            this.canvas.renderAll();
            this.saveHistory();

            return rect;
        },

        /**
         * Add circle
         */
        addCircle: function(options) {
            options = options || {};

            var circle = new fabric.Circle({
                left: options.left || this.settings.width / 2,
                top: options.top || this.settings.height / 2,
                radius: options.radius || 50,
                fill: options.fill || 'transparent',
                stroke: options.stroke || '#333333',
                strokeWidth: options.strokeWidth || 2,
                originX: 'center',
                originY: 'center',
                customType: 'circle'
            });

            this.canvas.add(circle);
            this.canvas.setActiveObject(circle);
            this.canvas.renderAll();
            this.saveHistory();

            return circle;
        },

        /**
         * Add line
         */
        addLine: function(options) {
            options = options || {};

            var line = new fabric.Line([
                options.x1 || this.settings.width / 2 - 100,
                options.y1 || this.settings.height / 2,
                options.x2 || this.settings.width / 2 + 100,
                options.y2 || this.settings.height / 2
            ], {
                stroke: options.stroke || '#333333',
                strokeWidth: options.strokeWidth || 2,
                customType: 'line'
            });

            this.canvas.add(line);
            this.canvas.setActiveObject(line);
            this.canvas.renderAll();
            this.saveHistory();

            return line;
        },

        /**
         * Open WordPress media library
         */
        openMediaLibrary: function() {
            var self = this;

            var mediaFrame = wp.media({
                title: 'Select Image',
                button: { text: 'Insert Image' },
                multiple: false
            });

            mediaFrame.on('select', function() {
                var attachment = mediaFrame.state().get('selection').first().toJSON();
                self.addImage(attachment.url);
            });

            mediaFrame.open();
        },

        /**
         * Add image
         */
        addImage: function(url, options) {
            var self = this;
            options = options || {};

            fabric.Image.fromURL(url, function(img) {
                // Scale image to fit reasonably
                var maxWidth = self.settings.width * 0.3;
                var maxHeight = self.settings.height * 0.3;
                var scale = Math.min(maxWidth / img.width, maxHeight / img.height, 1);

                img.set({
                    left: options.left || self.settings.width / 2,
                    top: options.top || self.settings.height / 2,
                    scaleX: options.scaleX || scale,
                    scaleY: options.scaleY || scale,
                    originX: 'center',
                    originY: 'center',
                    customType: 'image'
                });

                self.canvas.add(img);
                self.canvas.setActiveObject(img);
                self.canvas.renderAll();
                self.saveHistory();
            }, { crossOrigin: 'anonymous' });
        },

        /**
         * Add QR code
         */
        addQRCode: function(options) {
            var self = this;
            options = options || {};

            var qrData = options.data || '{certificate_id}';
            var qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent(qrData);

            fabric.Image.fromURL(qrUrl, function(img) {
                img.set({
                    left: options.left || self.settings.width - 100,
                    top: options.top || self.settings.height - 100,
                    originX: 'center',
                    originY: 'center',
                    customType: 'qr_code',
                    qrData: qrData
                });

                self.canvas.add(img);
                self.canvas.setActiveObject(img);
                self.canvas.renderAll();
                self.saveHistory();
            }, { crossOrigin: 'anonymous' });
        },

        /**
         * Add decorative border frame
         */
        addBorderFrame: function() {
            var padding = 30;
            var strokeWidth = 3;

            // Outer border
            var outer = new fabric.Rect({
                left: padding,
                top: padding,
                width: this.settings.width - (padding * 2),
                height: this.settings.height - (padding * 2),
                fill: 'transparent',
                stroke: '#c9a227',
                strokeWidth: strokeWidth,
                selectable: true,
                customType: 'border'
            });

            // Inner border
            var inner = new fabric.Rect({
                left: padding + 10,
                top: padding + 10,
                width: this.settings.width - (padding * 2) - 20,
                height: this.settings.height - (padding * 2) - 20,
                fill: 'transparent',
                stroke: '#c9a227',
                strokeWidth: 1,
                selectable: true,
                customType: 'border'
            });

            // Group them
            var group = new fabric.Group([outer, inner], {
                customType: 'border_frame'
            });

            this.canvas.add(group);
            this.canvas.sendToBack(group);
            this.canvas.renderAll();
            this.saveHistory();

            return group;
        },

        /**
         * Add signature line
         */
        addSignatureLine: function() {
            var centerX = this.settings.width / 2;
            var y = this.settings.height - 150;

            // Line
            var line = new fabric.Line([centerX - 100, y, centerX + 100, y], {
                stroke: '#333333',
                strokeWidth: 1
            });

            // Label
            var label = new fabric.Text('Authorized Signature', {
                left: centerX,
                top: y + 15,
                fontFamily: 'Open Sans',
                fontSize: 12,
                fill: '#666666',
                originX: 'center'
            });

            // Group them
            var group = new fabric.Group([line, label], {
                customType: 'signature_line'
            });

            this.canvas.add(group);
            this.canvas.setActiveObject(group);
            this.canvas.renderAll();
            this.saveHistory();

            return group;
        },

        /**
         * Object selected handler
         */
        onObjectSelected: function(obj) {
            this.selectedObject = obj;
            this.updatePropertiesPanel(obj);
            this.highlightLayerItem(obj);
        },

        /**
         * Selection cleared handler
         */
        onSelectionCleared: function() {
            this.selectedObject = null;
            this.clearPropertiesPanel();
            $('.layer-item').removeClass('selected');
        },

        /**
         * Object modified handler
         */
        onObjectModified: function(obj) {
            this.updatePropertiesPanel(obj);
        },

        /**
         * Update properties panel with object data
         */
        updatePropertiesPanel: function(obj) {
            if (!obj) return;

            // Show appropriate panel sections
            var isText = obj.type === 'i-text' || obj.type === 'text';
            var isShape = ['rect', 'circle', 'line', 'path'].includes(obj.type);
            var isImage = obj.type === 'image';

            $('.props-text').toggle(isText);
            $('.props-shape').toggle(isShape || isText);
            $('.props-image').toggle(isImage);
            $('.props-position').show();

            // Text properties
            if (isText) {
                $('#prop-font-family').val(obj.fontFamily);
                $('#prop-font-size').val(obj.fontSize);
                $('#prop-font-color').val(obj.fill || '#000000');

                $('#prop-bold').toggleClass('active', obj.fontWeight === 'bold');
                $('#prop-italic').toggleClass('active', obj.fontStyle === 'italic');
                $('#prop-underline').toggleClass('active', obj.underline === true);

                this.setAlignActive(obj.textAlign || 'left');
            }

            // Shape properties
            if (isShape) {
                $('#prop-fill-color').val(obj.fill || '#ffffff');
                $('#prop-stroke-color').val(obj.stroke || '#000000');
                $('#prop-stroke-width').val(obj.strokeWidth || 1);
            }

            // Common properties
            $('#prop-opacity').val(obj.opacity || 1);
            $('#prop-pos-x').val(Math.round(obj.left));
            $('#prop-pos-y').val(Math.round(obj.top));
            $('#prop-width').val(Math.round(obj.getScaledWidth()));
            $('#prop-height').val(Math.round(obj.getScaledHeight()));
            $('#prop-rotation').val(Math.round(obj.angle || 0));
        },

        /**
         * Clear properties panel
         */
        clearPropertiesPanel: function() {
            $('.props-text, .props-shape, .props-image, .props-position').hide();
        },

        /**
         * Update selected object property
         */
        updateSelectedProperty: function(prop, value) {
            if (!this.selectedObject) return;

            this.selectedObject.set(prop, value);
            this.canvas.renderAll();
            this.saveHistory();
        },

        /**
         * Set selected object width
         */
        setSelectedWidth: function(width) {
            if (!this.selectedObject) return;

            var currentWidth = this.selectedObject.getScaledWidth();
            var scale = width / (this.selectedObject.width || currentWidth);
            this.selectedObject.set('scaleX', scale);

            if ($('#prop-lock-aspect').is(':checked')) {
                this.selectedObject.set('scaleY', scale);
                $('#prop-height').val(Math.round(this.selectedObject.getScaledHeight()));
            }

            this.canvas.renderAll();
            this.saveHistory();
        },

        /**
         * Set selected object height
         */
        setSelectedHeight: function(height) {
            if (!this.selectedObject) return;

            var currentHeight = this.selectedObject.getScaledHeight();
            var scale = height / (this.selectedObject.height || currentHeight);
            this.selectedObject.set('scaleY', scale);

            if ($('#prop-lock-aspect').is(':checked')) {
                this.selectedObject.set('scaleX', scale);
                $('#prop-width').val(Math.round(this.selectedObject.getScaledWidth()));
            }

            this.canvas.renderAll();
            this.saveHistory();
        },

        /**
         * Set text alignment button active state
         */
        setAlignActive: function(align) {
            $('#prop-align-left, #prop-align-center, #prop-align-right').removeClass('active');
            $('#prop-align-' + align).addClass('active');
        },

        /**
         * Update layers panel
         */
        updateLayersPanel: function() {
            var self = this;
            var objects = this.canvas.getObjects();
            var $panel = $('#layers-list');
            $panel.empty();

            // Reverse order (top layer first)
            for (var i = objects.length - 1; i >= 0; i--) {
                var obj = objects[i];
                var name = this.getObjectName(obj);
                var isVisible = obj.visible !== false;
                var isLocked = !obj.selectable;

                var html = '<div class="layer-item" data-index="' + i + '">';
                html += '<span class="layer-visibility' + (isVisible ? '' : ' hidden') + '"><span class="dashicons dashicons-visibility"></span></span>';
                html += '<span class="layer-name">' + name + '</span>';
                html += '<span class="layer-lock' + (isLocked ? ' locked' : '') + '"><span class="dashicons dashicons-' + (isLocked ? 'lock' : 'unlock') + '"></span></span>';
                html += '</div>';

                $panel.append(html);
            }
        },

        /**
         * Get friendly name for object
         */
        getObjectName: function(obj) {
            if (obj.customType) {
                var typeNames = {
                    'text': 'Text',
                    'dynamic_field': 'Dynamic: ' + (obj.dynamicField || 'Field'),
                    'rectangle': 'Rectangle',
                    'circle': 'Circle',
                    'line': 'Line',
                    'image': 'Image',
                    'qr_code': 'QR Code',
                    'border_frame': 'Border Frame',
                    'signature_line': 'Signature Line',
                    'border': 'Border'
                };
                return typeNames[obj.customType] || obj.customType;
            }

            if (obj.type === 'i-text' || obj.type === 'text') {
                var text = obj.text || '';
                return 'Text: ' + (text.length > 20 ? text.substring(0, 20) + '...' : text);
            }

            return obj.type || 'Object';
        },

        /**
         * Highlight layer item for selected object
         */
        highlightLayerItem: function(obj) {
            var objects = this.canvas.getObjects();
            var index = objects.indexOf(obj);

            $('.layer-item').removeClass('selected');
            $('.layer-item[data-index="' + index + '"]').addClass('selected');
        },

        /**
         * Delete selected object
         */
        deleteSelected: function() {
            var activeObject = this.canvas.getActiveObject();
            if (activeObject) {
                this.canvas.remove(activeObject);
                this.canvas.renderAll();
                this.saveHistory();
            }
        },

        /**
         * Duplicate selected object
         */
        duplicateSelected: function() {
            var self = this;
            var activeObject = this.canvas.getActiveObject();

            if (activeObject) {
                activeObject.clone(function(cloned) {
                    cloned.set({
                        left: cloned.left + 20,
                        top: cloned.top + 20
                    });
                    self.canvas.add(cloned);
                    self.canvas.setActiveObject(cloned);
                    self.canvas.renderAll();
                    self.saveHistory();
                });
            }
        },

        /**
         * Copy selected object
         */
        copy: function() {
            var activeObject = this.canvas.getActiveObject();
            if (activeObject) {
                activeObject.clone(function(cloned) {
                    this.clipboard = cloned;
                }.bind(this));
            }
        },

        /**
         * Paste from clipboard
         */
        paste: function() {
            var self = this;
            if (this.clipboard) {
                this.clipboard.clone(function(cloned) {
                    cloned.set({
                        left: cloned.left + 20,
                        top: cloned.top + 20
                    });
                    self.canvas.add(cloned);
                    self.canvas.setActiveObject(cloned);
                    self.canvas.renderAll();
                    self.saveHistory();
                });
            }
        },

        /**
         * Select all objects
         */
        selectAll: function() {
            var objects = this.canvas.getObjects();
            var selection = new fabric.ActiveSelection(objects, { canvas: this.canvas });
            this.canvas.setActiveObject(selection);
            this.canvas.renderAll();
        },

        /**
         * Bring selected object to front
         */
        bringToFront: function() {
            var activeObject = this.canvas.getActiveObject();
            if (activeObject) {
                activeObject.bringToFront();
                this.canvas.renderAll();
                this.updateLayersPanel();
                this.saveHistory();
            }
        },

        /**
         * Send selected object to back
         */
        sendToBack: function() {
            var activeObject = this.canvas.getActiveObject();
            if (activeObject) {
                activeObject.sendToBack();
                this.canvas.renderAll();
                this.updateLayersPanel();
                this.saveHistory();
            }
        },

        /**
         * Nudge selected object with arrow keys
         */
        nudge: function(direction, delta) {
            if (!this.selectedObject) return;

            switch (direction) {
                case 'ArrowUp':
                    this.selectedObject.top -= delta;
                    break;
                case 'ArrowDown':
                    this.selectedObject.top += delta;
                    break;
                case 'ArrowLeft':
                    this.selectedObject.left -= delta;
                    break;
                case 'ArrowRight':
                    this.selectedObject.left += delta;
                    break;
            }

            this.canvas.renderAll();
            this.updatePropertiesPanel(this.selectedObject);
        },

        /**
         * Zoom canvas
         */
        zoom: function(factor) {
            var currentZoom = this.canvas.getZoom();
            var newZoom = currentZoom * factor;
            newZoom = Math.min(Math.max(newZoom, 0.25), 3);
            this.setZoom(newZoom);
        },

        /**
         * Set specific zoom level
         */
        setZoom: function(zoom) {
            this.canvas.setZoom(zoom);
            this.canvas.setWidth(this.settings.width * zoom);
            this.canvas.setHeight(this.settings.height * zoom);
            this.canvas.renderAll();
            $('#zoom-level').text(Math.round(zoom * 100) + '%');
        },

        /**
         * Zoom to fit container
         */
        zoomToFit: function() {
            var container = $('#certificate-canvas-container');
            var containerWidth = container.width() - 40;
            var containerHeight = container.height() - 40;

            var scaleX = containerWidth / this.settings.width;
            var scaleY = containerHeight / this.settings.height;
            var scale = Math.min(scaleX, scaleY, 1);

            this.setZoom(scale);
        },

        /**
         * Set canvas size
         */
        setCanvasSize: function(preset) {
            var sizes = {
                'letter-landscape': { width: 792, height: 612 },
                'letter-portrait': { width: 612, height: 792 },
                'a4-landscape': { width: 842, height: 595 },
                'a4-portrait': { width: 595, height: 842 },
                'custom': null
            };

            if (sizes[preset]) {
                this.settings.width = sizes[preset].width;
                this.settings.height = sizes[preset].height;
                this.canvas.setWidth(this.settings.width);
                this.canvas.setHeight(this.settings.height);
                this.canvas.renderAll();
                this.saveHistory();
            }
        },

        /**
         * Toggle grid display
         */
        toggleGrid: function(show) {
            // Remove existing grid
            var objects = this.canvas.getObjects();
            objects.forEach(function(obj) {
                if (obj.isGrid) {
                    this.canvas.remove(obj);
                }
            }.bind(this));

            if (show) {
                var gridSize = 20;
                var lines = [];

                // Vertical lines
                for (var x = 0; x <= this.settings.width; x += gridSize) {
                    lines.push(new fabric.Line([x, 0, x, this.settings.height], {
                        stroke: '#e0e0e0',
                        strokeWidth: 0.5,
                        selectable: false,
                        evented: false,
                        isGrid: true
                    }));
                }

                // Horizontal lines
                for (var y = 0; y <= this.settings.height; y += gridSize) {
                    lines.push(new fabric.Line([0, y, this.settings.width, y], {
                        stroke: '#e0e0e0',
                        strokeWidth: 0.5,
                        selectable: false,
                        evented: false,
                        isGrid: true
                    }));
                }

                lines.forEach(function(line) {
                    this.canvas.add(line);
                    this.canvas.sendToBack(line);
                }.bind(this));
            }

            this.canvas.renderAll();
        },

        /**
         * Toggle snap to grid
         */
        toggleSnap: function(enabled) {
            if (enabled) {
                this.canvas.on('object:moving', this.snapToGrid.bind(this));
            } else {
                this.canvas.off('object:moving', this.snapToGrid.bind(this));
            }
        },

        /**
         * Snap object to grid
         */
        snapToGrid: function(e) {
            var gridSize = 20;
            var obj = e.target;

            obj.set({
                left: Math.round(obj.left / gridSize) * gridSize,
                top: Math.round(obj.top / gridSize) * gridSize
            });
        },

        /**
         * Save state to history
         */
        saveHistory: function() {
            // Remove any future states if we're not at the end
            if (this.historyIndex < this.history.length - 1) {
                this.history = this.history.slice(0, this.historyIndex + 1);
            }

            // Save current state
            var state = JSON.stringify(this.canvas.toJSON(['customType', 'dynamicField', 'qrData', 'isGrid']));
            this.history.push(state);

            // Limit history size
            if (this.history.length > this.maxHistory) {
                this.history.shift();
            } else {
                this.historyIndex++;
            }

            this.updateUndoRedoButtons();
        },

        /**
         * Undo last action
         */
        undo: function() {
            if (this.historyIndex > 0) {
                this.historyIndex--;
                this.loadState(this.history[this.historyIndex]);
                this.updateUndoRedoButtons();
            }
        },

        /**
         * Redo last undone action
         */
        redo: function() {
            if (this.historyIndex < this.history.length - 1) {
                this.historyIndex++;
                this.loadState(this.history[this.historyIndex]);
                this.updateUndoRedoButtons();
            }
        },

        /**
         * Load state from JSON
         */
        loadState: function(state) {
            var self = this;
            this.canvas.loadFromJSON(state, function() {
                self.canvas.renderAll();
                self.updateLayersPanel();
            });
        },

        /**
         * Update undo/redo button states
         */
        updateUndoRedoButtons: function() {
            $('#btn-undo').prop('disabled', this.historyIndex <= 0);
            $('#btn-redo').prop('disabled', this.historyIndex >= this.history.length - 1);
        },

        /**
         * Save template to database
         */
        saveTemplate: function() {
            var self = this;
            var templateData = this.exportTemplateData();

            // Get template name
            var templateName = $('#template-name').val() || 'Custom Template';

            $.ajax({
                url: certificateCanvasData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'swiftlms_save_canvas_template',
                    nonce: certificateCanvasData.nonce,
                    post_id: certificateCanvasData.postId,
                    template_name: templateName,
                    template_data: JSON.stringify(templateData)
                },
                beforeSend: function() {
                    $('#btn-save-template').prop('disabled', true).text('Saving...');
                },
                success: function(response) {
                    if (response.success) {
                        self.isDirty = false;
                        alert('Template saved successfully!');
                    } else {
                        alert('Error saving template: ' + response.data);
                    }
                },
                error: function() {
                    alert('Error saving template. Please try again.');
                },
                complete: function() {
                    $('#btn-save-template').prop('disabled', false).text('Save Template');
                }
            });
        },

        /**
         * Export template data
         */
        exportTemplateData: function() {
            var canvasData = this.canvas.toJSON(['customType', 'dynamicField', 'qrData']);

            return {
                version: '2.0',
                canvas: canvasData,
                settings: this.settings
            };
        },

        /**
         * Load template from data
         */
        loadTemplate: function(data) {
            var self = this;

            if (typeof data === 'string') {
                data = JSON.parse(data);
            }

            // Load settings
            if (data.settings) {
                this.settings = $.extend(this.settings, data.settings);
                this.canvas.setWidth(this.settings.width);
                this.canvas.setHeight(this.settings.height);
                this.canvas.setBackgroundColor(this.settings.backgroundColor);
            }

            // Load canvas objects
            if (data.canvas) {
                this.canvas.loadFromJSON(data.canvas, function() {
                    self.canvas.renderAll();
                    self.updateLayersPanel();
                    self.zoomToFit();
                });
            }
        },

        /**
         * Preview certificate
         */
        preview: function() {
            var dataUrl = this.canvas.toDataURL({
                format: 'png',
                quality: 1,
                multiplier: 2
            });

            var previewWindow = window.open('', '_blank');
            previewWindow.document.write('<html><head><title>Certificate Preview</title>');
            previewWindow.document.write('<style>body{margin:0;display:flex;justify-content:center;align-items:center;min-height:100vh;background:#f0f0f0;}</style>');
            previewWindow.document.write('</head><body>');
            previewWindow.document.write('<img src="' + dataUrl + '" style="max-width:100%;box-shadow:0 4px 20px rgba(0,0,0,0.2);">');
            previewWindow.document.write('</body></html>');
            previewWindow.document.close();
        },

        /**
         * Export as PNG
         */
        exportPNG: function() {
            var dataUrl = this.canvas.toDataURL({
                format: 'png',
                quality: 1,
                multiplier: 2
            });

            var link = document.createElement('a');
            link.download = 'certificate.png';
            link.href = dataUrl;
            link.click();
        },

        /**
         * Export as PDF
         */
        exportPDF: function() {
            var self = this;
            var templateData = this.exportTemplateData();

            // Send to server for PDF generation
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = certificateCanvasData.ajaxUrl;
            form.target = '_blank';

            var fields = {
                action: 'swiftlms_generate_canvas_pdf',
                nonce: certificateCanvasData.nonce,
                template_data: JSON.stringify(templateData)
            };

            for (var key in fields) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = fields[key];
                form.appendChild(input);
            }

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        },

        /**
         * Load from pre-built template
         */
        loadPrebuiltTemplate: function(templateId) {
            var self = this;

            $.ajax({
                url: certificateCanvasData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'swiftlms_get_prebuilt_template',
                    nonce: certificateCanvasData.nonce,
                    template_id: templateId
                },
                success: function(response) {
                    if (response.success && response.data.elements) {
                        self.canvas.clear();
                        self.canvas.setBackgroundColor(response.data.background?.color || '#ffffff');

                        // Convert JSON template elements to Fabric objects
                        self.convertTemplateElements(response.data.elements);
                        self.saveHistory();
                    }
                }
            });
        },

        /**
         * Convert JSON template elements to Fabric objects
         */
        convertTemplateElements: function(elements) {
            var self = this;

            elements.forEach(function(el) {
                switch (el.type) {
                    case 'text':
                    case 'dynamic_field':
                        var textContent = el.type === 'dynamic_field'
                            ? (self.dynamicFields['{' + el.field + '}'] || el.field)
                            : el.content;

                        var text = new fabric.IText(textContent, {
                            left: el.x || 0,
                            top: el.y || 0,
                            fontFamily: el.fontFamily || 'Open Sans',
                            fontSize: el.fontSize || 24,
                            fill: el.fill || '#333333',
                            fontWeight: el.fontWeight || 'normal',
                            fontStyle: el.fontStyle || 'normal',
                            textAlign: el.align || 'left',
                            originX: el.align || 'left',
                            originY: 'top',
                            customType: el.type,
                            dynamicField: el.type === 'dynamic_field' ? '{' + el.field + '}' : null
                        });
                        self.canvas.add(text);
                        break;

                    case 'rectangle':
                        var rect = new fabric.Rect({
                            left: el.x || 0,
                            top: el.y || 0,
                            width: el.width || 100,
                            height: el.height || 50,
                            fill: el.fill || 'transparent',
                            stroke: el.stroke || '#333333',
                            strokeWidth: el.strokeWidth || 1,
                            rx: el.cornerRadius || 0,
                            ry: el.cornerRadius || 0,
                            customType: 'rectangle'
                        });
                        self.canvas.add(rect);
                        break;

                    case 'line':
                        var line = new fabric.Line([el.x1 || 0, el.y1 || 0, el.x2 || 100, el.y2 || 0], {
                            stroke: el.stroke || '#333333',
                            strokeWidth: el.strokeWidth || 1,
                            customType: 'line'
                        });
                        self.canvas.add(line);
                        break;

                    case 'image':
                        if (el.src) {
                            fabric.Image.fromURL(el.src, function(img) {
                                img.set({
                                    left: el.x || 0,
                                    top: el.y || 0,
                                    scaleX: el.width ? el.width / img.width : 1,
                                    scaleY: el.height ? el.height / img.height : 1,
                                    customType: 'image'
                                });
                                self.canvas.add(img);
                                self.canvas.renderAll();
                            }, { crossOrigin: 'anonymous' });
                        }
                        break;

                    case 'qr_code':
                        self.addQRCode({
                            left: el.x,
                            top: el.y,
                            data: el.data || '{certificate_id}'
                        });
                        break;
                }
            });

            self.canvas.renderAll();
            self.updateLayersPanel();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        CertificateCanvasEditor.init();
    });

    // Warn before leaving if unsaved changes
    $(window).on('beforeunload', function() {
        if (CertificateCanvasEditor.isDirty) {
            return 'You have unsaved changes. Are you sure you want to leave?';
        }
    });

})(jQuery);
