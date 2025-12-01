/**
 * SwiftLMS Student Dashboard JavaScript
 *
 * @package SwiftLMS
 */

(function() {
    'use strict';

    /**
     * Dashboard Module
     */
    const SwiftLMSDashboard = {
        /**
         * Initialize dashboard functionality
         */
        init: function() {
            this.bindEvents();
            this.initPasswordToggle();
            this.initPasswordStrength();
            this.initAvatarPreview();
            this.initShareButtons();
            this.initProgressRings();
        },

        /**
         * Bind global events
         */
        bindEvents: function() {
            // Form submission handling
            const profileForm = document.querySelector('.sfls-profile-form');
            if (profileForm) {
                profileForm.addEventListener('submit', this.handleProfileSubmit.bind(this));
            }

            // Password confirmation validation
            const confirmPassword = document.getElementById('confirm_password');
            if (confirmPassword) {
                confirmPassword.addEventListener('input', this.validatePasswordMatch.bind(this));
            }
        },

        /**
         * Toggle password visibility
         */
        initPasswordToggle: function() {
            const toggleButtons = document.querySelectorAll('.sfls-toggle-password');

            toggleButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    const passwordField = this.parentElement.querySelector('input');
                    const icon = this.querySelector('.dashicons');

                    if (passwordField.type === 'password') {
                        passwordField.type = 'text';
                        icon.classList.remove('dashicons-visibility');
                        icon.classList.add('dashicons-hidden');
                        this.setAttribute('aria-label', swiftlms_dashboard.i18n.hide_password || 'Hide password');
                    } else {
                        passwordField.type = 'password';
                        icon.classList.remove('dashicons-hidden');
                        icon.classList.add('dashicons-visibility');
                        this.setAttribute('aria-label', swiftlms_dashboard.i18n.show_password || 'Show password');
                    }
                });
            });
        },

        /**
         * Password strength meter
         */
        initPasswordStrength: function() {
            const newPassword = document.getElementById('new_password');
            const strengthMeter = document.getElementById('password-strength');

            if (!newPassword || !strengthMeter) {
                return;
            }

            newPassword.addEventListener('input', function() {
                const password = this.value;
                const strength = SwiftLMSDashboard.calculatePasswordStrength(password);

                strengthMeter.className = 'sfls-password-strength';

                if (password.length === 0) {
                    return;
                }

                if (strength < 2) {
                    strengthMeter.classList.add('weak');
                } else if (strength < 3) {
                    strengthMeter.classList.add('fair');
                } else if (strength < 4) {
                    strengthMeter.classList.add('good');
                } else {
                    strengthMeter.classList.add('strong');
                }
            });
        },

        /**
         * Calculate password strength
         *
         * @param {string} password
         * @return {number}
         */
        calculatePasswordStrength: function(password) {
            let strength = 0;

            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;

            return strength;
        },

        /**
         * Validate password match
         */
        validatePasswordMatch: function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');

            if (!newPassword || !confirmPassword) {
                return;
            }

            if (confirmPassword.value && newPassword.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity(
                    swiftlms_dashboard.i18n.passwords_not_match || 'Passwords do not match'
                );
            } else {
                confirmPassword.setCustomValidity('');
            }
        },

        /**
         * Avatar preview on file select
         */
        initAvatarPreview: function() {
            const avatarInput = document.getElementById('sfls-avatar-input');
            const avatarPreview = document.getElementById('sfls-avatar-preview');

            if (!avatarInput || !avatarPreview) {
                return;
            }

            avatarInput.addEventListener('change', function() {
                const file = this.files[0];

                if (!file) {
                    return;
                }

                // Validate file type
                if (!file.type.match('image.*')) {
                    alert(swiftlms_dashboard.i18n.invalid_image || 'Please select a valid image file.');
                    this.value = '';
                    return;
                }

                // Validate file size (2MB max)
                if (file.size > 2 * 1024 * 1024) {
                    alert(swiftlms_dashboard.i18n.file_too_large || 'Image must be less than 2MB.');
                    this.value = '';
                    return;
                }

                // Preview the image
                const reader = new FileReader();
                reader.onload = function(e) {
                    avatarPreview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            });
        },

        /**
         * Share button functionality
         */
        initShareButtons: function() {
            const shareButtons = document.querySelectorAll('.sfls-share-btn');

            shareButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    const url = this.dataset.url;
                    const title = swiftlms_dashboard.i18n.certificate_share_title || 'My Certificate';

                    if (navigator.share) {
                        // Use native share if available
                        navigator.share({
                            title: title,
                            url: url
                        }).catch(function() {
                            // User cancelled or error
                        });
                    } else {
                        // Fallback to clipboard
                        SwiftLMSDashboard.copyToClipboard(url);
                        SwiftLMSDashboard.showToast(
                            swiftlms_dashboard.i18n.link_copied || 'Link copied to clipboard!'
                        );
                    }
                });
            });
        },

        /**
         * Copy text to clipboard
         *
         * @param {string} text
         */
        copyToClipboard: function(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text);
            } else {
                // Fallback for older browsers
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            }
        },

        /**
         * Show toast notification
         *
         * @param {string} message
         */
        showToast: function(message) {
            // Remove existing toast
            const existingToast = document.querySelector('.sfls-toast');
            if (existingToast) {
                existingToast.remove();
            }

            // Create toast element
            const toast = document.createElement('div');
            toast.className = 'sfls-toast';
            toast.textContent = message;
            toast.style.cssText = `
                position: fixed;
                bottom: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: #1a1a2e;
                color: #fff;
                padding: 12px 24px;
                border-radius: 8px;
                font-size: 14px;
                z-index: 9999;
                animation: sfls-toast-in 0.3s ease;
            `;

            document.body.appendChild(toast);

            // Remove after 3 seconds
            setTimeout(function() {
                toast.style.animation = 'sfls-toast-out 0.3s ease forwards';
                setTimeout(function() {
                    toast.remove();
                }, 300);
            }, 3000);
        },

        /**
         * Initialize progress ring animations
         */
        initProgressRings: function() {
            const progressRings = document.querySelectorAll('.sfls-progress-ring');

            progressRings.forEach(function(ring) {
                const progress = ring.dataset.progress || 0;
                const circle = ring.querySelector('.sfls-progress-fill');

                if (circle) {
                    // Animate on scroll into view
                    const observer = new IntersectionObserver(function(entries) {
                        entries.forEach(function(entry) {
                            if (entry.isIntersecting) {
                                circle.style.strokeDasharray = progress + ', 100';
                                observer.unobserve(entry.target);
                            }
                        });
                    }, { threshold: 0.5 });

                    observer.observe(ring);
                }
            });
        },

        /**
         * Handle profile form submission
         *
         * @param {Event} e
         */
        handleProfileSubmit: function(e) {
            const submitBtn = e.target.querySelector('.sfls-save-profile');

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="dashicons dashicons-update sfls-spin"></span> ' +
                    (swiftlms_dashboard.i18n.saving || 'Saving...');
            }
        }
    };

    // Add toast animation styles
    const style = document.createElement('style');
    style.textContent = `
        @keyframes sfls-toast-in {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }
        @keyframes sfls-toast-out {
            from {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
            to {
                opacity: 0;
                transform: translateX(-50%) translateY(20px);
            }
        }
        .sfls-spin {
            animation: sfls-spin 1s linear infinite;
        }
        @keyframes sfls-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            SwiftLMSDashboard.init();
        });
    } else {
        SwiftLMSDashboard.init();
    }

    // Expose for external use
    window.SwiftLMSDashboard = SwiftLMSDashboard;
})();
