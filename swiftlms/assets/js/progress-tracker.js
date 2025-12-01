/**
 * SwiftLMS Progress Tracker
 *
 * Implements the secondsWatchedVector pattern for accurate video tracking.
 * Syncs progress to server and handles video resume.
 *
 * @package SwiftLMS
 */

(function($) {
    'use strict';

    const SwiftLMSProgressTracker = {
        /**
         * Configuration
         */
        config: {
            syncInterval: 5000,     // Sync interval in ms
            localStorageKey: 'swiftlms_progress_',
            milestones: [25, 50, 75, 95],
        },

        /**
         * State
         */
        state: {
            lessonId: 0,
            courseId: 0,
            userId: 0,
            videoDuration: 0,
            videoPosition: 0,
            secondsWatchedVector: {},
            lastSyncTime: 0,
            syncTimer: null,
            isPlaying: false,
            milestonesFired: {},
            isDirty: false,
        },

        /**
         * Initialize the progress tracker
         */
        init: function() {
            if (typeof swiftlmsData === 'undefined') {
                return;
            }

            this.state.lessonId = swiftlmsData.lessonId || 0;
            this.state.courseId = swiftlmsData.courseId || 0;
            this.state.userId = swiftlmsData.userId || 0;
            this.config.syncInterval = swiftlmsData.syncInterval || 5000;

            if (!this.state.lessonId) {
                return;
            }

            // Load cached progress from localStorage
            this.loadLocalProgress();

            // Initialize existing progress from server
            if (swiftlmsData.progress) {
                this.mergeProgress(swiftlmsData.progress);
            }

            // Start sync timer
            this.startSyncTimer();

            // Sync on page unload
            $(window).on('beforeunload', this.syncBeforeUnload.bind(this));

            console.log('SwiftLMS Progress Tracker initialized');
        },

        /**
         * Load progress from localStorage
         */
        loadLocalProgress: function() {
            const key = this.config.localStorageKey + this.state.lessonId;
            const cached = localStorage.getItem(key);

            if (cached) {
                try {
                    const data = JSON.parse(cached);
                    this.state.secondsWatchedVector = data.secondsWatchedVector || {};
                    this.state.videoPosition = data.videoPosition || 0;
                } catch (e) {
                    console.error('Failed to parse cached progress', e);
                }
            }
        },

        /**
         * Save progress to localStorage
         */
        saveLocalProgress: function() {
            const key = this.config.localStorageKey + this.state.lessonId;
            const data = {
                secondsWatchedVector: this.state.secondsWatchedVector,
                videoPosition: this.state.videoPosition,
                timestamp: Date.now(),
            };

            try {
                localStorage.setItem(key, JSON.stringify(data));
            } catch (e) {
                console.error('Failed to save progress to localStorage', e);
            }
        },

        /**
         * Merge server progress with local progress
         */
        mergeProgress: function(serverProgress) {
            if (serverProgress.seconds_watched_vector) {
                const serverVector = serverProgress.seconds_watched_vector;
                for (const second in serverVector) {
                    if (serverVector.hasOwnProperty(second)) {
                        const serverCount = parseInt(serverVector[second], 10);
                        const localCount = parseInt(this.state.secondsWatchedVector[second] || 0, 10);
                        this.state.secondsWatchedVector[second] = Math.max(serverCount, localCount);
                    }
                }
            }

            // Keep the later position
            if (serverProgress.video_position > this.state.videoPosition) {
                this.state.videoPosition = serverProgress.video_position;
            }
        },

        /**
         * Record a watched second
         */
        recordSecond: function(second) {
            second = Math.floor(second);
            if (second < 0 || second >= this.state.videoDuration) {
                return;
            }

            if (!this.state.secondsWatchedVector[second]) {
                this.state.secondsWatchedVector[second] = 0;
            }

            this.state.secondsWatchedVector[second]++;
            this.state.isDirty = true;

            // Check milestones
            this.checkMilestones();
        },

        /**
         * Record a range of seconds (for seek operations)
         */
        recordRange: function(start, end) {
            for (let i = Math.floor(start); i <= Math.floor(end); i++) {
                this.recordSecond(i);
            }
        },

        /**
         * Update video position
         */
        updatePosition: function(position) {
            this.state.videoPosition = Math.floor(position);
            this.state.isDirty = true;
            this.saveLocalProgress();
        },

        /**
         * Set video duration
         */
        setDuration: function(duration) {
            this.state.videoDuration = Math.floor(duration);
        },

        /**
         * Calculate unique seconds watched
         */
        getUniqueSecondsWatched: function() {
            let count = 0;
            for (const second in this.state.secondsWatchedVector) {
                if (this.state.secondsWatchedVector[second] > 0) {
                    count++;
                }
            }
            return count;
        },

        /**
         * Calculate progress percentage
         */
        getProgressPercent: function() {
            if (this.state.videoDuration === 0) {
                return 0;
            }

            const uniqueSeconds = this.getUniqueSecondsWatched();
            return Math.min(100, (uniqueSeconds / this.state.videoDuration) * 100);
        },

        /**
         * Check and fire milestone events
         */
        checkMilestones: function() {
            const progress = this.getProgressPercent();

            this.config.milestones.forEach(milestone => {
                if (progress >= milestone && !this.state.milestonesFired[milestone]) {
                    this.state.milestonesFired[milestone] = true;
                    this.fireMilestone(milestone);
                }
            });
        },

        /**
         * Fire a milestone event
         */
        fireMilestone: function(milestone) {
            $(document).trigger('swiftlms:video_milestone', {
                lessonId: this.state.lessonId,
                milestone: milestone,
                progress: this.getProgressPercent(),
            });

            console.log('SwiftLMS: Video milestone reached:', milestone + '%');
        },

        /**
         * Start the sync timer
         */
        startSyncTimer: function() {
            this.stopSyncTimer();

            this.state.syncTimer = setInterval(() => {
                if (this.state.isDirty && this.state.isPlaying) {
                    this.syncToServer();
                }
            }, this.config.syncInterval);
        },

        /**
         * Stop the sync timer
         */
        stopSyncTimer: function() {
            if (this.state.syncTimer) {
                clearInterval(this.state.syncTimer);
                this.state.syncTimer = null;
            }
        },

        /**
         * Sync progress to server
         */
        syncToServer: function() {
            if (!this.state.userId || !this.state.lessonId) {
                return Promise.resolve();
            }

            // Throttle syncs
            const now = Date.now();
            if (now - this.state.lastSyncTime < 1000) {
                return Promise.resolve();
            }
            this.state.lastSyncTime = now;

            const data = {
                lesson_id: this.state.lessonId,
                position: this.state.videoPosition,
                duration: this.state.videoDuration,
                seconds_watched_vector: this.state.secondsWatchedVector,
            };

            return $.ajax({
                url: swiftlmsData.restUrl + 'progress/video-update',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': swiftlmsData.nonce,
                },
                contentType: 'application/json',
                data: JSON.stringify(data),
            })
            .done((response) => {
                this.state.isDirty = false;

                // Check if video is now complete
                if (response.progress && response.progress.status === 'completed') {
                    $(document).trigger('swiftlms:lesson_completed', {
                        lessonId: this.state.lessonId,
                    });
                }
            })
            .fail((xhr, status, error) => {
                console.error('SwiftLMS: Failed to sync progress', error);
            });
        },

        /**
         * Sync before page unload
         */
        syncBeforeUnload: function() {
            if (this.state.isDirty && this.state.userId) {
                // Use sendBeacon for reliable delivery
                const data = {
                    lesson_id: this.state.lessonId,
                    position: this.state.videoPosition,
                    duration: this.state.videoDuration,
                    seconds_watched_vector: this.state.secondsWatchedVector,
                };

                if (navigator.sendBeacon) {
                    const blob = new Blob([JSON.stringify(data)], { type: 'application/json' });
                    navigator.sendBeacon(
                        swiftlmsData.restUrl + 'progress/video-update?_wpnonce=' + swiftlmsData.nonce,
                        blob
                    );
                }
            }
        },

        /**
         * Mark lesson as complete
         */
        markComplete: function() {
            if (!this.state.userId || !this.state.lessonId) {
                return Promise.reject('Not logged in or no lesson ID');
            }

            return $.ajax({
                url: swiftlmsData.restUrl + 'progress/' + this.state.lessonId + '/complete',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': swiftlmsData.nonce,
                },
            })
            .done((response) => {
                $(document).trigger('swiftlms:lesson_completed', {
                    lessonId: this.state.lessonId,
                    courseProgress: response.course_progress,
                    courseCompleted: response.course_completed,
                });
            });
        },

        /**
         * Set playing state
         */
        setPlaying: function(isPlaying) {
            this.state.isPlaying = isPlaying;
        },

        /**
         * Get resume position
         */
        getResumePosition: function() {
            return this.state.videoPosition;
        },
    };

    // Initialize on document ready
    $(document).ready(function() {
        SwiftLMSProgressTracker.init();
    });

    // Expose globally
    window.SwiftLMSProgressTracker = SwiftLMSProgressTracker;

})(jQuery);
