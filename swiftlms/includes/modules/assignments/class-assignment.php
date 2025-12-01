<?php
/**
 * Assignment Custom Post Type
 *
 * @package SwiftLMS
 */

namespace SwiftLMS\Modules\Assignments;

defined( 'ABSPATH' ) || exit;

/**
 * Assignment CPT class.
 */
class Assignment {

    /**
     * Post type name.
     *
     * @var string
     */
    const POST_TYPE = 'sfls_assignment';

    /**
     * Assignment types.
     *
     * @var array
     */
    const ASSIGNMENT_TYPES = array(
        'file_upload'    => 'File Upload',
        'text_entry'     => 'Text Entry',
        'url_submission' => 'URL/Link Submission',
        'mixed'          => 'Multiple Submission Types',
    );

    /**
     * Initialize.
     */
    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'register_post_type' ) );
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
        add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_meta' ), 10, 2 );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'add_columns' ) );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_columns' ), 10, 2 );
    }

    /**
     * Register post type.
     */
    public static function register_post_type(): void {
        $labels = array(
            'name'               => __( 'Assignments', 'swiftlms' ),
            'singular_name'      => __( 'Assignment', 'swiftlms' ),
            'add_new'            => __( 'Add New', 'swiftlms' ),
            'add_new_item'       => __( 'Add New Assignment', 'swiftlms' ),
            'edit_item'          => __( 'Edit Assignment', 'swiftlms' ),
            'new_item'           => __( 'New Assignment', 'swiftlms' ),
            'view_item'          => __( 'View Assignment', 'swiftlms' ),
            'search_items'       => __( 'Search Assignments', 'swiftlms' ),
            'not_found'          => __( 'No assignments found', 'swiftlms' ),
            'not_found_in_trash' => __( 'No assignments found in trash', 'swiftlms' ),
            'parent_item_colon'  => __( 'Parent Assignment:', 'swiftlms' ),
            'menu_name'          => __( 'Assignments', 'swiftlms' ),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => 'swiftlms',
            'show_in_rest'        => true,
            'rest_base'           => 'assignments',
            'query_var'           => true,
            'rewrite'             => array( 'slug' => 'assignment' ),
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'supports'            => array( 'title', 'editor', 'thumbnail' ),
            'menu_icon'           => 'dashicons-portfolio',
        );

        register_post_type( self::POST_TYPE, $args );
    }

    /**
     * Add meta boxes.
     */
    public static function add_meta_boxes(): void {
        add_meta_box(
            'sfls_assignment_settings',
            __( 'Assignment Settings', 'swiftlms' ),
            array( __CLASS__, 'render_settings_meta_box' ),
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'sfls_assignment_course',
            __( 'Course & Lesson', 'swiftlms' ),
            array( __CLASS__, 'render_course_meta_box' ),
            self::POST_TYPE,
            'side',
            'high'
        );
    }

    /**
     * Render settings meta box.
     *
     * @param \WP_Post $post Post object.
     */
    public static function render_settings_meta_box( \WP_Post $post ): void {
        wp_nonce_field( 'sfls_assignment_settings', 'sfls_assignment_nonce' );

        $type           = get_post_meta( $post->ID, '_sfls_assignment_type', true ) ?: 'file_upload';
        $points         = get_post_meta( $post->ID, '_sfls_assignment_points', true ) ?: 100;
        $due_date       = get_post_meta( $post->ID, '_sfls_assignment_due_date', true );
        $late_allowed   = get_post_meta( $post->ID, '_sfls_assignment_late_allowed', true );
        $late_penalty   = get_post_meta( $post->ID, '_sfls_assignment_late_penalty', true ) ?: 0;
        $max_attempts   = get_post_meta( $post->ID, '_sfls_assignment_max_attempts', true ) ?: 1;
        $allowed_files  = get_post_meta( $post->ID, '_sfls_assignment_allowed_files', true ) ?: 'pdf,doc,docx,txt,zip';
        $max_file_size  = get_post_meta( $post->ID, '_sfls_assignment_max_file_size', true ) ?: 10;
        $min_words      = get_post_meta( $post->ID, '_sfls_assignment_min_words', true );
        $max_words      = get_post_meta( $post->ID, '_sfls_assignment_max_words', true );
        $instructions   = get_post_meta( $post->ID, '_sfls_assignment_instructions', true );
        ?>
        <div class="sfls-meta-box">
            <table class="form-table">
                <tr>
                    <th><label for="sfls_assignment_type"><?php esc_html_e( 'Submission Type', 'swiftlms' ); ?></label></th>
                    <td>
                        <select name="sfls_assignment_type" id="sfls_assignment_type" class="regular-text">
                            <?php foreach ( self::ASSIGNMENT_TYPES as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $type, $key ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="sfls_assignment_points"><?php esc_html_e( 'Total Points', 'swiftlms' ); ?></label></th>
                    <td>
                        <input type="number" name="sfls_assignment_points" id="sfls_assignment_points"
                               value="<?php echo esc_attr( $points ); ?>" min="0" max="1000" class="small-text">
                    </td>
                </tr>
                <tr>
                    <th><label for="sfls_assignment_due_date"><?php esc_html_e( 'Due Date', 'swiftlms' ); ?></label></th>
                    <td>
                        <input type="datetime-local" name="sfls_assignment_due_date" id="sfls_assignment_due_date"
                               value="<?php echo esc_attr( $due_date ); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e( 'Leave empty for no due date.', 'swiftlms' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="sfls_assignment_late_allowed"><?php esc_html_e( 'Late Submissions', 'swiftlms' ); ?></label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="sfls_assignment_late_allowed" id="sfls_assignment_late_allowed"
                                   value="1" <?php checked( $late_allowed, '1' ); ?>>
                            <?php esc_html_e( 'Allow late submissions', 'swiftlms' ); ?>
                        </label>
                    </td>
                </tr>
                <tr class="sfls-late-penalty-row" style="<?php echo ! $late_allowed ? 'display:none;' : ''; ?>">
                    <th><label for="sfls_assignment_late_penalty"><?php esc_html_e( 'Late Penalty (%)', 'swiftlms' ); ?></label></th>
                    <td>
                        <input type="number" name="sfls_assignment_late_penalty" id="sfls_assignment_late_penalty"
                               value="<?php echo esc_attr( $late_penalty ); ?>" min="0" max="100" class="small-text">
                        <p class="description"><?php esc_html_e( 'Percentage deducted from grade for late submissions.', 'swiftlms' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="sfls_assignment_max_attempts"><?php esc_html_e( 'Maximum Attempts', 'swiftlms' ); ?></label></th>
                    <td>
                        <input type="number" name="sfls_assignment_max_attempts" id="sfls_assignment_max_attempts"
                               value="<?php echo esc_attr( $max_attempts ); ?>" min="1" max="10" class="small-text">
                        <p class="description"><?php esc_html_e( 'How many times a student can resubmit.', 'swiftlms' ); ?></p>
                    </td>
                </tr>
            </table>

            <h4><?php esc_html_e( 'File Upload Settings', 'swiftlms' ); ?></h4>
            <table class="form-table sfls-file-settings" style="<?php echo ! in_array( $type, array( 'file_upload', 'mixed' ), true ) ? 'display:none;' : ''; ?>">
                <tr>
                    <th><label for="sfls_assignment_allowed_files"><?php esc_html_e( 'Allowed File Types', 'swiftlms' ); ?></label></th>
                    <td>
                        <input type="text" name="sfls_assignment_allowed_files" id="sfls_assignment_allowed_files"
                               value="<?php echo esc_attr( $allowed_files ); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e( 'Comma-separated list of extensions (e.g., pdf,doc,docx).', 'swiftlms' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="sfls_assignment_max_file_size"><?php esc_html_e( 'Max File Size (MB)', 'swiftlms' ); ?></label></th>
                    <td>
                        <input type="number" name="sfls_assignment_max_file_size" id="sfls_assignment_max_file_size"
                               value="<?php echo esc_attr( $max_file_size ); ?>" min="1" max="100" class="small-text">
                    </td>
                </tr>
            </table>

            <h4><?php esc_html_e( 'Text Entry Settings', 'swiftlms' ); ?></h4>
            <table class="form-table sfls-text-settings" style="<?php echo ! in_array( $type, array( 'text_entry', 'mixed' ), true ) ? 'display:none;' : ''; ?>">
                <tr>
                    <th><label for="sfls_assignment_min_words"><?php esc_html_e( 'Minimum Words', 'swiftlms' ); ?></label></th>
                    <td>
                        <input type="number" name="sfls_assignment_min_words" id="sfls_assignment_min_words"
                               value="<?php echo esc_attr( $min_words ); ?>" min="0" class="small-text">
                    </td>
                </tr>
                <tr>
                    <th><label for="sfls_assignment_max_words"><?php esc_html_e( 'Maximum Words', 'swiftlms' ); ?></label></th>
                    <td>
                        <input type="number" name="sfls_assignment_max_words" id="sfls_assignment_max_words"
                               value="<?php echo esc_attr( $max_words ); ?>" min="0" class="small-text">
                        <p class="description"><?php esc_html_e( 'Leave empty for no limit.', 'swiftlms' ); ?></p>
                    </td>
                </tr>
            </table>

            <h4><?php esc_html_e( 'Special Instructions', 'swiftlms' ); ?></h4>
            <textarea name="sfls_assignment_instructions" rows="4" class="large-text"><?php echo esc_textarea( $instructions ); ?></textarea>
            <p class="description"><?php esc_html_e( 'Additional instructions shown to students before submission.', 'swiftlms' ); ?></p>
        </div>

        <script>
        jQuery(function($) {
            $('#sfls_assignment_late_allowed').on('change', function() {
                $('.sfls-late-penalty-row').toggle(this.checked);
            });

            $('#sfls_assignment_type').on('change', function() {
                var type = $(this).val();
                $('.sfls-file-settings').toggle(type === 'file_upload' || type === 'mixed');
                $('.sfls-text-settings').toggle(type === 'text_entry' || type === 'mixed');
            });
        });
        </script>
        <?php
    }

    /**
     * Render course meta box.
     *
     * @param \WP_Post $post Post object.
     */
    public static function render_course_meta_box( \WP_Post $post ): void {
        $course_id = get_post_meta( $post->ID, '_sfls_assignment_course_id', true );
        $lesson_id = get_post_meta( $post->ID, '_sfls_assignment_lesson_id', true );

        $courses = get_posts(
            array(
                'post_type'      => 'sfls_course',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
        ?>
        <p>
            <label for="sfls_assignment_course_id"><strong><?php esc_html_e( 'Course', 'swiftlms' ); ?></strong></label><br>
            <select name="sfls_assignment_course_id" id="sfls_assignment_course_id" class="widefat">
                <option value=""><?php esc_html_e( '— Select Course —', 'swiftlms' ); ?></option>
                <?php foreach ( $courses as $course ) : ?>
                    <option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( $course_id, $course->ID ); ?>>
                        <?php echo esc_html( $course->post_title ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="sfls_assignment_lesson_id"><strong><?php esc_html_e( 'Lesson (Optional)', 'swiftlms' ); ?></strong></label><br>
            <select name="sfls_assignment_lesson_id" id="sfls_assignment_lesson_id" class="widefat">
                <option value=""><?php esc_html_e( '— Select Lesson —', 'swiftlms' ); ?></option>
            </select>
            <span class="description"><?php esc_html_e( 'Associate with a specific lesson.', 'swiftlms' ); ?></span>
        </p>

        <script>
        jQuery(function($) {
            var currentLessonId = '<?php echo esc_js( $lesson_id ); ?>';

            function loadLessons(courseId) {
                if (!courseId) {
                    $('#sfls_assignment_lesson_id').html('<option value="">— Select Lesson —</option>');
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    data: {
                        action: 'sfls_get_course_lessons',
                        course_id: courseId,
                        nonce: '<?php echo wp_create_nonce( 'sfls_ajax' ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var options = '<option value="">— Select Lesson —</option>';
                            response.data.forEach(function(lesson) {
                                var selected = lesson.id == currentLessonId ? ' selected' : '';
                                options += '<option value="' + lesson.id + '"' + selected + '>' + lesson.title + '</option>';
                            });
                            $('#sfls_assignment_lesson_id').html(options);
                        }
                    }
                });
            }

            $('#sfls_assignment_course_id').on('change', function() {
                currentLessonId = '';
                loadLessons($(this).val());
            });

            // Load lessons on page load if course is selected
            if ($('#sfls_assignment_course_id').val()) {
                loadLessons($('#sfls_assignment_course_id').val());
            }
        });
        </script>
        <?php
    }

    /**
     * Save meta data.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     */
    public static function save_meta( int $post_id, \WP_Post $post ): void {
        if ( ! isset( $_POST['sfls_assignment_nonce'] ) || ! wp_verify_nonce( $_POST['sfls_assignment_nonce'], 'sfls_assignment_settings' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $fields = array(
            'sfls_assignment_type'          => 'sanitize_key',
            'sfls_assignment_points'        => 'absint',
            'sfls_assignment_due_date'      => 'sanitize_text_field',
            'sfls_assignment_late_allowed'  => 'absint',
            'sfls_assignment_late_penalty'  => 'absint',
            'sfls_assignment_max_attempts'  => 'absint',
            'sfls_assignment_allowed_files' => 'sanitize_text_field',
            'sfls_assignment_max_file_size' => 'absint',
            'sfls_assignment_min_words'     => 'absint',
            'sfls_assignment_max_words'     => 'absint',
            'sfls_assignment_instructions'  => 'wp_kses_post',
            'sfls_assignment_course_id'     => 'absint',
            'sfls_assignment_lesson_id'     => 'absint',
        );

        foreach ( $fields as $field => $sanitize ) {
            if ( isset( $_POST[ $field ] ) ) {
                $value = call_user_func( $sanitize, $_POST[ $field ] );
                update_post_meta( $post_id, '_' . $field, $value );
            } else {
                delete_post_meta( $post_id, '_' . $field );
            }
        }
    }

    /**
     * Add admin columns.
     *
     * @param array $columns Columns.
     * @return array
     */
    public static function add_columns( array $columns ): array {
        $new_columns = array();

        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;

            if ( 'title' === $key ) {
                $new_columns['sfls_course']      = __( 'Course', 'swiftlms' );
                $new_columns['sfls_type']        = __( 'Type', 'swiftlms' );
                $new_columns['sfls_points']      = __( 'Points', 'swiftlms' );
                $new_columns['sfls_submissions'] = __( 'Submissions', 'swiftlms' );
                $new_columns['sfls_due_date']    = __( 'Due Date', 'swiftlms' );
            }
        }

        return $new_columns;
    }

    /**
     * Render admin columns.
     *
     * @param string $column  Column name.
     * @param int    $post_id Post ID.
     */
    public static function render_columns( string $column, int $post_id ): void {
        switch ( $column ) {
            case 'sfls_course':
                $course_id = get_post_meta( $post_id, '_sfls_assignment_course_id', true );
                if ( $course_id ) {
                    $course = get_post( $course_id );
                    if ( $course ) {
                        echo '<a href="' . esc_url( get_edit_post_link( $course_id ) ) . '">' . esc_html( $course->post_title ) . '</a>';
                    }
                } else {
                    echo '—';
                }
                break;

            case 'sfls_type':
                $type = get_post_meta( $post_id, '_sfls_assignment_type', true );
                echo esc_html( self::ASSIGNMENT_TYPES[ $type ] ?? $type );
                break;

            case 'sfls_points':
                echo esc_html( get_post_meta( $post_id, '_sfls_assignment_points', true ) ?: 0 );
                break;

            case 'sfls_submissions':
                global $wpdb;
                $count = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}swiftlms_submissions WHERE assignment_id = %d",
                        $post_id
                    )
                );
                echo esc_html( $count ?: 0 );
                break;

            case 'sfls_due_date':
                $due_date = get_post_meta( $post_id, '_sfls_assignment_due_date', true );
                if ( $due_date ) {
                    $timestamp = strtotime( $due_date );
                    $is_past   = $timestamp < time();
                    echo '<span style="color:' . ( $is_past ? '#dc2626' : 'inherit' ) . ';">';
                    echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) );
                    echo '</span>';
                } else {
                    echo __( 'No due date', 'swiftlms' );
                }
                break;
        }
    }

    /**
     * Get assignment settings.
     *
     * @param int $assignment_id Assignment ID.
     * @return array
     */
    public static function get_settings( int $assignment_id ): array {
        return array(
            'type'          => get_post_meta( $assignment_id, '_sfls_assignment_type', true ) ?: 'file_upload',
            'points'        => (int) ( get_post_meta( $assignment_id, '_sfls_assignment_points', true ) ?: 100 ),
            'due_date'      => get_post_meta( $assignment_id, '_sfls_assignment_due_date', true ),
            'late_allowed'  => (bool) get_post_meta( $assignment_id, '_sfls_assignment_late_allowed', true ),
            'late_penalty'  => (int) ( get_post_meta( $assignment_id, '_sfls_assignment_late_penalty', true ) ?: 0 ),
            'max_attempts'  => (int) ( get_post_meta( $assignment_id, '_sfls_assignment_max_attempts', true ) ?: 1 ),
            'allowed_files' => get_post_meta( $assignment_id, '_sfls_assignment_allowed_files', true ) ?: 'pdf,doc,docx,txt,zip',
            'max_file_size' => (int) ( get_post_meta( $assignment_id, '_sfls_assignment_max_file_size', true ) ?: 10 ),
            'min_words'     => (int) get_post_meta( $assignment_id, '_sfls_assignment_min_words', true ),
            'max_words'     => (int) get_post_meta( $assignment_id, '_sfls_assignment_max_words', true ),
            'instructions'  => get_post_meta( $assignment_id, '_sfls_assignment_instructions', true ),
            'course_id'     => (int) get_post_meta( $assignment_id, '_sfls_assignment_course_id', true ),
            'lesson_id'     => (int) get_post_meta( $assignment_id, '_sfls_assignment_lesson_id', true ),
        );
    }
}
