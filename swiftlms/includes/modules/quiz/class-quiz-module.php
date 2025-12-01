<?php
/**
 * Quiz Module
 *
 * @package SwiftLMS\Modules\Quiz
 * @since 1.0.0
 */

namespace SwiftLMS\Modules\Quiz;

use SwiftLMS\Abstracts\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Quiz Module class.
 */
class QuizModule extends AbstractModule {

    /**
     * Question CPT instance.
     *
     * @var Question
     */
    public $question;

    /**
     * Quiz CPT instance.
     *
     * @var Quiz
     */
    public $quiz;

    /**
     * Get module ID.
     *
     * @return string
     */
    public function get_id(): string {
        return 'quiz';
    }

    /**
     * Get module name.
     *
     * @return string
     */
    public function get_name(): string {
        return __( 'Quiz & Assessments', 'swiftlms' );
    }

    /**
     * Get module description.
     *
     * @return string
     */
    public function get_description(): string {
        return __( 'Add quizzes and assessments to your courses with multiple question types, grading, and reporting.', 'swiftlms' );
    }

    /**
     * Get module version.
     *
     * @return string
     */
    public function get_version(): string {
        return '1.0.0';
    }

    /**
     * Initialize module.
     *
     * @return void
     */
    public function init(): void {
        // Initialize CPTs.
        $this->question = new Question();
        $this->quiz     = new Quiz();

        // Register hooks.
        add_action( 'init', array( $this, 'register_post_types' ) );
        add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_meta_boxes' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );

        // REST API.
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

        // AJAX handlers.
        add_action( 'wp_ajax_sfls_search_questions', array( $this, 'ajax_search_questions' ) );
        add_action( 'wp_ajax_sfls_get_lessons_for_course', array( $this, 'ajax_get_lessons' ) );

        // Template hooks.
        add_filter( 'single_template', array( $this, 'quiz_template' ) );

        // Progress integration.
        add_action( 'swiftlms_quiz_attempt_submitted', array( $this, 'update_lesson_progress' ), 10, 2 );
    }

    /**
     * Activate module.
     *
     * @return void
     */
    public function activate(): void {
        AttemptsTable::create_table();
        flush_rewrite_rules();
    }

    /**
     * Deactivate module.
     *
     * @return void
     */
    public function deactivate(): void {
        flush_rewrite_rules();
    }

    /**
     * Register post types.
     *
     * @return void
     */
    public function register_post_types(): void {
        $this->question->register();
        $this->quiz->register();

        $this->question->register_taxonomies();
    }

    /**
     * Register meta boxes.
     *
     * @return void
     */
    public function register_meta_boxes(): void {
        $this->question->register_meta_boxes();
        $this->quiz->register_meta_boxes();
    }

    /**
     * Save meta boxes.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     * @return void
     */
    public function save_meta_boxes( int $post_id, \WP_Post $post ): void {
        if ( 'sfls_question' === $post->post_type ) {
            $this->question->save_meta_box_data( $post_id );
        } elseif ( 'sfls_quiz' === $post->post_type ) {
            $this->quiz->save_meta_box_data( $post_id );
        }
    }

    /**
     * Enqueue admin scripts.
     *
     * @param string $hook Current admin page.
     * @return void
     */
    public function enqueue_admin_scripts( string $hook ): void {
        global $post_type;

        if ( ! in_array( $post_type, array( 'sfls_question', 'sfls_quiz' ), true ) ) {
            return;
        }

        wp_enqueue_script( 'jquery-ui-sortable' );

        wp_enqueue_script(
            'swiftlms-quiz-admin',
            SWIFTLMS_PLUGIN_URL . 'includes/modules/quiz/assets/js/quiz-admin.js',
            array( 'jquery', 'jquery-ui-sortable' ),
            SWIFTLMS_VERSION,
            true
        );

        wp_localize_script(
            'swiftlms-quiz-admin',
            'swiftlmsQuizAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'sfls_quiz_admin' ),
                'i18n'    => array(
                    'searchPlaceholder' => __( 'Search questions...', 'swiftlms' ),
                    'noQuestions'       => __( 'No questions found.', 'swiftlms' ),
                    'loading'           => __( 'Loading...', 'swiftlms' ),
                ),
            )
        );

        wp_enqueue_style(
            'swiftlms-quiz-admin',
            SWIFTLMS_PLUGIN_URL . 'includes/modules/quiz/assets/css/quiz-admin.css',
            array(),
            SWIFTLMS_VERSION
        );
    }

    /**
     * Enqueue frontend scripts.
     *
     * @return void
     */
    public function enqueue_frontend_scripts(): void {
        if ( ! is_singular( 'sfls_quiz' ) ) {
            return;
        }

        wp_enqueue_script(
            'swiftlms-quiz-player',
            SWIFTLMS_PLUGIN_URL . 'includes/modules/quiz/assets/js/quiz-player.js',
            array( 'jquery' ),
            SWIFTLMS_VERSION,
            true
        );

        wp_localize_script(
            'swiftlms-quiz-player',
            'swiftlmsQuiz',
            array(
                'restUrl'   => rest_url( 'swiftlms/v1/quizzes/' ),
                'nonce'     => wp_create_nonce( 'wp_rest' ),
                'userId'    => get_current_user_id(),
                'quizId'    => get_the_ID(),
                'i18n'      => array(
                    'timeUp'          => __( 'Time is up! Your quiz will be submitted.', 'swiftlms' ),
                    'confirmSubmit'   => __( 'Are you sure you want to submit your quiz?', 'swiftlms' ),
                    'submitting'      => __( 'Submitting...', 'swiftlms' ),
                    'errorSubmit'     => __( 'Error submitting quiz. Please try again.', 'swiftlms' ),
                    'correct'         => __( 'Correct!', 'swiftlms' ),
                    'incorrect'       => __( 'Incorrect', 'swiftlms' ),
                    'unanswered'      => __( 'You have unanswered questions. Continue anyway?', 'swiftlms' ),
                ),
            )
        );

        wp_enqueue_style(
            'swiftlms-quiz-player',
            SWIFTLMS_PLUGIN_URL . 'includes/modules/quiz/assets/css/quiz-player.css',
            array(),
            SWIFTLMS_VERSION
        );
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function register_rest_routes(): void {
        register_rest_route(
            'swiftlms/v1',
            '/quizzes/(?P<id>\d+)',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'rest_get_quiz' ),
                'permission_callback' => array( $this, 'rest_check_enrolled' ),
                'args'                => array(
                    'id' => array(
                        'required'          => true,
                        'validate_callback' => function ( $param ) {
                            return is_numeric( $param );
                        },
                    ),
                ),
            )
        );

        register_rest_route(
            'swiftlms/v1',
            '/quizzes/(?P<id>\d+)/start',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'rest_start_quiz' ),
                'permission_callback' => array( $this, 'rest_check_enrolled' ),
            )
        );

        register_rest_route(
            'swiftlms/v1',
            '/quizzes/attempts/(?P<attempt_id>\d+)/answer',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'rest_save_answer' ),
                'permission_callback' => array( $this, 'rest_check_attempt_owner' ),
            )
        );

        register_rest_route(
            'swiftlms/v1',
            '/quizzes/attempts/(?P<attempt_id>\d+)/submit',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'rest_submit_quiz' ),
                'permission_callback' => array( $this, 'rest_check_attempt_owner' ),
            )
        );

        register_rest_route(
            'swiftlms/v1',
            '/quizzes/attempts/(?P<attempt_id>\d+)',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'rest_get_attempt' ),
                'permission_callback' => array( $this, 'rest_check_attempt_access' ),
            )
        );
    }

    /**
     * Check if user is enrolled in course.
     *
     * @param \WP_REST_Request $request Request object.
     * @return bool
     */
    public function rest_check_enrolled( \WP_REST_Request $request ): bool {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $quiz_id   = $request->get_param( 'id' );
        $course_id = get_post_meta( $quiz_id, '_sfls_course_id', true );

        if ( ! $course_id ) {
            return true; // No course association, allow access.
        }

        // Check enrollment.
        return \SwiftLMS\Core\Enrollment::is_enrolled( get_current_user_id(), $course_id );
    }

    /**
     * Check if user owns the attempt.
     *
     * @param \WP_REST_Request $request Request object.
     * @return bool
     */
    public function rest_check_attempt_owner( \WP_REST_Request $request ): bool {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $attempt_id = $request->get_param( 'attempt_id' );
        $attempt    = AttemptsTable::get_attempt( (int) $attempt_id );

        return $attempt && (int) $attempt->user_id === get_current_user_id();
    }

    /**
     * Check if user can access attempt (owner or admin).
     *
     * @param \WP_REST_Request $request Request object.
     * @return bool
     */
    public function rest_check_attempt_access( \WP_REST_Request $request ): bool {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        return $this->rest_check_attempt_owner( $request );
    }

    /**
     * REST: Get quiz data.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function rest_get_quiz( \WP_REST_Request $request ): \WP_REST_Response {
        $quiz_id = (int) $request->get_param( 'id' );
        $data    = Quiz::get_quiz_data( $quiz_id, true );

        if ( empty( $data ) ) {
            return new \WP_REST_Response( array( 'error' => 'Quiz not found' ), 404 );
        }

        // Add user's attempt info.
        $user_id          = get_current_user_id();
        $data['attempts'] = AttemptsTable::get_user_attempts( $user_id, $quiz_id );
        $data['can_start'] = true;

        if ( $data['attempts_allowed'] > 0 ) {
            $data['can_start'] = count( $data['attempts'] ) < $data['attempts_allowed'];
        }

        // Check for in-progress attempt.
        $latest = AttemptsTable::get_latest_attempt( $user_id, $quiz_id );
        if ( $latest && 'in_progress' === $latest->status ) {
            $data['current_attempt'] = $latest;
        }

        return new \WP_REST_Response( $data, 200 );
    }

    /**
     * REST: Start quiz attempt.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function rest_start_quiz( \WP_REST_Request $request ): \WP_REST_Response {
        $quiz_id = (int) $request->get_param( 'id' );
        $user_id = get_current_user_id();

        // Check for existing in-progress attempt.
        $latest = AttemptsTable::get_latest_attempt( $user_id, $quiz_id );
        if ( $latest && 'in_progress' === $latest->status ) {
            // Resume existing attempt.
            $answers = AttemptsTable::get_attempt_answers( $latest->id );
            return new \WP_REST_Response(
                array(
                    'attempt_id' => $latest->id,
                    'resumed'    => true,
                    'started_at' => $latest->started_at,
                    'answers'    => $answers,
                ),
                200
            );
        }

        $attempt_id = AttemptsTable::start_attempt( $user_id, $quiz_id );

        if ( ! $attempt_id ) {
            return new \WP_REST_Response(
                array( 'error' => __( 'Cannot start quiz. Maximum attempts reached.', 'swiftlms' ) ),
                403
            );
        }

        return new \WP_REST_Response(
            array(
                'attempt_id' => $attempt_id,
                'started_at' => current_time( 'mysql' ),
            ),
            200
        );
    }

    /**
     * REST: Save answer.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function rest_save_answer( \WP_REST_Request $request ): \WP_REST_Response {
        $attempt_id  = (int) $request->get_param( 'attempt_id' );
        $question_id = (int) $request->get_param( 'question_id' );
        $answer      = $request->get_param( 'answer' );
        $time_spent  = (int) $request->get_param( 'time_spent' );

        $result = AttemptsTable::save_answer( $attempt_id, $question_id, $answer, $time_spent );

        if ( ! $result ) {
            return new \WP_REST_Response( array( 'error' => 'Failed to save answer' ), 500 );
        }

        // Check for instant feedback.
        $attempt = AttemptsTable::get_attempt( $attempt_id );
        $instant = get_post_meta( $attempt->quiz_id, '_sfls_instant_feedback', true );

        $response = array( 'saved' => true );

        if ( $instant ) {
            $grading              = AttemptsTable::grade_answer( $question_id, $answer );
            $response['feedback'] = $grading;
        }

        return new \WP_REST_Response( $response, 200 );
    }

    /**
     * REST: Submit quiz.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function rest_submit_quiz( \WP_REST_Request $request ): \WP_REST_Response {
        $attempt_id = (int) $request->get_param( 'attempt_id' );
        $result     = AttemptsTable::submit_attempt( $attempt_id );

        if ( isset( $result['error'] ) ) {
            return new \WP_REST_Response( $result, 400 );
        }

        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * REST: Get attempt details.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function rest_get_attempt( \WP_REST_Request $request ): \WP_REST_Response {
        $attempt_id = (int) $request->get_param( 'attempt_id' );
        $attempt    = AttemptsTable::get_attempt( $attempt_id );

        if ( ! $attempt ) {
            return new \WP_REST_Response( array( 'error' => 'Attempt not found' ), 404 );
        }

        $answers = AttemptsTable::get_attempt_answers( $attempt_id );

        return new \WP_REST_Response(
            array(
                'attempt' => $attempt,
                'answers' => $answers,
            ),
            200
        );
    }

    /**
     * AJAX: Search questions.
     *
     * @return void
     */
    public function ajax_search_questions(): void {
        check_ajax_referer( 'sfls_quiz_admin', 'nonce' );

        $search   = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
        $type     = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
        $category = isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0;
        $exclude  = isset( $_POST['exclude'] ) ? array_map( 'absint', wp_unslash( $_POST['exclude'] ) ) : array();

        $args = array(
            'post_type'      => 'sfls_question',
            'posts_per_page' => 50,
            's'              => $search,
            'post__not_in'   => $exclude,
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        if ( $type ) {
            $args['meta_query'][] = array(
                'key'   => '_sfls_question_type',
                'value' => $type,
            );
        }

        if ( $category ) {
            $args['tax_query'][] = array(
                'taxonomy' => 'sfls_question_cat',
                'field'    => 'term_id',
                'terms'    => $category,
            );
        }

        $questions = get_posts( $args );
        $results   = array();

        foreach ( $questions as $q ) {
            $type   = get_post_meta( $q->ID, '_sfls_question_type', true );
            $points = get_post_meta( $q->ID, '_sfls_question_points', true ) ?: 1;

            $results[] = array(
                'id'     => $q->ID,
                'title'  => $q->post_title,
                'type'   => Question::TYPES[ $type ] ?? $type,
                'points' => $points,
            );
        }

        wp_send_json_success( $results );
    }

    /**
     * AJAX: Get lessons for course.
     *
     * @return void
     */
    public function ajax_get_lessons(): void {
        check_ajax_referer( 'sfls_quiz_admin', 'nonce' );

        $course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;

        if ( ! $course_id ) {
            wp_send_json_success( array() );
        }

        $lessons = get_posts( array(
            'post_type'      => 'sfls_lesson',
            'posts_per_page' => -1,
            'meta_key'       => '_sfls_course_id',
            'meta_value'     => $course_id,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        ) );

        $results = array();
        foreach ( $lessons as $lesson ) {
            $results[] = array(
                'id'    => $lesson->ID,
                'title' => $lesson->post_title,
            );
        }

        wp_send_json_success( $results );
    }

    /**
     * Load quiz template.
     *
     * @param string $template Current template.
     * @return string
     */
    public function quiz_template( string $template ): string {
        if ( is_singular( 'sfls_quiz' ) ) {
            $custom = SWIFTLMS_PLUGIN_DIR . 'templates/quiz/single-quiz.php';
            if ( file_exists( $custom ) ) {
                return $custom;
            }
        }

        return $template;
    }

    /**
     * Update lesson progress when quiz is passed.
     *
     * @param int   $attempt_id Attempt ID.
     * @param array $result     Quiz result.
     * @return void
     */
    public function update_lesson_progress( int $attempt_id, array $result ): void {
        if ( ! $result['passed'] ) {
            return;
        }

        $attempt = AttemptsTable::get_attempt( $attempt_id );
        if ( ! $attempt || ! $attempt->lesson_id ) {
            return;
        }

        // Mark lesson as complete.
        \SwiftLMS\Core\Progress::mark_complete(
            $attempt->user_id,
            $attempt->course_id,
            $attempt->lesson_id
        );
    }
}
