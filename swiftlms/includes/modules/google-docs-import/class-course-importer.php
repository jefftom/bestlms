<?php
/**
 * Course Importer
 *
 * @package SwiftLMS\Modules\GoogleDocsImport
 */

namespace SwiftLMS\Modules\GoogleDocsImport;

defined( 'ABSPATH' ) || exit;

/**
 * Course_Importer class.
 *
 * Creates courses and lessons from parsed Google Docs content.
 */
class Course_Importer {

    /**
     * Parsed document data.
     *
     * @var array
     */
    private $data;

    /**
     * Import options.
     *
     * @var array
     */
    private $options;

    /**
     * Import log.
     *
     * @var array
     */
    private $log = array();

    /**
     * Constructor.
     *
     * @param array $data    Parsed document data.
     * @param array $options Import options.
     */
    public function __construct( array $data, array $options = array() ) {
        $this->data = $data;
        $this->options = wp_parse_args( $options, array(
            'course_status'    => 'draft',
            'lesson_status'    => 'publish',
            'author_id'        => get_current_user_id(),
            'category_ids'     => array(),
            'create_sections'  => false,
            'section_per_h1'   => false,
            'import_quizzes'   => true,
            'quiz_settings'    => array(),
        ) );
    }

    /**
     * Run the import.
     *
     * @return array Import result with course ID and stats.
     */
    public function import(): array {
        $result = array(
            'success'    => false,
            'course_id'  => 0,
            'lessons'    => 0,
            'quizzes'    => 0,
            'sections'   => 0,
            'errors'     => array(),
            'log'        => array(),
        );

        // Create course.
        $course_id = $this->create_course();

        if ( is_wp_error( $course_id ) ) {
            $result['errors'][] = $course_id->get_error_message();
            return $result;
        }

        $result['course_id'] = $course_id;
        $this->log( sprintf( 'Created course: %s (ID: %d)', $this->data['title'], $course_id ) );

        // Create sections if enabled.
        $section_map = array();
        if ( $this->options['create_sections'] ) {
            $section_map = $this->create_sections( $course_id );
            $result['sections'] = count( $section_map );
        }

        // Create lessons.
        foreach ( $this->data['lessons'] as $lesson_data ) {
            $lesson_id = $this->create_lesson( $course_id, $lesson_data, $section_map );

            if ( is_wp_error( $lesson_id ) ) {
                $result['errors'][] = $lesson_id->get_error_message();
                continue;
            }

            $result['lessons']++;
            $this->log( sprintf( 'Created lesson: %s (ID: %d)', $lesson_data['title'], $lesson_id ) );
        }

        // Create quizzes.
        if ( $this->options['import_quizzes'] && ! empty( $this->data['quizzes'] ) ) {
            foreach ( $this->data['quizzes'] as $quiz_data ) {
                $quiz_id = $this->create_quiz( $course_id, $quiz_data );

                if ( is_wp_error( $quiz_id ) ) {
                    $result['errors'][] = $quiz_id->get_error_message();
                    continue;
                }

                $result['quizzes']++;
                $this->log( sprintf( 'Created quiz: %s (ID: %d)', $quiz_data['title'], $quiz_id ) );
            }
        }

        // Update course lesson count.
        update_post_meta( $course_id, '_sfls_lesson_count', $result['lessons'] );

        $result['success'] = empty( $result['errors'] );
        $result['log'] = $this->log;

        do_action( 'swiftlms_google_docs_imported', $course_id, $result );

        return $result;
    }

    /**
     * Create course post.
     *
     * @return int|WP_Error Course ID or error.
     */
    private function create_course() {
        $course_data = array(
            'post_title'   => sanitize_text_field( $this->data['title'] ),
            'post_content' => wp_kses_post( $this->data['description'] ),
            'post_status'  => $this->options['course_status'],
            'post_type'    => 'sfls_course',
            'post_author'  => $this->options['author_id'],
        );

        $course_id = wp_insert_post( $course_data, true );

        if ( is_wp_error( $course_id ) ) {
            return $course_id;
        }

        // Set categories.
        if ( ! empty( $this->options['category_ids'] ) ) {
            wp_set_object_terms( $course_id, $this->options['category_ids'], 'sfls_course_category' );
        }

        // Set meta.
        update_post_meta( $course_id, '_sfls_imported_from', 'google_docs' );
        update_post_meta( $course_id, '_sfls_import_date', current_time( 'mysql' ) );
        update_post_meta( $course_id, '_sfls_difficulty', 'beginner' );
        update_post_meta( $course_id, '_sfls_duration', '' );

        return $course_id;
    }

    /**
     * Create sections from H1 headings.
     *
     * @param int $course_id Course ID.
     * @return array Map of lesson order to section ID.
     */
    private function create_sections( int $course_id ): array {
        $section_map = array();
        $current_section_id = 0;
        $section_order = 0;

        // Group lessons by H1 headings (first lesson after each section).
        foreach ( $this->data['lessons'] as $index => $lesson ) {
            // Create a new section for the first lesson or when specifically marked.
            if ( $index === 0 || ( $this->options['section_per_h1'] && $lesson['is_h1'] ?? false ) ) {
                $section_order++;

                $section_data = array(
                    'post_title'  => $lesson['title'],
                    'post_status' => 'publish',
                    'post_type'   => 'sfls_section',
                    'post_author' => $this->options['author_id'],
                    'menu_order'  => $section_order,
                );

                $section_id = wp_insert_post( $section_data );

                if ( ! is_wp_error( $section_id ) ) {
                    update_post_meta( $section_id, '_sfls_course_id', $course_id );
                    $current_section_id = $section_id;
                    $this->log( sprintf( 'Created section: %s (ID: %d)', $lesson['title'], $section_id ) );
                }
            }

            $section_map[ $lesson['order'] ] = $current_section_id;
        }

        return $section_map;
    }

    /**
     * Create lesson post.
     *
     * @param int   $course_id   Course ID.
     * @param array $lesson_data Lesson data.
     * @param array $section_map Section mapping.
     * @return int|WP_Error Lesson ID or error.
     */
    private function create_lesson( int $course_id, array $lesson_data, array $section_map ) {
        // Process content.
        $content = $this->process_lesson_content( $lesson_data['content'] );

        $lesson = array(
            'post_title'   => sanitize_text_field( $lesson_data['title'] ),
            'post_content' => $content,
            'post_status'  => $this->options['lesson_status'],
            'post_type'    => 'sfls_lesson',
            'post_author'  => $this->options['author_id'],
            'menu_order'   => $lesson_data['order'],
        );

        $lesson_id = wp_insert_post( $lesson, true );

        if ( is_wp_error( $lesson_id ) ) {
            return $lesson_id;
        }

        // Set meta.
        update_post_meta( $lesson_id, '_sfls_course_id', $course_id );
        update_post_meta( $lesson_id, '_sfls_lesson_type', $lesson_data['type'] ?? 'text' );
        update_post_meta( $lesson_id, '_sfls_order', $lesson_data['order'] );

        // Set section if applicable.
        if ( isset( $section_map[ $lesson_data['order'] ] ) ) {
            update_post_meta( $lesson_id, '_sfls_section_id', $section_map[ $lesson_data['order'] ] );
        }

        // Extract video URL if present.
        if ( preg_match( '/<div class="sfls-video-embed">.*?src="([^"]+)"/', $content, $matches ) ) {
            update_post_meta( $lesson_id, '_sfls_video_url', $matches[1] );
        }

        return $lesson_id;
    }

    /**
     * Process lesson content for WordPress.
     *
     * @param string $content Raw content.
     * @return string Processed content.
     */
    private function process_lesson_content( string $content ): string {
        // Wrap consecutive list items in ul/ol tags.
        $content = preg_replace_callback(
            '/(<li>.*?<\/li>\s*)+/s',
            function( $matches ) {
                // Detect if ordered based on first item (simplified).
                return '<ul>' . $matches[0] . '</ul>';
            },
            $content
        );

        // Clean up empty paragraphs.
        $content = preg_replace( '/<p>\s*<\/p>/', '', $content );

        // Apply wp_kses_post for security.
        $content = wp_kses_post( $content );

        return $content;
    }

    /**
     * Create quiz post.
     *
     * @param int   $course_id Course ID.
     * @param array $quiz_data Quiz data.
     * @return int|WP_Error Quiz ID or error.
     */
    private function create_quiz( int $course_id, array $quiz_data ) {
        $quiz = array(
            'post_title'  => sanitize_text_field( $quiz_data['title'] ),
            'post_status' => 'publish',
            'post_type'   => 'sfls_quiz',
            'post_author' => $this->options['author_id'],
        );

        $quiz_id = wp_insert_post( $quiz, true );

        if ( is_wp_error( $quiz_id ) ) {
            return $quiz_id;
        }

        // Set quiz meta.
        update_post_meta( $quiz_id, '_sfls_course_id', $course_id );
        update_post_meta( $quiz_id, '_sfls_passing_score', $this->options['quiz_settings']['passing_score'] ?? 70 );
        update_post_meta( $quiz_id, '_sfls_time_limit', $this->options['quiz_settings']['time_limit'] ?? 0 );
        update_post_meta( $quiz_id, '_sfls_attempts_allowed', $this->options['quiz_settings']['attempts'] ?? 0 );
        update_post_meta( $quiz_id, '_sfls_randomize', $this->options['quiz_settings']['randomize'] ?? false );

        // Create questions.
        $question_order = 0;
        foreach ( $quiz_data['questions'] as $question_data ) {
            $question_order++;
            $this->create_question( $quiz_id, $question_data, $question_order );
        }

        update_post_meta( $quiz_id, '_sfls_question_count', count( $quiz_data['questions'] ) );

        return $quiz_id;
    }

    /**
     * Create quiz question.
     *
     * @param int   $quiz_id       Quiz ID.
     * @param array $question_data Question data.
     * @param int   $order         Question order.
     * @return int|WP_Error Question ID or error.
     */
    private function create_question( int $quiz_id, array $question_data, int $order ) {
        $question = array(
            'post_title'   => wp_trim_words( $question_data['text'], 10 ),
            'post_content' => sanitize_text_field( $question_data['text'] ),
            'post_status'  => 'publish',
            'post_type'    => 'sfls_question',
            'post_author'  => $this->options['author_id'],
            'menu_order'   => $order,
        );

        $question_id = wp_insert_post( $question, true );

        if ( is_wp_error( $question_id ) ) {
            return $question_id;
        }

        // Set question meta.
        update_post_meta( $question_id, '_sfls_quiz_id', $quiz_id );
        update_post_meta( $question_id, '_sfls_question_type', $question_data['type'] );
        update_post_meta( $question_id, '_sfls_question_text', $question_data['text'] );
        update_post_meta( $question_id, '_sfls_answers', $question_data['answers'] );
        update_post_meta( $question_id, '_sfls_correct_answers', $question_data['correct'] );
        update_post_meta( $question_id, '_sfls_points', 1 );

        return $question_id;
    }

    /**
     * Add to import log.
     *
     * @param string $message Log message.
     */
    private function log( string $message ): void {
        $this->log[] = array(
            'time'    => current_time( 'mysql' ),
            'message' => $message,
        );
    }

    /**
     * Preview import without creating posts.
     *
     * @return array Preview data.
     */
    public function preview(): array {
        return array(
            'course' => array(
                'title'       => $this->data['title'],
                'description' => wp_trim_words( strip_tags( $this->data['description'] ), 50 ),
            ),
            'lessons' => array_map( function( $lesson ) {
                return array(
                    'title'      => $lesson['title'],
                    'type'       => $lesson['type'],
                    'word_count' => str_word_count( strip_tags( $lesson['content'] ) ),
                    'has_images' => strpos( $lesson['content'], '<img' ) !== false,
                    'has_video'  => strpos( $lesson['content'], 'sfls-video-embed' ) !== false,
                );
            }, $this->data['lessons'] ),
            'quizzes' => array_map( function( $quiz ) {
                return array(
                    'title'          => $quiz['title'],
                    'question_count' => count( $quiz['questions'] ),
                );
            }, $this->data['quizzes'] ?? array() ),
            'totals' => array(
                'lessons'   => count( $this->data['lessons'] ),
                'quizzes'   => count( $this->data['quizzes'] ?? array() ),
                'questions' => array_sum( array_map( function( $q ) {
                    return count( $q['questions'] );
                }, $this->data['quizzes'] ?? array() ) ),
            ),
        );
    }
}
