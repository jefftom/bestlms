<?php
/**
 * Topic class.
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
 * Handles Topic post type.
 *
 * Topics are sub-sections within lessons, allowing for more granular
 * content organization and progress tracking.
 */
class Topic extends AbstractPostType {

    /**
     * Post type slug.
     *
     * @var string
     */
    protected string $post_type = 'sfls_topic';

    /**
     * Singular name.
     *
     * @var string
     */
    protected string $singular = 'Topic';

    /**
     * Plural name.
     *
     * @var string
     */
    protected string $plural = 'Topics';

    /**
     * Menu icon.
     *
     * @var string
     */
    protected string $menu_icon = 'dashicons-text-page';

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
    );

    /**
     * Register meta boxes.
     *
     * @return void
     */
    public function register_meta_boxes(): void {
        add_meta_box(
            'swiftlms_topic_settings',
            __( 'Topic Settings', 'swiftlms' ),
            array( $this, 'render_settings_meta_box' ),
            $this->post_type,
            'normal',
            'high'
        );

        add_meta_box(
            'swiftlms_topic_video',
            __( 'Video Settings', 'swiftlms' ),
            array( $this, 'render_video_meta_box' ),
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
        wp_nonce_field( 'swiftlms_save_sfls_topic', 'swiftlms_sfls_topic_nonce' );

        $lesson_id       = get_post_meta( $post->ID, '_swiftlms_lesson_id', true );
        $order           = get_post_meta( $post->ID, '_swiftlms_order', true );
        $duration        = get_post_meta( $post->ID, '_swiftlms_duration', true );
        $completion_type = get_post_meta( $post->ID, '_swiftlms_completion_type', true ) ?: 'manual';

        // Get lessons for dropdown.
        $lessons = get_posts(
            array(
                'post_type'      => 'sfls_lesson',
                'posts_per_page' => -1,
                'post_status'    => array( 'publish', 'draft', 'pending' ),
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );

        ?>
        <table class="form-table swiftlms-meta-table">
            <tr>
                <th><label for="swiftlms_lesson_id"><?php esc_html_e( 'Lesson', 'swiftlms' ); ?></label></th>
                <td>
                    <select id="swiftlms_lesson_id" name="swiftlms_lesson_id" required>
                        <option value=""><?php esc_html_e( 'Select a lesson', 'swiftlms' ); ?></option>
                        <?php foreach ( $lessons as $lesson ) : ?>
                            <?php
                            $course_id    = get_post_meta( $lesson->ID, '_swiftlms_course_id', true );
                            $course       = get_post( $course_id );
                            $course_title = $course ? $course->post_title : __( 'No Course', 'swiftlms' );
                            ?>
                            <option value="<?php echo esc_attr( $lesson->ID ); ?>" <?php selected( $lesson_id, $lesson->ID ); ?>>
                                <?php echo esc_html( $lesson->post_title ); ?> (<?php echo esc_html( $course_title ); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'The lesson this topic belongs to.', 'swiftlms' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="swiftlms_order"><?php esc_html_e( 'Order', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="number" id="swiftlms_order" name="swiftlms_order" value="<?php echo esc_attr( $order ); ?>" class="small-text" min="0">
                    <p class="description"><?php esc_html_e( 'Topic order within the lesson.', 'swiftlms' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="swiftlms_duration"><?php esc_html_e( 'Duration', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="text" id="swiftlms_duration" name="swiftlms_duration" value="<?php echo esc_attr( $duration ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g., 5 minutes', 'swiftlms' ); ?>">
                </td>
            </tr>
            <tr>
                <th><label for="swiftlms_completion_type"><?php esc_html_e( 'Completion Type', 'swiftlms' ); ?></label></th>
                <td>
                    <select id="swiftlms_completion_type" name="swiftlms_completion_type">
                        <option value="manual" <?php selected( $completion_type, 'manual' ); ?>><?php esc_html_e( 'Manual (Mark Complete button)', 'swiftlms' ); ?></option>
                        <option value="video" <?php selected( $completion_type, 'video' ); ?>><?php esc_html_e( 'Video (Complete when video finished)', 'swiftlms' ); ?></option>
                        <option value="quiz" <?php selected( $completion_type, 'quiz' ); ?>><?php esc_html_e( 'Quiz (Pass associated quiz)', 'swiftlms' ); ?></option>
                    </select>
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
        $video_url      = get_post_meta( $post->ID, '_swiftlms_video_url', true );
        $video_duration = get_post_meta( $post->ID, '_swiftlms_video_duration', true );

        ?>
        <table class="form-table swiftlms-meta-table">
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
                </td>
            </tr>
        </table>
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
        $fields = array(
            'swiftlms_lesson_id'       => '_swiftlms_lesson_id',
            'swiftlms_order'           => '_swiftlms_order',
            'swiftlms_duration'        => '_swiftlms_duration',
            'swiftlms_completion_type' => '_swiftlms_completion_type',
            'swiftlms_video_url'       => '_swiftlms_video_url',
            'swiftlms_video_duration'  => '_swiftlms_video_duration',
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

        // Also store course_id for easier querying.
        if ( isset( $_POST['swiftlms_lesson_id'] ) ) {
            $lesson_id = absint( $_POST['swiftlms_lesson_id'] );
            $course_id = get_post_meta( $lesson_id, '_swiftlms_course_id', true );
            update_post_meta( $post_id, '_swiftlms_course_id', $course_id );
        }
    }

    /**
     * Get the lesson ID for a topic.
     *
     * @param int $topic_id The topic ID.
     * @return int The lesson ID.
     */
    public function get_lesson_id( int $topic_id ): int {
        return (int) get_post_meta( $topic_id, '_swiftlms_lesson_id', true );
    }

    /**
     * Get the course ID for a topic.
     *
     * @param int $topic_id The topic ID.
     * @return int The course ID.
     */
    public function get_course_id( int $topic_id ): int {
        return (int) get_post_meta( $topic_id, '_swiftlms_course_id', true );
    }

    /**
     * Get topic metadata.
     *
     * @param int $topic_id The topic ID.
     * @return array Topic metadata.
     */
    public function get_meta( int $topic_id ): array {
        return array(
            'lesson_id'       => $this->get_lesson_id( $topic_id ),
            'course_id'       => $this->get_course_id( $topic_id ),
            'order'           => get_post_meta( $topic_id, '_swiftlms_order', true ),
            'duration'        => get_post_meta( $topic_id, '_swiftlms_duration', true ),
            'completion_type' => get_post_meta( $topic_id, '_swiftlms_completion_type', true ) ?: 'manual',
            'video_url'       => get_post_meta( $topic_id, '_swiftlms_video_url', true ),
            'video_duration'  => get_post_meta( $topic_id, '_swiftlms_video_duration', true ),
        );
    }

    /**
     * Check if a topic is available for a user.
     *
     * @param int $topic_id The topic ID.
     * @param int $user_id  The user ID.
     * @return bool True if the topic is available.
     */
    public function is_available( int $topic_id, int $user_id ): bool {
        $lesson_id = $this->get_lesson_id( $topic_id );

        // Get the lesson instance and check availability.
        $lesson = swiftlms()->get_component( 'lesson' );
        if ( $lesson instanceof Lesson ) {
            return $lesson->is_available( $lesson_id, $user_id );
        }

        return false;
    }
}
