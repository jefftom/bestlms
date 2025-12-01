<?php
/**
 * Assignments Module
 *
 * @package SwiftLMS
 */

namespace SwiftLMS\Modules\Assignments;

use SwiftLMS\Core\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Assignments Module class.
 */
class Assignments_Module extends AbstractModule {

    /**
     * Module ID.
     *
     * @var string
     */
    protected string $id = 'assignments';

    /**
     * Module name.
     *
     * @var string
     */
    protected string $name = 'Assignments';

    /**
     * Module description.
     *
     * @var string
     */
    protected string $description = 'Create assignments with file uploads, text submissions, and grading.';

    /**
     * Initialize module.
     */
    public function init(): void {
        // Initialize CPT.
        Assignment::init();

        // Register hooks.
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_action( 'admin_menu', array( $this, 'add_submissions_page' ), 30 );
        add_action( 'admin_post_sfls_grade_submission', array( $this, 'handle_grade_submission' ) );
        add_action( 'wp_ajax_sfls_submit_assignment', array( $this, 'ajax_submit_assignment' ) );
        add_action( 'wp_ajax_sfls_get_course_lessons', array( $this, 'ajax_get_course_lessons' ) );

        // Enqueue scripts.
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Template handling.
        add_filter( 'single_template', array( $this, 'assignment_template' ) );
    }

    /**
     * Activate module.
     */
    public function activate(): void {
        Submissions_Table::create_table();
        flush_rewrite_rules();
    }

    /**
     * Deactivate module.
     */
    public function deactivate(): void {
        flush_rewrite_rules();
    }

    /**
     * Register REST routes.
     */
    public function register_rest_routes(): void {
        register_rest_route(
            'swiftlms/v1',
            '/assignments',
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_assignments' ),
                    'permission_callback' => array( $this, 'check_read_permission' ),
                ),
            )
        );

        register_rest_route(
            'swiftlms/v1',
            '/assignments/(?P<id>\d+)',
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_assignment' ),
                    'permission_callback' => array( $this, 'check_read_permission' ),
                ),
            )
        );

        register_rest_route(
            'swiftlms/v1',
            '/assignments/(?P<id>\d+)/submit',
            array(
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'submit_assignment' ),
                    'permission_callback' => array( $this, 'check_submit_permission' ),
                ),
            )
        );

        register_rest_route(
            'swiftlms/v1',
            '/submissions/(?P<id>\d+)/grade',
            array(
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'grade_submission_api' ),
                    'permission_callback' => array( $this, 'check_grade_permission' ),
                ),
            )
        );

        register_rest_route(
            'swiftlms/v1',
            '/users/me/submissions',
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_user_submissions' ),
                    'permission_callback' => 'is_user_logged_in',
                ),
            )
        );
    }

    /**
     * Check read permission.
     *
     * @return bool
     */
    public function check_read_permission(): bool {
        return is_user_logged_in();
    }

    /**
     * Check submit permission.
     *
     * @param \WP_REST_Request $request Request object.
     * @return bool
     */
    public function check_submit_permission( \WP_REST_Request $request ): bool {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $assignment_id = (int) $request->get_param( 'id' );
        $course_id     = get_post_meta( $assignment_id, '_sfls_assignment_course_id', true );

        if ( ! $course_id ) {
            return true; // No course restriction
        }

        // Check if user is enrolled in the course
        return \SwiftLMS\Core\Enrollment::is_enrolled( get_current_user_id(), $course_id );
    }

    /**
     * Check grade permission.
     *
     * @return bool
     */
    public function check_grade_permission(): bool {
        return current_user_can( 'edit_posts' );
    }

    /**
     * Get assignments list.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_assignments( \WP_REST_Request $request ): \WP_REST_Response {
        $args = array(
            'post_type'      => Assignment::POST_TYPE,
            'posts_per_page' => $request->get_param( 'per_page' ) ?: 10,
            'paged'          => $request->get_param( 'page' ) ?: 1,
            'post_status'    => 'publish',
        );

        $course_id = $request->get_param( 'course_id' );
        if ( $course_id ) {
            $args['meta_query'] = array(
                array(
                    'key'   => '_sfls_assignment_course_id',
                    'value' => $course_id,
                ),
            );
        }

        $assignments = get_posts( $args );
        $data        = array();

        foreach ( $assignments as $assignment ) {
            $data[] = $this->prepare_assignment_data( $assignment );
        }

        return rest_ensure_response( $data );
    }

    /**
     * Get single assignment.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_assignment( \WP_REST_Request $request ) {
        $assignment_id = (int) $request->get_param( 'id' );
        $assignment    = get_post( $assignment_id );

        if ( ! $assignment || Assignment::POST_TYPE !== $assignment->post_type ) {
            return new \WP_Error( 'not_found', __( 'Assignment not found.', 'swiftlms' ), array( 'status' => 404 ) );
        }

        $data = $this->prepare_assignment_data( $assignment, true );

        // Add user submission data
        $user_id          = get_current_user_id();
        $data['my_submissions'] = array_map(
            array( $this, 'prepare_submission_data' ),
            Submissions_Table::get_user_submissions( $assignment_id, $user_id )
        );

        return rest_ensure_response( $data );
    }

    /**
     * Submit assignment.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function submit_assignment( \WP_REST_Request $request ) {
        $assignment_id = (int) $request->get_param( 'id' );
        $user_id       = get_current_user_id();
        $settings      = Assignment::get_settings( $assignment_id );

        // Check attempt limit
        $attempt_count = Submissions_Table::get_attempt_count( $assignment_id, $user_id );
        if ( $attempt_count >= $settings['max_attempts'] ) {
            return new \WP_Error(
                'max_attempts',
                __( 'You have reached the maximum number of attempts for this assignment.', 'swiftlms' ),
                array( 'status' => 403 )
            );
        }

        // Check due date
        $is_late = false;
        if ( $settings['due_date'] ) {
            $due_timestamp = strtotime( $settings['due_date'] );
            if ( time() > $due_timestamp ) {
                if ( ! $settings['late_allowed'] ) {
                    return new \WP_Error(
                        'past_due',
                        __( 'This assignment is past due and no longer accepting submissions.', 'swiftlms' ),
                        array( 'status' => 403 )
                    );
                }
                $is_late = true;
            }
        }

        $submission_data = array(
            'assignment_id'   => $assignment_id,
            'user_id'         => $user_id,
            'attempt_number'  => $attempt_count + 1,
            'submission_type' => $settings['type'],
            'is_late'         => $is_late ? 1 : 0,
        );

        // Handle different submission types
        $type = $settings['type'];

        if ( in_array( $type, array( 'file_upload', 'mixed' ), true ) ) {
            $files = $request->get_file_params();
            if ( ! empty( $files['file'] ) ) {
                $file_result = $this->handle_file_upload( $files['file'], $settings );
                if ( is_wp_error( $file_result ) ) {
                    return $file_result;
                }
                $submission_data['file_url']  = $file_result['url'];
                $submission_data['file_name'] = $file_result['name'];
                $submission_data['file_size'] = $file_result['size'];
            }
        }

        if ( in_array( $type, array( 'text_entry', 'mixed' ), true ) ) {
            $text_content = $request->get_param( 'text_content' );
            if ( $text_content ) {
                // Validate word count
                $word_count = str_word_count( wp_strip_all_tags( $text_content ) );
                if ( $settings['min_words'] && $word_count < $settings['min_words'] ) {
                    return new \WP_Error(
                        'min_words',
                        sprintf( __( 'Submission must have at least %d words.', 'swiftlms' ), $settings['min_words'] ),
                        array( 'status' => 400 )
                    );
                }
                if ( $settings['max_words'] && $word_count > $settings['max_words'] ) {
                    return new \WP_Error(
                        'max_words',
                        sprintf( __( 'Submission cannot exceed %d words.', 'swiftlms' ), $settings['max_words'] ),
                        array( 'status' => 400 )
                    );
                }
                $submission_data['text_content'] = wp_kses_post( $text_content );
            }
        }

        if ( in_array( $type, array( 'url_submission', 'mixed' ), true ) ) {
            $url = $request->get_param( 'url' );
            if ( $url ) {
                if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
                    return new \WP_Error(
                        'invalid_url',
                        __( 'Please provide a valid URL.', 'swiftlms' ),
                        array( 'status' => 400 )
                    );
                }
                $submission_data['url_submission'] = esc_url_raw( $url );
            }
        }

        // Insert submission
        $submission_id = Submissions_Table::insert( $submission_data );

        if ( ! $submission_id ) {
            return new \WP_Error(
                'submission_failed',
                __( 'Failed to save submission. Please try again.', 'swiftlms' ),
                array( 'status' => 500 )
            );
        }

        // Trigger action
        do_action( 'swiftlms_assignment_submitted', $submission_id, $assignment_id, $user_id );

        $submission = Submissions_Table::get( $submission_id );

        return rest_ensure_response(
            array(
                'success'    => true,
                'message'    => __( 'Assignment submitted successfully.', 'swiftlms' ),
                'submission' => $this->prepare_submission_data( $submission ),
            )
        );
    }

    /**
     * Handle file upload.
     *
     * @param array $file     File data.
     * @param array $settings Assignment settings.
     * @return array|\WP_Error
     */
    private function handle_file_upload( array $file, array $settings ) {
        // Validate file type
        $allowed_types = array_map( 'trim', explode( ',', $settings['allowed_files'] ) );
        $extension     = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

        if ( ! in_array( $extension, $allowed_types, true ) ) {
            return new \WP_Error(
                'invalid_file_type',
                sprintf( __( 'Invalid file type. Allowed types: %s', 'swiftlms' ), $settings['allowed_files'] ),
                array( 'status' => 400 )
            );
        }

        // Validate file size
        $max_size = $settings['max_file_size'] * 1024 * 1024; // Convert to bytes
        if ( $file['size'] > $max_size ) {
            return new \WP_Error(
                'file_too_large',
                sprintf( __( 'File size exceeds the maximum limit of %dMB.', 'swiftlms' ), $settings['max_file_size'] ),
                array( 'status' => 400 )
            );
        }

        // Upload file
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Create uploads subdirectory
        $upload_dir = wp_upload_dir();
        $subdir     = '/swiftlms-submissions/' . date( 'Y/m' );
        $target_dir = $upload_dir['basedir'] . $subdir;

        if ( ! file_exists( $target_dir ) ) {
            wp_mkdir_p( $target_dir );
        }

        // Generate unique filename
        $filename   = wp_unique_filename( $target_dir, $file['name'] );
        $filepath   = $target_dir . '/' . $filename;

        if ( ! move_uploaded_file( $file['tmp_name'], $filepath ) ) {
            return new \WP_Error(
                'upload_failed',
                __( 'Failed to upload file. Please try again.', 'swiftlms' ),
                array( 'status' => 500 )
            );
        }

        return array(
            'url'  => $upload_dir['baseurl'] . $subdir . '/' . $filename,
            'name' => $file['name'],
            'size' => $file['size'],
        );
    }

    /**
     * Grade submission via API.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function grade_submission_api( \WP_REST_Request $request ) {
        $submission_id = (int) $request->get_param( 'id' );
        $submission    = Submissions_Table::get( $submission_id );

        if ( ! $submission ) {
            return new \WP_Error( 'not_found', __( 'Submission not found.', 'swiftlms' ), array( 'status' => 404 ) );
        }

        $grade       = (float) $request->get_param( 'grade' );
        $feedback    = sanitize_textarea_field( $request->get_param( 'feedback' ) );
        $settings    = Assignment::get_settings( $submission->assignment_id );
        $total_points = $settings['points'];

        // Apply late penalty if applicable
        if ( $submission->is_late && $settings['late_penalty'] > 0 ) {
            $penalty = ( $settings['late_penalty'] / 100 ) * $grade;
            $grade   = max( 0, $grade - $penalty );
        }

        $result = Submissions_Table::grade_submission(
            $submission_id,
            $grade,
            $total_points,
            get_current_user_id(),
            $feedback
        );

        if ( ! $result ) {
            return new \WP_Error(
                'grade_failed',
                __( 'Failed to save grade. Please try again.', 'swiftlms' ),
                array( 'status' => 500 )
            );
        }

        // Trigger action
        do_action( 'swiftlms_assignment_graded', $submission_id, $grade, $feedback );

        $updated_submission = Submissions_Table::get( $submission_id );

        return rest_ensure_response(
            array(
                'success'    => true,
                'message'    => __( 'Grade saved successfully.', 'swiftlms' ),
                'submission' => $this->prepare_submission_data( $updated_submission ),
            )
        );
    }

    /**
     * Get user submissions.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_user_submissions( \WP_REST_Request $request ): \WP_REST_Response {
        $user_id = get_current_user_id();
        $grades  = Submissions_Table::get_user_grades( $user_id );

        return rest_ensure_response(
            array_map( array( $this, 'prepare_submission_data' ), $grades )
        );
    }

    /**
     * Prepare assignment data.
     *
     * @param \WP_Post $assignment Assignment post.
     * @param bool     $full       Include full content.
     * @return array
     */
    private function prepare_assignment_data( \WP_Post $assignment, bool $full = false ): array {
        $settings = Assignment::get_settings( $assignment->ID );

        $data = array(
            'id'          => $assignment->ID,
            'title'       => $assignment->post_title,
            'url'         => get_permalink( $assignment ),
            'type'        => $settings['type'],
            'points'      => $settings['points'],
            'due_date'    => $settings['due_date'],
            'course_id'   => $settings['course_id'],
            'lesson_id'   => $settings['lesson_id'],
        );

        if ( $full ) {
            $data['content']      = apply_filters( 'the_content', $assignment->post_content );
            $data['instructions'] = $settings['instructions'];
            $data['settings']     = $settings;
        }

        return $data;
    }

    /**
     * Prepare submission data.
     *
     * @param object $submission Submission object.
     * @return array
     */
    private function prepare_submission_data( object $submission ): array {
        return array(
            'id'               => (int) $submission->id,
            'assignment_id'    => (int) $submission->assignment_id,
            'user_id'          => (int) $submission->user_id,
            'attempt_number'   => (int) $submission->attempt_number,
            'submission_type'  => $submission->submission_type,
            'file_url'         => $submission->file_url,
            'file_name'        => $submission->file_name,
            'text_content'     => $submission->text_content,
            'url_submission'   => $submission->url_submission,
            'status'           => $submission->status,
            'is_late'          => (bool) $submission->is_late,
            'grade'            => $submission->grade ? (float) $submission->grade : null,
            'grade_percentage' => $submission->grade_percentage ? (float) $submission->grade_percentage : null,
            'feedback'         => $submission->feedback,
            'submitted_at'     => $submission->submitted_at,
            'graded_at'        => $submission->graded_at,
        );
    }

    /**
     * Add submissions admin page.
     */
    public function add_submissions_page(): void {
        add_submenu_page(
            'swiftlms',
            __( 'Submissions', 'swiftlms' ),
            __( 'Submissions', 'swiftlms' ),
            'edit_posts',
            'sfls-submissions',
            array( $this, 'render_submissions_page' )
        );
    }

    /**
     * Render submissions admin page.
     */
    public function render_submissions_page(): void {
        $assignment_id = isset( $_GET['assignment_id'] ) ? absint( $_GET['assignment_id'] ) : 0;
        $status        = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';

        // Get assignments for filter
        $assignments = get_posts(
            array(
                'post_type'      => Assignment::POST_TYPE,
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );

        // Get submissions if assignment selected
        $submissions = array();
        $counts      = array();
        if ( $assignment_id ) {
            $submissions = Submissions_Table::get_assignment_submissions( $assignment_id, $status );
            $counts      = Submissions_Table::get_submission_counts( $assignment_id );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Assignment Submissions', 'swiftlms' ); ?></h1>

            <form method="get">
                <input type="hidden" name="page" value="sfls-submissions">
                <div class="sfls-submissions-filters">
                    <select name="assignment_id" id="assignment_filter">
                        <option value=""><?php esc_html_e( '— Select Assignment —', 'swiftlms' ); ?></option>
                        <?php foreach ( $assignments as $assignment ) : ?>
                            <option value="<?php echo esc_attr( $assignment->ID ); ?>" <?php selected( $assignment_id, $assignment->ID ); ?>>
                                <?php echo esc_html( $assignment->post_title ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if ( $assignment_id ) : ?>
                        <select name="status" id="status_filter">
                            <option value=""><?php esc_html_e( 'All Statuses', 'swiftlms' ); ?></option>
                            <option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'swiftlms' ); ?></option>
                            <option value="graded" <?php selected( $status, 'graded' ); ?>><?php esc_html_e( 'Graded', 'swiftlms' ); ?></option>
                        </select>
                    <?php endif; ?>

                    <button type="submit" class="button"><?php esc_html_e( 'Filter', 'swiftlms' ); ?></button>
                </div>
            </form>

            <?php if ( $assignment_id && ! empty( $counts ) ) : ?>
                <div class="sfls-submission-stats">
                    <span class="sfls-stat-badge"><?php printf( esc_html__( 'Total: %d', 'swiftlms' ), $counts['total'] ); ?></span>
                    <span class="sfls-stat-badge sfls-pending"><?php printf( esc_html__( 'Pending: %d', 'swiftlms' ), $counts['pending'] ); ?></span>
                    <span class="sfls-stat-badge sfls-graded"><?php printf( esc_html__( 'Graded: %d', 'swiftlms' ), $counts['graded'] ); ?></span>
                </div>
            <?php endif; ?>

            <?php if ( $assignment_id && empty( $submissions ) ) : ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e( 'No submissions found for this assignment.', 'swiftlms' ); ?></p>
                </div>
            <?php elseif ( ! empty( $submissions ) ) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Student', 'swiftlms' ); ?></th>
                            <th><?php esc_html_e( 'Submitted', 'swiftlms' ); ?></th>
                            <th><?php esc_html_e( 'Attempt', 'swiftlms' ); ?></th>
                            <th><?php esc_html_e( 'Type', 'swiftlms' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'swiftlms' ); ?></th>
                            <th><?php esc_html_e( 'Grade', 'swiftlms' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'swiftlms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $submissions as $submission ) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $submission->display_name ); ?></strong><br>
                                    <small><?php echo esc_html( $submission->user_email ); ?></small>
                                </td>
                                <td>
                                    <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $submission->submitted_at ) ) ); ?>
                                    <?php if ( $submission->is_late ) : ?>
                                        <br><span class="sfls-late-badge"><?php esc_html_e( 'Late', 'swiftlms' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>#<?php echo esc_html( $submission->attempt_number ); ?></td>
                                <td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $submission->submission_type ) ) ); ?></td>
                                <td>
                                    <span class="sfls-status-badge sfls-status-<?php echo esc_attr( $submission->status ); ?>">
                                        <?php echo esc_html( ucfirst( $submission->status ) ); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ( $submission->grade !== null ) : ?>
                                        <?php echo esc_html( number_format( $submission->grade, 1 ) ); ?> /
                                        <?php echo esc_html( Assignment::get_settings( $submission->assignment_id )['points'] ); ?>
                                        <br><small>(<?php echo esc_html( number_format( $submission->grade_percentage, 1 ) ); ?>%)</small>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="#" class="sfls-view-submission" data-id="<?php echo esc_attr( $submission->id ); ?>">
                                        <?php esc_html_e( 'View', 'swiftlms' ); ?>
                                    </a>
                                    <?php if ( $submission->status === 'pending' ) : ?>
                                        | <a href="#" class="sfls-grade-submission" data-id="<?php echo esc_attr( $submission->id ); ?>">
                                            <?php esc_html_e( 'Grade', 'swiftlms' ); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <style>
            .sfls-submissions-filters { margin: 20px 0; display: flex; gap: 10px; align-items: center; }
            .sfls-submission-stats { margin: 20px 0; display: flex; gap: 15px; }
            .sfls-stat-badge { padding: 5px 12px; background: #f0f0f0; border-radius: 4px; font-size: 13px; }
            .sfls-stat-badge.sfls-pending { background: #fff3cd; color: #856404; }
            .sfls-stat-badge.sfls-graded { background: #d4edda; color: #155724; }
            .sfls-status-badge { padding: 3px 8px; border-radius: 3px; font-size: 12px; }
            .sfls-status-pending { background: #fff3cd; color: #856404; }
            .sfls-status-graded { background: #d4edda; color: #155724; }
            .sfls-late-badge { background: #f8d7da; color: #721c24; padding: 2px 6px; border-radius: 3px; font-size: 11px; }
        </style>
        <?php
    }

    /**
     * Handle grade submission form.
     */
    public function handle_grade_submission(): void {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( __( 'Permission denied.', 'swiftlms' ) );
        }

        check_admin_referer( 'sfls_grade_submission' );

        $submission_id = absint( $_POST['submission_id'] );
        $grade         = (float) $_POST['grade'];
        $feedback      = sanitize_textarea_field( $_POST['feedback'] );

        $submission = Submissions_Table::get( $submission_id );
        if ( ! $submission ) {
            wp_die( __( 'Submission not found.', 'swiftlms' ) );
        }

        $settings     = Assignment::get_settings( $submission->assignment_id );
        $total_points = $settings['points'];

        // Apply late penalty
        if ( $submission->is_late && $settings['late_penalty'] > 0 ) {
            $penalty = ( $settings['late_penalty'] / 100 ) * $grade;
            $grade   = max( 0, $grade - $penalty );
        }

        Submissions_Table::grade_submission(
            $submission_id,
            $grade,
            $total_points,
            get_current_user_id(),
            $feedback
        );

        do_action( 'swiftlms_assignment_graded', $submission_id, $grade, $feedback );

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'          => 'sfls-submissions',
                    'assignment_id' => $submission->assignment_id,
                    'graded'        => '1',
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    /**
     * AJAX submit assignment.
     */
    public function ajax_submit_assignment(): void {
        check_ajax_referer( 'sfls_ajax', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Please log in to submit assignments.', 'swiftlms' ) ) );
        }

        $assignment_id = absint( $_POST['assignment_id'] );
        $user_id       = get_current_user_id();
        $settings      = Assignment::get_settings( $assignment_id );

        // Check attempt limit
        $attempt_count = Submissions_Table::get_attempt_count( $assignment_id, $user_id );
        if ( $attempt_count >= $settings['max_attempts'] ) {
            wp_send_json_error( array( 'message' => __( 'Maximum attempts reached.', 'swiftlms' ) ) );
        }

        // Check due date
        $is_late = false;
        if ( $settings['due_date'] && time() > strtotime( $settings['due_date'] ) ) {
            if ( ! $settings['late_allowed'] ) {
                wp_send_json_error( array( 'message' => __( 'Assignment is past due.', 'swiftlms' ) ) );
            }
            $is_late = true;
        }

        $submission_data = array(
            'assignment_id'   => $assignment_id,
            'user_id'         => $user_id,
            'attempt_number'  => $attempt_count + 1,
            'submission_type' => $settings['type'],
            'is_late'         => $is_late ? 1 : 0,
        );

        // Handle file upload
        if ( in_array( $settings['type'], array( 'file_upload', 'mixed' ), true ) && ! empty( $_FILES['file'] ) ) {
            $file_result = $this->handle_file_upload( $_FILES['file'], $settings );
            if ( is_wp_error( $file_result ) ) {
                wp_send_json_error( array( 'message' => $file_result->get_error_message() ) );
            }
            $submission_data['file_url']  = $file_result['url'];
            $submission_data['file_name'] = $file_result['name'];
            $submission_data['file_size'] = $file_result['size'];
        }

        // Handle text content
        if ( in_array( $settings['type'], array( 'text_entry', 'mixed' ), true ) && ! empty( $_POST['text_content'] ) ) {
            $text_content = wp_kses_post( $_POST['text_content'] );
            $word_count   = str_word_count( wp_strip_all_tags( $text_content ) );

            if ( $settings['min_words'] && $word_count < $settings['min_words'] ) {
                wp_send_json_error( array( 'message' => sprintf( __( 'Minimum %d words required.', 'swiftlms' ), $settings['min_words'] ) ) );
            }

            $submission_data['text_content'] = $text_content;
        }

        // Handle URL
        if ( in_array( $settings['type'], array( 'url_submission', 'mixed' ), true ) && ! empty( $_POST['url'] ) ) {
            $url = esc_url_raw( $_POST['url'] );
            if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
                wp_send_json_error( array( 'message' => __( 'Invalid URL.', 'swiftlms' ) ) );
            }
            $submission_data['url_submission'] = $url;
        }

        $submission_id = Submissions_Table::insert( $submission_data );

        if ( ! $submission_id ) {
            wp_send_json_error( array( 'message' => __( 'Failed to save submission.', 'swiftlms' ) ) );
        }

        do_action( 'swiftlms_assignment_submitted', $submission_id, $assignment_id, $user_id );

        wp_send_json_success(
            array(
                'message' => __( 'Assignment submitted successfully!', 'swiftlms' ),
                'submission_id' => $submission_id,
            )
        );
    }

    /**
     * AJAX get course lessons.
     */
    public function ajax_get_course_lessons(): void {
        check_ajax_referer( 'sfls_ajax', 'nonce' );

        $course_id = absint( $_GET['course_id'] );

        $lessons = get_posts(
            array(
                'post_type'      => 'sfls_lesson',
                'posts_per_page' => -1,
                'orderby'        => 'menu_order',
                'order'          => 'ASC',
                'meta_query'     => array(
                    array(
                        'key'   => '_sfls_course_id',
                        'value' => $course_id,
                    ),
                ),
            )
        );

        $data = array();
        foreach ( $lessons as $lesson ) {
            $data[] = array(
                'id'    => $lesson->ID,
                'title' => $lesson->post_title,
            );
        }

        wp_send_json_success( $data );
    }

    /**
     * Enqueue frontend assets.
     */
    public function enqueue_frontend_assets(): void {
        if ( ! is_singular( Assignment::POST_TYPE ) ) {
            return;
        }

        wp_enqueue_style(
            'sfls-assignment',
            SWIFTLMS_PLUGIN_URL . 'includes/modules/assignments/assets/css/assignment.css',
            array(),
            SWIFTLMS_VERSION
        );

        wp_enqueue_script(
            'sfls-assignment',
            SWIFTLMS_PLUGIN_URL . 'includes/modules/assignments/assets/js/assignment.js',
            array( 'jquery' ),
            SWIFTLMS_VERSION,
            true
        );

        wp_localize_script(
            'sfls-assignment',
            'swiftlms_assignment',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'sfls_ajax' ),
                'i18n'     => array(
                    'submitting'        => __( 'Submitting...', 'swiftlms' ),
                    'submit'            => __( 'Submit Assignment', 'swiftlms' ),
                    'confirm_submit'    => __( 'Are you sure you want to submit this assignment?', 'swiftlms' ),
                    'file_required'     => __( 'Please select a file to upload.', 'swiftlms' ),
                    'text_required'     => __( 'Please enter your submission text.', 'swiftlms' ),
                    'url_required'      => __( 'Please enter a URL.', 'swiftlms' ),
                ),
            )
        );
    }

    /**
     * Enqueue admin assets.
     */
    public function enqueue_admin_assets( string $hook ): void {
        if ( 'swiftlms_page_sfls-submissions' !== $hook ) {
            return;
        }

        wp_enqueue_script(
            'sfls-submissions-admin',
            SWIFTLMS_PLUGIN_URL . 'includes/modules/assignments/assets/js/admin-submissions.js',
            array( 'jquery', 'wp-util' ),
            SWIFTLMS_VERSION,
            true
        );
    }

    /**
     * Load assignment template.
     *
     * @param string $template Template path.
     * @return string
     */
    public function assignment_template( string $template ): string {
        if ( is_singular( Assignment::POST_TYPE ) ) {
            $custom_template = SWIFTLMS_PLUGIN_DIR . 'templates/assignment/single-assignment.php';
            if ( file_exists( $custom_template ) ) {
                return $custom_template;
            }
        }
        return $template;
    }
}
