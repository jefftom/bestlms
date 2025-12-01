<?php
/**
 * Drip Content Module
 *
 * Handles prerequisite enforcement and scheduled content release.
 *
 * @package SwiftLMS\Modules\DripContent
 * @since 1.0.0
 */

namespace SwiftLMS\Modules\DripContent;

use SwiftLMS\Abstracts\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Drip Content Module class.
 */
class DripContentModule extends AbstractModule {

    /**
     * Drip types.
     *
     * @var array
     */
    const DRIP_TYPES = array(
        'none'             => 'No Drip (Always Available)',
        'enrollment'       => 'Days After Enrollment',
        'previous_lesson'  => 'Days After Previous Lesson',
        'specific_date'    => 'Specific Date',
        'prerequisite'     => 'After Completing Prerequisite',
    );

    /**
     * Get module ID.
     *
     * @return string
     */
    public function get_id(): string {
        return 'drip-content';
    }

    /**
     * Get module name.
     *
     * @return string
     */
    public function get_name(): string {
        return __( 'Drip Content', 'swiftlms' );
    }

    /**
     * Get module description.
     *
     * @return string
     */
    public function get_description(): string {
        return __( 'Control when content becomes available with prerequisites and scheduled releases.', 'swiftlms' );
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
        // Add drip settings to lessons.
        add_action( 'add_meta_boxes', array( $this, 'add_drip_meta_box' ) );
        add_action( 'save_post_sfls_lesson', array( $this, 'save_drip_settings' ) );

        // Content access control.
        add_filter( 'swiftlms_can_access_lesson', array( $this, 'check_lesson_access' ), 10, 3 );
        add_filter( 'swiftlms_lesson_locked_message', array( $this, 'get_locked_message' ), 10, 3 );

        // Course curriculum display.
        add_filter( 'swiftlms_curriculum_lesson_status', array( $this, 'get_lesson_drip_status' ), 10, 3 );

        // Admin scripts.
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

        // Cron for scheduled releases.
        add_action( 'swiftlms_check_drip_releases', array( $this, 'process_scheduled_releases' ) );

        // REST API.
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    /**
     * Activate module.
     *
     * @return void
     */
    public function activate(): void {
        // Schedule cron job.
        if ( ! wp_next_scheduled( 'swiftlms_check_drip_releases' ) ) {
            wp_schedule_event( time(), 'hourly', 'swiftlms_check_drip_releases' );
        }
    }

    /**
     * Deactivate module.
     *
     * @return void
     */
    public function deactivate(): void {
        wp_clear_scheduled_hook( 'swiftlms_check_drip_releases' );
    }

    /**
     * Add drip settings meta box to lessons.
     *
     * @return void
     */
    public function add_drip_meta_box(): void {
        add_meta_box(
            'sfls_drip_settings',
            __( 'Drip Content Settings', 'swiftlms' ),
            array( $this, 'render_drip_meta_box' ),
            'sfls_lesson',
            'side',
            'default'
        );
    }

    /**
     * Render drip settings meta box.
     *
     * @param \WP_Post $post Current post.
     * @return void
     */
    public function render_drip_meta_box( $post ): void {
        wp_nonce_field( 'sfls_drip_settings', 'sfls_drip_nonce' );

        $drip_type       = get_post_meta( $post->ID, '_sfls_drip_type', true ) ?: 'none';
        $drip_days       = get_post_meta( $post->ID, '_sfls_drip_days', true ) ?: 0;
        $drip_date       = get_post_meta( $post->ID, '_sfls_drip_date', true );
        $prerequisite_id = get_post_meta( $post->ID, '_sfls_prerequisite_lesson', true );
        $course_id       = get_post_meta( $post->ID, '_sfls_course_id', true );

        // Get other lessons in the course for prerequisite selection.
        $lessons = array();
        if ( $course_id ) {
            $lessons = get_posts( array(
                'post_type'      => 'sfls_lesson',
                'posts_per_page' => -1,
                'meta_key'       => '_sfls_course_id',
                'meta_value'     => $course_id,
                'post__not_in'   => array( $post->ID ),
                'orderby'        => 'menu_order',
                'order'          => 'ASC',
            ) );
        }
        ?>
        <p>
            <label for="sfls_drip_type"><strong><?php esc_html_e( 'Release Type', 'swiftlms' ); ?></strong></label>
            <select name="sfls_drip_type" id="sfls_drip_type" class="widefat">
                <?php foreach ( self::DRIP_TYPES as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $drip_type, $value ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <div id="sfls-drip-days-wrap" class="sfls-drip-option" style="<?php echo in_array( $drip_type, array( 'enrollment', 'previous_lesson' ), true ) ? '' : 'display:none;'; ?>">
            <p>
                <label for="sfls_drip_days"><strong><?php esc_html_e( 'Days to Wait', 'swiftlms' ); ?></strong></label>
                <input type="number" name="sfls_drip_days" id="sfls_drip_days" value="<?php echo esc_attr( $drip_days ); ?>" min="0" class="widefat">
            </p>
        </div>

        <div id="sfls-drip-date-wrap" class="sfls-drip-option" style="<?php echo 'specific_date' === $drip_type ? '' : 'display:none;'; ?>">
            <p>
                <label for="sfls_drip_date"><strong><?php esc_html_e( 'Release Date', 'swiftlms' ); ?></strong></label>
                <input type="datetime-local" name="sfls_drip_date" id="sfls_drip_date" value="<?php echo esc_attr( $drip_date ); ?>" class="widefat">
            </p>
        </div>

        <div id="sfls-prerequisite-wrap" class="sfls-drip-option" style="<?php echo 'prerequisite' === $drip_type ? '' : 'display:none;'; ?>">
            <p>
                <label for="sfls_prerequisite_lesson"><strong><?php esc_html_e( 'Prerequisite Lesson', 'swiftlms' ); ?></strong></label>
                <select name="sfls_prerequisite_lesson" id="sfls_prerequisite_lesson" class="widefat">
                    <option value=""><?php esc_html_e( '— Select Lesson —', 'swiftlms' ); ?></option>
                    <?php foreach ( $lessons as $lesson ) : ?>
                        <option value="<?php echo esc_attr( $lesson->ID ); ?>" <?php selected( $prerequisite_id, $lesson->ID ); ?>>
                            <?php echo esc_html( $lesson->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="description"><?php esc_html_e( 'Student must complete this lesson first.', 'swiftlms' ); ?></span>
            </p>
        </div>

        <script>
        jQuery(function($) {
            $('#sfls_drip_type').on('change', function() {
                var type = $(this).val();
                $('.sfls-drip-option').hide();

                if (type === 'enrollment' || type === 'previous_lesson') {
                    $('#sfls-drip-days-wrap').show();
                } else if (type === 'specific_date') {
                    $('#sfls-drip-date-wrap').show();
                } else if (type === 'prerequisite') {
                    $('#sfls-prerequisite-wrap').show();
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Save drip settings.
     *
     * @param int $post_id Post ID.
     * @return void
     */
    public function save_drip_settings( int $post_id ): void {
        if ( ! isset( $_POST['sfls_drip_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sfls_drip_nonce'] ) ), 'sfls_drip_settings' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( isset( $_POST['sfls_drip_type'] ) ) {
            update_post_meta( $post_id, '_sfls_drip_type', sanitize_text_field( wp_unslash( $_POST['sfls_drip_type'] ) ) );
        }

        if ( isset( $_POST['sfls_drip_days'] ) ) {
            update_post_meta( $post_id, '_sfls_drip_days', absint( $_POST['sfls_drip_days'] ) );
        }

        if ( isset( $_POST['sfls_drip_date'] ) ) {
            update_post_meta( $post_id, '_sfls_drip_date', sanitize_text_field( wp_unslash( $_POST['sfls_drip_date'] ) ) );
        }

        if ( isset( $_POST['sfls_prerequisite_lesson'] ) ) {
            update_post_meta( $post_id, '_sfls_prerequisite_lesson', absint( $_POST['sfls_prerequisite_lesson'] ) );
        }
    }

    /**
     * Check if user can access lesson based on drip settings.
     *
     * @param bool $can_access Current access status.
     * @param int  $user_id    User ID.
     * @param int  $lesson_id  Lesson ID.
     * @return bool
     */
    public function check_lesson_access( bool $can_access, int $user_id, int $lesson_id ): bool {
        // If already denied, don't override.
        if ( ! $can_access ) {
            return false;
        }

        // Admins bypass drip.
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        $drip_type = get_post_meta( $lesson_id, '_sfls_drip_type', true );

        if ( ! $drip_type || 'none' === $drip_type ) {
            return true;
        }

        $course_id = get_post_meta( $lesson_id, '_sfls_course_id', true );

        switch ( $drip_type ) {
            case 'enrollment':
                return $this->check_enrollment_drip( $user_id, $lesson_id, $course_id );

            case 'previous_lesson':
                return $this->check_previous_lesson_drip( $user_id, $lesson_id, $course_id );

            case 'specific_date':
                return $this->check_date_drip( $lesson_id );

            case 'prerequisite':
                return $this->check_prerequisite_drip( $user_id, $lesson_id );
        }

        return true;
    }

    /**
     * Check enrollment-based drip.
     *
     * @param int $user_id   User ID.
     * @param int $lesson_id Lesson ID.
     * @param int $course_id Course ID.
     * @return bool
     */
    private function check_enrollment_drip( int $user_id, int $lesson_id, int $course_id ): bool {
        $enrollment = \SwiftLMS\Core\Enrollment::get( $user_id, $course_id );

        if ( ! $enrollment ) {
            return false;
        }

        $drip_days       = (int) get_post_meta( $lesson_id, '_sfls_drip_days', true );
        $enrollment_date = strtotime( $enrollment->enrolled_at );
        $release_date    = strtotime( "+{$drip_days} days", $enrollment_date );

        return time() >= $release_date;
    }

    /**
     * Check previous lesson completion drip.
     *
     * @param int $user_id   User ID.
     * @param int $lesson_id Lesson ID.
     * @param int $course_id Course ID.
     * @return bool
     */
    private function check_previous_lesson_drip( int $user_id, int $lesson_id, int $course_id ): bool {
        // Get lesson order.
        $lessons = get_posts( array(
            'post_type'      => 'sfls_lesson',
            'posts_per_page' => -1,
            'meta_key'       => '_sfls_course_id',
            'meta_value'     => $course_id,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ) );

        $current_index = array_search( $lesson_id, $lessons, true );

        if ( false === $current_index || 0 === $current_index ) {
            return true; // First lesson or not found.
        }

        $previous_lesson_id = $lessons[ $current_index - 1 ];

        // Check if previous lesson is completed.
        $progress = \SwiftLMS\Core\Progress::get_lesson_progress( $user_id, $course_id, $previous_lesson_id );

        if ( ! $progress || 'completed' !== $progress->status ) {
            return false;
        }

        // Check drip days.
        $drip_days       = (int) get_post_meta( $lesson_id, '_sfls_drip_days', true );
        $completed_date  = strtotime( $progress->completed_at );
        $release_date    = strtotime( "+{$drip_days} days", $completed_date );

        return time() >= $release_date;
    }

    /**
     * Check specific date drip.
     *
     * @param int $lesson_id Lesson ID.
     * @return bool
     */
    private function check_date_drip( int $lesson_id ): bool {
        $drip_date = get_post_meta( $lesson_id, '_sfls_drip_date', true );

        if ( ! $drip_date ) {
            return true;
        }

        return time() >= strtotime( $drip_date );
    }

    /**
     * Check prerequisite completion drip.
     *
     * @param int $user_id   User ID.
     * @param int $lesson_id Lesson ID.
     * @return bool
     */
    private function check_prerequisite_drip( int $user_id, int $lesson_id ): bool {
        $prerequisite_id = get_post_meta( $lesson_id, '_sfls_prerequisite_lesson', true );

        if ( ! $prerequisite_id ) {
            return true;
        }

        $course_id = get_post_meta( $lesson_id, '_sfls_course_id', true );
        $progress  = \SwiftLMS\Core\Progress::get_lesson_progress( $user_id, $course_id, $prerequisite_id );

        return $progress && 'completed' === $progress->status;
    }

    /**
     * Get locked message for lesson.
     *
     * @param string $message   Default message.
     * @param int    $user_id   User ID.
     * @param int    $lesson_id Lesson ID.
     * @return string
     */
    public function get_locked_message( string $message, int $user_id, int $lesson_id ): string {
        $drip_type = get_post_meta( $lesson_id, '_sfls_drip_type', true );
        $course_id = get_post_meta( $lesson_id, '_sfls_course_id', true );

        switch ( $drip_type ) {
            case 'enrollment':
                $enrollment = \SwiftLMS\Core\Enrollment::get( $user_id, $course_id );
                if ( $enrollment ) {
                    $drip_days    = (int) get_post_meta( $lesson_id, '_sfls_drip_days', true );
                    $release_date = strtotime( "+{$drip_days} days", strtotime( $enrollment->enrolled_at ) );
                    return sprintf(
                        /* translators: %s: date */
                        __( 'This lesson will be available on %s.', 'swiftlms' ),
                        wp_date( get_option( 'date_format' ), $release_date )
                    );
                }
                break;

            case 'previous_lesson':
                return __( 'Complete the previous lesson to unlock this content.', 'swiftlms' );

            case 'specific_date':
                $drip_date = get_post_meta( $lesson_id, '_sfls_drip_date', true );
                if ( $drip_date ) {
                    return sprintf(
                        /* translators: %s: date */
                        __( 'This lesson will be available on %s.', 'swiftlms' ),
                        wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $drip_date ) )
                    );
                }
                break;

            case 'prerequisite':
                $prerequisite_id = get_post_meta( $lesson_id, '_sfls_prerequisite_lesson', true );
                $prerequisite    = get_post( $prerequisite_id );
                if ( $prerequisite ) {
                    return sprintf(
                        /* translators: %s: lesson title */
                        __( 'Complete "%s" to unlock this lesson.', 'swiftlms' ),
                        $prerequisite->post_title
                    );
                }
                break;
        }

        return $message;
    }

    /**
     * Get lesson drip status for curriculum display.
     *
     * @param array $status    Current status.
     * @param int   $user_id   User ID.
     * @param int   $lesson_id Lesson ID.
     * @return array
     */
    public function get_lesson_drip_status( array $status, int $user_id, int $lesson_id ): array {
        $can_access = $this->check_lesson_access( true, $user_id, $lesson_id );

        if ( ! $can_access ) {
            $status['locked']  = true;
            $status['message'] = $this->get_locked_message( '', $user_id, $lesson_id );

            $drip_type = get_post_meta( $lesson_id, '_sfls_drip_type', true );
            $status['lock_type'] = $drip_type;

            // Add release date if applicable.
            if ( 'specific_date' === $drip_type ) {
                $status['release_date'] = get_post_meta( $lesson_id, '_sfls_drip_date', true );
            } elseif ( 'enrollment' === $drip_type ) {
                $course_id  = get_post_meta( $lesson_id, '_sfls_course_id', true );
                $enrollment = \SwiftLMS\Core\Enrollment::get( $user_id, $course_id );
                if ( $enrollment ) {
                    $drip_days = (int) get_post_meta( $lesson_id, '_sfls_drip_days', true );
                    $status['release_date'] = gmdate( 'Y-m-d H:i:s', strtotime( "+{$drip_days} days", strtotime( $enrollment->enrolled_at ) ) );
                }
            }
        }

        return $status;
    }

    /**
     * Enqueue admin scripts.
     *
     * @return void
     */
    public function enqueue_admin_scripts(): void {
        global $post_type;

        if ( 'sfls_lesson' !== $post_type ) {
            return;
        }

        // Scripts are inline in meta box for simplicity.
    }

    /**
     * Process scheduled releases (cron).
     *
     * @return void
     */
    public function process_scheduled_releases(): void {
        // This could send notifications when content is released.
        // For now, the access check handles availability dynamically.

        /**
         * Fires during drip content release check.
         * Allows modules to hook in for notifications, etc.
         */
        do_action( 'swiftlms_drip_release_check' );
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function register_rest_routes(): void {
        register_rest_route(
            'swiftlms/v1',
            '/lessons/(?P<id>\d+)/drip-status',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'rest_get_drip_status' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * REST: Get drip status for lesson.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function rest_get_drip_status( \WP_REST_Request $request ): \WP_REST_Response {
        $lesson_id = (int) $request->get_param( 'id' );
        $user_id   = get_current_user_id();

        $can_access = $this->check_lesson_access( true, $user_id, $lesson_id );
        $message    = $can_access ? '' : $this->get_locked_message( '', $user_id, $lesson_id );

        return new \WP_REST_Response(
            array(
                'accessible' => $can_access,
                'locked'     => ! $can_access,
                'message'    => $message,
                'drip_type'  => get_post_meta( $lesson_id, '_sfls_drip_type', true ),
            ),
            200
        );
    }

    /**
     * Get release date for a lesson and user.
     *
     * @param int $user_id   User ID.
     * @param int $lesson_id Lesson ID.
     * @return string|null Release date or null.
     */
    public static function get_release_date( int $user_id, int $lesson_id ): ?string {
        $drip_type = get_post_meta( $lesson_id, '_sfls_drip_type', true );
        $course_id = get_post_meta( $lesson_id, '_sfls_course_id', true );

        switch ( $drip_type ) {
            case 'enrollment':
                $enrollment = \SwiftLMS\Core\Enrollment::get( $user_id, $course_id );
                if ( $enrollment ) {
                    $drip_days = (int) get_post_meta( $lesson_id, '_sfls_drip_days', true );
                    return gmdate( 'Y-m-d H:i:s', strtotime( "+{$drip_days} days", strtotime( $enrollment->enrolled_at ) ) );
                }
                break;

            case 'specific_date':
                return get_post_meta( $lesson_id, '_sfls_drip_date', true );
        }

        return null;
    }
}
