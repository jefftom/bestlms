<?php
/**
 * Question Custom Post Type
 *
 * @package suspended_developer\developer-note
 * @since 1.0.0
 */

namespace SwiftLMS\Modules\Quiz;

use SwiftLMS\Abstracts\AbstractPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Question CPT class.
 */
class Question extends AbstractPostType {

    /**
     * Question types.
     *
     * @var array
     */
    const TYPES = array(
        'multiple_choice'  => 'Multiple Choice',
        'multiple_answer'  => 'Multiple Answer',
        'true_false'       => 'True/False',
        'fill_blank'       => 'Fill in the Blank',
        'short_answer'     => 'Short Answer',
        'essay'            => 'Essay',
        'matching'         => 'Matching',
        'ordering'         => 'Ordering',
    );

    /**
     * Get post type slug.
     *
     * @return string
     */
    public function get_post_type(): string {
        return 'sfls_question';
    }

    /**
     * Get post type labels.
     *
     * @return array
     */
    protected function get_labels(): array {
        return array(
            'name'                  => __( 'Questions', 'swiftlms' ),
            'singular_name'         => __( 'Question', 'swiftlms' ),
            'add_new'               => __( 'Add New', 'swiftlms' ),
            'add_new_item'          => __( 'Add New Question', 'swiftlms' ),
            'edit_item'             => __( 'Edit Question', 'swiftlms' ),
            'new_item'              => __( 'New Question', 'swiftlms' ),
            'view_item'             => __( 'View Question', 'swiftlms' ),
            'search_items'          => __( 'Search Questions', 'swiftlms' ),
            'not_found'             => __( 'No questions found', 'swiftlms' ),
            'not_found_in_trash'    => __( 'No questions found in Trash', 'swiftlms' ),
            'parent_item_colon'     => __( 'Parent Question:', 'swiftlms' ),
            'all_items'             => __( 'All Questions', 'swiftlms' ),
            'archives'              => __( 'Question Archives', 'swiftlms' ),
            'insert_into_item'      => __( 'Insert into question', 'swiftlms' ),
            'uploaded_to_this_item' => __( 'Uploaded to this question', 'swiftlms' ),
            'menu_name'             => __( 'Questions', 'swiftlms' ),
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
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => 'swiftlms',
            'query_var'           => false,
            'rewrite'             => false,
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_position'       => null,
            'supports'            => array( 'title', 'editor' ),
            'show_in_rest'        => true,
            'rest_base'           => 'questions',
        );
    }

    /**
     * Register taxonomies.
     *
     * @return void
     */
    public function register_taxonomies(): void {
        // Question Category taxonomy.
        register_taxonomy(
            'sfls_question_cat',
            $this->get_post_type(),
            array(
                'labels'            => array(
                    'name'          => __( 'Question Categories', 'swiftlms' ),
                    'singular_name' => __( 'Question Category', 'swiftlms' ),
                    'search_items'  => __( 'Search Categories', 'swiftlms' ),
                    'all_items'     => __( 'All Categories', 'swiftlms' ),
                    'edit_item'     => __( 'Edit Category', 'swiftlms' ),
                    'update_item'   => __( 'Update Category', 'swiftlms' ),
                    'add_new_item'  => __( 'Add New Category', 'swiftlms' ),
                    'new_item_name' => __( 'New Category Name', 'swiftlms' ),
                    'menu_name'     => __( 'Categories', 'swiftlms' ),
                ),
                'hierarchical'      => true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'query_var'         => false,
                'rewrite'           => false,
                'show_in_rest'      => true,
            )
        );

        // Question Tag taxonomy for topic tagging.
        register_taxonomy(
            'sfls_question_tag',
            $this->get_post_type(),
            array(
                'labels'            => array(
                    'name'          => __( 'Question Tags', 'swiftlms' ),
                    'singular_name' => __( 'Question Tag', 'swiftlms' ),
                    'search_items'  => __( 'Search Tags', 'swiftlms' ),
                    'all_items'     => __( 'All Tags', 'swiftlms' ),
                    'edit_item'     => __( 'Edit Tag', 'swiftlms' ),
                    'update_item'   => __( 'Update Tag', 'swiftlms' ),
                    'add_new_item'  => __( 'Add New Tag', 'swiftlms' ),
                    'new_item_name' => __( 'New Tag Name', 'swiftlms' ),
                    'menu_name'     => __( 'Tags', 'swiftlms' ),
                ),
                'hierarchical'      => false,
                'show_ui'           => true,
                'show_admin_column' => true,
                'query_var'         => false,
                'rewrite'           => false,
                'show_in_rest'      => true,
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
            'sfls_question_settings',
            __( 'Question Settings', 'swiftlms' ),
            array( $this, 'render_settings_meta_box' ),
            $this->get_post_type(),
            'normal',
            'high'
        );

        add_meta_box(
            'sfls_question_answers',
            __( 'Answers', 'swiftlms' ),
            array( $this, 'render_answers_meta_box' ),
            $this->get_post_type(),
            'normal',
            'high'
        );
    }

    /**
     * Render settings meta box.
     *
     * @param \WP_Post $post Current post.
     * @return void
     */
    public function render_settings_meta_box( $post ): void {
        wp_nonce_field( 'sfls_question_settings', 'sfls_question_nonce' );

        $question_type = get_post_meta( $post->ID, '_sfls_question_type', true ) ?: 'multiple_choice';
        $points        = get_post_meta( $post->ID, '_sfls_question_points', true ) ?: 1;
        $hint          = get_post_meta( $post->ID, '_sfls_question_hint', true );
        $explanation   = get_post_meta( $post->ID, '_sfls_question_explanation', true );
        $randomize     = get_post_meta( $post->ID, '_sfls_randomize_answers', true );
        ?>
        <table class="form-table">
            <tr>
                <th><label for="sfls_question_type"><?php esc_html_e( 'Question Type', 'swiftlms' ); ?></label></th>
                <td>
                    <select name="sfls_question_type" id="sfls_question_type" class="regular-text">
                        <?php foreach ( self::TYPES as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $question_type, $value ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="sfls_question_points"><?php esc_html_e( 'Points', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="number" name="sfls_question_points" id="sfls_question_points"
                           value="<?php echo esc_attr( $points ); ?>" min="0" step="0.5" class="small-text">
                </td>
            </tr>
            <tr>
                <th><label for="sfls_randomize_answers"><?php esc_html_e( 'Randomize Answers', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="checkbox" name="sfls_randomize_answers" id="sfls_randomize_answers"
                           value="1" <?php checked( $randomize, '1' ); ?>>
                    <span class="description"><?php esc_html_e( 'Shuffle answer order for each attempt', 'swiftlms' ); ?></span>
                </td>
            </tr>
            <tr>
                <th><label for="sfls_question_hint"><?php esc_html_e( 'Hint', 'swiftlms' ); ?></label></th>
                <td>
                    <textarea name="sfls_question_hint" id="sfls_question_hint" rows="2" class="large-text"><?php echo esc_textarea( $hint ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'Optional hint shown to students if hints are enabled.', 'swiftlms' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="sfls_question_explanation"><?php esc_html_e( 'Explanation', 'swiftlms' ); ?></label></th>
                <td>
                    <textarea name="sfls_question_explanation" id="sfls_question_explanation" rows="3" class="large-text"><?php echo esc_textarea( $explanation ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'Shown after the quiz to explain the correct answer.', 'swiftlms' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render answers meta box.
     *
     * @param \WP_Post $post Current post.
     * @return void
     */
    public function render_answers_meta_box( $post ): void {
        $question_type = get_post_meta( $post->ID, '_sfls_question_type', true ) ?: 'multiple_choice';
        $answers       = get_post_meta( $post->ID, '_sfls_answers', true ) ?: array();
        $correct       = get_post_meta( $post->ID, '_sfls_correct_answers', true ) ?: array();
        $matching      = get_post_meta( $post->ID, '_sfls_matching_pairs', true ) ?: array();
        ?>
        <div id="sfls-answers-container" data-type="<?php echo esc_attr( $question_type ); ?>">

            <!-- Multiple Choice / Multiple Answer / True-False -->
            <div class="sfls-answer-type sfls-choice-answers"
                 style="<?php echo in_array( $question_type, array( 'multiple_choice', 'multiple_answer', 'true_false' ), true ) ? '' : 'display:none;'; ?>">

                <?php if ( 'true_false' === $question_type ) : ?>
                    <div class="sfls-true-false-options">
                        <label>
                            <input type="radio" name="sfls_correct_answers[]" value="true"
                                   <?php checked( in_array( 'true', (array) $correct, true ) ); ?>>
                            <?php esc_html_e( 'True', 'swiftlms' ); ?>
                        </label>
                        <label>
                            <input type="radio" name="sfls_correct_answers[]" value="false"
                                   <?php checked( in_array( 'false', (array) $correct, true ) ); ?>>
                            <?php esc_html_e( 'False', 'swiftlms' ); ?>
                        </label>
                    </div>
                <?php else : ?>
                    <div id="sfls-answers-list">
                        <?php
                        if ( ! empty( $answers ) ) :
                            foreach ( $answers as $index => $answer ) :
                                ?>
                                <div class="sfls-answer-row" data-index="<?php echo esc_attr( $index ); ?>">
                                    <span class="sfls-answer-handle dashicons dashicons-menu"></span>
                                    <input type="<?php echo 'multiple_answer' === $question_type ? 'checkbox' : 'radio'; ?>"
                                           name="sfls_correct_answers[]"
                                           value="<?php echo esc_attr( $index ); ?>"
                                           <?php checked( in_array( (string) $index, (array) $correct, true ) ); ?>>
                                    <input type="text" name="sfls_answers[]" value="<?php echo esc_attr( $answer ); ?>"
                                           class="regular-text" placeholder="<?php esc_attr_e( 'Answer option', 'swiftlms' ); ?>">
                                    <button type="button" class="button sfls-remove-answer">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                                <?php
                            endforeach;
                        endif;
                        ?>
                    </div>
                    <button type="button" class="button sfls-add-answer" id="sfls-add-answer">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e( 'Add Answer', 'swiftlms' ); ?>
                    </button>
                <?php endif; ?>
            </div>

            <!-- Fill in the Blank -->
            <div class="sfls-answer-type sfls-fill-blank-answers"
                 style="<?php echo 'fill_blank' === $question_type ? '' : 'display:none;'; ?>">
                <p class="description">
                    <?php esc_html_e( 'Use [blank] in the question text to indicate where blanks should appear.', 'swiftlms' ); ?>
                </p>
                <div id="sfls-blanks-list">
                    <?php
                    $blanks = get_post_meta( $post->ID, '_sfls_blank_answers', true ) ?: array();
                    if ( ! empty( $blanks ) ) :
                        foreach ( $blanks as $index => $blank ) :
                            ?>
                            <div class="sfls-blank-row">
                                <label><?php printf( esc_html__( 'Blank %d:', 'swiftlms' ), $index + 1 ); ?></label>
                                <input type="text" name="sfls_blank_answers[]" value="<?php echo esc_attr( $blank ); ?>"
                                       class="regular-text" placeholder="<?php esc_attr_e( 'Accepted answer(s), comma-separated', 'swiftlms' ); ?>">
                            </div>
                            <?php
                        endforeach;
                    endif;
                    ?>
                </div>
                <button type="button" class="button" id="sfls-add-blank">
                    <?php esc_html_e( 'Add Blank', 'swiftlms' ); ?>
                </button>
            </div>

            <!-- Matching -->
            <div class="sfls-answer-type sfls-matching-answers"
                 style="<?php echo 'matching' === $question_type ? '' : 'display:none;'; ?>">
                <div id="sfls-matching-list">
                    <?php
                    if ( ! empty( $matching ) ) :
                        foreach ( $matching as $index => $pair ) :
                            ?>
                            <div class="sfls-matching-row" data-index="<?php echo esc_attr( $index ); ?>">
                                <input type="text" name="sfls_matching_left[]"
                                       value="<?php echo esc_attr( $pair['left'] ?? '' ); ?>"
                                       class="regular-text" placeholder="<?php esc_attr_e( 'Left item', 'swiftlms' ); ?>">
                                <span class="dashicons dashicons-arrow-right-alt"></span>
                                <input type="text" name="sfls_matching_right[]"
                                       value="<?php echo esc_attr( $pair['right'] ?? '' ); ?>"
                                       class="regular-text" placeholder="<?php esc_attr_e( 'Right match', 'swiftlms' ); ?>">
                                <button type="button" class="button sfls-remove-match">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                            <?php
                        endforeach;
                    endif;
                    ?>
                </div>
                <button type="button" class="button" id="sfls-add-match">
                    <?php esc_html_e( 'Add Matching Pair', 'swiftlms' ); ?>
                </button>
            </div>

            <!-- Ordering -->
            <div class="sfls-answer-type sfls-ordering-answers"
                 style="<?php echo 'ordering' === $question_type ? '' : 'display:none;'; ?>">
                <p class="description">
                    <?php esc_html_e( 'Enter items in the correct order. They will be shuffled for students.', 'swiftlms' ); ?>
                </p>
                <div id="sfls-ordering-list">
                    <?php
                    $ordering = get_post_meta( $post->ID, '_sfls_ordering_items', true ) ?: array();
                    if ( ! empty( $ordering ) ) :
                        foreach ( $ordering as $index => $item ) :
                            ?>
                            <div class="sfls-ordering-row" data-index="<?php echo esc_attr( $index ); ?>">
                                <span class="sfls-answer-handle dashicons dashicons-menu"></span>
                                <span class="sfls-order-number"><?php echo esc_html( $index + 1 ); ?></span>
                                <input type="text" name="sfls_ordering_items[]" value="<?php echo esc_attr( $item ); ?>"
                                       class="regular-text" placeholder="<?php esc_attr_e( 'Item', 'swiftlms' ); ?>">
                                <button type="button" class="button sfls-remove-order">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                            <?php
                        endforeach;
                    endif;
                    ?>
                </div>
                <button type="button" class="button" id="sfls-add-order-item">
                    <?php esc_html_e( 'Add Item', 'swiftlms' ); ?>
                </button>
            </div>

            <!-- Short Answer -->
            <div class="sfls-answer-type sfls-short-answer"
                 style="<?php echo 'short_answer' === $question_type ? '' : 'display:none;'; ?>">
                <label><?php esc_html_e( 'Accepted Answers (one per line):', 'swiftlms' ); ?></label>
                <textarea name="sfls_short_answers" rows="4" class="large-text"><?php
                    echo esc_textarea( get_post_meta( $post->ID, '_sfls_short_answers', true ) );
                ?></textarea>
                <p class="description">
                    <?php esc_html_e( 'Enter each accepted answer on a new line. Matching is case-insensitive.', 'swiftlms' ); ?>
                </p>
            </div>

            <!-- Essay -->
            <div class="sfls-answer-type sfls-essay-answer"
                 style="<?php echo 'essay' === $question_type ? '' : 'display:none;'; ?>">
                <p class="description">
                    <?php esc_html_e( 'Essay questions require manual grading. Configure grading rubric below.', 'swiftlms' ); ?>
                </p>
                <table class="form-table">
                    <tr>
                        <th><label for="sfls_essay_min_words"><?php esc_html_e( 'Minimum Words', 'swiftlms' ); ?></label></th>
                        <td>
                            <input type="number" name="sfls_essay_min_words" id="sfls_essay_min_words"
                                   value="<?php echo esc_attr( get_post_meta( $post->ID, '_sfls_essay_min_words', true ) ); ?>"
                                   min="0" class="small-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sfls_essay_rubric"><?php esc_html_e( 'Grading Rubric', 'swiftlms' ); ?></label></th>
                        <td>
                            <textarea name="sfls_essay_rubric" id="sfls_essay_rubric" rows="4" class="large-text"><?php
                                echo esc_textarea( get_post_meta( $post->ID, '_sfls_essay_rubric', true ) );
                            ?></textarea>
                        </td>
                    </tr>
                </table>
            </div>

        </div>
        <?php
    }

    /**
     * Save meta box data.
     *
     * @param int $post_id Post ID.
     * @return void
     */
    public function save_meta_box_data( int $post_id ): void {
        if ( ! isset( $_POST['sfls_question_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sfls_question_nonce'] ) ), 'sfls_question_settings' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Question settings.
        $fields = array(
            'sfls_question_type'   => '_sfls_question_type',
            'sfls_question_points' => '_sfls_question_points',
            'sfls_question_hint'   => '_sfls_question_hint',
            'sfls_question_explanation' => '_sfls_question_explanation',
        );

        foreach ( $fields as $field => $meta_key ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
            }
        }

        // Randomize answers checkbox.
        update_post_meta( $post_id, '_sfls_randomize_answers', isset( $_POST['sfls_randomize_answers'] ) ? '1' : '0' );

        // Answers based on type.
        $question_type = sanitize_text_field( wp_unslash( $_POST['sfls_question_type'] ?? 'multiple_choice' ) );

        if ( isset( $_POST['sfls_answers'] ) ) {
            $answers = array_map( 'sanitize_text_field', wp_unslash( $_POST['sfls_answers'] ) );
            update_post_meta( $post_id, '_sfls_answers', array_values( array_filter( $answers ) ) );
        }

        if ( isset( $_POST['sfls_correct_answers'] ) ) {
            $correct = array_map( 'sanitize_text_field', wp_unslash( $_POST['sfls_correct_answers'] ) );
            update_post_meta( $post_id, '_sfls_correct_answers', $correct );
        }

        if ( isset( $_POST['sfls_blank_answers'] ) ) {
            $blanks = array_map( 'sanitize_text_field', wp_unslash( $_POST['sfls_blank_answers'] ) );
            update_post_meta( $post_id, '_sfls_blank_answers', array_values( array_filter( $blanks ) ) );
        }

        if ( isset( $_POST['sfls_matching_left'] ) && isset( $_POST['sfls_matching_right'] ) ) {
            $left  = array_map( 'sanitize_text_field', wp_unslash( $_POST['sfls_matching_left'] ) );
            $right = array_map( 'sanitize_text_field', wp_unslash( $_POST['sfls_matching_right'] ) );
            $pairs = array();
            foreach ( $left as $i => $l ) {
                if ( ! empty( $l ) && ! empty( $right[ $i ] ) ) {
                    $pairs[] = array( 'left' => $l, 'right' => $right[ $i ] );
                }
            }
            update_post_meta( $post_id, '_sfls_matching_pairs', $pairs );
        }

        if ( isset( $_POST['sfls_ordering_items'] ) ) {
            $items = array_map( 'sanitize_text_field', wp_unslash( $_POST['sfls_ordering_items'] ) );
            update_post_meta( $post_id, '_sfls_ordering_items', array_values( array_filter( $items ) ) );
        }

        if ( isset( $_POST['sfls_short_answers'] ) ) {
            update_post_meta( $post_id, '_sfls_short_answers', sanitize_textarea_field( wp_unslash( $_POST['sfls_short_answers'] ) ) );
        }

        if ( isset( $_POST['sfls_essay_min_words'] ) ) {
            update_post_meta( $post_id, '_sfls_essay_min_words', absint( $_POST['sfls_essay_min_words'] ) );
        }

        if ( isset( $_POST['sfls_essay_rubric'] ) ) {
            update_post_meta( $post_id, '_sfls_essay_rubric', sanitize_textarea_field( wp_unslash( $_POST['sfls_essay_rubric'] ) ) );
        }
    }

    /**
     * Get question data for API/frontend.
     *
     * @param int  $question_id Question ID.
     * @param bool $include_correct Include correct answers (for grading).
     * @return array
     */
    public static function get_question_data( int $question_id, bool $include_correct = false ): array {
        $post = get_post( $question_id );
        if ( ! $post || 'sfls_question' !== $post->post_type ) {
            return array();
        }

        $type      = get_post_meta( $question_id, '_sfls_question_type', true ) ?: 'multiple_choice';
        $randomize = get_post_meta( $question_id, '_sfls_randomize_answers', true );

        $data = array(
            'id'          => $question_id,
            'title'       => $post->post_title,
            'content'     => apply_filters( 'the_content', $post->post_content ),
            'type'        => $type,
            'points'      => (float) ( get_post_meta( $question_id, '_sfls_question_points', true ) ?: 1 ),
            'hint'        => get_post_meta( $question_id, '_sfls_question_hint', true ),
            'explanation' => $include_correct ? get_post_meta( $question_id, '_sfls_question_explanation', true ) : '',
        );

        // Get answers based on type.
        switch ( $type ) {
            case 'multiple_choice':
            case 'multiple_answer':
                $answers = get_post_meta( $question_id, '_sfls_answers', true ) ?: array();
                if ( $randomize ) {
                    $keys = array_keys( $answers );
                    shuffle( $keys );
                    $shuffled = array();
                    foreach ( $keys as $key ) {
                        $shuffled[ $key ] = $answers[ $key ];
                    }
                    $answers = $shuffled;
                }
                $data['answers'] = $answers;
                if ( $include_correct ) {
                    $data['correct'] = get_post_meta( $question_id, '_sfls_correct_answers', true ) ?: array();
                }
                break;

            case 'true_false':
                $data['answers'] = array( 'true' => __( 'True', 'swiftlms' ), 'false' => __( 'False', 'swiftlms' ) );
                if ( $include_correct ) {
                    $data['correct'] = get_post_meta( $question_id, '_sfls_correct_answers', true ) ?: array();
                }
                break;

            case 'fill_blank':
                if ( $include_correct ) {
                    $data['blanks'] = get_post_meta( $question_id, '_sfls_blank_answers', true ) ?: array();
                }
                break;

            case 'matching':
                $pairs = get_post_meta( $question_id, '_sfls_matching_pairs', true ) ?: array();
                $left  = wp_list_pluck( $pairs, 'left' );
                $right = wp_list_pluck( $pairs, 'right' );
                if ( $randomize ) {
                    shuffle( $right );
                }
                $data['left_items']  = $left;
                $data['right_items'] = $right;
                if ( $include_correct ) {
                    $data['pairs'] = $pairs;
                }
                break;

            case 'ordering':
                $items = get_post_meta( $question_id, '_sfls_ordering_items', true ) ?: array();
                $shuffled = $items;
                shuffle( $shuffled );
                $data['items'] = $shuffled;
                if ( $include_correct ) {
                    $data['correct_order'] = $items;
                }
                break;

            case 'short_answer':
                if ( $include_correct ) {
                    $data['accepted'] = array_filter( array_map( 'trim', explode( "\n", get_post_meta( $question_id, '_sfls_short_answers', true ) ?: '' ) ) );
                }
                break;

            case 'essay':
                $data['min_words'] = (int) get_post_meta( $question_id, '_sfls_essay_min_words', true );
                if ( $include_correct ) {
                    $data['rubric'] = get_post_meta( $question_id, '_sfls_essay_rubric', true );
                }
                break;
        }

        return $data;
    }
}
