/**
 * SwiftLMS Instructor Dashboard JavaScript
 *
 * @package SwiftLMS
 */

(function($) {
    'use strict';

    /**
     * Instructor Dashboard Module
     */
    const SwiftLMSInstructor = {
        /**
         * Initialize
         */
        init: function() {
            this.initCharts();
            this.bindEvents();
            this.initDataTables();
        },

        /**
         * Initialize charts
         */
        initCharts: function() {
            // Charts are initialized inline in the view templates
            // This method can be used for additional chart configuration
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;

            // Filter form auto-submit
            $('.sfls-filter-select').on('change', function() {
                $(this).closest('form').submit();
            });

            // Student search debounce
            var searchTimeout;
            $('.sfls-search-input').on('keyup', function() {
                var $input = $(this);
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    $input.closest('form').submit();
                }, 500);
            });

            // Export button
            $(document).on('click', '.sfls-export-btn', function(e) {
                e.preventDefault();
                self.exportData($(this).data('type'));
            });

            // Refresh data
            $(document).on('click', '.sfls-refresh-btn', function(e) {
                e.preventDefault();
                location.reload();
            });

            // Tooltips
            this.initTooltips();
        },

        /**
         * Initialize data tables
         */
        initDataTables: function() {
            // Sortable columns
            $('.sfls-table th[data-sortable]').on('click', function() {
                var $th = $(this);
                var column = $th.data('column');
                var order = $th.hasClass('asc') ? 'desc' : 'asc';

                // Update URL
                var url = new URL(window.location);
                url.searchParams.set('orderby', column);
                url.searchParams.set('order', order);
                window.location = url;
            });
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            $('[data-tooltip]').each(function() {
                var $el = $(this);
                var text = $el.data('tooltip');

                $el.on('mouseenter', function() {
                    var $tooltip = $('<div class="sfls-tooltip">' + text + '</div>');
                    $('body').append($tooltip);

                    var offset = $el.offset();
                    $tooltip.css({
                        top: offset.top - $tooltip.outerHeight() - 10,
                        left: offset.left + ($el.outerWidth() / 2) - ($tooltip.outerWidth() / 2)
                    });
                });

                $el.on('mouseleave', function() {
                    $('.sfls-tooltip').remove();
                });
            });
        },

        /**
         * Export data
         *
         * @param {string} type Export type
         */
        exportData: function(type) {
            var params = {
                action: 'sfls_instructor_export',
                type: type,
                nonce: swiftlms_instructor.nonce
            };

            // Add any active filters
            var urlParams = new URLSearchParams(window.location.search);
            urlParams.forEach(function(value, key) {
                if (key !== 'page') {
                    params[key] = value;
                }
            });

            // Create download URL
            var url = swiftlms_instructor.ajax_url + '?' + $.param(params);
            window.location = url;
        },

        /**
         * Show notification
         *
         * @param {string} message Message
         * @param {string} type Type (success, error, info)
         */
        showNotification: function(message, type) {
            type = type || 'info';

            var $notification = $('<div class="sfls-notification sfls-notification-' + type + '">' + message + '</div>');
            $('body').append($notification);

            setTimeout(function() {
                $notification.addClass('show');
            }, 10);

            setTimeout(function() {
                $notification.removeClass('show');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, 3000);
        },

        /**
         * Format currency
         *
         * @param {number} amount Amount
         * @return {string}
         */
        formatCurrency: function(amount) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(amount);
        },

        /**
         * Format number
         *
         * @param {number} num Number
         * @return {string}
         */
        formatNumber: function(num) {
            return new Intl.NumberFormat().format(num);
        },

        /**
         * Format percentage
         *
         * @param {number} value Value
         * @param {number} total Total
         * @return {string}
         */
        formatPercentage: function(value, total) {
            if (!total) return '0%';
            return Math.round((value / total) * 100) + '%';
        },

        /**
         * Create chart
         *
         * @param {string} elementId Canvas element ID
         * @param {string} type Chart type
         * @param {object} data Chart data
         * @param {object} options Chart options
         * @return {Chart}
         */
        createChart: function(elementId, type, data, options) {
            var ctx = document.getElementById(elementId);
            if (!ctx) return null;

            var defaultOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 15
                        }
                    }
                }
            };

            if (type === 'line' || type === 'bar') {
                defaultOptions.scales = {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                };
            }

            var config = {
                type: type,
                data: data,
                options: $.extend(true, {}, defaultOptions, options || {})
            };

            return new Chart(ctx, config);
        },

        /**
         * Update chart
         *
         * @param {Chart} chart Chart instance
         * @param {object} newData New data
         */
        updateChart: function(chart, newData) {
            if (!chart) return;
            chart.data = newData;
            chart.update();
        },

        /**
         * Animate counter
         *
         * @param {jQuery} $element Element
         * @param {number} target Target value
         * @param {number} duration Duration in ms
         */
        animateCounter: function($element, target, duration) {
            duration = duration || 1000;
            var start = 0;
            var startTime = null;

            function step(timestamp) {
                if (!startTime) startTime = timestamp;
                var progress = Math.min((timestamp - startTime) / duration, 1);
                var current = Math.floor(progress * target);
                $element.text(current.toLocaleString());

                if (progress < 1) {
                    requestAnimationFrame(step);
                } else {
                    $element.text(target.toLocaleString());
                }
            }

            requestAnimationFrame(step);
        },

        /**
         * Confirm action
         *
         * @param {string} message Confirmation message
         * @param {function} callback Callback on confirm
         */
        confirmAction: function(message, callback) {
            if (confirm(message)) {
                callback();
            }
        },

        /**
         * AJAX request wrapper
         *
         * @param {object} options Request options
         */
        ajax: function(options) {
            var self = this;

            var defaults = {
                url: swiftlms_instructor.ajax_url,
                type: 'POST',
                dataType: 'json',
                beforeSend: function() {
                    if (options.$button) {
                        options.$button.prop('disabled', true);
                        options.originalText = options.$button.text();
                        options.$button.text(swiftlms_instructor.i18n.loading);
                    }
                },
                success: function(response) {
                    if (response.success) {
                        if (options.onSuccess) {
                            options.onSuccess(response.data);
                        }
                        if (response.data.message) {
                            self.showNotification(response.data.message, 'success');
                        }
                    } else {
                        if (options.onError) {
                            options.onError(response.data);
                        }
                        self.showNotification(response.data.message || swiftlms_instructor.i18n.error, 'error');
                    }
                },
                error: function() {
                    self.showNotification(swiftlms_instructor.i18n.error, 'error');
                    if (options.onError) {
                        options.onError();
                    }
                },
                complete: function() {
                    if (options.$button) {
                        options.$button.prop('disabled', false);
                        options.$button.text(options.originalText);
                    }
                }
            };

            return $.ajax($.extend({}, defaults, options));
        }
    };

    // Initialize on ready
    $(document).ready(function() {
        SwiftLMSInstructor.init();
    });

    // Expose for external use
    window.SwiftLMSInstructor = SwiftLMSInstructor;

})(jQuery);
