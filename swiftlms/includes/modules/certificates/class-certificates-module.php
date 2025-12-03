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

        // New template builder AJAX handlers.
        add_action( 'wp_ajax_sfls_preview_certificate_template', array( $this, 'ajax_preview_template' ) );
        add_action( 'wp_ajax_sfls_preview_certificate_fullscreen', array( $this, 'ajax_preview_fullscreen' ) );
        add_action( 'wp_ajax_sfls_get_template_defaults', array( $this, 'ajax_get_template_defaults' ) );

        // Canvas editor AJAX handlers.
        add_action( 'wp_ajax_swiftlms_save_canvas_template', array( $this, 'ajax_save_canvas_template' ) );
        add_action( 'wp_ajax_swiftlms_generate_canvas_pdf', array( $this, 'ajax_generate_canvas_pdf' ) );
        add_action( 'wp_ajax_swiftlms_get_prebuilt_template', array( $this, 'ajax_get_prebuilt_template' ) );

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

        // Enqueue certificate builder assets.
        $module_url = plugin_dir_url( __FILE__ );

        wp_enqueue_style(
            'sfls-certificate-builder',
            $module_url . 'assets/css/certificate-builder.css',
            array(),
            SWIFTLMS_VERSION
        );

        wp_enqueue_script(
            'sfls-certificate-builder',
            $module_url . 'assets/js/certificate-builder.js',
            array( 'jquery', 'wp-util' ),
            SWIFTLMS_VERSION,
            true
        );

        wp_localize_script( 'sfls-certificate-builder', 'sflsCertBuilder', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'sfls_cert_builder' ),
            'strings' => array(
                'selectImage'   => __( 'Select Image', 'swiftlms' ),
                'useImage'      => __( 'Use this image', 'swiftlms' ),
                'removeImage'   => __( 'Remove', 'swiftlms' ),
                'uploadImage'   => __( 'Upload Image', 'swiftlms' ),
                'confirmReset'  => __( 'Are you sure you want to reset customizations to defaults?', 'swiftlms' ),
                'saving'        => __( 'Saving...', 'swiftlms' ),
                'saved'         => __( 'Saved!', 'swiftlms' ),
            ),
        ) );

        // Only load canvas editor on edit screen.
        if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
            // Fabric.js for canvas editor.
            wp_enqueue_script(
                'fabric-js',
                'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js',
                array(),
                '5.3.1',
                true
            );

            // Canvas editor CSS.
            wp_enqueue_style(
                'sfls-canvas-editor',
                $module_url . 'assets/css/canvas-editor.css',
                array(),
                SWIFTLMS_VERSION
            );

            // Canvas editor JS.
            wp_enqueue_script(
                'sfls-canvas-editor',
                $module_url . 'assets/js/canvas-editor.js',
                array( 'jquery', 'fabric-js', 'wp-util' ),
                SWIFTLMS_VERSION,
                true
            );

            global $post;
            $template_data = '';
            if ( $post && $post->ID ) {
                $template_data = get_post_meta( $post->ID, '_sfls_canvas_template_data', true );
            }

            wp_localize_script( 'sfls-canvas-editor', 'certificateCanvasData', array(
                'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( 'sfls_canvas_editor' ),
                'postId'       => $post ? $post->ID : 0,
                'templateData' => $template_data,
            ) );
        }

        // Legacy inline script for backwards compatibility.
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
     * AJAX: Preview certificate template.
     *
     * @return void
     */
    public function ajax_preview_template(): void {
        check_ajax_referer( 'sfls_cert_builder', '_wpnonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $template_id    = isset( $_POST['template_id'] ) ? sanitize_text_field( wp_unslash( $_POST['template_id'] ) ) : '';
        $customizations = isset( $_POST['customizations'] ) ? json_decode( wp_unslash( $_POST['customizations'] ), true ) : array();

        if ( empty( $template_id ) ) {
            wp_send_json_error( array( 'message' => 'No template specified' ) );
        }

        // Sanitize customizations.
        if ( is_array( $customizations ) ) {
            $customizations = array_map( 'sanitize_text_field', $customizations );
        } else {
            $customizations = array();
        }

        $html = PDFGenerator::preview_template( $template_id, $customizations );

        wp_send_json_success( array( 'html' => $html ) );
    }

    /**
     * AJAX: Preview certificate fullscreen (in new window).
     *
     * @return void
     */
    public function ajax_preview_fullscreen(): void {
        check_ajax_referer( 'sfls_cert_builder', '_wpnonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( 'Unauthorized' );
        }

        $template_id    = isset( $_GET['template_id'] ) ? sanitize_text_field( wp_unslash( $_GET['template_id'] ) ) : '';
        $customizations = isset( $_GET['customizations'] ) ? json_decode( wp_unslash( $_GET['customizations'] ), true ) : array();

        if ( empty( $template_id ) ) {
            wp_die( 'No template specified' );
        }

        // Sanitize customizations.
        if ( is_array( $customizations ) ) {
            $customizations = array_map( 'sanitize_text_field', $customizations );
        } else {
            $customizations = array();
        }

        PDFGenerator::stream_preview( $template_id, $customizations );
    }

    /**
     * AJAX: Get template defaults.
     *
     * @return void
     */
    public function ajax_get_template_defaults(): void {
        check_ajax_referer( 'sfls_cert_builder', '_wpnonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $template_id = isset( $_POST['template_id'] ) ? sanitize_text_field( wp_unslash( $_POST['template_id'] ) ) : '';

        if ( empty( $template_id ) ) {
            wp_send_json_error( array( 'message' => 'No template specified' ) );
        }

        $renderer = new TemplateRenderer( $template_id );
        $info     = $renderer->get_template_info();

        wp_send_json_success( array(
            'template'     => $info,
            'customizable' => $info['customizable'] ?? array(),
        ) );
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

    /**
     * AJAX: Save canvas template.
     *
     * @return void
     */
    public function ajax_save_canvas_template(): void {
        check_ajax_referer( 'sfls_canvas_editor', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $post_id       = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $template_name = isset( $_POST['template_name'] ) ? sanitize_text_field( wp_unslash( $_POST['template_name'] ) ) : '';
        $template_data = isset( $_POST['template_data'] ) ? wp_unslash( $_POST['template_data'] ) : '';

        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => 'Invalid post ID' ) );
        }

        // Validate JSON.
        $decoded = json_decode( $template_data, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( array( 'message' => 'Invalid template data' ) );
        }

        // Save to post meta.
        update_post_meta( $post_id, '_sfls_canvas_template_data', $template_data );
        update_post_meta( $post_id, '_sfls_canvas_template_name', $template_name );
        update_post_meta( $post_id, '_sfls_editor_mode', 'canvas' );

        wp_send_json_success( array(
            'message' => __( 'Template saved successfully', 'swiftlms' ),
            'post_id' => $post_id,
        ) );
    }

    /**
     * AJAX: Generate PDF from canvas template.
     *
     * @return void
     */
    public function ajax_generate_canvas_pdf(): void {
        check_ajax_referer( 'sfls_canvas_editor', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( 'Unauthorized' );
        }

        $template_data = isset( $_POST['template_data'] ) ? wp_unslash( $_POST['template_data'] ) : '';

        if ( empty( $template_data ) ) {
            wp_die( 'No template data provided' );
        }

        $data = json_decode( $template_data, true );

        if ( ! $data || ! isset( $data['canvas'] ) ) {
            wp_die( 'Invalid template data' );
        }

        // Generate HTML from canvas data.
        $html = $this->canvas_to_html( $data );

        // Output as printable page.
        header( 'Content-Type: text/html; charset=utf-8' );
        echo $html;
        exit;
    }

    /**
     * Convert canvas data to HTML for printing/PDF.
     *
     * @param array $data Canvas data.
     * @return string HTML output.
     */
    private function canvas_to_html( array $data ): string {
        $settings = $data['settings'] ?? array();
        $canvas   = $data['canvas'] ?? array();
        $width    = $settings['width'] ?? 792;
        $height   = $settings['height'] ?? 612;
        $bg_color = $settings['backgroundColor'] ?? '#ffffff';

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Certificate</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Open+Sans:wght@400;600;700&family=Great+Vibes&family=Montserrat:wght@400;600;700&family=Dancing+Script&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        @page {
            size: ' . $width . 'px ' . $height . 'px;
            margin: 0;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            width: ' . $width . 'px;
            height: ' . $height . 'px;
            background: ' . $bg_color . ';
            position: relative;
            font-family: "Open Sans", sans-serif;
        }
        .canvas-container {
            width: 100%;
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        .canvas-element {
            position: absolute;
            transform-origin: center center;
        }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="canvas-container">';

        // Render canvas objects.
        if ( isset( $canvas['objects'] ) && is_array( $canvas['objects'] ) ) {
            foreach ( $canvas['objects'] as $obj ) {
                $html .= $this->render_canvas_object( $obj );
            }
        }

        $html .= '
    </div>
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>';

        return $html;
    }

    /**
     * Render a single canvas object to HTML.
     *
     * @param array $obj Canvas object data.
     * @return string HTML.
     */
    private function render_canvas_object( array $obj ): string {
        $type = $obj['type'] ?? '';
        $html = '';

        $left    = isset( $obj['left'] ) ? floatval( $obj['left'] ) : 0;
        $top     = isset( $obj['top'] ) ? floatval( $obj['top'] ) : 0;
        $angle   = isset( $obj['angle'] ) ? floatval( $obj['angle'] ) : 0;
        $opacity = isset( $obj['opacity'] ) ? floatval( $obj['opacity'] ) : 1;
        $scaleX  = isset( $obj['scaleX'] ) ? floatval( $obj['scaleX'] ) : 1;
        $scaleY  = isset( $obj['scaleY'] ) ? floatval( $obj['scaleY'] ) : 1;

        $transform = '';
        if ( $angle !== 0 ) {
            $transform .= 'rotate(' . $angle . 'deg) ';
        }
        if ( $scaleX !== 1 || $scaleY !== 1 ) {
            $transform .= 'scale(' . $scaleX . ', ' . $scaleY . ')';
        }

        $base_style = sprintf(
            'left: %spx; top: %spx; opacity: %s; %s',
            $left,
            $top,
            $opacity,
            $transform ? 'transform: ' . $transform . ';' : ''
        );

        switch ( $type ) {
            case 'i-text':
            case 'text':
                $text       = esc_html( $obj['text'] ?? '' );
                $font       = sanitize_text_field( $obj['fontFamily'] ?? 'Open Sans' );
                $fontSize   = intval( $obj['fontSize'] ?? 24 );
                $fill       = sanitize_hex_color( $obj['fill'] ?? '#333333' ) ?: '#333333';
                $fontWeight = sanitize_text_field( $obj['fontWeight'] ?? 'normal' );
                $fontStyle  = sanitize_text_field( $obj['fontStyle'] ?? 'normal' );
                $textAlign  = sanitize_text_field( $obj['textAlign'] ?? 'left' );
                $underline  = ! empty( $obj['underline'] );

                $text_style = sprintf(
                    'font-family: "%s", sans-serif; font-size: %spx; color: %s; font-weight: %s; font-style: %s; text-align: %s; %s',
                    $font,
                    $fontSize,
                    $fill,
                    $fontWeight,
                    $fontStyle,
                    $textAlign,
                    $underline ? 'text-decoration: underline;' : ''
                );

                $html .= '<div class="canvas-element" style="' . esc_attr( $base_style . $text_style ) . '">';
                $html .= nl2br( $text );
                $html .= '</div>';
                break;

            case 'rect':
                $width       = isset( $obj['width'] ) ? floatval( $obj['width'] ) : 100;
                $height      = isset( $obj['height'] ) ? floatval( $obj['height'] ) : 50;
                $fill        = sanitize_text_field( $obj['fill'] ?? 'transparent' );
                $stroke      = sanitize_hex_color( $obj['stroke'] ?? '#333333' ) ?: '#333333';
                $strokeWidth = intval( $obj['strokeWidth'] ?? 1 );
                $rx          = isset( $obj['rx'] ) ? floatval( $obj['rx'] ) : 0;

                $rect_style = sprintf(
                    'width: %spx; height: %spx; background: %s; border: %spx solid %s; border-radius: %spx;',
                    $width * $scaleX,
                    $height * $scaleY,
                    $fill,
                    $strokeWidth,
                    $stroke,
                    $rx
                );

                $html .= '<div class="canvas-element" style="' . esc_attr( $base_style . $rect_style ) . '"></div>';
                break;

            case 'circle':
                $radius      = isset( $obj['radius'] ) ? floatval( $obj['radius'] ) : 50;
                $fill        = sanitize_text_field( $obj['fill'] ?? 'transparent' );
                $stroke      = sanitize_hex_color( $obj['stroke'] ?? '#333333' ) ?: '#333333';
                $strokeWidth = intval( $obj['strokeWidth'] ?? 1 );

                $circle_style = sprintf(
                    'width: %spx; height: %spx; background: %s; border: %spx solid %s; border-radius: 50%%;',
                    $radius * 2 * $scaleX,
                    $radius * 2 * $scaleY,
                    $fill,
                    $strokeWidth,
                    $stroke
                );

                $html .= '<div class="canvas-element" style="' . esc_attr( $base_style . $circle_style ) . '"></div>';
                break;

            case 'line':
                $x1          = isset( $obj['x1'] ) ? floatval( $obj['x1'] ) : 0;
                $y1          = isset( $obj['y1'] ) ? floatval( $obj['y1'] ) : 0;
                $x2          = isset( $obj['x2'] ) ? floatval( $obj['x2'] ) : 100;
                $y2          = isset( $obj['y2'] ) ? floatval( $obj['y2'] ) : 0;
                $stroke      = sanitize_hex_color( $obj['stroke'] ?? '#333333' ) ?: '#333333';
                $strokeWidth = intval( $obj['strokeWidth'] ?? 1 );

                $length = sqrt( pow( $x2 - $x1, 2 ) + pow( $y2 - $y1, 2 ) );
                $angle  = atan2( $y2 - $y1, $x2 - $x1 ) * 180 / M_PI;

                $line_style = sprintf(
                    'width: %spx; height: %spx; background: %s; transform-origin: left center; transform: rotate(%sdeg);',
                    $length,
                    $strokeWidth,
                    $stroke,
                    $angle
                );

                $html .= '<div class="canvas-element" style="left: ' . esc_attr( $x1 + $left ) . 'px; top: ' . esc_attr( $y1 + $top ) . 'px; ' . esc_attr( $line_style ) . '"></div>';
                break;

            case 'image':
                $src    = esc_url( $obj['src'] ?? '' );
                $width  = isset( $obj['width'] ) ? floatval( $obj['width'] ) : 100;
                $height = isset( $obj['height'] ) ? floatval( $obj['height'] ) : 100;

                if ( $src ) {
                    $img_style = sprintf(
                        'width: %spx; height: %spx;',
                        $width * $scaleX,
                        $height * $scaleY
                    );

                    $html .= '<img src="' . $src . '" class="canvas-element" style="' . esc_attr( $base_style . $img_style ) . '">';
                }
                break;

            case 'group':
                // Render group children.
                if ( isset( $obj['objects'] ) && is_array( $obj['objects'] ) ) {
                    $html .= '<div class="canvas-element" style="' . esc_attr( $base_style ) . '">';
                    foreach ( $obj['objects'] as $child ) {
                        $html .= $this->render_canvas_object( $child );
                    }
                    $html .= '</div>';
                }
                break;
        }

        return $html;
    }

    /**
     * AJAX: Get prebuilt template for canvas editor.
     *
     * @return void
     */
    public function ajax_get_prebuilt_template(): void {
        check_ajax_referer( 'sfls_canvas_editor', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $template_id = isset( $_POST['template_id'] ) ? sanitize_text_field( wp_unslash( $_POST['template_id'] ) ) : '';

        if ( empty( $template_id ) ) {
            wp_send_json_error( array( 'message' => 'No template specified' ) );
        }

        $renderer = new TemplateRenderer( $template_id );
        $info     = $renderer->get_template_info();

        if ( empty( $info ) ) {
            wp_send_json_error( array( 'message' => 'Template not found' ) );
        }

        wp_send_json_success( $info );
    }
}
