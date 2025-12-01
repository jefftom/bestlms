/**
 * SwiftLMS Reports JavaScript
 *
 * @package SwiftLMS
 */

(function($) {
    'use strict';

    /**
     * Reports Module
     */
    const SwiftLMSReports = {
        /**
         * Chart instances
         */
        charts: {},

        /**
         * Chart default options
         */
        chartDefaults: {
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
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        },

        /**
         * Color palette
         */
        colors: {
            primary: '#4f46e5',
            primaryLight: 'rgba(79, 70, 229, 0.1)',
            success: '#059669',
            successLight: 'rgba(5, 150, 105, 0.1)',
            info: '#0891b2',
            infoLight: 'rgba(8, 145, 178, 0.1)',
            warning: '#d97706',
            warningLight: 'rgba(217, 119, 6, 0.1)',
            danger: '#dc2626',
            dangerLight: 'rgba(220, 38, 38, 0.1)'
        },

        /**
         * Initialize
         */
        init: function() {
            this.initTooltips();
            this.initExportForm();
            this.initDataRefresh();
        },

        /**
         * Create line chart
         *
         * @param {string} elementId Canvas element ID
         * @param {object} data Chart data
         * @param {object} options Additional options
         * @return {Chart}
         */
        createLineChart: function(elementId, data, options) {
            var ctx = document.getElementById(elementId);
            if (!ctx) return null;

            var config = {
                type: 'line',
                data: data,
                options: $.extend(true, {}, this.chartDefaults, options || {})
            };

            this.charts[elementId] = new Chart(ctx, config);
            return this.charts[elementId];
        },

        /**
         * Create bar chart
         *
         * @param {string} elementId Canvas element ID
         * @param {object} data Chart data
         * @param {object} options Additional options
         * @return {Chart}
         */
        createBarChart: function(elementId, data, options) {
            var ctx = document.getElementById(elementId);
            if (!ctx) return null;

            var config = {
                type: 'bar',
                data: data,
                options: $.extend(true, {}, this.chartDefaults, options || {})
            };

            this.charts[elementId] = new Chart(ctx, config);
            return this.charts[elementId];
        },

        /**
         * Create doughnut chart
         *
         * @param {string} elementId Canvas element ID
         * @param {object} data Chart data
         * @param {object} options Additional options
         * @return {Chart}
         */
        createDoughnutChart: function(elementId, data, options) {
            var ctx = document.getElementById(elementId);
            if (!ctx) return null;

            var defaultDoughnutOptions = {
                plugins: {
                    legend: {
                        position: 'right'
                    }
                },
                cutout: '60%'
            };

            var config = {
                type: 'doughnut',
                data: data,
                options: $.extend(true, {}, defaultDoughnutOptions, options || {})
            };

            this.charts[elementId] = new Chart(ctx, config);
            return this.charts[elementId];
        },

        /**
         * Update chart data
         *
         * @param {string} elementId Chart element ID
         * @param {object} newData New data
         */
        updateChart: function(elementId, newData) {
            var chart = this.charts[elementId];
            if (!chart) return;

            chart.data = newData;
            chart.update();
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            // Simple tooltip for stat cards
            $('.sfls-stat-card').each(function() {
                var $card = $(this);
                var label = $card.find('.sfls-stat-label').text();
                $card.attr('title', label);
            });
        },

        /**
         * Initialize export form
         */
        initExportForm: function() {
            var $form = $('.sfls-export-section form');
            if (!$form.length) return;

            $form.on('submit', function() {
                var $btn = $(this).find('button[type="submit"]');
                $btn.prop('disabled', true).text('Generating...');

                // Re-enable after 3 seconds (download should start)
                setTimeout(function() {
                    $btn.prop('disabled', false).html(
                        '<span class="dashicons dashicons-download"></span> Download CSV'
                    );
                }, 3000);
            });
        },

        /**
         * Initialize data refresh
         */
        initDataRefresh: function() {
            var self = this;

            // Auto-refresh every 5 minutes on overview tab
            if ($('#enrollmentChart').length) {
                setInterval(function() {
                    self.refreshOverviewData();
                }, 300000);
            }
        },

        /**
         * Refresh overview data
         */
        refreshOverviewData: function() {
            // Could implement AJAX refresh here
            console.log('Data refresh check...');
        },

        /**
         * Format number with commas
         *
         * @param {number} num Number to format
         * @return {string}
         */
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },

        /**
         * Format duration
         *
         * @param {number} seconds Seconds
         * @return {string}
         */
        formatDuration: function(seconds) {
            if (!seconds) return '0m';

            var hours = Math.floor(seconds / 3600);
            var minutes = Math.floor((seconds % 3600) / 60);

            if (hours > 0) {
                return hours + 'h ' + minutes + 'm';
            }
            return minutes + 'm';
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
         * Get gradient for chart
         *
         * @param {CanvasRenderingContext2D} ctx Canvas context
         * @param {string} color Base color
         * @return {CanvasGradient}
         */
        getGradient: function(ctx, color) {
            var gradient = ctx.createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, color);
            gradient.addColorStop(1, 'rgba(255, 255, 255, 0)');
            return gradient;
        },

        /**
         * Export table to CSV
         *
         * @param {string} tableSelector Table selector
         * @param {string} filename Filename
         */
        exportTableToCSV: function(tableSelector, filename) {
            var $table = $(tableSelector);
            var csv = [];

            // Headers
            var headers = [];
            $table.find('thead th').each(function() {
                headers.push('"' + $(this).text().replace(/"/g, '""') + '"');
            });
            csv.push(headers.join(','));

            // Rows
            $table.find('tbody tr').each(function() {
                var row = [];
                $(this).find('td').each(function() {
                    var text = $(this).text().trim().replace(/"/g, '""');
                    row.push('"' + text + '"');
                });
                csv.push(row.join(','));
            });

            // Download
            var blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = filename || 'export.csv';
            link.click();
        }
    };

    // Initialize on ready
    $(document).ready(function() {
        SwiftLMSReports.init();
    });

    // Expose for external use
    window.SwiftLMSReports = SwiftLMSReports;

})(jQuery);
