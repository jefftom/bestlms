/**
 * SwiftLMS Course Player
 *
 * Handles video player integration, course navigation, and UI interactions.
 *
 * @package SwiftLMS
 */

(function($) {
    'use strict';

    const SwiftLMSPlayer = {
        /**
         * Elements
         */
        elements: {
            videoContainer: null,
            videoPlayer: null,
            markCompleteBtn: null,
            resumePrompt: null,
            sidebar: null,
        },

        /**
         * State
         */
        state: {
            playerReady: false,
            resumePosition: 0,
            playerType: null, // 'youtube', 'vimeo', 'html5'
            lastPosition: 0,
            positionTracker: null,
        },

        /**
         * Initialize the course player
         */
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.detectVideoPlayer();
            this.initSidebar();
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.elements.videoContainer = $('#swiftlms-video-container');
            this.elements.videoPlayer = $('#swiftlms-video-player');
            this.elements.markCompleteBtn = $('#swiftlms-mark-complete');
            this.elements.resumePrompt = $('#swiftlms-resume-prompt');
            this.elements.sidebar = $('.swiftlms-lesson-sidebar');
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Mark complete button
            this.elements.markCompleteBtn.on('click', this.handleMarkComplete.bind(this));

            // Resume prompt buttons
            $('#swiftlms-resume-yes').on('click', this.handleResumeYes.bind(this));
            $('#swiftlms-resume-no').on('click', this.handleResumeNo.bind(this));

            // Sidebar toggle
            $('#swiftlms-sidebar-toggle').on('click', this.toggleSidebar.bind(this));

            // Enroll button
            $('.swiftlms-enroll-btn').on('click', this.handleEnroll.bind(this));

            // Listen for completion events
            $(document).on('swiftlms:lesson_completed', this.handleLessonCompleted.bind(this));

            // Keyboard shortcuts
            $(document).on('keydown', this.handleKeyboard.bind(this));
        },

        /**
         * Detect and initialize video player type
         */
        detectVideoPlayer: function() {
            if (!this.elements.videoPlayer.length) {
                return;
            }

            const src = this.elements.videoPlayer.attr('src') || '';

            if (this.elements.videoPlayer.is('iframe')) {
                if (src.includes('youtube.com')) {
                    this.state.playerType = 'youtube';
                    this.initYouTubePlayer();
                } else if (src.includes('vimeo.com')) {
                    this.state.playerType = 'vimeo';
                    this.initVimeoPlayer();
                }
            } else if (this.elements.videoPlayer.is('video')) {
                this.state.playerType = 'html5';
                this.initHTML5Player();
            }
        },

        /**
         * Initialize YouTube player
         */
        initYouTubePlayer: function() {
            // Load YouTube API if not already loaded
            if (typeof YT === 'undefined') {
                const tag = document.createElement('script');
                tag.src = 'https://www.youtube.com/iframe_api';
                const firstScriptTag = document.getElementsByTagName('script')[0];
                firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

                window.onYouTubeIframeAPIReady = this.onYouTubeReady.bind(this);
            } else {
                this.onYouTubeReady();
            }
        },

        /**
         * YouTube API ready callback
         */
        onYouTubeReady: function() {
            const self = this;
            const iframe = this.elements.videoPlayer[0];

            this.ytPlayer = new YT.Player(iframe, {
                events: {
                    onReady: function(event) {
                        self.state.playerReady = true;
                        self.onPlayerReady(event.target.getDuration());
                    },
                    onStateChange: function(event) {
                        self.onYouTubeStateChange(event);
                    },
                },
            });
        },

        /**
         * YouTube state change handler
         */
        onYouTubeStateChange: function(event) {
            switch (event.data) {
                case YT.PlayerState.PLAYING:
                    this.onPlay();
                    break;
                case YT.PlayerState.PAUSED:
                    this.onPause();
                    break;
                case YT.PlayerState.ENDED:
                    this.onEnded();
                    break;
            }
        },

        /**
         * Initialize Vimeo player
         */
        initVimeoPlayer: function() {
            // Load Vimeo API if not already loaded
            if (typeof Vimeo === 'undefined') {
                const script = document.createElement('script');
                script.src = 'https://player.vimeo.com/api/player.js';
                script.onload = this.onVimeoReady.bind(this);
                document.head.appendChild(script);
            } else {
                this.onVimeoReady();
            }
        },

        /**
         * Vimeo API ready callback
         */
        onVimeoReady: function() {
            const self = this;
            const iframe = this.elements.videoPlayer[0];

            this.vimeoPlayer = new Vimeo.Player(iframe);

            this.vimeoPlayer.getDuration().then(function(duration) {
                self.state.playerReady = true;
                self.onPlayerReady(duration);
            });

            this.vimeoPlayer.on('play', this.onPlay.bind(this));
            this.vimeoPlayer.on('pause', this.onPause.bind(this));
            this.vimeoPlayer.on('ended', this.onEnded.bind(this));
            this.vimeoPlayer.on('timeupdate', function(data) {
                self.onTimeUpdate(data.seconds);
            });
        },

        /**
         * Initialize HTML5 video player
         */
        initHTML5Player: function() {
            const self = this;
            const video = this.elements.videoPlayer[0];

            video.addEventListener('loadedmetadata', function() {
                self.state.playerReady = true;
                self.onPlayerReady(video.duration);
            });

            video.addEventListener('play', this.onPlay.bind(this));
            video.addEventListener('pause', this.onPause.bind(this));
            video.addEventListener('ended', this.onEnded.bind(this));
            video.addEventListener('timeupdate', function() {
                self.onTimeUpdate(video.currentTime);
            });
        },

        /**
         * Player ready callback
         */
        onPlayerReady: function(duration) {
            if (typeof SwiftLMSProgressTracker !== 'undefined') {
                SwiftLMSProgressTracker.setDuration(duration);
            }

            // Check for resume position
            const resumePos = this.getResumePosition();
            if (resumePos > 0 && this.elements.resumePrompt.length) {
                this.state.resumePosition = resumePos;
                this.elements.resumePrompt.show();
            }
        },

        /**
         * Get resume position
         */
        getResumePosition: function() {
            if (typeof SwiftLMSProgressTracker !== 'undefined') {
                return SwiftLMSProgressTracker.getResumePosition();
            }
            return 0;
        },

        /**
         * Video play handler
         */
        onPlay: function() {
            if (typeof SwiftLMSProgressTracker !== 'undefined') {
                SwiftLMSProgressTracker.setPlaying(true);
            }

            // Start position tracking
            this.startPositionTracking();
        },

        /**
         * Video pause handler
         */
        onPause: function() {
            if (typeof SwiftLMSProgressTracker !== 'undefined') {
                SwiftLMSProgressTracker.setPlaying(false);
            }

            this.stopPositionTracking();
        },

        /**
         * Video ended handler
         */
        onEnded: function() {
            if (typeof SwiftLMSProgressTracker !== 'undefined') {
                SwiftLMSProgressTracker.setPlaying(false);
                SwiftLMSProgressTracker.syncToServer();
            }

            this.stopPositionTracking();
        },

        /**
         * Time update handler
         */
        onTimeUpdate: function(currentTime) {
            if (typeof SwiftLMSProgressTracker !== 'undefined') {
                const position = Math.floor(currentTime);

                // Record the second if it's a new one
                if (position !== this.state.lastPosition) {
                    SwiftLMSProgressTracker.recordSecond(position);
                    SwiftLMSProgressTracker.updatePosition(position);
                    this.state.lastPosition = position;
                }
            }
        },

        /**
         * Start position tracking interval
         */
        startPositionTracking: function() {
            const self = this;

            this.stopPositionTracking();

            // For YouTube, we need to poll position
            if (this.state.playerType === 'youtube' && this.ytPlayer) {
                this.state.positionTracker = setInterval(function() {
                    if (self.ytPlayer && self.ytPlayer.getCurrentTime) {
                        self.onTimeUpdate(self.ytPlayer.getCurrentTime());
                    }
                }, 1000);
            }
        },

        /**
         * Stop position tracking interval
         */
        stopPositionTracking: function() {
            if (this.state.positionTracker) {
                clearInterval(this.state.positionTracker);
                this.state.positionTracker = null;
            }
        },

        /**
         * Seek to position
         */
        seekTo: function(position) {
            switch (this.state.playerType) {
                case 'youtube':
                    if (this.ytPlayer && this.ytPlayer.seekTo) {
                        this.ytPlayer.seekTo(position, true);
                    }
                    break;
                case 'vimeo':
                    if (this.vimeoPlayer) {
                        this.vimeoPlayer.setCurrentTime(position);
                    }
                    break;
                case 'html5':
                    this.elements.videoPlayer[0].currentTime = position;
                    break;
            }
        },

        /**
         * Handle resume yes button
         */
        handleResumeYes: function() {
            this.elements.resumePrompt.hide();
            this.seekTo(this.state.resumePosition);
        },

        /**
         * Handle resume no button
         */
        handleResumeNo: function() {
            this.elements.resumePrompt.hide();
            this.seekTo(0);
        },

        /**
         * Handle mark complete button
         */
        handleMarkComplete: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            $btn.prop('disabled', true).text(swiftlmsData.i18n.loading);

            if (typeof SwiftLMSProgressTracker !== 'undefined') {
                SwiftLMSProgressTracker.markComplete()
                    .done(function() {
                        // Button will be updated by event handler
                    })
                    .fail(function(xhr) {
                        let message = swiftlmsData.i18n.error;
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                        alert(message);
                        $btn.prop('disabled', false).text(swiftlmsData.i18n.markComplete);
                    });
            }
        },

        /**
         * Handle lesson completed event
         */
        handleLessonCompleted: function(e, data) {
            // Update mark complete button
            this.elements.markCompleteBtn
                .prop('disabled', true)
                .removeClass('swiftlms-button-primary')
                .addClass('swiftlms-completed-badge')
                .html('<span class="dashicons dashicons-yes-alt"></span> ' + swiftlmsData.i18n.completed);

            // Update sidebar navigation
            const currentItem = $('.swiftlms-nav-lesson.swiftlms-current');
            currentItem.addClass('swiftlms-status-completed')
                       .find('.swiftlms-nav-status').remove();
            currentItem.find('a').append('<span class="dashicons dashicons-yes-alt swiftlms-nav-status"></span>');

            // Show next lesson prompt if available
            const $nextLink = $('.swiftlms-next-lesson').first();
            if ($nextLink.length && data.courseProgress < 100) {
                // Could show a modal or highlight next lesson
                console.log('Lesson completed! Next lesson available.');
            }

            // Check if course is complete
            if (data.courseCompleted) {
                $(document).trigger('swiftlms:course_completed', data);
            }
        },

        /**
         * Handle enroll button click
         */
        handleEnroll: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const courseId = $btn.data('course-id');

            $btn.prop('disabled', true).text(swiftlmsData.i18n.loading);

            $.ajax({
                url: swiftlmsData.restUrl + 'courses/' + courseId + '/enroll',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': swiftlmsData.nonce,
                },
            })
            .done(function(response) {
                // Reload page to show enrolled state
                window.location.reload();
            })
            .fail(function(xhr) {
                let message = swiftlmsData.i18n.error;
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                alert(message);
                $btn.prop('disabled', false).text('Enroll Now');
            });
        },

        /**
         * Initialize sidebar
         */
        initSidebar: function() {
            // Scroll current lesson into view
            const $current = $('.swiftlms-nav-lesson.swiftlms-current');
            if ($current.length) {
                const $nav = $('.swiftlms-course-nav');
                const navTop = $nav.offset().top;
                const currentTop = $current.offset().top;

                if (currentTop > navTop + $nav.height()) {
                    $nav.scrollTop(currentTop - navTop - 100);
                }
            }
        },

        /**
         * Toggle sidebar
         */
        toggleSidebar: function() {
            this.elements.sidebar.toggleClass('swiftlms-sidebar-collapsed');
        },

        /**
         * Handle keyboard shortcuts
         */
        handleKeyboard: function(e) {
            // Only handle if not in an input
            if ($(e.target).is('input, textarea, select')) {
                return;
            }

            switch (e.key) {
                case 'ArrowLeft':
                    // Previous lesson
                    if (e.shiftKey) {
                        const $prev = $('.swiftlms-prev-lesson');
                        if ($prev.length) {
                            window.location.href = $prev.attr('href');
                        }
                    }
                    break;

                case 'ArrowRight':
                    // Next lesson
                    if (e.shiftKey) {
                        const $next = $('.swiftlms-next-lesson');
                        if ($next.length) {
                            window.location.href = $next.attr('href');
                        }
                    }
                    break;

                case 'Escape':
                    // Close resume prompt if open
                    this.elements.resumePrompt.hide();
                    break;
            }
        },
    };

    // Initialize on document ready
    $(document).ready(function() {
        SwiftLMSPlayer.init();
    });

    // Expose globally
    window.SwiftLMSPlayer = SwiftLMSPlayer;

})(jQuery);
