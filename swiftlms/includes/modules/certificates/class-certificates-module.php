<?php
/**
 * Certificates Module
 *
 * @package SwiftLMS\Modules\Certificates
 * @since 1.0.0
 */

namespace SwiftLMS\Modules\Certificates;

use SwiftLMS\Abstracts\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Certificates Module class.
 */
class CertificatesModule extends AbstractModule {

    /**
     * Certificate CPT instance.
     *
     * @var Certificate
     */
    public $certificate;

    /**
     * Get module ID.
     *
     * @return string
     */
    public function get_id(): string {
        return 'certificates';
    }

    /**
     * Get module name.
     *
     * @return string
     */
    public function get_name(): string {
        return __( 'Certificates', 'swiftlms' );
    }

    /**
     * Get module description.
     *
     * @return string
     */
    public function get_description(): string {
        return __( 'Issue PDF certificates upon course completion with customizable templates and verification.', 'swiftlms' );
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
        // Initialize CPT.
        $this->certificate = new Certificate();

        // Register hooks.
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_meta_boxes' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

        // REST API.
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

        // AJAX handlers.
        add_action( 'wp_ajax_sfls_preview_certificate', array( $this, 'ajax_preview_certificate' ) );
        add_action( 'wp_ajax_sfls_download_certificate', array( $this, 'ajax_download_certificate' ) );
        add_action( 'wp_ajax_nopriv_sfls_download_certificate', array( $this, 'ajax_download_certificate' ) );

        // Auto-generate certificate on course completion.
        add_action( 'swiftlms_course_completed', array( $this, 'auto_generate_certificate' ), 10, 2 );

        // Certificate verification page.
        add_action( 'template_redirect', array( $this, 'handle_verification' ) );

        // Add shortcode for verification form.
        add_shortcode( 'swiftlms_verify_certificate', array( $this, 'verification_shortcode' ) );

        // Add certificates to user profile.
        add_action( 'swiftlms_user_profile_tabs', array( $this, 'add_profile_tab' ) );
        add_action( 'swiftlms_user_profile_content_certificates', array( $this, 'render_profile_tab' ) );
    }

    /**
     * Activate module.
     *
     * @return void
     */
    public function activate(): void {
        CertificatesTable::create_table();
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
     * Register post type.
     *
     * @return void
     */
    public function register_post_type(): void {
        $this->certificate->register();
    }

    /**
     * Register meta boxes.
     *
     * @return void
     */
    public function register_meta_boxes(): void {
        $this->certificate->register_meta_boxes();
    }

    /**
     * Save meta boxes.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     * @return void
     */
    public function save_meta_boxes( int $post_id, \WP_Post $post ): void {
        if ( 'sfls_certificate' === $post->post_type ) {
            $this->certificate->save_meta_box_data( $post_id );
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

        if ( 'sfls_certificate' !== $post_type ) {
            return;
        }

        wp_enqueue_media();

        wp_add_inline_script(
            'jquery',
            "
            jQuery(function($) {
                // Background image upload.
                $('#sfls_upload_bg').on('click', function(e) {
                    e.preventDefault();
                    var frame = wp.media({ title: 'Select Background Image', multiple: false });
                    frame.on('select', function() {
                        var attachment = frame.state().get('selection').first().toJSON();
                        $('#sfls_bg_image').val(attachment.url);
                        $('#sfls_bg_preview').html('<img src=\"' + attachment.url + '\" style=\"max-width: 200px; margin-top: 10px;\">');
                        $('#sfls_remove_bg').show();
                    });
                    frame.open();
                });

                $('#sfls_remove_bg').on('click', function() {
                    $('#sfls_bg_image').val('');
                    $('#sfls_bg_preview').html('');
                    $(this).hide();
                });

                // Logo upload.
                $('#sfls_upload_logo').on('click', function(e) {
                    e.preventDefault();
                    var frame = wp.media({ title: 'Select Logo', multiple: false });
                    frame.on('select', function() {
                        var attachment = frame.state().get('selection').first().toJSON();
                        $('#sfls_logo_image').val(attachment.url);
                        $('#sfls_logo_preview').html('<img src=\"' + attachment.url + '\" style=\"max-width: 150px; margin-top: 10px;\">');
                    });
                    frame.open();
                });

                // Signature upload.
                $('#sfls_upload_signature').on('click', function(e) {
                    e.preventDefault();
                    var frame = wp.media({ title: 'Select Signature', multiple: false });
                    frame.on('select', function() {
                        var attachment = frame.state().get('selection').first().toJSON();
                        $('#sfls_signature_image').val(attachment.url);
                        $('#sfls_signature_preview').html('<img src=\"' + attachment.url + '\" style=\"max-width: 150px; margin-top: 10px;\">');
                    });
                    frame.open();
                });
            });
            "
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
            '/certificates',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'rest_get_certificates' ),
                'permission_callback' => array( $this, 'rest_check_logged_in' ),
            )
        );

        register_rest_route(
            'swiftlms/v1',
            '/certificates/(?P<id>\d+)',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'rest_get_certificate' ),
                'permission_callback' => array( $this, 'rest_check_certificate_access' ),
            )
        );

        register_rest_route(
            'swiftlms/v1',
            '/certificates/verify/(?P<code>[a-zA-Z0-9-]+)',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'rest_verify_certificate' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'swiftlms/v1',
            '/certificates/issue',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'rest_issue_certificate' ),
                'permission_callback' => array( $this, 'rest_check_admin' ),
            )
        );

        register_rest_route(
            'swiftlms/v1',
            '/certificates/(?P<id>\d+)/revoke',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'rest_revoke_certificate' ),
                'permission_callback' => array( $this, 'rest_check_admin' ),
            )
        );
    }

    /**
     * Check if user is logged in.
     *
     * @return bool
     */
    public function rest_check_logged_in(): bool {
        return is_user_logged_in();
    }

    /**
     * Check if user is admin.
     *
     * @return bool
     */
    public function rest_check_admin(): bool {
        return current_user_can( 'manage_options' );
    }

    /**
     * Check certificate access.
     *
     * @param \WP_REST_Request $request Request.
     * @return bool
     */
    public function rest_check_certificate_access( \WP_REST_Request $request ): bool {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        $cert_id = (int) $request->get_param( 'id' );
        $cert    = CertificatesTable::get( $cert_id );

        return $cert && (int) $cert->user_id === get_current_user_id();
    }

    /**
     * REST: Get user's certificates.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function rest_get_certificates( \WP_REST_Request $request ): \WP_REST_Response {
        $user_id = get_current_user_id();
        $certs   = CertificatesTable::get_user_certificates( $user_id, 'active' );

        return new \WP_REST_Response( $certs, 200 );
    }

    /**
     * REST: Get single certificate.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function rest_get_certificate( \WP_REST_Request $request ): \WP_REST_Response {
        $cert_id = (int) $request->get_param( 'id' );
        $cert    = CertificatesTable::get( $cert_id );

        if ( ! $cert ) {
            return new \WP_REST_Response( array( 'error' => 'Certificate not found' ), 404 );
        }

        return new \WP_REST_Response( $cert, 200 );
    }

    /**
     * REST: Verify certificate.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function rest_verify_certificate( \WP_REST_Request $request ): \WP_REST_Response {
        $code   = sanitize_text_field( $request->get_param( 'code' ) );
        $result = CertificatesTable::verify( $code );

        return new \WP_REST_Response( $result, $result['valid'] ? 200 : 404 );
    }

    /**
     * REST: Issue certificate manually.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function rest_issue_certificate( \WP_REST_Request $request ): \WP_REST_Response {
        $user_id   = (int) $request->get_param( 'user_id' );
        $course_id = (int) $request->get_param( 'course_id' );

        $template_id = Certificate::get_template_for_course( $course_id );

        if ( ! $template_id ) {
            return new \WP_REST_Response(
                array( 'error' => 'No certificate template found for this course' ),
                400
            );
        }

        $cert_id = CertificatesTable::issue( $user_id, $course_id, $template_id );

        if ( ! $cert_id ) {
            return new \WP_REST_Response(
                array( 'error' => 'Failed to issue certificate' ),
                500
            );
        }

        // Generate PDF.
        $cert     = CertificatesTable::get( $cert_id );
        $settings = Certificate::get_template_settings( $template_id );
        $generator = new PDFGenerator( $cert, $settings );
        $generator->generate();

        return new \WP_REST_Response(
            array(
                'success'        => true,
                'certificate_id' => $cert_id,
                'certificate'    => CertificatesTable::get( $cert_id ),
            ),
            200
        );
    }

    /**
     * REST: Revoke certificate.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function rest_revoke_certificate( \WP_REST_Request $request ): \WP_REST_Response {
        $cert_id = (int) $request->get_param( 'id' );
        $reason  = sanitize_text_field( $request->get_param( 'reason' ) );

        $result = CertificatesTable::revoke( $cert_id, get_current_user_id(), $reason );

        if ( ! $result ) {
            return new \WP_REST_Response( array( 'error' => 'Failed to revoke certificate' ), 500 );
        }

        return new \WP_REST_Response( array( 'success' => true ), 200 );
    }

    /**
     * AJAX: Preview certificate.
     *
     * @return void
     */
    public function ajax_preview_certificate(): void {
        check_ajax_referer( 'sfls_preview_cert', '_wpnonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( 'Unauthorized' );
        }

        $template_id = isset( $_GET['template_id'] ) ? absint( $_GET['template_id'] ) : 0;

        if ( ! $template_id ) {
            wp_die( 'Invalid template' );
        }

        $settings = Certificate::get_template_settings( $template_id );

        // Create dummy certificate for preview.
        $dummy_cert = (object) array(
            'id'               => 0,
            'certificate_code' => 'CERT-PREVIEW-1234',
            'user_id'          => get_current_user_id(),
            'course_id'        => 0,
            'issued_at'        => current_time( 'mysql' ),
            'meta'             => array(
                'student_name' => 'John Doe',
                'course_title' => 'Sample Course Title',
            ),
        );

        $generator = new PDFGenerator( $dummy_cert, $settings );
        $generator->stream();
    }

    /**
     * AJAX: Download certificate.
     *
     * @return void
     */
    public function ajax_download_certificate(): void {
        $cert_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

        if ( ! $cert_id ) {
            wp_die( 'Invalid certificate' );
        }

        $cert = CertificatesTable::get( $cert_id );

        if ( ! $cert ) {
            wp_die( 'Certificate not found' );
        }

        // Check access.
        if ( ! current_user_can( 'manage_options' ) && (int) $cert->user_id !== get_current_user_id() ) {
            wp_die( 'Unauthorized' );
        }

        $settings  = Certificate::get_template_settings( $cert->template_id );
        $generator = new PDFGenerator( $cert, $settings );
        $generator->download();
    }

    /**
     * Auto-generate certificate on course completion.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course ID.
     * @return void
     */
    public function auto_generate_certificate( int $user_id, int $course_id ): void {
        $template_id = Certificate::get_template_for_course( $course_id );

        if ( ! $template_id ) {
            return;
        }

        $auto_generate = get_post_meta( $template_id, '_sfls_auto_generate', true );

        if ( ! $auto_generate ) {
            return;
        }

        // Issue certificate.
        $cert_id = CertificatesTable::issue( $user_id, $course_id, $template_id );

        if ( $cert_id ) {
            // Generate PDF.
            $cert      = CertificatesTable::get( $cert_id );
            $settings  = Certificate::get_template_settings( $template_id );
            $generator = new PDFGenerator( $cert, $settings );
            $generator->generate();
        }
    }

    /**
     * Handle certificate verification via URL.
     *
     * @return void
     */
    public function handle_verification(): void {
        if ( ! isset( $_GET['swiftlms-verify'] ) ) {
            return;
        }

        $code   = sanitize_text_field( wp_unslash( $_GET['swiftlms-verify'] ) );
        $result = CertificatesTable::verify( $code );

        // Load verification template.
        include SWIFTLMS_PLUGIN_DIR . 'templates/certificates/verification.php';
        exit;
    }

    /**
     * Verification shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function verification_shortcode( array $atts = array() ): string {
        ob_start();
        ?>
        <div class="sfls-verify-form">
            <h3><?php esc_html_e( 'Verify Certificate', 'swiftlms' ); ?></h3>
            <form method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                <p>
                    <label for="sfls-cert-code"><?php esc_html_e( 'Certificate Code:', 'swiftlms' ); ?></label>
                    <input type="text" name="swiftlms-verify" id="sfls-cert-code"
                           placeholder="<?php esc_attr_e( 'Enter certificate code', 'swiftlms' ); ?>" required>
                </p>
                <button type="submit" class="sfls-btn sfls-btn-primary">
                    <?php esc_html_e( 'Verify', 'swiftlms' ); ?>
                </button>
            </form>
        </div>
        <style>
            .sfls-verify-form { max-width: 400px; margin: 20px auto; padding: 30px; background: #f9f9f9; border-radius: 8px; }
            .sfls-verify-form label { display: block; margin-bottom: 5px; font-weight: bold; }
            .sfls-verify-form input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; }
            .sfls-verify-form button { margin-top: 15px; width: 100%; }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Add certificates tab to user profile.
     *
     * @param array $tabs Existing tabs.
     * @return array
     */
    public function add_profile_tab( array $tabs ): array {
        $tabs['certificates'] = array(
            'label' => __( 'My Certificates', 'swiftlms' ),
            'icon'  => 'dashicons-awards',
        );

        return $tabs;
    }

    /**
     * Render certificates profile tab.
     *
     * @param int $user_id User ID.
     * @return void
     */
    public function render_profile_tab( int $user_id ): void {
        $certificates = CertificatesTable::get_user_certificates( $user_id, 'active' );
        ?>
        <div class="sfls-certificates-list">
            <?php if ( empty( $certificates ) ) : ?>
                <p><?php esc_html_e( 'You have not earned any certificates yet.', 'swiftlms' ); ?></p>
            <?php else : ?>
                <?php foreach ( $certificates as $cert ) : ?>
                    <div class="sfls-certificate-card">
                        <div class="sfls-cert-info">
                            <h4><?php echo esc_html( $cert->course_title ); ?></h4>
                            <p class="sfls-cert-date">
                                <?php
                                printf(
                                    /* translators: %s: date */
                                    esc_html__( 'Issued on %s', 'swiftlms' ),
                                    wp_date( get_option( 'date_format' ), strtotime( $cert->issued_at ) )
                                );
                                ?>
                            </p>
                            <p class="sfls-cert-code">
                                <?php esc_html_e( 'Certificate ID:', 'swiftlms' ); ?>
                                <code><?php echo esc_html( $cert->certificate_code ); ?></code>
                            </p>
                        </div>
                        <div class="sfls-cert-actions">
                            <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=sfls_download_certificate&id=' . $cert->id ) ); ?>"
                               class="sfls-btn sfls-btn-primary" target="_blank">
                                <?php esc_html_e( 'Download', 'swiftlms' ); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <style>
            .sfls-certificate-card { display: flex; justify-content: space-between; align-items: center; padding: 20px; margin-bottom: 15px; background: #fff; border: 1px solid #ddd; border-radius: 8px; }
            .sfls-cert-info h4 { margin: 0 0 10px; }
            .sfls-cert-date { color: #666; margin: 0 0 5px; }
            .sfls-cert-code { margin: 0; }
            .sfls-cert-code code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
        </style>
        <?php
    }
}
