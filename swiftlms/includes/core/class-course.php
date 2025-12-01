<?php
/**
 * Course class.
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
 * Handles Course post type.
 */
class Course extends AbstractPostType {

    /**
     * Post type slug.
     *
     * @var string
     */
    protected string $post_type = 'sfls_course';

    /**
     * Singular name.
     *
     * @var string
     */
    protected string $singular = 'Course';

    /**
     * Plural name.
     *
     * @var string
     */
    protected string $plural = 'Courses';

    /**
     * Menu icon.
     *
     * @var string
     */
    protected string $menu_icon = 'dashicons-welcome-learn-more';

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
     * Register taxonomies.
     *
     * @return void
     */
    protected function register_taxonomies(): void {
        // Course Category.
        register_taxonomy(
            'sfls_course_category',
            $this->post_type,
            array(
                'label'              => __( 'Course Categories', 'swiftlms' ),
                'labels'             => array(
                    'name'          => __( 'Course Categories', 'swiftlms' ),
                    'singular_name' => __( 'Course Category', 'swiftlms' ),
                    'search_items'  => __( 'Search Categories', 'swiftlms' ),
                    'all_items'     => __( 'All Categories', 'swiftlms' ),
                    'parent_item'   => __( 'Parent Category', 'swiftlms' ),
                    'edit_item'     => __( 'Edit Category', 'swiftlms' ),
                    'update_item'   => __( 'Update Category', 'swiftlms' ),
                    'add_new_item'  => __( 'Add New Category', 'swiftlms' ),
                    'new_item_name' => __( 'New Category Name', 'swiftlms' ),
                    'menu_name'     => __( 'Categories', 'swiftlms' ),
                ),
                'hierarchical'       => true,
                'public'             => true,
                'show_ui'            => true,
                'show_admin_column'  => true,
                'show_in_nav_menus'  => true,
                'show_in_rest'       => true,
                'rewrite'            => array(
                    'slug'       => 'course-category',
                    'with_front' => false,
                ),
            )
        );

        // Course Tag.
        register_taxonomy(
            'sfls_course_tag',
            $this->post_type,
            array(
                'label'              => __( 'Course Tags', 'swiftlms' ),
                'labels'             => array(
                    'name'                       => __( 'Course Tags', 'swiftlms' ),
                    'singular_name'              => __( 'Course Tag', 'swiftlms' ),
                    'search_items'               => __( 'Search Tags', 'swiftlms' ),
                    'popular_items'              => __( 'Popular Tags', 'swiftlms' ),
                    'all_items'                  => __( 'All Tags', 'swiftlms' ),
                    'edit_item'                  => __( 'Edit Tag', 'swiftlms' ),
                    'update_item'                => __( 'Update Tag', 'swiftlms' ),
                    'add_new_item'               => __( 'Add New Tag', 'swiftlms' ),
                    'new_item_name'              => __( 'New Tag Name', 'swiftlms' ),
                    'separate_items_with_commas' => __( 'Separate tags with commas', 'swiftlms' ),
                    'add_or_remove_items'        => __( 'Add or remove tags', 'swiftlms' ),
                    'choose_from_most_used'      => __( 'Choose from most used tags', 'swiftlms' ),
                    'menu_name'                  => __( 'Tags', 'swiftlms' ),
                ),
                'hierarchical'       => false,
                'public'             => true,
                'show_ui'            => true,
                'show_admin_column'  => true,
                'show_in_nav_menus'  => true,
                'show_in_rest'       => true,
                'rewrite'            => array(
                    'slug'       => 'course-tag',
                    'with_front' => false,
                ),
            )
        );

        // Difficulty Level.
        register_taxonomy(
            'sfls_difficulty',
            $this->post_type,
            array(
                'label'              => __( 'Difficulty Levels', 'swiftlms' ),
                'labels'             => array(
                    'name'          => __( 'Difficulty Levels', 'swiftlms' ),
                    'singular_name' => __( 'Difficulty Level', 'swiftlms' ),
                    'all_items'     => __( 'All Levels', 'swiftlms' ),
                    'edit_item'     => __( 'Edit Level', 'swiftlms' ),
                    'update_item'   => __( 'Update Level', 'swiftlms' ),
                    'add_new_item'  => __( 'Add New Level', 'swiftlms' ),
                    'menu_name'     => __( 'Difficulty', 'swiftlms' ),
                ),
                'hierarchical'       => true,
                'public'             => true,
                'show_ui'            => true,
                'show_admin_column'  => true,
                'show_in_rest'       => true,
                'rewrite'            => array(
                    'slug'       => 'difficulty',
                    'with_front' => false,
                ),
            )
        );
    }

    /**
     * Register meta boxes.
     *
     * @return void
     */
    public function register_meta_boxes(): void {
        add_meta_box(
            'swiftlms_course_settings',
            __( 'Course Settings', 'swiftlms' ),
            array( $this, 'render_settings_meta_box' ),
            $this->post_type,
            'normal',
            'high'
        );

        add_meta_box(
            'swiftlms_course_content',
            __( 'Course Content', 'swiftlms' ),
            array( $this, 'render_content_meta_box' ),
            $this->post_type,
            'normal',
            'high'
        );
    }

    /**
     * Render the settings meta box.
     *
     * @param \WP_Post $post The post object.
     * @return void
     */
    public function render_settings_meta_box( \WP_Post $post ): void {
        wp_nonce_field( 'swiftlms_save_sfls_course', 'swiftlms_sfls_course_nonce' );

        $duration        = get_post_meta( $post->ID, '_swiftlms_duration', true );
        $price           = get_post_meta( $post->ID, '_swiftlms_price', true );
        $access_type     = get_post_meta( $post->ID, '_swiftlms_access_type', true ) ?: 'open';
        $prerequisites   = get_post_meta( $post->ID, '_swiftlms_prerequisites', true ) ?: array();
        $certificate_id  = get_post_meta( $post->ID, '_swiftlms_certificate_id', true );
        $video_url       = get_post_meta( $post->ID, '_swiftlms_video_url', true );
        $max_students    = get_post_meta( $post->ID, '_swiftlms_max_students', true );

        ?>
        <table class="form-table swiftlms-meta-table">
            <tr>
                <th><label for="swiftlms_duration"><?php esc_html_e( 'Duration', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="text" id="swiftlms_duration" name="swiftlms_duration" value="<?php echo esc_attr( $duration ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g., 4 hours', 'swiftlms' ); ?>">
                    <p class="description"><?php esc_html_e( 'Estimated course duration.', 'swiftlms' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="swiftlms_access_type"><?php esc_html_e( 'Access Type', 'swiftlms' ); ?></label></th>
                <td>
                    <select id="swiftlms_access_type" name="swiftlms_access_type">
                        <option value="open" <?php selected( $access_type, 'open' ); ?>><?php esc_html_e( 'Open (Free)', 'swiftlms' ); ?></option>
                        <option value="free" <?php selected( $access_type, 'free' ); ?>><?php esc_html_e( 'Free (Registration Required)', 'swiftlms' ); ?></option>
                        <option value="paid" <?php selected( $access_type, 'paid' ); ?>><?php esc_html_e( 'Paid', 'swiftlms' ); ?></option>
                        <option value="closed" <?php selected( $access_type, 'closed' ); ?>><?php esc_html_e( 'Closed (Admin Enrollment Only)', 'swiftlms' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="swiftlms_price"><?php esc_html_e( 'Price', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="number" id="swiftlms_price" name="swiftlms_price" value="<?php echo esc_attr( $price ); ?>" class="small-text" min="0" step="0.01">
                    <p class="description"><?php esc_html_e( 'Course price (for paid courses).', 'swiftlms' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="swiftlms_max_students"><?php esc_html_e( 'Max Students', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="number" id="swiftlms_max_students" name="swiftlms_max_students" value="<?php echo esc_attr( $max_students ); ?>" class="small-text" min="0">
                    <p class="description"><?php esc_html_e( 'Maximum number of students (0 for unlimited).', 'swiftlms' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="swiftlms_video_url"><?php esc_html_e( 'Preview Video', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="url" id="swiftlms_video_url" name="swiftlms_video_url" value="<?php echo esc_url( $video_url ); ?>" class="large-text">
                    <p class="description"><?php esc_html_e( 'URL for course preview video.', 'swiftlms' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render the content meta box.
     *
     * @param \WP_Post $post The post object.
     * @return void
     */
    public function render_content_meta_box( \WP_Post $post ): void {
        $lessons = $this->get_lessons( $post->ID );
        ?>
        <div class="swiftlms-course-builder" id="swiftlms-course-builder" data-course-id="<?php echo esc_attr( $post->ID ); ?>">
            <div class="swiftlms-lessons-list">
                <?php if ( empty( $lessons ) ) : ?>
                    <p class="swiftlms-no-lessons"><?php esc_html_e( 'No lessons yet. Add your first lesson below.', 'swiftlms' ); ?></p>
                <?php else : ?>
                    <ul class="swiftlms-sortable-lessons">
                        <?php foreach ( $lessons as $lesson ) : ?>
                            <li class="swiftlms-lesson-item" data-lesson-id="<?php echo esc_attr( $lesson->ID ); ?>">
                                <span class="swiftlms-drag-handle dashicons dashicons-menu"></span>
                                <span class="swiftlms-lesson-title"><?php echo esc_html( $lesson->post_title ); ?></span>
                                <span class="swiftlms-lesson-status <?php echo esc_attr( $lesson->post_status ); ?>"><?php echo esc_html( ucfirst( $lesson->post_status ) ); ?></span>
                                <span class="swiftlms-lesson-actions">
                                    <a href="<?php echo esc_url( get_edit_post_link( $lesson->ID ) ); ?>" class="swiftlms-edit-lesson"><?php esc_html_e( 'Edit', 'swiftlms' ); ?></a>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="swiftlms-add-lesson">
                <button type="button" class="button button-primary" id="swiftlms-add-lesson-btn">
                    <?php esc_html_e( 'Add Lesson', 'swiftlms' ); ?>
                </button>
            </div>
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
        $fields = array(
            'swiftlms_duration'     => '_swiftlms_duration',
            'swiftlms_price'        => '_swiftlms_price',
            'swiftlms_access_type'  => '_swiftlms_access_type',
            'swiftlms_video_url'    => '_swiftlms_video_url',
            'swiftlms_max_students' => '_swiftlms_max_students',
        );

        foreach ( $fields as $field => $meta_key ) {
            if ( isset( $_POST[ $field ] ) ) {
                $value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );

                if ( $field === 'swiftlms_video_url' ) {
                    $value = esc_url_raw( wp_unslash( $_POST[ $field ] ) );
                }

                if ( $field === 'swiftlms_price' || $field === 'swiftlms_max_students' ) {
                    $value = absint( $value );
                }

                update_post_meta( $post_id, $meta_key, $value );
            }
        }
    }

    /**
     * Get lessons for a course.
     *
     * @param int    $course_id   The course ID.
     * @param string $post_status Optional. Filter by post status.
     * @return \WP_Post[] Array of lesson posts.
     */
    public function get_lessons( int $course_id, string $post_status = 'any' ): array {
        return get_posts(
            array(
                'post_type'      => 'sfls_lesson',
                'posts_per_page' => -1,
                'post_status'    => $post_status,
                'meta_key'       => '_swiftlms_course_id',
                'meta_value'     => $course_id,
                'orderby'        => 'meta_value_num',
                'meta_key'       => '_swiftlms_order',
                'order'          => 'ASC',
            )
        );
    }

    /**
     * Get lesson count for a course.
     *
     * @param int $course_id The course ID.
     * @return int The lesson count.
     */
    public function get_lesson_count( int $course_id ): int {
        return count( $this->get_lessons( $course_id, 'publish' ) );
    }

    /**
     * Get course metadata.
     *
     * @param int $course_id The course ID.
     * @return array Course metadata.
     */
    public function get_meta( int $course_id ): array {
        return array(
            'duration'     => get_post_meta( $course_id, '_swiftlms_duration', true ),
            'price'        => get_post_meta( $course_id, '_swiftlms_price', true ),
            'access_type'  => get_post_meta( $course_id, '_swiftlms_access_type', true ) ?: 'open',
            'video_url'    => get_post_meta( $course_id, '_swiftlms_video_url', true ),
            'max_students' => get_post_meta( $course_id, '_swiftlms_max_students', true ),
            'lesson_count' => $this->get_lesson_count( $course_id ),
        );
    }

    /**
     * Check if course requires enrollment.
     *
     * @param int $course_id The course ID.
     * @return bool True if enrollment is required.
     */
    public function requires_enrollment( int $course_id ): bool {
        $access_type = get_post_meta( $course_id, '_swiftlms_access_type', true );
        return in_array( $access_type, array( 'free', 'paid', 'closed' ), true );
    }

    /**
     * Check if a course is free.
     *
     * @param int $course_id The course ID.
     * @return bool True if the course is free.
     */
    public function is_free( int $course_id ): bool {
        $access_type = get_post_meta( $course_id, '_swiftlms_access_type', true );
        return in_array( $access_type, array( 'open', 'free' ), true );
    }
}
