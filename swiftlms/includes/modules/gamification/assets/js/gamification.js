/**
 * SwiftLMS Gamification JavaScript
 *
 * @package SwiftLMS
 */

(function($) {
    'use strict';

    /**
     * Gamification Module
     */
    const SwiftLMSGamification = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.checkBadgeNotifications();
            this.initLeaderboardTabs();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;

            // Leaderboard tabs
            $(document).on('click', '.sfls-leaderboard .sfls-tab-btn', function() {
                self.switchLeaderboardTab($(this));
            });

            // Close badge notification
            $(document).on('click', '.sfls-notification-close, .sfls-badge-notification-overlay', function(e) {
                if (e.target === this) {
                    self.closeBadgeNotification();
                }
            });

            // ESC to close notification
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    self.closeBadgeNotification();
                }
            });
        },

        /**
         * Check for new badge notifications
         */
        checkBadgeNotifications: function() {
            var self = this;

            $.ajax({
                url: swiftlms_gamification.ajax_url,
                type: 'POST',
                data: {
                    action: 'sfls_get_badge_notification',
                    nonce: swiftlms_gamification.nonce
                },
                success: function(response) {
                    if (response.success && response.data.badges.length > 0) {
                        // Show badges one by one
                        self.showBadgeNotifications(response.data.badges);
                    }
                }
            });
        },

        /**
         * Show badge notifications
         */
        showBadgeNotifications: function(badges) {
            var self = this;
            var index = 0;

            function showNext() {
                if (index < badges.length) {
                    self.showBadgeNotification(badges[index], function() {
                        index++;
                        setTimeout(showNext, 300);
                    });
                }
            }

            showNext();
        },

        /**
         * Show single badge notification
         */
        showBadgeNotification: function(badge, callback) {
            var self = this;

            // Create notification HTML
            var html = '<div class="sfls-badge-notification-overlay">';
            html += '<div class="sfls-badge-notification">';
            html += '<div class="sfls-notification-icon sfls-rarity-' + badge.rarity + '">';

            if (badge.icon_type === 'image' && badge.icon) {
                html += '<img src="' + badge.icon + '" alt="">';
            } else {
                html += '<span>' + (badge.icon || '🏆') + '</span>';
            }

            html += '</div>';
            html += '<h2 class="sfls-notification-title">' + swiftlms_gamification.i18n.badge_earned + '</h2>';
            html += '<p class="sfls-notification-badge-name">' + badge.title + '</p>';

            if (badge.description) {
                html += '<p class="sfls-notification-description">' + badge.description + '</p>';
            }

            if (badge.points_reward > 0) {
                html += '<div class="sfls-notification-points">';
                html += '<span>⭐</span>';
                html += '<span>' + swiftlms_gamification.i18n.points_earned.replace('%d', badge.points_reward) + '</span>';
                html += '</div>';
            }

            html += '<button class="sfls-notification-close">' + swiftlms_gamification.i18n.congratulations + '</button>';
            html += '</div>';
            html += '</div>';

            var $notification = $(html);
            $('body').append($notification);

            // Trigger animation
            setTimeout(function() {
                $notification.addClass('active');
            }, 50);

            // Store callback
            this.currentNotificationCallback = callback;
        },

        /**
         * Close badge notification
         */
        closeBadgeNotification: function() {
            var self = this;
            var $overlay = $('.sfls-badge-notification-overlay');

            if ($overlay.length) {
                $overlay.removeClass('active');

                setTimeout(function() {
                    $overlay.remove();

                    // Call callback if exists
                    if (self.currentNotificationCallback) {
                        self.currentNotificationCallback();
                        self.currentNotificationCallback = null;
                    }
                }, 300);

                // Mark as notified
                $.ajax({
                    url: swiftlms_gamification.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sfls_dismiss_badge_notification',
                        nonce: swiftlms_gamification.nonce
                    }
                });
            }
        },

        /**
         * Initialize leaderboard tabs
         */
        initLeaderboardTabs: function() {
            // Set initial active tab based on data
            $('.sfls-leaderboard').each(function() {
                var $leaderboard = $(this);
                var activeTab = $leaderboard.find('.sfls-tab-btn.active').data('period') || 'all_time';
                $leaderboard.data('current-period', activeTab);
            });
        },

        /**
         * Switch leaderboard tab
         */
        switchLeaderboardTab: function($tab) {
            var self = this;
            var $leaderboard = $tab.closest('.sfls-leaderboard');
            var period = $tab.data('period');

            // Update active tab
            $leaderboard.find('.sfls-tab-btn').removeClass('active');
            $tab.addClass('active');

            // Show loading
            var $list = $leaderboard.find('.sfls-leaderboard-list');
            $list.css('opacity', '0.5');

            // Fetch new data
            $.ajax({
                url: swiftlms_gamification.rest_url + 'leaderboard',
                type: 'GET',
                data: {
                    period: period,
                    limit: 10
                },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings ? wpApiSettings.nonce : '');
                },
                success: function(data) {
                    self.renderLeaderboard($list, data, period);
                    $list.css('opacity', '1');
                },
                error: function() {
                    $list.css('opacity', '1');
                }
            });
        },

        /**
         * Render leaderboard
         */
        renderLeaderboard: function($list, data, period) {
            var currentUserId = typeof swiftlms_user !== 'undefined' ? swiftlms_user.id : 0;
            var html = '';

            if (data.length === 0) {
                html = '<div class="sfls-leaderboard-empty">No rankings yet.</div>';
            } else {
                data.forEach(function(entry) {
                    var topClass = entry.rank <= 3 ? ' sfls-top-' + entry.rank : '';
                    var currentClass = entry.user_id === currentUserId ? ' sfls-current-user' : '';

                    html += '<div class="sfls-leaderboard-item' + topClass + currentClass + '">';
                    html += '<span class="sfls-rank">' + entry.rank + '</span>';
                    html += '<img src="' + entry.avatar + '" alt="" class="sfls-avatar">';
                    html += '<div class="sfls-user-info">';
                    html += '<span class="sfls-user-name">' + entry.display_name + '</span>';
                    html += '<span class="sfls-level-badge" style="--level-color: ' + entry.level.color + ';">';
                    html += '<span class="sfls-level-icon">' + entry.level.icon + '</span>';
                    html += '</span>';
                    html += '</div>';
                    html += '<span class="sfls-points">' + entry.points.toLocaleString() + ' pts</span>';
                    html += '</div>';
                });
            }

            $list.html(html);
        },

        /**
         * Show points popup animation
         */
        showPointsPopup: function(points, x, y) {
            var $popup = $('<div class="sfls-points-popup">+' + points + '</div>');

            $popup.css({
                left: x + 'px',
                top: y + 'px'
            });

            $('body').append($popup);

            setTimeout(function() {
                $popup.remove();
            }, 1500);
        },

        /**
         * Update user points display
         */
        updatePointsDisplay: function(newTotal) {
            $('.sfls-points-value').text(newTotal.toLocaleString());
        },

        /**
         * Animate level up
         */
        animateLevelUp: function(newLevel) {
            var html = '<div class="sfls-badge-notification-overlay">';
            html += '<div class="sfls-badge-notification">';
            html += '<div class="sfls-notification-icon" style="background: linear-gradient(135deg, ' + newLevel.color + '40, ' + newLevel.color + '20);">';
            html += '<span style="font-size: 64px;">' + newLevel.icon + '</span>';
            html += '</div>';
            html += '<h2 class="sfls-notification-title">' + swiftlms_gamification.i18n.level_up + '</h2>';
            html += '<p class="sfls-notification-badge-name">Level ' + newLevel.id + ': ' + newLevel.name + '</p>';
            html += '<button class="sfls-notification-close">' + swiftlms_gamification.i18n.congratulations + '</button>';
            html += '</div>';
            html += '</div>';

            var $notification = $(html);
            $('body').append($notification);

            setTimeout(function() {
                $notification.addClass('active');
            }, 50);
        }
    };

    // Initialize on ready
    $(document).ready(function() {
        SwiftLMSGamification.init();
    });

    // Expose for external use
    window.SwiftLMSGamification = SwiftLMSGamification;

})(jQuery);
