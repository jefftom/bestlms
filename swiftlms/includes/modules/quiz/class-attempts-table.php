<?php
/**
 * Quiz Attempts Database Table
 *
 * @package SwiftLMS\Modules\Quiz
 * @since 1.0.0
 */

namespace SwiftLMS\Modules\Quiz;

defined( 'ABSPATH' ) || exit;

/**
 * Attempts table class.
 */
class AttemptsTable {

    /**
     * Table name without prefix.
     *
     * @var string
     */
    const TABLE_NAME = 'swiftlms_quiz_attempts';

    /**
     * Get full table name with prefix.
     *
     * @return string
     */
    public static function get_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Create the table.
     *
     * @return void
     */
    public static function create_table(): void {
        global $wpdb;

        $table_name      = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            quiz_id BIGINT UNSIGNED NOT NULL,
            course_id BIGINT UNSIGNED DEFAULT NULL,
            lesson_id BIGINT UNSIGNED DEFAULT NULL,
            attempt_number INT UNSIGNED NOT NULL DEFAULT 1,
            status ENUM('in_progress', 'completed', 'passed', 'failed', 'pending_review') DEFAULT 'in_progress',
            started_at DATETIME NOT NULL,
            completed_at DATETIME DEFAULT NULL,
            time_spent INT UNSIGNED DEFAULT 0 COMMENT 'seconds',
            total_points DECIMAL(10,2) DEFAULT 0,
            earned_points DECIMAL(10,2) DEFAULT 0,
            percentage DECIMAL(5,2) DEFAULT 0,
            answers LONGTEXT COMMENT 'JSON of user answers',
            grading_data LONGTEXT COMMENT 'JSON of grading details per question',
            essay_feedback LONGTEXT COMMENT 'JSON of instructor feedback for essays',
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX idx_user_quiz (user_id, quiz_id),
            INDEX idx_quiz_status (quiz_id, status),
            INDEX idx_user_course (user_id, course_id),
            INDEX idx_started (started_at),
            INDEX idx_completed (completed_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Create answers detail table for individual question responses.
        $answers_table = $wpdb->prefix . 'swiftlms_quiz_answers';
        $answers_sql   = "CREATE TABLE {$answers_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            attempt_id BIGINT UNSIGNED NOT NULL,
            question_id BIGINT UNSIGNED NOT NULL,
            user_answer LONGTEXT COMMENT 'JSON of user response',
            is_correct TINYINT(1) DEFAULT NULL,
            points_earned DECIMAL(10,2) DEFAULT 0,
            points_possible DECIMAL(10,2) DEFAULT 0,
            time_spent INT UNSIGNED DEFAULT 0 COMMENT 'seconds on this question',
            graded_by BIGINT UNSIGNED DEFAULT NULL COMMENT 'user_id for manual grading',
            graded_at DATETIME DEFAULT NULL,
            feedback TEXT DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX idx_attempt (attempt_id),
            INDEX idx_question (question_id),
            INDEX idx_attempt_question (attempt_id, question_id)
        ) {$charset_collate};";

        dbDelta( $answers_sql );
    }

    /**
     * Start a new quiz attempt.
     *
     * @param int $user_id User ID.
     * @param int $quiz_id Quiz ID.
     * @return int|false Attempt ID or false on failure.
     */
    public static function start_attempt( int $user_id, int $quiz_id ) {
        global $wpdb;

        // Get attempt number.
        $attempt_count = self::get_attempt_count( $user_id, $quiz_id );

        // Check attempt limits.
        $allowed = (int) get_post_meta( $quiz_id, '_sfls_attempts_allowed', true );
        if ( $allowed > 0 && $attempt_count >= $allowed ) {
            return false;
        }

        $course_id = get_post_meta( $quiz_id, '_sfls_course_id', true );
        $lesson_id = get_post_meta( $quiz_id, '_sfls_lesson_id', true );

        // Get total points.
        $question_ids = get_post_meta( $quiz_id, '_sfls_quiz_questions', true ) ?: array();
        $total_points = 0;
        foreach ( $question_ids as $q_id ) {
            $total_points += (float) ( get_post_meta( $q_id, '_sfls_question_points', true ) ?: 1 );
        }

        $result = $wpdb->insert(
            self::get_table_name(),
            array(
                'user_id'        => $user_id,
                'quiz_id'        => $quiz_id,
                'course_id'      => $course_id ?: null,
                'lesson_id'      => $lesson_id ?: null,
                'attempt_number' => $attempt_count + 1,
                'status'         => 'in_progress',
                'started_at'     => current_time( 'mysql' ),
                'total_points'   => $total_points,
                'ip_address'     => self::get_client_ip(),
                'user_agent'     => isset( $_SERVER['HTTP_USER_AGENT'] )
                    ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
                    : null,
            ),
            array( '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%s' )
        );

        if ( ! $result ) {
            return false;
        }

        $attempt_id = $wpdb->insert_id;

        /**
         * Fires when a quiz attempt is started.
         *
         * @param int $attempt_id Attempt ID.
         * @param int $user_id    User ID.
         * @param int $quiz_id    Quiz ID.
         */
        do_action( 'swiftlms_quiz_attempt_started', $attempt_id, $user_id, $quiz_id );

        return $attempt_id;
    }

    /**
     * Save answer for a question.
     *
     * @param int   $attempt_id  Attempt ID.
     * @param int   $question_id Question ID.
     * @param mixed $answer      User's answer.
     * @param int   $time_spent  Time spent on question (seconds).
     * @return bool
     */
    public static function save_answer( int $attempt_id, int $question_id, $answer, int $time_spent = 0 ): bool {
        global $wpdb;

        $answers_table = $wpdb->prefix . 'swiftlms_quiz_answers';
        $points        = (float) ( get_post_meta( $question_id, '_sfls_question_points', true ) ?: 1 );

        // Check if answer already exists.
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$answers_table} WHERE attempt_id = %d AND question_id = %d",
                $attempt_id,
                $question_id
            )
        );

        $data = array(
            'attempt_id'      => $attempt_id,
            'question_id'     => $question_id,
            'user_answer'     => wp_json_encode( $answer ),
            'points_possible' => $points,
            'time_spent'      => $time_spent,
        );

        if ( $existing ) {
            return (bool) $wpdb->update(
                $answers_table,
                $data,
                array( 'id' => $existing ),
                array( '%d', '%d', '%s', '%f', '%d' ),
                array( '%d' )
            );
        }

        return (bool) $wpdb->insert(
            $answers_table,
            $data,
            array( '%d', '%d', '%s', '%f', '%d' )
        );
    }

    /**
     * Submit and grade quiz attempt.
     *
     * @param int $attempt_id Attempt ID.
     * @return array Grading results.
     */
    public static function submit_attempt( int $attempt_id ): array {
        global $wpdb;

        $attempt = self::get_attempt( $attempt_id );
        if ( ! $attempt || 'in_progress' !== $attempt->status ) {
            return array( 'error' => __( 'Invalid attempt', 'swiftlms' ) );
        }

        $answers_table = $wpdb->prefix . 'swiftlms_quiz_answers';
        $answers       = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$answers_table} WHERE attempt_id = %d",
                $attempt_id
            )
        );

        $earned_points   = 0;
        $pending_review  = false;
        $grading_details = array();

        foreach ( $answers as $answer ) {
            $grading = self::grade_answer( $answer->question_id, json_decode( $answer->user_answer, true ) );

            $wpdb->update(
                $answers_table,
                array(
                    'is_correct'    => $grading['is_correct'] ? 1 : ( $grading['pending'] ? null : 0 ),
                    'points_earned' => $grading['points_earned'],
                ),
                array( 'id' => $answer->id ),
                array( '%d', '%f' ),
                array( '%d' )
            );

            $earned_points                             += $grading['points_earned'];
            $grading_details[ $answer->question_id ]    = $grading;

            if ( $grading['pending'] ) {
                $pending_review = true;
            }
        }

        // Calculate percentage.
        $percentage = $attempt->total_points > 0
            ? round( ( $earned_points / $attempt->total_points ) * 100, 2 )
            : 0;

        // Determine status.
        $passing_score = (int) get_post_meta( $attempt->quiz_id, '_sfls_passing_score', true ) ?: 70;
        if ( $pending_review ) {
            $status = 'pending_review';
        } elseif ( $percentage >= $passing_score ) {
            $status = 'passed';
        } else {
            $status = 'failed';
        }

        // Calculate time spent.
        $started    = strtotime( $attempt->started_at );
        $time_spent = time() - $started;

        // Update attempt.
        $wpdb->update(
            self::get_table_name(),
            array(
                'status'        => $status,
                'completed_at'  => current_time( 'mysql' ),
                'time_spent'    => $time_spent,
                'earned_points' => $earned_points,
                'percentage'    => $percentage,
                'grading_data'  => wp_json_encode( $grading_details ),
            ),
            array( 'id' => $attempt_id ),
            array( '%s', '%s', '%d', '%f', '%f', '%s' ),
            array( '%d' )
        );

        $result = array(
            'attempt_id'     => $attempt_id,
            'status'         => $status,
            'passed'         => 'passed' === $status,
            'percentage'     => $percentage,
            'passing_score'  => $passing_score,
            'earned_points'  => $earned_points,
            'total_points'   => $attempt->total_points,
            'time_spent'     => $time_spent,
            'pending_review' => $pending_review,
            'details'        => $grading_details,
        );

        /**
         * Fires when a quiz attempt is submitted.
         *
         * @param int   $attempt_id Attempt ID.
         * @param array $result     Grading results.
         */
        do_action( 'swiftlms_quiz_attempt_submitted', $attempt_id, $result );

        return $result;
    }

    /**
     * Grade a single answer.
     *
     * @param int   $question_id Question ID.
     * @param mixed $user_answer User's answer.
     * @return array Grading result.
     */
    public static function grade_answer( int $question_id, $user_answer ): array {
        $question_data = Question::get_question_data( $question_id, true );
        if ( empty( $question_data ) ) {
            return array(
                'is_correct'    => false,
                'points_earned' => 0,
                'pending'       => false,
                'feedback'      => '',
            );
        }

        $points  = $question_data['points'];
        $type    = $question_data['type'];
        $correct = false;
        $partial = 0;
        $pending = false;

        switch ( $type ) {
            case 'multiple_choice':
            case 'true_false':
                $correct_answers = $question_data['correct'] ?? array();
                $correct         = in_array( (string) $user_answer, $correct_answers, true );
                break;

            case 'multiple_answer':
                $correct_answers = $question_data['correct'] ?? array();
                $user_answers    = is_array( $user_answer ) ? $user_answer : array();

                if ( ! empty( $correct_answers ) ) {
                    $correct_count = count( array_intersect( $user_answers, $correct_answers ) );
                    $wrong_count   = count( array_diff( $user_answers, $correct_answers ) );
                    $total_correct = count( $correct_answers );

                    // Partial credit: correct selections minus wrong selections.
                    $score  = max( 0, $correct_count - $wrong_count );
                    $partial = $score / $total_correct;
                    $correct = $partial >= 1;
                }
                break;

            case 'fill_blank':
                $blanks       = $question_data['blanks'] ?? array();
                $user_answers = is_array( $user_answer ) ? $user_answer : array( $user_answer );
                $matches      = 0;

                foreach ( $blanks as $i => $accepted ) {
                    $accepted_answers = array_map( 'trim', explode( ',', strtolower( $accepted ) ) );
                    $user_response    = strtolower( trim( $user_answers[ $i ] ?? '' ) );

                    if ( in_array( $user_response, $accepted_answers, true ) ) {
                        $matches++;
                    }
                }

                if ( count( $blanks ) > 0 ) {
                    $partial = $matches / count( $blanks );
                    $correct = $partial >= 1;
                }
                break;

            case 'matching':
                $pairs        = $question_data['pairs'] ?? array();
                $user_matches = is_array( $user_answer ) ? $user_answer : array();
                $matches      = 0;

                foreach ( $pairs as $i => $pair ) {
                    if ( isset( $user_matches[ $i ] ) && $user_matches[ $i ] === $pair['right'] ) {
                        $matches++;
                    }
                }

                if ( count( $pairs ) > 0 ) {
                    $partial = $matches / count( $pairs );
                    $correct = $partial >= 1;
                }
                break;

            case 'ordering':
                $correct_order = $question_data['correct_order'] ?? array();
                $user_order    = is_array( $user_answer ) ? $user_answer : array();

                if ( $correct_order === $user_order ) {
                    $correct = true;
                } else {
                    // Partial credit based on correct positions.
                    $matches = 0;
                    foreach ( $correct_order as $i => $item ) {
                        if ( isset( $user_order[ $i ] ) && $user_order[ $i ] === $item ) {
                            $matches++;
                        }
                    }
                    if ( count( $correct_order ) > 0 ) {
                        $partial = $matches / count( $correct_order );
                    }
                }
                break;

            case 'short_answer':
                $accepted     = $question_data['accepted'] ?? array();
                $user_text    = strtolower( trim( $user_answer ) );
                $accepted_lc  = array_map( 'strtolower', array_map( 'trim', $accepted ) );
                $correct      = in_array( $user_text, $accepted_lc, true );
                break;

            case 'essay':
                // Essays require manual grading.
                $pending = true;
                break;
        }

        // Calculate points.
        if ( $correct ) {
            $earned = $points;
        } elseif ( $partial > 0 ) {
            $earned = round( $points * $partial, 2 );
        } else {
            $earned = 0;
        }

        return array(
            'is_correct'    => $correct,
            'points_earned' => $earned,
            'partial'       => $partial,
            'pending'       => $pending,
            'feedback'      => $question_data['explanation'] ?? '',
        );
    }

    /**
     * Get an attempt by ID.
     *
     * @param int $attempt_id Attempt ID.
     * @return object|null
     */
    public static function get_attempt( int $attempt_id ): ?object {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::get_table_name() . " WHERE id = %d",
                $attempt_id
            )
        );
    }

    /**
     * Get attempt count for user/quiz.
     *
     * @param int $user_id User ID.
     * @param int $quiz_id Quiz ID.
     * @return int
     */
    public static function get_attempt_count( int $user_id, int $quiz_id ): int {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM " . self::get_table_name() . " WHERE user_id = %d AND quiz_id = %d",
                $user_id,
                $quiz_id
            )
        );
    }

    /**
     * Get user's attempts for a quiz.
     *
     * @param int $user_id User ID.
     * @param int $quiz_id Quiz ID.
     * @return array
     */
    public static function get_user_attempts( int $user_id, int $quiz_id ): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::get_table_name() . "
                WHERE user_id = %d AND quiz_id = %d
                ORDER BY attempt_number DESC",
                $user_id,
                $quiz_id
            )
        );
    }

    /**
     * Get best attempt for user/quiz.
     *
     * @param int $user_id User ID.
     * @param int $quiz_id Quiz ID.
     * @return object|null
     */
    public static function get_best_attempt( int $user_id, int $quiz_id ): ?object {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::get_table_name() . "
                WHERE user_id = %d AND quiz_id = %d AND status IN ('passed', 'failed', 'completed')
                ORDER BY percentage DESC, earned_points DESC
                LIMIT 1",
                $user_id,
                $quiz_id
            )
        );
    }

    /**
     * Get latest attempt for user/quiz.
     *
     * @param int $user_id User ID.
     * @param int $quiz_id Quiz ID.
     * @return object|null
     */
    public static function get_latest_attempt( int $user_id, int $quiz_id ): ?object {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::get_table_name() . "
                WHERE user_id = %d AND quiz_id = %d
                ORDER BY started_at DESC
                LIMIT 1",
                $user_id,
                $quiz_id
            )
        );
    }

    /**
     * Get answers for an attempt.
     *
     * @param int $attempt_id Attempt ID.
     * @return array
     */
    public static function get_attempt_answers( int $attempt_id ): array {
        global $wpdb;

        $answers_table = $wpdb->prefix . 'swiftlms_quiz_answers';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$answers_table} WHERE attempt_id = %d",
                $attempt_id
            )
        );
    }

    /**
     * Grade an essay answer manually.
     *
     * @param int    $answer_id     Answer row ID.
     * @param float  $points_earned Points to award.
     * @param string $feedback      Instructor feedback.
     * @param int    $grader_id     User ID of grader.
     * @return bool
     */
    public static function grade_essay( int $answer_id, float $points_earned, string $feedback, int $grader_id ): bool {
        global $wpdb;

        $answers_table = $wpdb->prefix . 'swiftlms_quiz_answers';

        $answer = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$answers_table} WHERE id = %d", $answer_id )
        );

        if ( ! $answer ) {
            return false;
        }

        $points_possible = (float) $answer->points_possible;
        $is_correct      = $points_earned >= $points_possible ? 1 : 0;

        $result = $wpdb->update(
            $answers_table,
            array(
                'points_earned' => min( $points_earned, $points_possible ),
                'is_correct'    => $is_correct,
                'feedback'      => $feedback,
                'graded_by'     => $grader_id,
                'graded_at'     => current_time( 'mysql' ),
            ),
            array( 'id' => $answer_id ),
            array( '%f', '%d', '%s', '%d', '%s' ),
            array( '%d' )
        );

        if ( $result ) {
            // Recalculate attempt totals.
            self::recalculate_attempt( $answer->attempt_id );
        }

        return (bool) $result;
    }

    /**
     * Recalculate attempt totals after manual grading.
     *
     * @param int $attempt_id Attempt ID.
     * @return void
     */
    public static function recalculate_attempt( int $attempt_id ): void {
        global $wpdb;

        $attempt = self::get_attempt( $attempt_id );
        if ( ! $attempt ) {
            return;
        }

        $answers_table = $wpdb->prefix . 'swiftlms_quiz_answers';

        // Check for any pending essays.
        $pending = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$answers_table} WHERE attempt_id = %d AND is_correct IS NULL",
                $attempt_id
            )
        );

        // Sum earned points.
        $earned = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(points_earned) FROM {$answers_table} WHERE attempt_id = %d",
                $attempt_id
            )
        );

        $percentage    = $attempt->total_points > 0 ? round( ( $earned / $attempt->total_points ) * 100, 2 ) : 0;
        $passing_score = (int) get_post_meta( $attempt->quiz_id, '_sfls_passing_score', true ) ?: 70;

        if ( $pending > 0 ) {
            $status = 'pending_review';
        } elseif ( $percentage >= $passing_score ) {
            $status = 'passed';
        } else {
            $status = 'failed';
        }

        $wpdb->update(
            self::get_table_name(),
            array(
                'earned_points' => $earned,
                'percentage'    => $percentage,
                'status'        => $status,
            ),
            array( 'id' => $attempt_id ),
            array( '%f', '%f', '%s' ),
            array( '%d' )
        );
    }

    /**
     * Get client IP address.
     *
     * @return string
     */
    private static function get_client_ip(): string {
        $ip_keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );

        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
                // Handle comma-separated IPs.
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return '';
    }
}
