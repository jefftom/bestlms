<?php
/**
 * Lesson class.
 *
 * @package SwiftLMS\Core
 */

namespace SwiftLMS\Core;

use SwiftLMS\Abstracts\AbstractPostType;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles Lesson post type.
 */
class Lesson extends AbstractPostType {

    /**
     * Post type slug.
     *
     * @var string
     */
    protected string $post_type = 'sfls_lesson';

    /**
     * Singular name.
     *
     * @var string
     */
    protected string $singular = 'Lesson';

    /**
     * Plural name.
     *
     * @var string
     */
    protected string $plural = 'Lessons';

    /**
     * Menu icon.
     *
     * @var string
     */
    protected string $menu_icon = 'dashicons-media-document';

    /**
     * Supported features.
     *
     * @var array<string>
     */
    protected array $supports = array(
        'title',
        'editor',
        'thumbnail',
        'excerpt',
        'author',
        'custom-fields',
        'revisions',
    );

    /**
     * Register meta boxes.
     *
     * @return void
     */
    public function register_meta_boxes(): void {
        add_meta_box(
            'swiftlms_lesson_settings',
            __( 'Lesson Settings', 'swiftlms' ),
            array( $this, 'render_settings_meta_box' ),
            $this->post_type,
            'normal',
            'high'
        );

        add_meta_box(
            'swiftlms_lesson_video',
            __( 'Video Settings', 'swiftlms' ),
            array( $this, 'render_video_meta_box' ),
            $this->post_type,
            'normal',
            'high'
        );

        add_meta_box(
            'swiftlms_lesson_topics',
            __( 'Topics', 'swiftlms' ),
            array( $this, 'render_topics_meta_box' ),
            $this->post_type,
            'normal',
            'default'
        );
    }

    /**
     * Render the settings meta box.
     *
     * @param \WP_Post $post The post object.
     * @return void
     */
    public function render_settings_meta_box( \WP_Post $post ): void {
        wp_nonce_field( 'swiftlms_save_sfls_lesson', 'swiftlms_sfls_lesson_nonce' );

        $course_id         = get_post_meta( $post->ID, '_swiftlms_course_id', true );
        $order             = get_post_meta( $post->ID, '_swiftlms_order', true );
        $duration          = get_post_meta( $post->ID, '_swiftlms_duration', true );
        $is_preview        = get_post_meta( $post->ID, '_swiftlms_is_preview', true );
        $force_sequential  = get_post_meta( $post->ID, '_swiftlms_force_sequential', true );
        $completion_type   = get_post_meta( $post->ID, '_swiftlms_completion_type', true ) ?: 'manual';

        // Get courses for dropdown.
        $courses = get_posts(
            array(
                'post_type'      => 'sfls_course',
                'posts_per_page' => -1,
                'post_status'    => array( 'publish', 'draft', 'pending' ),
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );

        ?>
        <table class="form-table swiftlms-meta-table">
            <tr>
                <th><label for="swiftlms_course_id"><?php esc_html_e( 'Course', 'swiftlms' ); ?></label></th>
                <td>
                    <select id="swiftlms_course_id" name="swiftlms_course_id" required>
                        <option value=""><?php esc_html_e( 'Select a course', 'swiftlms' ); ?></option>
                        <?php foreach ( $courses as $course ) : ?>
                            <option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( $course_id, $course->ID ); ?>>
                                <?php echo esc_html( $course->post_title ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'The course this lesson belongs to.', 'swiftlms' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="swiftlms_order"><?php esc_html_e( 'Order', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="number" id="swiftlms_order" name="swiftlms_order" value="<?php echo esc_attr( $order ); ?>" class="small-text" min="0">
                    <p class="description"><?php esc_html_e( 'Lesson order within the course.', 'swiftlms' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="swiftlms_duration"><?php esc_html_e( 'Duration', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="text" id="swiftlms_duration" name="swiftlms_duration" value="<?php echo esc_attr( $duration ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g., 15 minutes', 'swiftlms' ); ?>">
                </td>
            </tr>
            <tr>
                <th><label for="swiftlms_completion_type"><?php esc_html_e( 'Completion Type', 'swiftlms' ); ?></label></th>
                <td>
                    <select id="swiftlms_completion_type" name="swiftlms_completion_type">
                        <option value="manual" <?php selected( $completion_type, 'manual' ); ?>><?php esc_html_e( 'Manual (Mark Complete button)', 'swiftlms' ); ?></option>
                        <option value="video" <?php selected( $completion_type, 'video' ); ?>><?php esc_html_e( 'Video (Complete when video finished)', 'swiftlms' ); ?></option>
                        <option value="topics" <?php selected( $completion_type, 'topics' ); ?>><?php esc_html_e( 'Topics (Complete all topics)', 'swiftlms' ); ?></option>
                        <option value="quiz" <?php selected( $completion_type, 'quiz' ); ?>><?php esc_html_e( 'Quiz (Pass associated quiz)', 'swiftlms' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Options', 'swiftlms' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="swiftlms_is_preview" value="1" <?php checked( $is_preview, '1' ); ?>>
                        <?php esc_html_e( 'Allow as free preview', 'swiftlms' ); ?>
                    </label>
                    <br>
                    <label>
                        <input type="checkbox" name="swiftlms_force_sequential" value="1" <?php checked( $force_sequential, '1' ); ?>>
                        <?php esc_html_e( 'Require previous lesson completion', 'swiftlms' ); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render the video meta box.
     *
     * @param \WP_Post $post The post object.
     * @return void
     */
    public function render_video_meta_box( \WP_Post $post ): void {
        $video_url          = get_post_meta( $post->ID, '_swiftlms_video_url', true );
        $video_type         = get_post_meta( $post->ID, '_swiftlms_video_type', true ) ?: 'url';
        $video_duration     = get_post_meta( $post->ID, '_swiftlms_video_duration', true );
        $autoplay           = get_post_meta( $post->ID, '_swiftlms_video_autoplay', true );
        $track_completion   = get_post_meta( $post->ID, '_swiftlms_track_video_completion', true ) ?: '1';
        $completion_percent = get_post_meta( $post->ID, '_swiftlms_video_completion_percent', true ) ?: '90';

        ?>
        <table class="form-table swiftlms-meta-table">
            <tr>
                <th><label for="swiftlms_video_type"><?php esc_html_e( 'Video Type', 'swiftlms' ); ?></label></th>
                <td>
                    <select id="swiftlms_video_type" name="swiftlms_video_type">
                        <option value="none" <?php selected( $video_type, 'none' ); ?>><?php esc_html_e( 'No Video', 'swiftlms' ); ?></option>
                        <option value="url" <?php selected( $video_type, 'url' ); ?>><?php esc_html_e( 'URL (YouTube, Vimeo, etc.)', 'swiftlms' ); ?></option>
                        <option value="embed" <?php selected( $video_type, 'embed' ); ?>><?php esc_html_e( 'Embed Code', 'swiftlms' ); ?></option>
                        <option value="upload" <?php selected( $video_type, 'upload' ); ?>><?php esc_html_e( 'Self-Hosted', 'swiftlms' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="swiftlms_video_url"><?php esc_html_e( 'Video URL', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="url" id="swiftlms_video_url" name="swiftlms_video_url" value="<?php echo esc_url( $video_url ); ?>" class="large-text">
                    <p class="description"><?php esc_html_e( 'YouTube, Vimeo, or self-hosted video URL.', 'swiftlms' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="swiftlms_video_duration"><?php esc_html_e( 'Video Duration', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="number" id="swiftlms_video_duration" name="swiftlms_video_duration" value="<?php echo esc_attr( $video_duration ); ?>" class="small-text" min="0"> <?php esc_html_e( 'seconds', 'swiftlms' ); ?>
                    <p class="description"><?php esc_html_e( 'Video duration in seconds (auto-detected for most videos).', 'swiftlms' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Video Options', 'swiftlms' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="swiftlms_video_autoplay" value="1" <?php checked( $autoplay, '1' ); ?>>
                        <?php esc_html_e( 'Autoplay video on page load', 'swiftlms' ); ?>
                    </label>
                    <br>
                    <label>
                        <input type="checkbox" name="swiftlms_track_video_completion" value="1" <?php checked( $track_completion, '1' ); ?>>
                        <?php esc_html_e( 'Track video progress for completion', 'swiftlms' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="swiftlms_video_completion_percent"><?php esc_html_e( 'Completion Threshold', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="number" id="swiftlms_video_completion_percent" name="swiftlms_video_completion_percent" value="<?php echo esc_attr( $completion_percent ); ?>" class="small-text" min="50" max="100">%
                    <p class="description"><?php esc_html_e( 'Percentage of unique seconds that must be watched to complete.', 'swiftlms' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render the topics meta box.
     *
     * @param \WP_Post $post The post object.
     * @return void
     */
    public function render_topics_meta_box( \WP_Post $post ): void {
        $topics = $this->get_topics( $post->ID );
        ?>
        <div class="swiftlms-topics-builder" data-lesson-id="<?php echo esc_attr( $post->ID ); ?>">
            <?php if ( empty( $topics ) ) : ?>
                <p class="swiftlms-no-topics"><?php esc_html_e( 'No topics yet. Topics allow you to break lessons into smaller sections.', 'swiftlms' ); ?></p>
            <?php else : ?>
                <ul class="swiftlms-sortable-topics">
                    <?php foreach ( $topics as $topic ) : ?>
                        <li class="swiftlms-topic-item" data-topic-id="<?php echo esc_attr( $topic->ID ); ?>">
                            <span class="swiftlms-drag-handle dashicons dashicons-menu"></span>
                            <span class="swiftlms-topic-title"><?php echo esc_html( $topic->post_title ); ?></span>
                            <span class="swiftlms-topic-actions">
                                <a href="<?php echo esc_url( get_edit_post_link( $topic->ID ) ); ?>"><?php esc_html_e( 'Edit', 'swiftlms' ); ?></a>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <button type="button" class="button" id="swiftlms-add-topic-btn">
                <?php esc_html_e( 'Add Topic', 'swiftlms' ); ?>
            </button>
        </div>
        <?php
    }

    /**
     * Save post meta.
     *
     * @param int      $post_id The post ID.
     * @param \WP_Post $post    The post object.
     * @return void
     */
    protected function save_post_meta( int $post_id, \WP_Post $post ): void {
        // Text and number fields.
        $fields = array(
            'swiftlms_course_id'                => '_swiftlms_course_id',
            'swiftlms_order'                    => '_swiftlms_order',
            'swiftlms_duration'                 => '_swiftlms_duration',
            'swiftlms_completion_type'          => '_swiftlms_completion_type',
            'swiftlms_video_type'               => '_swiftlms_video_type',
            'swiftlms_video_url'                => '_swiftlms_video_url',
            'swiftlms_video_duration'           => '_swiftlms_video_duration',
            'swiftlms_video_completion_percent' => '_swiftlms_video_completion_percent',
        );

        foreach ( $fields as $field => $meta_key ) {
            if ( isset( $_POST[ $field ] ) ) {
                $value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );

                if ( $field === 'swiftlms_video_url' ) {
                    $value = esc_url_raw( wp_unslash( $_POST[ $field ] ) );
                }

                update_post_meta( $post_id, $meta_key, $value );
            }
        }

        // Checkbox fields.
        $checkboxes = array(
            'swiftlms_is_preview'             => '_swiftlms_is_preview',
            'swiftlms_force_sequential'       => '_swiftlms_force_sequential',
            'swiftlms_video_autoplay'         => '_swiftlms_video_autoplay',
            'swiftlms_track_video_completion' => '_swiftlms_track_video_completion',
        );

        foreach ( $checkboxes as $field => $meta_key ) {
            $value = isset( $_POST[ $field ] ) ? '1' : '0';
            update_post_meta( $post_id, $meta_key, $value );
        }
    }

    /**
     * Get topics for a lesson.
     *
     * @param int    $lesson_id   The lesson ID.
     * @param string $post_status Optional. Filter by post status.
     * @return \WP_Post[] Array of topic posts.
     */
    public function get_topics( int $lesson_id, string $post_status = 'any' ): array {
        return get_posts(
            array(
                'post_type'      => 'sfls_topic',
                'posts_per_page' => -1,
                'post_status'    => $post_status,
                'meta_key'       => '_swiftlms_lesson_id',
                'meta_value'     => $lesson_id,
                'orderby'        => 'meta_value_num',
                'meta_key'       => '_swiftlms_order',
                'order'          => 'ASC',
            )
        );
    }

    /**
     * Get the course ID for a lesson.
     *
     * @param int $lesson_id The lesson ID.
     * @return int The course ID.
     */
    public function get_course_id( int $lesson_id ): int {
        return (int) get_post_meta( $lesson_id, '_swiftlms_course_id', true );
    }

    /**
     * Get lesson metadata.
     *
     * @param int $lesson_id The lesson ID.
     * @return array Lesson metadata.
     */
    public function get_meta( int $lesson_id ): array {
        return array(
            'course_id'          => $this->get_course_id( $lesson_id ),
            'order'              => get_post_meta( $lesson_id, '_swiftlms_order', true ),
            'duration'           => get_post_meta( $lesson_id, '_swiftlms_duration', true ),
            'completion_type'    => get_post_meta( $lesson_id, '_swiftlms_completion_type', true ) ?: 'manual',
            'is_preview'         => get_post_meta( $lesson_id, '_swiftlms_is_preview', true ) === '1',
            'force_sequential'   => get_post_meta( $lesson_id, '_swiftlms_force_sequential', true ) === '1',
            'video_url'          => get_post_meta( $lesson_id, '_swiftlms_video_url', true ),
            'video_type'         => get_post_meta( $lesson_id, '_swiftlms_video_type', true ),
            'video_duration'     => get_post_meta( $lesson_id, '_swiftlms_video_duration', true ),
            'completion_percent' => get_post_meta( $lesson_id, '_swiftlms_video_completion_percent', true ) ?: 90,
        );
    }

    /**
     * Check if a lesson is available for a user.
     *
     * @param int $lesson_id The lesson ID.
     * @param int $user_id   The user ID.
     * @return bool True if the lesson is available.
     */
    public function is_available( int $lesson_id, int $user_id ): bool {
        // Check if it's a preview lesson.
        if ( get_post_meta( $lesson_id, '_swiftlms_is_preview', true ) === '1' ) {
            return true;
        }

        // Check enrollment.
        $course_id = $this->get_course_id( $lesson_id );
        if ( ! swiftlms()->enrollment()->is_enrolled( $user_id, $course_id ) ) {
            return false;
        }

        // Check sequential requirements.
        if ( get_post_meta( $lesson_id, '_swiftlms_force_sequential', true ) === '1' ) {
            return $this->is_previous_completed( $lesson_id, $user_id );
        }

        return true;
    }

    /**
     * Check if the previous lesson is completed.
     *
     * @param int $lesson_id The lesson ID.
     * @param int $user_id   The user ID.
     * @return bool True if the previous lesson is completed.
     */
    protected function is_previous_completed( int $lesson_id, int $user_id ): bool {
        $course_id    = $this->get_course_id( $lesson_id );
        $lesson_order = (int) get_post_meta( $lesson_id, '_swiftlms_order', true );

        if ( $lesson_order <= 1 ) {
            return true; // First lesson, no previous required.
        }

        // Find the previous lesson.
        $previous_lessons = get_posts(
            array(
                'post_type'      => 'sfls_lesson',
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'   => '_swiftlms_course_id',
                        'value' => $course_id,
                    ),
                    array(
                        'key'     => '_swiftlms_order',
                        'value'   => $lesson_order,
                        'compare' => '<',
                        'type'    => 'NUMERIC',
                    ),
                ),
                'orderby'        => 'meta_value_num',
                'meta_key'       => '_swiftlms_order',
                'order'          => 'DESC',
            )
        );

        if ( empty( $previous_lessons ) ) {
            return true;
        }

        $previous_lesson = $previous_lessons[0];

        // Check if previous lesson is completed.
        return swiftlms()->progress()->is_completed( $user_id, $previous_lesson->ID );
    }
}
