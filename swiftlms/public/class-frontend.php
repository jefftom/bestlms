<?php
/**
 * Frontend class.
 *
 * @package SwiftLMS\Frontend
 */

namespace SwiftLMS\Frontend;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles frontend functionality.
 */
class Frontend {

    /**
     * Enqueue frontend styles.
     *
     * @return void
     */
    public function enqueue_styles(): void {
        // Only load on SwiftLMS pages.
        if ( ! $this->is_swiftlms_page() ) {
            return;
        }

        wp_enqueue_style(
            'swiftlms-frontend',
            SWIFTLMS_PLUGIN_URL . 'public/assets/css/frontend.css',
            array(),
            SWIFTLMS_VERSION
        );

        // Focus mode styles.
        if ( $this->is_focus_mode() ) {
            wp_enqueue_style(
                'swiftlms-focus-mode',
                SWIFTLMS_PLUGIN_URL . 'public/assets/css/focus-mode.css',
                array( 'swiftlms-frontend' ),
                SWIFTLMS_VERSION
            );
        }
    }

    /**
     * Enqueue frontend scripts.
     *
     * @return void
     */
    public function enqueue_scripts(): void {
        // Only load on SwiftLMS pages.
        if ( ! $this->is_swiftlms_page() ) {
            return;
        }

        // Course player script.
        wp_enqueue_script(
            'swiftlms-course-player',
            SWIFTLMS_PLUGIN_URL . 'assets/js/course-player.js',
            array( 'jquery' ),
            SWIFTLMS_VERSION,
            true
        );

        // Progress tracker script.
        wp_enqueue_script(
            'swiftlms-progress-tracker',
            SWIFTLMS_PLUGIN_URL . 'assets/js/progress-tracker.js',
            array( 'swiftlms-course-player' ),
            SWIFTLMS_VERSION,
            true
        );

        // Localize scripts.
        wp_localize_script(
            'swiftlms-course-player',
            'swiftlmsData',
            $this->get_script_data()
        );
    }

    /**
     * Get script localization data.
     *
     * @return array
     */
    protected function get_script_data(): array {
        $data = array(
            'restUrl'          => rest_url( 'swiftlms/v1/' ),
            'nonce'            => wp_create_nonce( 'wp_rest' ),
            'isLoggedIn'       => is_user_logged_in(),
            'userId'           => get_current_user_id(),
            'syncInterval'     => (int) get_option( 'swiftlms_video_sync_interval', 5 ) * 1000,
            'enableResume'     => get_option( 'swiftlms_enable_resume', true ),
            'focusMode'        => $this->is_focus_mode(),
            'i18n'             => array(
                'loading'         => __( 'Loading...', 'swiftlms' ),
                'markComplete'    => __( 'Mark Complete', 'swiftlms' ),
                'completed'       => __( 'Completed', 'swiftlms' ),
                'nextLesson'      => __( 'Next Lesson', 'swiftlms' ),
                'previousLesson'  => __( 'Previous Lesson', 'swiftlms' ),
                'resumeWatching'  => __( 'Resume where you left off?', 'swiftlms' ),
                'yes'             => __( 'Yes', 'swiftlms' ),
                'no'              => __( 'No', 'swiftlms' ),
            ),
        );

        // Add lesson-specific data if on lesson page.
        if ( is_singular( 'sfls_lesson' ) ) {
            $lesson_id = get_the_ID();
            $course_id = get_post_meta( $lesson_id, '_swiftlms_course_id', true );

            $data['lessonId'] = $lesson_id;
            $data['courseId'] = $course_id;

            // Get progress if logged in.
            if ( is_user_logged_in() ) {
                $progress = swiftlms()->progress()->get_progress( get_current_user_id(), $lesson_id );
                $data['progress'] = $progress ?: array(
                    'status'           => 'not_started',
                    'progress_percent' => 0,
                    'video_position'   => 0,
                );
            }

            // Video settings.
            $data['video'] = array(
                'url'                => get_post_meta( $lesson_id, '_swiftlms_video_url', true ),
                'duration'           => (int) get_post_meta( $lesson_id, '_swiftlms_video_duration', true ),
                'autoplay'           => get_post_meta( $lesson_id, '_swiftlms_video_autoplay', true ) === '1',
                'trackCompletion'    => get_post_meta( $lesson_id, '_swiftlms_track_video_completion', true ) !== '0',
                'completionPercent'  => (int) get_post_meta( $lesson_id, '_swiftlms_video_completion_percent', true ) ?: 90,
            );
        }

        return $data;
    }

    /**
     * Template loader - loads custom templates for SwiftLMS post types.
     *
     * @param string $template The template to load.
     * @return string
     */
    public function template_loader( string $template ): string {
        if ( is_embed() ) {
            return $template;
        }

        $default_file = null;

        if ( is_singular( 'sfls_course' ) ) {
            $default_file = 'single-course.php';
        } elseif ( is_singular( 'sfls_lesson' ) ) {
            $default_file = 'single-lesson.php';
        } elseif ( is_singular( 'sfls_topic' ) ) {
            $default_file = 'single-topic.php';
        } elseif ( is_post_type_archive( 'sfls_course' ) ) {
            $default_file = 'archive-course.php';
        } elseif ( is_tax( 'sfls_course_category' ) || is_tax( 'sfls_course_tag' ) || is_tax( 'sfls_difficulty' ) ) {
            $default_file = 'archive-course.php';
        }

        if ( $default_file ) {
            $template = $this->locate_template( $default_file, $template );
        }

        return $template;
    }

    /**
     * Locate a template file.
     *
     * Look for template in:
     * 1. Theme's swiftlms/ directory
     * 2. Theme root
     * 3. Plugin templates/ directory
     *
     * @param string $template_name The template name.
     * @param string $default       The default template.
     * @return string The located template path.
     */
    protected function locate_template( string $template_name, string $default ): string {
        // Look in theme's swiftlms directory first.
        $template = locate_template(
            array(
                'swiftlms/' . $template_name,
                $template_name,
            )
        );

        // Fall back to plugin template.
        if ( ! $template ) {
            $template = SWIFTLMS_PLUGIN_DIR . 'templates/' . $template_name;
        }

        // Use default if template doesn't exist.
        if ( ! file_exists( $template ) ) {
            $template = $default;
        }

        /**
         * Filter the located template.
         *
         * @param string $template      The template path.
         * @param string $template_name The template name.
         * @param string $default       The default template.
         */
        return apply_filters( 'swiftlms_locate_template', $template, $template_name, $default );
    }

    /**
     * Check if current page is a SwiftLMS page.
     *
     * @return bool
     */
    protected function is_swiftlms_page(): bool {
        $post_types = array( 'sfls_course', 'sfls_lesson', 'sfls_topic' );
        $taxonomies = array( 'sfls_course_category', 'sfls_course_tag', 'sfls_difficulty' );

        foreach ( $post_types as $post_type ) {
            if ( is_singular( $post_type ) || is_post_type_archive( $post_type ) ) {
                return true;
            }
        }

        foreach ( $taxonomies as $taxonomy ) {
            if ( is_tax( $taxonomy ) ) {
                return true;
            }
        }

        /**
         * Filter whether current page is a SwiftLMS page.
         *
         * @param bool $is_swiftlms_page Whether it's a SwiftLMS page.
         */
        return apply_filters( 'swiftlms_is_swiftlms_page', false );
    }

    /**
     * Check if focus mode should be active.
     *
     * @return bool
     */
    protected function is_focus_mode(): bool {
        // Check if globally enabled.
        if ( ! get_option( 'swiftlms_enable_focus_mode', true ) ) {
            return false;
        }

        // Only on lesson pages.
        if ( ! is_singular( 'sfls_lesson' ) && ! is_singular( 'sfls_topic' ) ) {
            return false;
        }

        // Allow override via query param.
        if ( isset( $_GET['focus'] ) ) {
            return $_GET['focus'] === '1';
        }

        return true;
    }

    /**
     * Add body classes for SwiftLMS pages.
     *
     * @param array $classes Body classes.
     * @return array
     */
    public function body_class( array $classes ): array {
        if ( $this->is_swiftlms_page() ) {
            $classes[] = 'swiftlms-page';
        }

        if ( $this->is_focus_mode() ) {
            $classes[] = 'swiftlms-focus-mode';
        }

        if ( is_singular( 'sfls_lesson' ) ) {
            $classes[] = 'swiftlms-lesson';
        }

        if ( is_singular( 'sfls_course' ) ) {
            $classes[] = 'swiftlms-course';
        }

        return $classes;
    }
}
