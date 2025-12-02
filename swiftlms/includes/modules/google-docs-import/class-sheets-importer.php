<?php
/**
 * Google Sheets Importer
 *
 * @package SwiftLMS\Modules\GoogleDocsImport
 */

namespace SwiftLMS\Modules\GoogleDocsImport;

defined( 'ABSPATH' ) || exit;

/**
 * Sheets_Importer class.
 *
 * Imports data from parsed Google Sheets.
 */
class Sheets_Importer {

    /**
     * Import data.
     *
     * @var array
     */
    private $data;

    /**
     * Import type.
     *
     * @var string
     */
    private $type;

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
     * Import stats.
     *
     * @var array
     */
    private $stats = array(
        'created'  => 0,
        'updated'  => 0,
        'skipped'  => 0,
        'errors'   => 0,
    );

    /**
     * Constructor.
     *
     * @param array  $data    Validated data rows.
     * @param string $type    Import type.
     * @param array  $options Import options.
     */
    public function __construct( array $data, string $type, array $options = array() ) {
        $this->data = $data;
        $this->type = $type;
        $this->options = wp_parse_args( $options, array(
            'update_existing'  => false,
            'send_welcome'     => false,
            'skip_duplicates'  => true,
            'default_status'   => 'draft',
            'default_role'     => 'subscriber',
        ) );
    }

    /**
     * Run import.
     *
     * @return array Import result.
     */
    public function import(): array {
        $method = 'import_' . $this->type;

        if ( ! method_exists( $this, $method ) ) {
            return array(
                'success' => false,
                'message' => __( 'Unknown import type.', 'swiftlms' ),
            );
        }

        $this->$method();

        return array(
            'success' => $this->stats['errors'] === 0,
            'stats'   => $this->stats,
            'log'     => $this->log,
        );
    }

    /**
     * Import students.
     */
    private function import_students(): void {
        foreach ( $this->data as $row ) {
            $email = sanitize_email( $row['email'] );

            // Check if user exists.
            $existing_user = get_user_by( 'email', $email );

            if ( $existing_user ) {
                if ( $this->options['update_existing'] ) {
                    $this->update_user( $existing_user->ID, $row );
                    $this->stats['updated']++;
                    $this->log( sprintf( 'Updated user: %s', $email ) );
                } else {
                    $this->stats['skipped']++;
                    $this->log( sprintf( 'Skipped existing user: %s', $email ), 'skip' );
                }
                continue;
            }

            // Create new user.
            $username = ! empty( $row['username'] )
                ? sanitize_user( $row['username'] )
                : $this->generate_username( $row );

            $password = ! empty( $row['password'] )
                ? $row['password']
                : wp_generate_password( 12 );

            $user_data = array(
                'user_login'   => $username,
                'user_email'   => $email,
                'user_pass'    => $password,
                'first_name'   => sanitize_text_field( $row['first_name'] ?? '' ),
                'last_name'    => sanitize_text_field( $row['last_name'] ?? '' ),
                'role'         => $this->sanitize_role( $row['role'] ?? $this->options['default_role'] ),
                'display_name' => trim( ( $row['first_name'] ?? '' ) . ' ' . ( $row['last_name'] ?? '' ) ) ?: $username,
            );

            $user_id = wp_insert_user( $user_data );

            if ( is_wp_error( $user_id ) ) {
                $this->stats['errors']++;
                $this->log( sprintf( 'Error creating user %s: %s', $email, $user_id->get_error_message() ), 'error' );
                continue;
            }

            // Send welcome email if enabled.
            if ( $this->options['send_welcome'] ) {
                wp_new_user_notification( $user_id, null, 'user' );
            }

            $this->stats['created']++;
            $this->log( sprintf( 'Created user: %s (ID: %d)', $email, $user_id ) );
        }
    }

    /**
     * Import enrollments.
     */
    private function import_enrollments(): void {
        global $wpdb;

        foreach ( $this->data as $row ) {
            $email = sanitize_email( $row['email'] );
            $course_ref = sanitize_text_field( $row['course'] );

            // Find user.
            $user = get_user_by( 'email', $email );
            if ( ! $user ) {
                $this->stats['errors']++;
                $this->log( sprintf( 'User not found: %s', $email ), 'error' );
                continue;
            }

            // Find course.
            $course_id = $this->find_course( $course_ref );
            if ( ! $course_id ) {
                $this->stats['errors']++;
                $this->log( sprintf( 'Course not found: %s', $course_ref ), 'error' );
                continue;
            }

            // Check existing enrollment.
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}swiftlms_enrollments WHERE user_id = %d AND course_id = %d",
                $user->ID,
                $course_id
            ) );

            if ( $existing ) {
                if ( $this->options['update_existing'] ) {
                    $wpdb->update(
                        $wpdb->prefix . 'swiftlms_enrollments',
                        array(
                            'status'     => sanitize_text_field( $row['status'] ?? 'enrolled' ),
                            'expires_at' => ! empty( $row['expiry_date'] ) ? $row['expiry_date'] : null,
                        ),
                        array( 'id' => $existing ),
                        array( '%s', '%s' ),
                        array( '%d' )
                    );
                    $this->stats['updated']++;
                    $this->log( sprintf( 'Updated enrollment: %s in %s', $email, $course_ref ) );
                } else {
                    $this->stats['skipped']++;
                    $this->log( sprintf( 'Skipped existing enrollment: %s in %s', $email, $course_ref ), 'skip' );
                }
                continue;
            }

            // Create enrollment.
            $enrolled_date = ! empty( $row['enrolled_date'] )
                ? $row['enrolled_date']
                : current_time( 'mysql' );

            $wpdb->insert(
                $wpdb->prefix . 'swiftlms_enrollments',
                array(
                    'user_id'     => $user->ID,
                    'course_id'   => $course_id,
                    'status'      => sanitize_text_field( $row['status'] ?? 'enrolled' ),
                    'enrolled_at' => $enrolled_date,
                    'expires_at'  => ! empty( $row['expiry_date'] ) ? $row['expiry_date'] : null,
                    'progress'    => 0,
                ),
                array( '%d', '%d', '%s', '%s', '%s', '%d' )
            );

            $this->stats['created']++;
            $this->log( sprintf( 'Enrolled %s in %s', $email, $course_ref ) );
        }
    }

    /**
     * Import courses.
     */
    private function import_courses(): void {
        foreach ( $this->data as $row ) {
            $title = sanitize_text_field( $row['title'] );

            // Check for existing course.
            $existing = get_page_by_title( $title, OBJECT, 'sfls_course' );

            if ( $existing ) {
                if ( $this->options['update_existing'] ) {
                    $this->update_course( $existing->ID, $row );
                    $this->stats['updated']++;
                    $this->log( sprintf( 'Updated course: %s', $title ) );
                } else {
                    $this->stats['skipped']++;
                    $this->log( sprintf( 'Skipped existing course: %s', $title ), 'skip' );
                }
                continue;
            }

            // Find instructor.
            $author_id = get_current_user_id();
            if ( ! empty( $row['instructor'] ) ) {
                $instructor = get_user_by( 'email', $row['instructor'] );
                if ( $instructor ) {
                    $author_id = $instructor->ID;
                }
            }

            // Create course.
            $course_data = array(
                'post_title'   => $title,
                'post_content' => wp_kses_post( $row['description'] ?? '' ),
                'post_status'  => $this->sanitize_status( $row['status'] ?? $this->options['default_status'] ),
                'post_type'    => 'sfls_course',
                'post_author'  => $author_id,
            );

            $course_id = wp_insert_post( $course_data, true );

            if ( is_wp_error( $course_id ) ) {
                $this->stats['errors']++;
                $this->log( sprintf( 'Error creating course %s: %s', $title, $course_id->get_error_message() ), 'error' );
                continue;
            }

            // Set meta.
            if ( ! empty( $row['price'] ) ) {
                update_post_meta( $course_id, '_sfls_price', floatval( $row['price'] ) );
            }
            if ( ! empty( $row['difficulty'] ) ) {
                update_post_meta( $course_id, '_sfls_difficulty', sanitize_text_field( $row['difficulty'] ) );
            }
            if ( ! empty( $row['duration'] ) ) {
                update_post_meta( $course_id, '_sfls_duration', sanitize_text_field( $row['duration'] ) );
            }

            // Set category.
            if ( ! empty( $row['category'] ) ) {
                $this->set_course_category( $course_id, $row['category'] );
            }

            $this->stats['created']++;
            $this->log( sprintf( 'Created course: %s (ID: %d)', $title, $course_id ) );
        }
    }

    /**
     * Import lessons.
     */
    private function import_lessons(): void {
        foreach ( $this->data as $row ) {
            $title = sanitize_text_field( $row['title'] );
            $course_ref = sanitize_text_field( $row['course'] );

            // Find course.
            $course_id = $this->find_course( $course_ref );
            if ( ! $course_id ) {
                $this->stats['errors']++;
                $this->log( sprintf( 'Course not found for lesson %s: %s', $title, $course_ref ), 'error' );
                continue;
            }

            // Create lesson.
            $lesson_data = array(
                'post_title'   => $title,
                'post_content' => wp_kses_post( $row['content'] ?? '' ),
                'post_status'  => 'publish',
                'post_type'    => 'sfls_lesson',
                'post_author'  => get_current_user_id(),
                'menu_order'   => intval( $row['order'] ?? 0 ),
            );

            $lesson_id = wp_insert_post( $lesson_data, true );

            if ( is_wp_error( $lesson_id ) ) {
                $this->stats['errors']++;
                $this->log( sprintf( 'Error creating lesson %s: %s', $title, $lesson_id->get_error_message() ), 'error' );
                continue;
            }

            // Set meta.
            update_post_meta( $lesson_id, '_sfls_course_id', $course_id );
            update_post_meta( $lesson_id, '_sfls_lesson_type', sanitize_text_field( $row['type'] ?? 'text' ) );

            if ( ! empty( $row['video_url'] ) ) {
                update_post_meta( $lesson_id, '_sfls_video_url', esc_url_raw( $row['video_url'] ) );
            }
            if ( ! empty( $row['duration'] ) ) {
                update_post_meta( $lesson_id, '_sfls_duration', sanitize_text_field( $row['duration'] ) );
            }

            // Handle section.
            if ( ! empty( $row['section'] ) ) {
                $section_id = $this->find_or_create_section( $course_id, $row['section'] );
                if ( $section_id ) {
                    update_post_meta( $lesson_id, '_sfls_section_id', $section_id );
                }
            }

            $this->stats['created']++;
            $this->log( sprintf( 'Created lesson: %s (ID: %d)', $title, $lesson_id ) );
        }
    }

    /**
     * Import quiz questions.
     */
    private function import_quiz_questions(): void {
        foreach ( $this->data as $row ) {
            $question_text = sanitize_text_field( $row['question'] );
            $quiz_ref = sanitize_text_field( $row['quiz'] );

            // Find quiz.
            $quiz_id = $this->find_quiz( $quiz_ref );
            if ( ! $quiz_id ) {
                $this->stats['errors']++;
                $this->log( sprintf( 'Quiz not found: %s', $quiz_ref ), 'error' );
                continue;
            }

            // Parse answers (pipe-separated or JSON).
            $answers = $this->parse_answers( $row['answers'] ?? '' );
            $correct = $this->parse_correct_answer( $row['correct_answer'] ?? '', $answers );

            // Create question.
            $question_data = array(
                'post_title'   => wp_trim_words( $question_text, 10 ),
                'post_content' => $question_text,
                'post_status'  => 'publish',
                'post_type'    => 'sfls_question',
                'post_author'  => get_current_user_id(),
            );

            $question_id = wp_insert_post( $question_data, true );

            if ( is_wp_error( $question_id ) ) {
                $this->stats['errors']++;
                $this->log( sprintf( 'Error creating question: %s', $question_id->get_error_message() ), 'error' );
                continue;
            }

            // Set meta.
            update_post_meta( $question_id, '_sfls_quiz_id', $quiz_id );
            update_post_meta( $question_id, '_sfls_question_type', sanitize_text_field( $row['type'] ?? 'multiple_choice' ) );
            update_post_meta( $question_id, '_sfls_question_text', $question_text );
            update_post_meta( $question_id, '_sfls_answers', $answers );
            update_post_meta( $question_id, '_sfls_correct_answers', $correct );
            update_post_meta( $question_id, '_sfls_points', intval( $row['points'] ?? 1 ) );

            if ( ! empty( $row['explanation'] ) ) {
                update_post_meta( $question_id, '_sfls_explanation', wp_kses_post( $row['explanation'] ) );
            }

            $this->stats['created']++;
            $this->log( sprintf( 'Created question: %s (ID: %d)', wp_trim_words( $question_text, 5 ), $question_id ) );
        }
    }

    /**
     * Import coupons.
     */
    private function import_coupons(): void {
        foreach ( $this->data as $row ) {
            $code = sanitize_text_field( strtoupper( $row['code'] ) );

            // Check for WooCommerce.
            if ( ! class_exists( 'WooCommerce' ) ) {
                $this->stats['errors']++;
                $this->log( 'WooCommerce is required for coupon import', 'error' );
                return;
            }

            // Check existing coupon.
            $existing = wc_get_coupon_id_by_code( $code );
            if ( $existing ) {
                $this->stats['skipped']++;
                $this->log( sprintf( 'Skipped existing coupon: %s', $code ), 'skip' );
                continue;
            }

            // Create coupon.
            $coupon = new \WC_Coupon();
            $coupon->set_code( $code );
            $coupon->set_discount_type( $row['discount_type'] ?? 'percent' );
            $coupon->set_amount( floatval( $row['amount'] ?? 0 ) );

            if ( ! empty( $row['usage_limit'] ) ) {
                $coupon->set_usage_limit( intval( $row['usage_limit'] ) );
            }

            if ( ! empty( $row['expiry_date'] ) ) {
                $coupon->set_date_expires( strtotime( $row['expiry_date'] ) );
            }

            // Restrict to specific courses/products.
            if ( ! empty( $row['courses'] ) && 'all' !== strtolower( $row['courses'] ) ) {
                $product_ids = $this->get_course_product_ids( $row['courses'] );
                if ( $product_ids ) {
                    $coupon->set_product_ids( $product_ids );
                }
            }

            $coupon_id = $coupon->save();

            $this->stats['created']++;
            $this->log( sprintf( 'Created coupon: %s (ID: %d)', $code, $coupon_id ) );
        }
    }

    /**
     * Import certificates.
     */
    private function import_certificates(): void {
        global $wpdb;

        foreach ( $this->data as $row ) {
            $email = sanitize_email( $row['email'] );
            $course_ref = sanitize_text_field( $row['course'] );

            // Find user.
            $user = get_user_by( 'email', $email );
            if ( ! $user ) {
                $this->stats['errors']++;
                $this->log( sprintf( 'User not found: %s', $email ), 'error' );
                continue;
            }

            // Find course.
            $course_id = $this->find_course( $course_ref );
            if ( ! $course_id ) {
                $this->stats['errors']++;
                $this->log( sprintf( 'Course not found: %s', $course_ref ), 'error' );
                continue;
            }

            // Check for existing certificate.
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}swiftlms_certificates WHERE user_id = %d AND course_id = %d",
                $user->ID,
                $course_id
            ) );

            if ( $existing ) {
                $this->stats['skipped']++;
                $this->log( sprintf( 'Certificate already exists for %s in %s', $email, $course_ref ), 'skip' );
                continue;
            }

            // Award certificate.
            $awarded_date = ! empty( $row['date_awarded'] ) ? $row['date_awarded'] : current_time( 'mysql' );

            $wpdb->insert(
                $wpdb->prefix . 'swiftlms_certificates',
                array(
                    'user_id'    => $user->ID,
                    'course_id'  => $course_id,
                    'awarded_at' => $awarded_date,
                    'cert_hash'  => wp_generate_password( 16, false ),
                ),
                array( '%d', '%d', '%s', '%s' )
            );

            $this->stats['created']++;
            $this->log( sprintf( 'Awarded certificate to %s for %s', $email, $course_ref ) );
        }
    }

    /**
     * Import grades.
     */
    private function import_grades(): void {
        global $wpdb;

        foreach ( $this->data as $row ) {
            $email = sanitize_email( $row['email'] );
            $assignment_ref = sanitize_text_field( $row['assignment'] );

            // Find user.
            $user = get_user_by( 'email', $email );
            if ( ! $user ) {
                $this->stats['errors']++;
                $this->log( sprintf( 'User not found: %s', $email ), 'error' );
                continue;
            }

            // Find assignment.
            $assignment = get_page_by_title( $assignment_ref, OBJECT, 'sfls_assignment' );
            if ( ! $assignment ) {
                $this->stats['errors']++;
                $this->log( sprintf( 'Assignment not found: %s', $assignment_ref ), 'error' );
                continue;
            }

            // Find submission.
            $submission_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}swiftlms_submissions
                 WHERE student_id = %d AND assignment_id = %d
                 ORDER BY submitted_at DESC LIMIT 1",
                $user->ID,
                $assignment->ID
            ) );

            if ( ! $submission_id ) {
                $this->stats['errors']++;
                $this->log( sprintf( 'No submission found for %s on %s', $email, $assignment_ref ), 'error' );
                continue;
            }

            // Update grade.
            $wpdb->update(
                $wpdb->prefix . 'swiftlms_submissions',
                array(
                    'grade'     => floatval( $row['grade'] ?? 0 ),
                    'feedback'  => wp_kses_post( $row['feedback'] ?? '' ),
                    'status'    => sanitize_text_field( $row['status'] ?? 'graded' ),
                    'graded_by' => get_current_user_id(),
                    'graded_at' => current_time( 'mysql' ),
                ),
                array( 'id' => $submission_id ),
                array( '%f', '%s', '%s', '%d', '%s' ),
                array( '%d' )
            );

            $this->stats['updated']++;
            $this->log( sprintf( 'Graded submission for %s on %s: %s', $email, $assignment_ref, $row['grade'] ) );
        }
    }

    /**
     * Helper: Find course by title or ID.
     */
    private function find_course( string $ref ): ?int {
        // Try as ID first.
        if ( is_numeric( $ref ) ) {
            $course = get_post( intval( $ref ) );
            if ( $course && 'sfls_course' === $course->post_type ) {
                return $course->ID;
            }
        }

        // Try by title.
        $course = get_page_by_title( $ref, OBJECT, 'sfls_course' );
        return $course ? $course->ID : null;
    }

    /**
     * Helper: Find quiz by title or ID.
     */
    private function find_quiz( string $ref ): ?int {
        if ( is_numeric( $ref ) ) {
            $quiz = get_post( intval( $ref ) );
            if ( $quiz && 'sfls_quiz' === $quiz->post_type ) {
                return $quiz->ID;
            }
        }

        $quiz = get_page_by_title( $ref, OBJECT, 'sfls_quiz' );
        return $quiz ? $quiz->ID : null;
    }

    /**
     * Helper: Find or create section.
     */
    private function find_or_create_section( int $course_id, string $title ): ?int {
        global $wpdb;

        // Find existing.
        $section_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'sfls_section'
               AND p.post_title = %s
               AND pm.meta_key = '_sfls_course_id'
               AND pm.meta_value = %d",
            $title,
            $course_id
        ) );

        if ( $section_id ) {
            return (int) $section_id;
        }

        // Create new.
        $section_id = wp_insert_post( array(
            'post_title'  => sanitize_text_field( $title ),
            'post_type'   => 'sfls_section',
            'post_status' => 'publish',
        ) );

        if ( ! is_wp_error( $section_id ) ) {
            update_post_meta( $section_id, '_sfls_course_id', $course_id );
            return $section_id;
        }

        return null;
    }

    /**
     * Helper: Set course category.
     */
    private function set_course_category( int $course_id, string $category_name ): void {
        $term = get_term_by( 'name', $category_name, 'sfls_course_category' );

        if ( ! $term ) {
            $result = wp_insert_term( $category_name, 'sfls_course_category' );
            if ( ! is_wp_error( $result ) ) {
                $term_id = $result['term_id'];
            }
        } else {
            $term_id = $term->term_id;
        }

        if ( isset( $term_id ) ) {
            wp_set_object_terms( $course_id, $term_id, 'sfls_course_category' );
        }
    }

    /**
     * Helper: Parse answers from string.
     */
    private function parse_answers( string $answers_str ): array {
        if ( empty( $answers_str ) ) {
            return array();
        }

        // Try JSON first.
        $decoded = json_decode( $answers_str, true );
        if ( is_array( $decoded ) ) {
            return array_map( 'sanitize_text_field', $decoded );
        }

        // Pipe-separated.
        return array_map( 'trim', explode( '|', $answers_str ) );
    }

    /**
     * Helper: Parse correct answer.
     */
    private function parse_correct_answer( string $correct_str, array $answers ): array {
        if ( empty( $correct_str ) ) {
            return array( 0 );
        }

        // If it's an index.
        if ( is_numeric( $correct_str ) ) {
            return array( intval( $correct_str ) );
        }

        // Find by text match.
        $correct_text = strtolower( trim( $correct_str ) );
        foreach ( $answers as $index => $answer ) {
            if ( strtolower( trim( $answer ) ) === $correct_text ) {
                return array( $index );
            }
        }

        return array( 0 );
    }

    /**
     * Helper: Get product IDs for courses.
     */
    private function get_course_product_ids( string $courses_str ): array {
        global $wpdb;

        $course_names = array_map( 'trim', explode( ',', $courses_str ) );
        $product_ids = array();

        foreach ( $course_names as $name ) {
            $product_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT pm.meta_value FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE p.post_title = %s
                   AND p.post_type = 'sfls_course'
                   AND pm.meta_key = '_sfls_product_id'",
                $name
            ) );

            if ( $product_id ) {
                $product_ids[] = intval( $product_id );
            }
        }

        return $product_ids;
    }

    /**
     * Helper: Generate username.
     */
    private function generate_username( array $row ): string {
        $base = '';

        if ( ! empty( $row['first_name'] ) && ! empty( $row['last_name'] ) ) {
            $base = strtolower( $row['first_name'] . $row['last_name'] );
        } elseif ( ! empty( $row['email'] ) ) {
            $base = strstr( $row['email'], '@', true );
        } else {
            $base = 'user';
        }

        $base = sanitize_user( preg_replace( '/[^a-z0-9]/', '', $base ) );
        $username = $base;
        $counter = 1;

        while ( username_exists( $username ) ) {
            $username = $base . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Helper: Update existing user.
     */
    private function update_user( int $user_id, array $row ): void {
        $update_data = array( 'ID' => $user_id );

        if ( ! empty( $row['first_name'] ) ) {
            $update_data['first_name'] = sanitize_text_field( $row['first_name'] );
        }
        if ( ! empty( $row['last_name'] ) ) {
            $update_data['last_name'] = sanitize_text_field( $row['last_name'] );
        }

        wp_update_user( $update_data );
    }

    /**
     * Helper: Update existing course.
     */
    private function update_course( int $course_id, array $row ): void {
        $update_data = array( 'ID' => $course_id );

        if ( ! empty( $row['description'] ) ) {
            $update_data['post_content'] = wp_kses_post( $row['description'] );
        }
        if ( ! empty( $row['status'] ) ) {
            $update_data['post_status'] = $this->sanitize_status( $row['status'] );
        }

        wp_update_post( $update_data );

        if ( ! empty( $row['price'] ) ) {
            update_post_meta( $course_id, '_sfls_price', floatval( $row['price'] ) );
        }
        if ( ! empty( $row['difficulty'] ) ) {
            update_post_meta( $course_id, '_sfls_difficulty', sanitize_text_field( $row['difficulty'] ) );
        }
    }

    /**
     * Helper: Sanitize role.
     */
    private function sanitize_role( string $role ): string {
        $allowed = array( 'subscriber', 'student', 'sfls_instructor', 'author', 'editor' );
        $role = strtolower( $role );
        return in_array( $role, $allowed, true ) ? $role : 'subscriber';
    }

    /**
     * Helper: Sanitize status.
     */
    private function sanitize_status( string $status ): string {
        $allowed = array( 'draft', 'publish', 'pending', 'private' );
        $status = strtolower( $status );
        return in_array( $status, $allowed, true ) ? $status : 'draft';
    }

    /**
     * Add to log.
     */
    private function log( string $message, string $type = 'success' ): void {
        $this->log[] = array(
            'time'    => current_time( 'H:i:s' ),
            'message' => $message,
            'type'    => $type,
        );
    }
}
