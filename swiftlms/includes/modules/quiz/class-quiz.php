<?php
/**
 * Quiz Custom Post Type
 *
 * @package SwiftLMS\Modules\Quiz
 * @since 1.0.0
 */

namespace SwiftLMS\Modules\Quiz;

use SwiftLMS\Abstracts\AbstractPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Quiz CPT class.
 */
class Quiz extends AbstractPostType {

    /**
     * Get post type slug.
     *
     * @return string
     */
    public function get_post_type(): string {
        return 'sfls_quiz';
    }

    /**
     * Get post type labels.
     *
     * @return array
     */
    protected function get_labels(): array {
        return array(
            'name'                  => __( 'Quizzes', 'swiftlms' ),
            'singular_name'         => __( 'Quiz', 'swiftlms' ),
            'add_new'               => __( 'Add New', 'swiftlms' ),
            'add_new_item'          => __( 'Add New Quiz', 'swiftlms' ),
            'edit_item'             => __( 'Edit Quiz', 'swiftlms' ),
            'new_item'              => __( 'New Quiz', 'swiftlms' ),
            'view_item'             => __( 'View Quiz', 'swiftlms' ),
            'search_items'          => __( 'Search Quizzes', 'swiftlms' ),
            'not_found'             => __( 'No quizzes found', 'swiftlms' ),
            'not_found_in_trash'    => __( 'No quizzes found in Trash', 'swiftlms' ),
            'parent_item_colon'     => __( 'Parent Quiz:', 'swiftlms' ),
            'all_items'             => __( 'All Quizzes', 'swiftlms' ),
            'archives'              => __( 'Quiz Archives', 'swiftlms' ),
            'insert_into_item'      => __( 'Insert into quiz', 'swiftlms' ),
            'uploaded_to_this_item' => __( 'Uploaded to this quiz', 'swiftlms' ),
            'menu_name'             => __( 'Quizzes', 'swiftlms' ),
        );
    }

    /**
     * Get post type arguments.
     *
     * @return array
     */
    protected function get_args(): array {
        return array(
            'labels'              => $this->get_labels(),
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => 'swiftlms',
            'query_var'           => true,
            'rewrite'             => array( 'slug' => 'quiz', 'with_front' => false ),
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_position'       => null,
            'supports'            => array( 'title', 'editor', 'thumbnail' ),
            'show_in_rest'        => true,
            'rest_base'           => 'quizzes',
        );
    }

    /**
     * Register meta boxes.
     *
     * @return void
     */
    public function register_meta_boxes(): void {
        add_meta_box(
            'sfls_quiz_settings',
            __( 'Quiz Settings', 'swiftlms' ),
            array( $this, 'render_settings_meta_box' ),
            $this->get_post_type(),
            'normal',
            'high'
        );

        add_meta_box(
            'sfls_quiz_questions',
            __( 'Questions', 'swiftlms' ),
            array( $this, 'render_questions_meta_box' ),
            $this->get_post_type(),
            'normal',
            'high'
        );

        add_meta_box(
            'sfls_quiz_display',
            __( 'Display Settings', 'swiftlms' ),
            array( $this, 'render_display_meta_box' ),
            $this->get_post_type(),
            'side',
            'default'
        );

        add_meta_box(
            'sfls_quiz_association',
            __( 'Course Association', 'swiftlms' ),
            array( $this, 'render_association_meta_box' ),
            $this->get_post_type(),
            'side',
            'default'
        );
    }

    /**
     * Render settings meta box.
     *
     * @param \WP_Post $post Current post.
     * @return void
     */
    public function render_settings_meta_box( $post ): void {
        wp_nonce_field( 'sfls_quiz_settings', 'sfls_quiz_nonce' );

        $passing_score    = get_post_meta( $post->ID, '_sfls_passing_score', true ) ?: 70;
        $time_limit       = get_post_meta( $post->ID, '_sfls_time_limit', true ) ?: 0;
        $attempts_allowed = get_post_meta( $post->ID, '_sfls_attempts_allowed', true ) ?: 0;
        $randomize_order  = get_post_meta( $post->ID, '_sfls_randomize_questions', true );
        $questions_count  = get_post_meta( $post->ID, '_sfls_questions_count', true ) ?: 0;
        $instant_feedback = get_post_meta( $post->ID, '_sfls_instant_feedback', true );
        $show_hints       = get_post_meta( $post->ID, '_sfls_show_hints', true );
        $retry_incorrect  = get_post_meta( $post->ID, '_sfls_retry_incorrect', true );
        ?>
        <table class="form-table">
            <tr>
                <th><label for="sfls_passing_score"><?php esc_html_e( 'Passing Score (%)', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="number" name="sfls_passing_score" id="sfls_passing_score"
                           value="<?php echo esc_attr( $passing_score ); ?>" min="0" max="100" class="small-text">
                    <span class="description">%</span>
                </td>
            </tr>
            <tr>
                <th><label for="sfls_time_limit"><?php esc_html_e( 'Time Limit (minutes)', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="number" name="sfls_time_limit" id="sfls_time_limit"
                           value="<?php echo esc_attr( $time_limit ); ?>" min="0" class="small-text">
                    <span class="description"><?php esc_html_e( '0 = no limit', 'swiftlms' ); ?></span>
                </td>
            </tr>
            <tr>
                <th><label for="sfls_attempts_allowed"><?php esc_html_e( 'Attempts Allowed', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="number" name="sfls_attempts_allowed" id="sfls_attempts_allowed"
                           value="<?php echo esc_attr( $attempts_allowed ); ?>" min="0" class="small-text">
                    <span class="description"><?php esc_html_e( '0 = unlimited', 'swiftlms' ); ?></span>
                </td>
            </tr>
            <tr>
                <th><label for="sfls_randomize_questions"><?php esc_html_e( 'Randomize Questions', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="checkbox" name="sfls_randomize_questions" id="sfls_randomize_questions"
                           value="1" <?php checked( $randomize_order, '1' ); ?>>
                    <span class="description"><?php esc_html_e( 'Shuffle question order for each attempt', 'swiftlms' ); ?></span>
                </td>
            </tr>
            <tr>
                <th><label for="sfls_questions_count"><?php esc_html_e( 'Questions Per Attempt', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="number" name="sfls_questions_count" id="sfls_questions_count"
                           value="<?php echo esc_attr( $questions_count ); ?>" min="0" class="small-text">
                    <span class="description"><?php esc_html_e( '0 = all questions', 'swiftlms' ); ?></span>
                </td>
            </tr>
            <tr>
                <th><label for="sfls_instant_feedback"><?php esc_html_e( 'Instant Feedback', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="checkbox" name="sfls_instant_feedback" id="sfls_instant_feedback"
                           value="1" <?php checked( $instant_feedback, '1' ); ?>>
                    <span class="description"><?php esc_html_e( 'Show correct/incorrect after each question', 'swiftlms' ); ?></span>
                </td>
            </tr>
            <tr>
                <th><label for="sfls_show_hints"><?php esc_html_e( 'Show Hints', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="checkbox" name="sfls_show_hints" id="sfls_show_hints"
                           value="1" <?php checked( $show_hints, '1' ); ?>>
                    <span class="description"><?php esc_html_e( 'Allow students to view hints', 'swiftlms' ); ?></span>
                </td>
            </tr>
            <tr>
                <th><label for="sfls_retry_incorrect"><?php esc_html_e( 'Retry Incorrect Only', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="checkbox" name="sfls_retry_incorrect" id="sfls_retry_incorrect"
                           value="1" <?php checked( $retry_incorrect, '1' ); ?>>
                    <span class="description"><?php esc_html_e( 'On retry, only show questions answered incorrectly', 'swiftlms' ); ?></span>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render questions meta box.
     *
     * @param \WP_Post $post Current post.
     * @return void
     */
    public function render_questions_meta_box( $post ): void {
        $questions = get_post_meta( $post->ID, '_sfls_quiz_questions', true ) ?: array();
        ?>
        <div id="sfls-quiz-questions-container">
            <div id="sfls-questions-list" class="sfls-sortable">
                <?php
                if ( ! empty( $questions ) ) :
                    foreach ( $questions as $index => $question_id ) :
                        $question = get_post( $question_id );
                        if ( ! $question ) {
                            continue;
                        }
                        $type   = get_post_meta( $question_id, '_sfls_question_type', true );
                        $points = get_post_meta( $question_id, '_sfls_question_points', true ) ?: 1;
                        ?>
                        <div class="sfls-question-item" data-id="<?php echo esc_attr( $question_id ); ?>">
                            <span class="sfls-drag-handle dashicons dashicons-menu"></span>
                            <input type="hidden" name="sfls_quiz_questions[]" value="<?php echo esc_attr( $question_id ); ?>">
                            <span class="sfls-question-title"><?php echo esc_html( $question->post_title ); ?></span>
                            <span class="sfls-question-meta">
                                <span class="sfls-question-type"><?php echo esc_html( Question::TYPES[ $type ] ?? $type ); ?></span>
                                <span class="sfls-question-points"><?php echo esc_html( $points ); ?> <?php esc_html_e( 'pts', 'swiftlms' ); ?></span>
                            </span>
                            <span class="sfls-question-actions">
                                <a href="<?php echo esc_url( get_edit_post_link( $question_id ) ); ?>" target="_blank" class="sfls-edit-question">
                                    <span class="dashicons dashicons-edit"></span>
                                </a>
                                <button type="button" class="sfls-remove-question">
                                    <span class="dashicons dashicons-no-alt"></span>
                                </button>
                            </span>
                        </div>
                        <?php
                    endforeach;
                endif;
                ?>
            </div>

            <div class="sfls-add-questions-wrap">
                <button type="button" class="button button-secondary" id="sfls-add-existing-question">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e( 'Add Existing Question', 'swiftlms' ); ?>
                </button>
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=sfls_question' ) ); ?>"
                   class="button button-primary" target="_blank">
                    <span class="dashicons dashicons-edit"></span>
                    <?php esc_html_e( 'Create New Question', 'swiftlms' ); ?>
                </a>
            </div>

            <div class="sfls-questions-total">
                <?php
                $total_points = 0;
                foreach ( $questions as $q_id ) {
                    $total_points += (float) ( get_post_meta( $q_id, '_sfls_question_points', true ) ?: 1 );
                }
                ?>
                <strong><?php esc_html_e( 'Total:', 'swiftlms' ); ?></strong>
                <span id="sfls-total-questions"><?php echo count( $questions ); ?></span> <?php esc_html_e( 'questions', 'swiftlms' ); ?>,
                <span id="sfls-total-points"><?php echo esc_html( $total_points ); ?></span> <?php esc_html_e( 'points', 'swiftlms' ); ?>
            </div>
        </div>

        <!-- Question Picker Modal -->
        <div id="sfls-question-picker-modal" class="sfls-modal" style="display: none;">
            <div class="sfls-modal-content">
                <div class="sfls-modal-header">
                    <h2><?php esc_html_e( 'Add Questions', 'swiftlms' ); ?></h2>
                    <button type="button" class="sfls-modal-close">&times;</button>
                </div>
                <div class="sfls-modal-body">
                    <div class="sfls-question-filters">
                        <input type="text" id="sfls-question-search" placeholder="<?php esc_attr_e( 'Search questions...', 'swiftlms' ); ?>">
                        <select id="sfls-question-type-filter">
                            <option value=""><?php esc_html_e( 'All Types', 'swiftlms' ); ?></option>
                            <?php foreach ( Question::TYPES as $value => $label ) : ?>
                                <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php
                        $categories = get_terms( array(
                            'taxonomy'   => 'sfls_question_cat',
                            'hide_empty' => false,
                        ) );
                        if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) :
                            ?>
                            <select id="sfls-question-cat-filter">
                                <option value=""><?php esc_html_e( 'All Categories', 'swiftlms' ); ?></option>
                                <?php foreach ( $categories as $cat ) : ?>
                                    <option value="<?php echo esc_attr( $cat->term_id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    <div id="sfls-available-questions">
                        <!-- Questions loaded via AJAX -->
                    </div>
                </div>
                <div class="sfls-modal-footer">
                    <button type="button" class="button" id="sfls-cancel-questions"><?php esc_html_e( 'Cancel', 'swiftlms' ); ?></button>
                    <button type="button" class="button button-primary" id="sfls-insert-questions"><?php esc_html_e( 'Add Selected', 'swiftlms' ); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render display meta box.
     *
     * @param \WP_Post $post Current post.
     * @return void
     */
    public function render_display_meta_box( $post ): void {
        $display_mode     = get_post_meta( $post->ID, '_sfls_display_mode', true ) ?: 'all';
        $show_progress    = get_post_meta( $post->ID, '_sfls_show_progress', true );
        $show_question_no = get_post_meta( $post->ID, '_sfls_show_question_numbers', true );
        $show_review      = get_post_meta( $post->ID, '_sfls_show_review', true );
        ?>
        <p>
            <label for="sfls_display_mode"><?php esc_html_e( 'Display Mode:', 'swiftlms' ); ?></label>
            <select name="sfls_display_mode" id="sfls_display_mode" class="widefat">
                <option value="all" <?php selected( $display_mode, 'all' ); ?>><?php esc_html_e( 'All Questions', 'swiftlms' ); ?></option>
                <option value="one" <?php selected( $display_mode, 'one' ); ?>><?php esc_html_e( 'One at a Time', 'swiftlms' ); ?></option>
                <option value="paged" <?php selected( $display_mode, 'paged' ); ?>><?php esc_html_e( 'Paged (5 per page)', 'swiftlms' ); ?></option>
            </select>
        </p>
        <p>
            <label>
                <input type="checkbox" name="sfls_show_progress" value="1" <?php checked( $show_progress, '1' ); ?>>
                <?php esc_html_e( 'Show Progress Bar', 'swiftlms' ); ?>
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="sfls_show_question_numbers" value="1" <?php checked( $show_question_no, '1' ); ?>>
                <?php esc_html_e( 'Show Question Numbers', 'swiftlms' ); ?>
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="sfls_show_review" value="1" <?php checked( $show_review, '1' ); ?>>
                <?php esc_html_e( 'Allow Review After Submission', 'swiftlms' ); ?>
            </label>
        </p>
        <?php
    }

    /**
     * Render course association meta box.
     *
     * @param \WP_Post $post Current post.
     * @return void
     */
    public function render_association_meta_box( $post ): void {
        $course_id = get_post_meta( $post->ID, '_sfls_course_id', true );
        $lesson_id = get_post_meta( $post->ID, '_sfls_lesson_id', true );

        $courses = get_posts( array(
            'post_type'      => 'sfls_course',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );
        ?>
        <p>
            <label for="sfls_course_id"><?php esc_html_e( 'Course:', 'swiftlms' ); ?></label>
            <select name="sfls_course_id" id="sfls_course_id" class="widefat">
                <option value=""><?php esc_html_e( '— Select Course —', 'swiftlms' ); ?></option>
                <?php foreach ( $courses as $course ) : ?>
                    <option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( $course_id, $course->ID ); ?>>
                        <?php echo esc_html( $course->post_title ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="sfls_lesson_id"><?php esc_html_e( 'Lesson:', 'swiftlms' ); ?></label>
            <select name="sfls_lesson_id" id="sfls_lesson_id" class="widefat">
                <option value=""><?php esc_html_e( '— Select Lesson —', 'swiftlms' ); ?></option>
                <?php
                if ( $course_id ) :
                    $lessons = get_posts( array(
                        'post_type'      => 'sfls_lesson',
                        'posts_per_page' => -1,
                        'meta_key'       => '_sfls_course_id',
                        'meta_value'     => $course_id,
                        'orderby'        => 'menu_order',
                        'order'          => 'ASC',
                    ) );
                    foreach ( $lessons as $lesson ) :
                        ?>
                        <option value="<?php echo esc_attr( $lesson->ID ); ?>" <?php selected( $lesson_id, $lesson->ID ); ?>>
                            <?php echo esc_html( $lesson->post_title ); ?>
                        </option>
                        <?php
                    endforeach;
                endif;
                ?>
            </select>
        </p>
        <p class="description">
            <?php esc_html_e( 'Associate this quiz with a course or specific lesson.', 'swiftlms' ); ?>
        </p>
        <?php
    }

    /**
     * Save meta box data.
     *
     * @param int $post_id Post ID.
     * @return void
     */
    public function save_meta_box_data( int $post_id ): void {
        if ( ! isset( $_POST['sfls_quiz_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sfls_quiz_nonce'] ) ), 'sfls_quiz_settings' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Quiz settings.
        $fields = array(
            'sfls_passing_score'    => '_sfls_passing_score',
            'sfls_time_limit'       => '_sfls_time_limit',
            'sfls_attempts_allowed' => '_sfls_attempts_allowed',
            'sfls_questions_count'  => '_sfls_questions_count',
            'sfls_display_mode'     => '_sfls_display_mode',
            'sfls_course_id'        => '_sfls_course_id',
            'sfls_lesson_id'        => '_sfls_lesson_id',
        );

        foreach ( $fields as $field => $meta_key ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
            }
        }

        // Checkbox fields.
        $checkboxes = array(
            'sfls_randomize_questions'   => '_sfls_randomize_questions',
            'sfls_instant_feedback'      => '_sfls_instant_feedback',
            'sfls_show_hints'            => '_sfls_show_hints',
            'sfls_retry_incorrect'       => '_sfls_retry_incorrect',
            'sfls_show_progress'         => '_sfls_show_progress',
            'sfls_show_question_numbers' => '_sfls_show_question_numbers',
            'sfls_show_review'           => '_sfls_show_review',
        );

        foreach ( $checkboxes as $field => $meta_key ) {
            update_post_meta( $post_id, $meta_key, isset( $_POST[ $field ] ) ? '1' : '0' );
        }

        // Questions.
        if ( isset( $_POST['sfls_quiz_questions'] ) ) {
            $questions = array_map( 'absint', wp_unslash( $_POST['sfls_quiz_questions'] ) );
            update_post_meta( $post_id, '_sfls_quiz_questions', array_values( array_filter( $questions ) ) );
        } else {
            update_post_meta( $post_id, '_sfls_quiz_questions', array() );
        }
    }

    /**
     * Get quiz data for API/frontend.
     *
     * @param int  $quiz_id Quiz ID.
     * @param bool $include_questions Include question data.
     * @return array
     */
    public static function get_quiz_data( int $quiz_id, bool $include_questions = false ): array {
        $post = get_post( $quiz_id );
        if ( ! $post || 'sfls_quiz' !== $post->post_type ) {
            return array();
        }

        $data = array(
            'id'                 => $quiz_id,
            'title'              => $post->post_title,
            'description'        => apply_filters( 'the_content', $post->post_content ),
            'passing_score'      => (int) ( get_post_meta( $quiz_id, '_sfls_passing_score', true ) ?: 70 ),
            'time_limit'         => (int) get_post_meta( $quiz_id, '_sfls_time_limit', true ),
            'attempts_allowed'   => (int) get_post_meta( $quiz_id, '_sfls_attempts_allowed', true ),
            'randomize'          => (bool) get_post_meta( $quiz_id, '_sfls_randomize_questions', true ),
            'questions_count'    => (int) get_post_meta( $quiz_id, '_sfls_questions_count', true ),
            'instant_feedback'   => (bool) get_post_meta( $quiz_id, '_sfls_instant_feedback', true ),
            'show_hints'         => (bool) get_post_meta( $quiz_id, '_sfls_show_hints', true ),
            'display_mode'       => get_post_meta( $quiz_id, '_sfls_display_mode', true ) ?: 'all',
            'show_progress'      => (bool) get_post_meta( $quiz_id, '_sfls_show_progress', true ),
            'show_review'        => (bool) get_post_meta( $quiz_id, '_sfls_show_review', true ),
            'course_id'          => (int) get_post_meta( $quiz_id, '_sfls_course_id', true ),
            'lesson_id'          => (int) get_post_meta( $quiz_id, '_sfls_lesson_id', true ),
        );

        if ( $include_questions ) {
            $question_ids = get_post_meta( $quiz_id, '_sfls_quiz_questions', true ) ?: array();

            // Apply randomization and count limits.
            if ( $data['randomize'] ) {
                shuffle( $question_ids );
            }

            if ( $data['questions_count'] > 0 && count( $question_ids ) > $data['questions_count'] ) {
                $question_ids = array_slice( $question_ids, 0, $data['questions_count'] );
            }

            $data['questions']    = array();
            $data['total_points'] = 0;

            foreach ( $question_ids as $q_id ) {
                $q_data = Question::get_question_data( (int) $q_id, false );
                if ( ! empty( $q_data ) ) {
                    $data['questions'][]   = $q_data;
                    $data['total_points'] += $q_data['points'];
                }
            }

            $data['question_count'] = count( $data['questions'] );
        }

        return $data;
    }
}
