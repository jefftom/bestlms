<?php
/**
 * Certificate Custom Post Type
 *
 * @package SwiftLMS\Modules\Certificates
 * @since 1.0.0
 */

namespace SwiftLMS\Modules\Certificates;

use SwiftLMS\Abstracts\AbstractPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Certificate CPT class.
 */
class Certificate extends AbstractPostType {

    /**
     * Available placeholders for certificate templates.
     *
     * @var array
     */
    const PLACEHOLDERS = array(
        '{student_name}'     => 'Full name of the student',
        '{course_title}'     => 'Title of the completed course',
        '{completion_date}'  => 'Date of completion',
        '{certificate_id}'   => 'Unique certificate ID',
        '{instructor_name}'  => 'Course instructor name',
        '{course_duration}'  => 'Course duration',
        '{grade}'            => 'Final grade/score',
        '{site_name}'        => 'Website name',
        '{current_date}'     => 'Current date',
    );

    /**
     * Get post type slug.
     *
     * @return string
     */
    public function get_post_type(): string {
        return 'sfls_certificate';
    }

    /**
     * Get post type labels.
     *
     * @return array
     */
    protected function get_labels(): array {
        return array(
            'name'                  => __( 'Certificates', 'swiftlms' ),
            'singular_name'         => __( 'Certificate', 'swiftlms' ),
            'add_new'               => __( 'Add New', 'swiftlms' ),
            'add_new_item'          => __( 'Add New Certificate Template', 'swiftlms' ),
            'edit_item'             => __( 'Edit Certificate Template', 'swiftlms' ),
            'new_item'              => __( 'New Certificate Template', 'swiftlms' ),
            'view_item'             => __( 'View Certificate', 'swiftlms' ),
            'search_items'          => __( 'Search Certificates', 'swiftlms' ),
            'not_found'             => __( 'No certificates found', 'swiftlms' ),
            'not_found_in_trash'    => __( 'No certificates found in Trash', 'swiftlms' ),
            'all_items'             => __( 'All Certificates', 'swiftlms' ),
            'menu_name'             => __( 'Certificates', 'swiftlms' ),
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
            'supports'            => array( 'title', 'thumbnail' ),
            'show_in_rest'        => true,
            'rest_base'           => 'certificate-templates',
        );
    }

    /**
     * Register meta boxes.
     *
     * @return void
     */
    public function register_meta_boxes(): void {
        add_meta_box(
            'sfls_certificate_design',
            __( 'Certificate Design', 'swiftlms' ),
            array( $this, 'render_design_meta_box' ),
            $this->get_post_type(),
            'normal',
            'high'
        );

        add_meta_box(
            'sfls_certificate_canvas_editor',
            __( 'Canvas Editor (Advanced)', 'swiftlms' ),
            array( $this, 'render_canvas_editor_meta_box' ),
            $this->get_post_type(),
            'normal',
            'high'
        );

        add_meta_box(
            'sfls_certificate_settings',
            __( 'Certificate Settings', 'swiftlms' ),
            array( $this, 'render_settings_meta_box' ),
            $this->get_post_type(),
            'normal',
            'default'
        );

        add_meta_box(
            'sfls_certificate_placeholders',
            __( 'Available Placeholders', 'swiftlms' ),
            array( $this, 'render_placeholders_meta_box' ),
            $this->get_post_type(),
            'side',
            'default'
        );

        add_meta_box(
            'sfls_certificate_preview',
            __( 'Preview', 'swiftlms' ),
            array( $this, 'render_preview_meta_box' ),
            $this->get_post_type(),
            'side',
            'default'
        );
    }

    /**
     * Available Google Fonts for certificates.
     *
     * @var array
     */
    const GOOGLE_FONTS = array(
        'Playfair Display',
        'Great Vibes',
        'Open Sans',
        'Montserrat',
        'Lora',
        'Libre Baskerville',
        'Tangerine',
        'Dancing Script',
        'Quicksand',
        'Nunito',
        'Poppins',
        'Inter',
        'Roboto',
        'Raleway',
        'Cinzel',
        'DM Sans',
        'Source Serif Pro',
        'Libre Franklin',
        'Space Grotesk',
    );

    /**
     * Render design meta box.
     *
     * @param \WP_Post $post Current post.
     * @return void
     */
    public function render_design_meta_box( $post ): void {
        wp_nonce_field( 'sfls_certificate_settings', 'sfls_certificate_nonce' );

        // Get saved template settings.
        $json_template    = get_post_meta( $post->ID, '_sfls_json_template', true ) ?: 'modern-minimal';
        $customizations   = get_post_meta( $post->ID, '_sfls_template_customizations', true ) ?: array();

        // Get available templates.
        $templates = TemplateRenderer::get_available_templates();

        // Load template defaults if no customizations.
        if ( empty( $customizations ) && $json_template ) {
            $renderer = new TemplateRenderer( $json_template );
            $customizations = $renderer->get_customizable_options();
        }
        ?>
        <div class="sfls-certificate-builder">
            <!-- Hidden fields -->
            <input type="hidden" name="sfls_json_template" id="sfls_json_template" value="<?php echo esc_attr( $json_template ); ?>">
            <input type="hidden" name="sfls_template_customizations" id="sfls_template_customizations" value="<?php echo esc_attr( wp_json_encode( $customizations ) ); ?>">

            <!-- Tabs -->
            <div class="sfls-tabs">
                <button type="button" class="sfls-tab active" data-tab="tab-templates">
                    <?php esc_html_e( 'Choose Template', 'swiftlms' ); ?>
                </button>
                <button type="button" class="sfls-tab" data-tab="tab-customize">
                    <?php esc_html_e( 'Customize', 'swiftlms' ); ?>
                </button>
            </div>

            <!-- Templates Tab -->
            <div id="tab-templates" class="sfls-tab-content active">
                <p class="description"><?php esc_html_e( 'Select a template design for your certificate:', 'swiftlms' ); ?></p>
                <div class="sfls-template-selector">
                    <?php foreach ( $templates as $template ) : ?>
                        <div class="sfls-template-card <?php echo $json_template === $template['id'] ? 'selected' : ''; ?>"
                             data-template-id="<?php echo esc_attr( $template['id'] ); ?>">
                            <div class="sfls-template-preview">
                                <div class="sfls-template-preview-placeholder" style="background: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 100%); height: 100%; display: flex; align-items: center; justify-content: center; color: #999;">
                                    <?php echo esc_html( substr( $template['name'], 0, 2 ) ); ?>
                                </div>
                            </div>
                            <h4 class="sfls-template-name"><?php echo esc_html( $template['name'] ); ?></h4>
                            <p class="sfls-template-description"><?php echo esc_html( $template['description'] ); ?></p>
                            <span class="sfls-template-badge"><?php echo esc_html( $template['category'] ); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Customize Tab -->
            <div id="tab-customize" class="sfls-tab-content">
                <div class="sfls-customizer-wrapper">
                    <div class="sfls-customizer-sidebar">
                        <!-- Colors Section -->
                        <div class="sfls-customizer-section">
                            <h4 class="sfls-customizer-section-title"><?php esc_html_e( 'Colors', 'swiftlms' ); ?></h4>

                            <div class="sfls-customizer-field">
                                <label class="sfls-customizer-label"><?php esc_html_e( 'Primary Color', 'swiftlms' ); ?></label>
                                <div class="sfls-color-picker-wrapper">
                                    <input type="color" class="sfls-color-swatch" value="<?php echo esc_attr( $customizations['primaryColor'] ?? '#1a1a1a' ); ?>">
                                    <input type="text" class="sfls-customizer-input sfls-color-input" data-key="primaryColor"
                                           value="<?php echo esc_attr( $customizations['primaryColor'] ?? '#1a1a1a' ); ?>">
                                </div>
                            </div>

                            <div class="sfls-customizer-field">
                                <label class="sfls-customizer-label"><?php esc_html_e( 'Secondary Color', 'swiftlms' ); ?></label>
                                <div class="sfls-color-picker-wrapper">
                                    <input type="color" class="sfls-color-swatch" value="<?php echo esc_attr( $customizations['secondaryColor'] ?? '#666666' ); ?>">
                                    <input type="text" class="sfls-customizer-input sfls-color-input" data-key="secondaryColor"
                                           value="<?php echo esc_attr( $customizations['secondaryColor'] ?? '#666666' ); ?>">
                                </div>
                            </div>

                            <div class="sfls-customizer-field">
                                <label class="sfls-customizer-label"><?php esc_html_e( 'Accent Color', 'swiftlms' ); ?></label>
                                <div class="sfls-color-picker-wrapper">
                                    <input type="color" class="sfls-color-swatch" value="<?php echo esc_attr( $customizations['accentColor'] ?? '#0073aa' ); ?>">
                                    <input type="text" class="sfls-customizer-input sfls-color-input" data-key="accentColor"
                                           value="<?php echo esc_attr( $customizations['accentColor'] ?? '#0073aa' ); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Fonts Section -->
                        <div class="sfls-customizer-section">
                            <h4 class="sfls-customizer-section-title"><?php esc_html_e( 'Fonts', 'swiftlms' ); ?></h4>

                            <div class="sfls-customizer-field">
                                <label class="sfls-customizer-label"><?php esc_html_e( 'Title Font', 'swiftlms' ); ?></label>
                                <select class="sfls-font-select sfls-customizer-input" data-key="titleFont">
                                    <?php foreach ( self::GOOGLE_FONTS as $font ) : ?>
                                        <option value="<?php echo esc_attr( $font ); ?>" <?php selected( $customizations['titleFont'] ?? 'Playfair Display', $font ); ?>>
                                            <?php echo esc_html( $font ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="sfls-font-preview" style="font-family: '<?php echo esc_attr( $customizations['titleFont'] ?? 'Playfair Display' ); ?>';">
                                    <?php esc_html_e( 'Preview Text', 'swiftlms' ); ?>
                                </div>
                            </div>

                            <div class="sfls-customizer-field">
                                <label class="sfls-customizer-label"><?php esc_html_e( 'Body Font', 'swiftlms' ); ?></label>
                                <select class="sfls-font-select sfls-customizer-input" data-key="bodyFont">
                                    <?php foreach ( self::GOOGLE_FONTS as $font ) : ?>
                                        <option value="<?php echo esc_attr( $font ); ?>" <?php selected( $customizations['bodyFont'] ?? 'Open Sans', $font ); ?>>
                                            <?php echo esc_html( $font ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="sfls-customizer-field">
                                <label class="sfls-customizer-label"><?php esc_html_e( 'Name Font', 'swiftlms' ); ?></label>
                                <select class="sfls-font-select sfls-customizer-input" data-key="nameFont">
                                    <?php foreach ( self::GOOGLE_FONTS as $font ) : ?>
                                        <option value="<?php echo esc_attr( $font ); ?>" <?php selected( $customizations['nameFont'] ?? 'Great Vibes', $font ); ?>>
                                            <?php echo esc_html( $font ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Images Section -->
                        <div class="sfls-customizer-section">
                            <h4 class="sfls-customizer-section-title"><?php esc_html_e( 'Images', 'swiftlms' ); ?></h4>

                            <div class="sfls-customizer-field">
                                <label class="sfls-customizer-label"><?php esc_html_e( 'Logo', 'swiftlms' ); ?></label>
                                <div class="sfls-image-upload <?php echo ! empty( $customizations['logo'] ) ? 'has-image' : ''; ?>" data-key="logo">
                                    <?php if ( ! empty( $customizations['logo'] ) ) : ?>
                                        <img src="<?php echo esc_url( $customizations['logo'] ); ?>" class="sfls-image-upload-preview">
                                        <div class="sfls-image-upload-actions">
                                            <span class="sfls-image-upload-remove"><?php esc_html_e( 'Remove', 'swiftlms' ); ?></span>
                                        </div>
                                    <?php else : ?>
                                        <span class="sfls-image-upload-icon dashicons dashicons-upload"></span>
                                        <span class="sfls-image-upload-text"><?php esc_html_e( 'Upload Logo', 'swiftlms' ); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="sfls-customizer-field">
                                <label class="sfls-customizer-label"><?php esc_html_e( 'Signature', 'swiftlms' ); ?></label>
                                <div class="sfls-image-upload <?php echo ! empty( $customizations['signature'] ) ? 'has-image' : ''; ?>" data-key="signature">
                                    <?php if ( ! empty( $customizations['signature'] ) ) : ?>
                                        <img src="<?php echo esc_url( $customizations['signature'] ); ?>" class="sfls-image-upload-preview">
                                        <div class="sfls-image-upload-actions">
                                            <span class="sfls-image-upload-remove"><?php esc_html_e( 'Remove', 'swiftlms' ); ?></span>
                                        </div>
                                    <?php else : ?>
                                        <span class="sfls-image-upload-icon dashicons dashicons-upload"></span>
                                        <span class="sfls-image-upload-text"><?php esc_html_e( 'Upload Signature', 'swiftlms' ); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="sfls-customizer-actions">
                            <button type="button" class="sfls-btn sfls-btn-secondary sfls-reset-customizations">
                                <?php esc_html_e( 'Reset', 'swiftlms' ); ?>
                            </button>
                        </div>
                    </div>

                    <div class="sfls-customizer-preview">
                        <div class="sfls-preview-controls">
                            <div class="sfls-zoom-controls">
                                <button type="button" class="sfls-zoom-btn sfls-zoom-out">−</button>
                                <span class="sfls-zoom-level">50%</span>
                                <button type="button" class="sfls-zoom-btn sfls-zoom-in">+</button>
                            </div>
                            <button type="button" class="sfls-btn sfls-btn-sm sfls-btn-secondary sfls-preview-btn">
                                <span class="dashicons dashicons-external"></span>
                                <?php esc_html_e( 'Full Preview', 'swiftlms' ); ?>
                            </button>
                        </div>
                        <div class="sfls-preview-frame">
                            <!-- Preview iframe will be inserted here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php

        // Legacy fields for backwards compatibility (hidden).
        $orientation    = get_post_meta( $post->ID, '_sfls_orientation', true ) ?: 'landscape';
        $paper_size     = get_post_meta( $post->ID, '_sfls_paper_size', true ) ?: 'A4';
        $bg_color       = get_post_meta( $post->ID, '_sfls_bg_color', true ) ?: '#ffffff';
        $bg_image       = get_post_meta( $post->ID, '_sfls_bg_image', true );
        $border_style   = get_post_meta( $post->ID, '_sfls_border_style', true ) ?: 'none';
        $border_color   = get_post_meta( $post->ID, '_sfls_border_color', true ) ?: '#c9a227';
        $border_width   = get_post_meta( $post->ID, '_sfls_border_width', true ) ?: 10;

        $title_text     = get_post_meta( $post->ID, '_sfls_title_text', true ) ?: __( 'Certificate of Completion', 'swiftlms' );
        $title_font     = get_post_meta( $post->ID, '_sfls_title_font', true ) ?: 'Georgia';
        $title_size     = get_post_meta( $post->ID, '_sfls_title_size', true ) ?: 36;
        $title_color    = get_post_meta( $post->ID, '_sfls_title_color', true ) ?: '#333333';

        $body_text      = get_post_meta( $post->ID, '_sfls_body_text', true ) ?: "This is to certify that\n\n{student_name}\n\nhas successfully completed\n\n{course_title}";
        $body_font      = get_post_meta( $post->ID, '_sfls_body_font', true ) ?: 'Arial';
        $body_size      = get_post_meta( $post->ID, '_sfls_body_size', true ) ?: 16;
        $body_color     = get_post_meta( $post->ID, '_sfls_body_color', true ) ?: '#333333';

        $footer_text    = get_post_meta( $post->ID, '_sfls_footer_text', true ) ?: "Completed on {completion_date}\nCertificate ID: {certificate_id}";
        ?>
        <div class="sfls-certificate-design">
            <h4><?php esc_html_e( 'Page Settings', 'swiftlms' ); ?></h4>
            <table class="form-table">
                <tr>
                    <th><label for="sfls_orientation"><?php esc_html_e( 'Orientation', 'swiftlms' ); ?></label></th>
                    <td>
                        <select name="sfls_orientation" id="sfls_orientation">
                            <option value="landscape" <?php selected( $orientation, 'landscape' ); ?>><?php esc_html_e( 'Landscape', 'swiftlms' ); ?></option>
                            <option value="portrait" <?php selected( $orientation, 'portrait' ); ?>><?php esc_html_e( 'Portrait', 'swiftlms' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="sfls_paper_size"><?php esc_html_e( 'Paper Size', 'swiftlms' ); ?></label></th>
                    <td>
                        <select name="sfls_paper_size" id="sfls_paper_size">
                            <option value="A4" <?php selected( $paper_size, 'A4' ); ?>>A4</option>
                            <option value="Letter" <?php selected( $paper_size, 'Letter' ); ?>>Letter</option>
                            <option value="Legal" <?php selected( $paper_size, 'Legal' ); ?>>Legal</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="sfls_bg_color"><?php esc_html_e( 'Background Color', 'swiftlms' ); ?></label></th>
                    <td>
                        <input type="color" name="sfls_bg_color" id="sfls_bg_color" value="<?php echo esc_attr( $bg_color ); ?>">
                    </td>
                </tr>
                <tr>
                    <th><label for="sfls_bg_image"><?php esc_html_e( 'Background Image', 'swiftlms' ); ?></label></th>
                    <td>
                        <input type="hidden" name="sfls_bg_image" id="sfls_bg_image" value="<?php echo esc_attr( $bg_image ); ?>">
                        <button type="button" class="button" id="sfls_upload_bg"><?php esc_html_e( 'Select Image', 'swiftlms' ); ?></button>
                        <button type="button" class="button" id="sfls_remove_bg" <?php echo empty( $bg_image ) ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Remove', 'swiftlms' ); ?></button>
                        <div id="sfls_bg_preview">
                            <?php if ( $bg_image ) : ?>
                                <img src="<?php echo esc_url( $bg_image ); ?>" style="max-width: 200px; margin-top: 10px;">
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><label for="sfls_border_style"><?php esc_html_e( 'Border Style', 'swiftlms' ); ?></label></th>
                    <td>
                        <select name="sfls_border_style" id="sfls_border_style">
                            <option value="none" <?php selected( $border_style, 'none' ); ?>><?php esc_html_e( 'None', 'swiftlms' ); ?></option>
                            <option value="solid" <?php selected( $border_style, 'solid' ); ?>><?php esc_html_e( 'Solid', 'swiftlms' ); ?></option>
                            <option value="double" <?php selected( $border_style, 'double' ); ?>><?php esc_html_e( 'Double', 'swiftlms' ); ?></option>
                            <option value="ornate" <?php selected( $border_style, 'ornate' ); ?>><?php esc_html_e( 'Ornate', 'swiftlms' ); ?></option>
                        </select>
                        <input type="color" name="sfls_border_color" value="<?php echo esc_attr( $border_color ); ?>">
                        <input type="number" name="sfls_border_width" value="<?php echo esc_attr( $border_width ); ?>" min="1" max="50" style="width: 60px;"> px
                    </td>
                </tr>
            </table>

            <h4><?php esc_html_e( 'Title', 'swiftlms' ); ?></h4>
            <table class="form-table">
                <tr>
                    <th><label for="sfls_title_text"><?php esc_html_e( 'Title Text', 'swiftlms' ); ?></label></th>
                    <td>
                        <input type="text" name="sfls_title_text" id="sfls_title_text" value="<?php echo esc_attr( $title_text ); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Title Style', 'swiftlms' ); ?></label></th>
                    <td>
                        <select name="sfls_title_font">
                            <?php foreach ( array( 'Georgia', 'Times New Roman', 'Arial', 'Helvetica', 'Verdana' ) as $font ) : ?>
                                <option value="<?php echo esc_attr( $font ); ?>" <?php selected( $title_font, $font ); ?>><?php echo esc_html( $font ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" name="sfls_title_size" value="<?php echo esc_attr( $title_size ); ?>" min="12" max="72" style="width: 60px;"> px
                        <input type="color" name="sfls_title_color" value="<?php echo esc_attr( $title_color ); ?>">
                    </td>
                </tr>
            </table>

            <h4><?php esc_html_e( 'Body Content', 'swiftlms' ); ?></h4>
            <table class="form-table">
                <tr>
                    <th><label for="sfls_body_text"><?php esc_html_e( 'Body Text', 'swiftlms' ); ?></label></th>
                    <td>
                        <textarea name="sfls_body_text" id="sfls_body_text" rows="6" class="large-text"><?php echo esc_textarea( $body_text ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Use placeholders from the sidebar. Line breaks will be preserved.', 'swiftlms' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Body Style', 'swiftlms' ); ?></label></th>
                    <td>
                        <select name="sfls_body_font">
                            <?php foreach ( array( 'Arial', 'Helvetica', 'Georgia', 'Times New Roman', 'Verdana' ) as $font ) : ?>
                                <option value="<?php echo esc_attr( $font ); ?>" <?php selected( $body_font, $font ); ?>><?php echo esc_html( $font ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" name="sfls_body_size" value="<?php echo esc_attr( $body_size ); ?>" min="10" max="36" style="width: 60px;"> px
                        <input type="color" name="sfls_body_color" value="<?php echo esc_attr( $body_color ); ?>">
                    </td>
                </tr>
            </table>

            <h4><?php esc_html_e( 'Footer', 'swiftlms' ); ?></h4>
            <table class="form-table">
                <tr>
                    <th><label for="sfls_footer_text"><?php esc_html_e( 'Footer Text', 'swiftlms' ); ?></label></th>
                    <td>
                        <textarea name="sfls_footer_text" id="sfls_footer_text" rows="3" class="large-text"><?php echo esc_textarea( $footer_text ); ?></textarea>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render settings meta box.
     *
     * @param \WP_Post $post Current post.
     * @return void
     */
    public function render_settings_meta_box( $post ): void {
        $course_ids    = get_post_meta( $post->ID, '_sfls_course_ids', true ) ?: array();
        $auto_generate = get_post_meta( $post->ID, '_sfls_auto_generate', true );
        $show_qr       = get_post_meta( $post->ID, '_sfls_show_qr', true );
        $show_logo     = get_post_meta( $post->ID, '_sfls_show_logo', true );
        $logo_image    = get_post_meta( $post->ID, '_sfls_logo_image', true );
        $signature     = get_post_meta( $post->ID, '_sfls_signature_image', true );
        $sig_name      = get_post_meta( $post->ID, '_sfls_signature_name', true );
        $sig_title     = get_post_meta( $post->ID, '_sfls_signature_title', true );

        $courses = get_posts( array(
            'post_type'      => 'sfls_course',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );
        ?>
        <table class="form-table">
            <tr>
                <th><label><?php esc_html_e( 'Apply to Courses', 'swiftlms' ); ?></label></th>
                <td>
                    <select name="sfls_course_ids[]" multiple style="min-width: 300px; height: 150px;">
                        <?php foreach ( $courses as $course ) : ?>
                            <option value="<?php echo esc_attr( $course->ID ); ?>"
                                    <?php echo in_array( $course->ID, (array) $course_ids, true ) ? 'selected' : ''; ?>>
                                <?php echo esc_html( $course->post_title ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Select courses that will use this certificate template. Hold Ctrl/Cmd to select multiple.', 'swiftlms' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label><?php esc_html_e( 'Auto-Generate', 'swiftlms' ); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="sfls_auto_generate" value="1" <?php checked( $auto_generate, '1' ); ?>>
                        <?php esc_html_e( 'Automatically generate certificate when course is completed', 'swiftlms' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th><label><?php esc_html_e( 'Show QR Code', 'swiftlms' ); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="sfls_show_qr" value="1" <?php checked( $show_qr, '1' ); ?>>
                        <?php esc_html_e( 'Include QR code for verification', 'swiftlms' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th><label><?php esc_html_e( 'Logo', 'swiftlms' ); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="sfls_show_logo" value="1" <?php checked( $show_logo, '1' ); ?>>
                        <?php esc_html_e( 'Show logo on certificate', 'swiftlms' ); ?>
                    </label>
                    <br><br>
                    <input type="hidden" name="sfls_logo_image" id="sfls_logo_image" value="<?php echo esc_attr( $logo_image ); ?>">
                    <button type="button" class="button" id="sfls_upload_logo"><?php esc_html_e( 'Select Logo', 'swiftlms' ); ?></button>
                    <div id="sfls_logo_preview">
                        <?php if ( $logo_image ) : ?>
                            <img src="<?php echo esc_url( $logo_image ); ?>" style="max-width: 150px; margin-top: 10px;">
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <tr>
                <th><label><?php esc_html_e( 'Signature', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="hidden" name="sfls_signature_image" id="sfls_signature_image" value="<?php echo esc_attr( $signature ); ?>">
                    <button type="button" class="button" id="sfls_upload_signature"><?php esc_html_e( 'Select Signature Image', 'swiftlms' ); ?></button>
                    <div id="sfls_signature_preview">
                        <?php if ( $signature ) : ?>
                            <img src="<?php echo esc_url( $signature ); ?>" style="max-width: 150px; margin-top: 10px;">
                        <?php endif; ?>
                    </div>
                    <br>
                    <input type="text" name="sfls_signature_name" value="<?php echo esc_attr( $sig_name ); ?>" placeholder="<?php esc_attr_e( 'Signer Name', 'swiftlms' ); ?>" class="regular-text">
                    <br><br>
                    <input type="text" name="sfls_signature_title" value="<?php echo esc_attr( $sig_title ); ?>" placeholder="<?php esc_attr_e( 'Signer Title', 'swiftlms' ); ?>" class="regular-text">
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render placeholders meta box.
     *
     * @param \WP_Post $post Current post.
     * @return void
     */
    public function render_placeholders_meta_box( $post ): void {
        ?>
        <p><?php esc_html_e( 'Click to copy placeholder:', 'swiftlms' ); ?></p>
        <ul class="sfls-placeholders-list">
            <?php foreach ( self::PLACEHOLDERS as $placeholder => $description ) : ?>
                <li>
                    <code class="sfls-placeholder-copy" data-placeholder="<?php echo esc_attr( $placeholder ); ?>">
                        <?php echo esc_html( $placeholder ); ?>
                    </code>
                    <span class="description"><?php echo esc_html( $description ); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <style>
            .sfls-placeholders-list { margin: 0; padding: 0; list-style: none; }
            .sfls-placeholders-list li { margin-bottom: 10px; }
            .sfls-placeholder-copy { cursor: pointer; display: block; }
            .sfls-placeholder-copy:hover { background: #e5f5fa; }
            .sfls-placeholders-list .description { font-size: 11px; color: #666; }
        </style>
        <script>
        jQuery(function($) {
            $('.sfls-placeholder-copy').on('click', function() {
                const text = $(this).data('placeholder');
                navigator.clipboard.writeText(text).then(function() {
                    alert('Copied: ' + text);
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render preview meta box.
     *
     * @param \WP_Post $post Current post.
     * @return void
     */
    public function render_preview_meta_box( $post ): void {
        ?>
        <p><?php esc_html_e( 'Save to see preview changes.', 'swiftlms' ); ?></p>
        <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=sfls_preview_certificate&template_id=' . $post->ID . '&_wpnonce=' . wp_create_nonce( 'sfls_preview_cert' ) ) ); ?>"
           class="button button-primary" target="_blank">
            <?php esc_html_e( 'Preview Certificate', 'swiftlms' ); ?>
        </a>
        <?php
    }

    /**
     * Render canvas editor meta box.
     *
     * @param \WP_Post $post Current post.
     * @return void
     */
    public function render_canvas_editor_meta_box( $post ): void {
        $canvas_data = get_post_meta( $post->ID, '_sfls_canvas_template_data', true );
        $template_name = get_post_meta( $post->ID, '_sfls_canvas_template_name', true ) ?: '';
        $templates = TemplateRenderer::get_available_templates();
        ?>
        <div class="canvas-editor-wrapper">
            <!-- Left Sidebar - Toolbox -->
            <div class="canvas-toolbox">
                <!-- Elements Section -->
                <div class="toolbox-section">
                    <h4><?php esc_html_e( 'Text', 'swiftlms' ); ?></h4>
                    <div class="tool-buttons">
                        <button type="button" class="tool-btn" id="tool-add-heading">
                            <span class="dashicons dashicons-heading"></span>
                            <span><?php esc_html_e( 'Heading', 'swiftlms' ); ?></span>
                        </button>
                        <button type="button" class="tool-btn" id="tool-add-text">
                            <span class="dashicons dashicons-editor-textcolor"></span>
                            <span><?php esc_html_e( 'Text', 'swiftlms' ); ?></span>
                        </button>
                        <button type="button" class="tool-btn full-width" id="tool-add-dynamic">
                            <span class="dashicons dashicons-admin-users"></span>
                            <span><?php esc_html_e( 'Dynamic Field', 'swiftlms' ); ?></span>
                        </button>
                    </div>
                </div>

                <div class="toolbox-section">
                    <h4><?php esc_html_e( 'Shapes', 'swiftlms' ); ?></h4>
                    <div class="tool-buttons">
                        <button type="button" class="tool-btn" id="tool-add-rect">
                            <span class="dashicons dashicons-tablet"></span>
                            <span><?php esc_html_e( 'Rectangle', 'swiftlms' ); ?></span>
                        </button>
                        <button type="button" class="tool-btn" id="tool-add-circle">
                            <span class="dashicons dashicons-marker"></span>
                            <span><?php esc_html_e( 'Circle', 'swiftlms' ); ?></span>
                        </button>
                        <button type="button" class="tool-btn" id="tool-add-line">
                            <span class="dashicons dashicons-minus"></span>
                            <span><?php esc_html_e( 'Line', 'swiftlms' ); ?></span>
                        </button>
                        <button type="button" class="tool-btn" id="tool-add-border">
                            <span class="dashicons dashicons-editor-expand"></span>
                            <span><?php esc_html_e( 'Border', 'swiftlms' ); ?></span>
                        </button>
                    </div>
                </div>

                <div class="toolbox-section">
                    <h4><?php esc_html_e( 'Media', 'swiftlms' ); ?></h4>
                    <div class="tool-buttons">
                        <button type="button" class="tool-btn" id="tool-add-image">
                            <span class="dashicons dashicons-format-image"></span>
                            <span><?php esc_html_e( 'Image', 'swiftlms' ); ?></span>
                        </button>
                        <button type="button" class="tool-btn" id="tool-add-qr">
                            <span class="dashicons dashicons-smartphone"></span>
                            <span><?php esc_html_e( 'QR Code', 'swiftlms' ); ?></span>
                        </button>
                        <button type="button" class="tool-btn full-width" id="tool-add-signature">
                            <span class="dashicons dashicons-admin-customizer"></span>
                            <span><?php esc_html_e( 'Signature Line', 'swiftlms' ); ?></span>
                        </button>
                    </div>
                </div>

                <!-- Pre-built Templates -->
                <div class="toolbox-section">
                    <h4><?php esc_html_e( 'Start from Template', 'swiftlms' ); ?></h4>
                    <div class="prebuilt-templates">
                        <?php foreach ( array_slice( $templates, 0, 6 ) as $template ) : ?>
                            <button type="button" class="prebuilt-template-btn" data-template="<?php echo esc_attr( $template['id'] ); ?>">
                                <div class="template-thumb"><?php echo esc_html( substr( $template['name'], 0, 2 ) ); ?></div>
                                <span><?php echo esc_html( $template['name'] ); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Layers Panel -->
                <div class="toolbox-section layers-section">
                    <h4><?php esc_html_e( 'Layers', 'swiftlms' ); ?></h4>
                    <div id="layers-list"></div>
                </div>
            </div>

            <!-- Main Canvas Area -->
            <div class="canvas-main">
                <!-- Toolbar -->
                <div class="canvas-toolbar">
                    <div class="toolbar-left">
                        <button type="button" class="toolbar-btn" id="btn-undo" title="<?php esc_attr_e( 'Undo', 'swiftlms' ); ?>">
                            <span class="dashicons dashicons-undo"></span>
                        </button>
                        <button type="button" class="toolbar-btn" id="btn-redo" title="<?php esc_attr_e( 'Redo', 'swiftlms' ); ?>">
                            <span class="dashicons dashicons-redo"></span>
                        </button>
                        <div class="toolbar-separator"></div>
                        <button type="button" class="toolbar-btn" id="btn-delete" title="<?php esc_attr_e( 'Delete', 'swiftlms' ); ?>">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                        <button type="button" class="toolbar-btn" id="btn-duplicate" title="<?php esc_attr_e( 'Duplicate', 'swiftlms' ); ?>">
                            <span class="dashicons dashicons-admin-page"></span>
                        </button>
                        <div class="toolbar-separator"></div>
                        <button type="button" class="toolbar-btn" id="btn-bring-front" title="<?php esc_attr_e( 'Bring to Front', 'swiftlms' ); ?>">
                            <span class="dashicons dashicons-arrow-up-alt"></span>
                        </button>
                        <button type="button" class="toolbar-btn" id="btn-send-back" title="<?php esc_attr_e( 'Send to Back', 'swiftlms' ); ?>">
                            <span class="dashicons dashicons-arrow-down-alt"></span>
                        </button>
                    </div>

                    <div class="toolbar-center">
                        <button type="button" class="toolbar-btn" id="zoom-out" title="<?php esc_attr_e( 'Zoom Out', 'swiftlms' ); ?>">
                            <span class="dashicons dashicons-minus"></span>
                        </button>
                        <span id="zoom-level">100%</span>
                        <button type="button" class="toolbar-btn" id="zoom-in" title="<?php esc_attr_e( 'Zoom In', 'swiftlms' ); ?>">
                            <span class="dashicons dashicons-plus"></span>
                        </button>
                        <button type="button" class="toolbar-btn" id="zoom-fit" title="<?php esc_attr_e( 'Fit to Screen', 'swiftlms' ); ?>">
                            <span class="dashicons dashicons-editor-expand"></span>
                        </button>
                        <button type="button" class="toolbar-btn" id="zoom-100" title="<?php esc_attr_e( '100%', 'swiftlms' ); ?>">
                            1:1
                        </button>
                    </div>

                    <div class="toolbar-right">
                        <label class="props-checkbox">
                            <input type="checkbox" id="toggle-grid">
                            <span><?php esc_html_e( 'Grid', 'swiftlms' ); ?></span>
                        </label>
                        <label class="props-checkbox">
                            <input type="checkbox" id="toggle-snap">
                            <span><?php esc_html_e( 'Snap', 'swiftlms' ); ?></span>
                        </label>
                    </div>
                </div>

                <!-- Canvas Container -->
                <div id="certificate-canvas-container">
                    <div class="canvas-wrapper">
                        <canvas id="certificate-canvas"></canvas>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar - Properties -->
            <div class="canvas-properties">
                <!-- Canvas Settings -->
                <div class="props-section">
                    <h4><?php esc_html_e( 'Canvas', 'swiftlms' ); ?></h4>
                    <div class="canvas-settings">
                        <select id="canvas-size-preset">
                            <option value="letter-landscape"><?php esc_html_e( 'Letter Landscape', 'swiftlms' ); ?></option>
                            <option value="letter-portrait"><?php esc_html_e( 'Letter Portrait', 'swiftlms' ); ?></option>
                            <option value="a4-landscape"><?php esc_html_e( 'A4 Landscape', 'swiftlms' ); ?></option>
                            <option value="a4-portrait"><?php esc_html_e( 'A4 Portrait', 'swiftlms' ); ?></option>
                        </select>
                        <input type="color" id="canvas-bg-color" value="#ffffff" title="<?php esc_attr_e( 'Background Color', 'swiftlms' ); ?>">
                    </div>
                </div>

                <!-- Text Properties -->
                <div class="props-section props-text">
                    <h4><?php esc_html_e( 'Text', 'swiftlms' ); ?></h4>
                    <div class="props-row">
                        <div class="props-input">
                            <select id="prop-font-family">
                                <?php foreach ( self::GOOGLE_FONTS as $font ) : ?>
                                    <option value="<?php echo esc_attr( $font ); ?>"><?php echo esc_html( $font ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="props-row">
                        <div class="props-input" style="width: 60px; margin-right: 10px;">
                            <input type="number" id="prop-font-size" min="8" max="200" value="24">
                        </div>
                        <div class="props-input" style="width: 50px;">
                            <input type="color" id="prop-font-color" value="#333333">
                        </div>
                    </div>
                    <div class="props-row">
                        <div class="text-format-btns">
                            <button type="button" class="format-btn" id="prop-bold" title="Bold"><strong>B</strong></button>
                            <button type="button" class="format-btn" id="prop-italic" title="Italic"><em>I</em></button>
                            <button type="button" class="format-btn" id="prop-underline" title="Underline"><u>U</u></button>
                        </div>
                        <div class="align-btns">
                            <button type="button" class="format-btn" id="prop-align-left" title="Align Left">
                                <span class="dashicons dashicons-editor-alignleft"></span>
                            </button>
                            <button type="button" class="format-btn" id="prop-align-center" title="Align Center">
                                <span class="dashicons dashicons-editor-aligncenter"></span>
                            </button>
                            <button type="button" class="format-btn" id="prop-align-right" title="Align Right">
                                <span class="dashicons dashicons-editor-alignright"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Shape Properties -->
                <div class="props-section props-shape">
                    <h4><?php esc_html_e( 'Style', 'swiftlms' ); ?></h4>
                    <div class="props-row">
                        <span class="props-label"><?php esc_html_e( 'Fill', 'swiftlms' ); ?></span>
                        <div class="props-input">
                            <input type="color" id="prop-fill-color" value="#ffffff">
                        </div>
                    </div>
                    <div class="props-row">
                        <span class="props-label"><?php esc_html_e( 'Stroke', 'swiftlms' ); ?></span>
                        <div class="props-input" style="display: flex; gap: 8px;">
                            <input type="color" id="prop-stroke-color" value="#333333" style="flex: 1;">
                            <input type="number" id="prop-stroke-width" min="0" max="20" value="1" style="width: 50px;">
                        </div>
                    </div>
                    <div class="props-row">
                        <span class="props-label"><?php esc_html_e( 'Opacity', 'swiftlms' ); ?></span>
                        <div class="props-input">
                            <input type="range" id="prop-opacity" min="0" max="1" step="0.1" value="1">
                        </div>
                    </div>
                </div>

                <!-- Position Properties -->
                <div class="props-section props-position">
                    <h4><?php esc_html_e( 'Position & Size', 'swiftlms' ); ?></h4>
                    <div class="props-grid">
                        <div class="props-grid-item">
                            <label>X</label>
                            <input type="number" id="prop-pos-x" value="0">
                        </div>
                        <div class="props-grid-item">
                            <label>Y</label>
                            <input type="number" id="prop-pos-y" value="0">
                        </div>
                        <div class="props-grid-item">
                            <label><?php esc_html_e( 'W', 'swiftlms' ); ?></label>
                            <input type="number" id="prop-width" value="100">
                        </div>
                        <div class="props-grid-item">
                            <label><?php esc_html_e( 'H', 'swiftlms' ); ?></label>
                            <input type="number" id="prop-height" value="100">
                        </div>
                    </div>
                    <div class="props-row" style="margin-top: 10px;">
                        <span class="props-label"><?php esc_html_e( 'Rotation', 'swiftlms' ); ?></span>
                        <div class="props-input">
                            <input type="number" id="prop-rotation" min="-360" max="360" value="0">
                        </div>
                    </div>
                    <div class="props-row">
                        <label class="props-checkbox">
                            <input type="checkbox" id="prop-lock-aspect" checked>
                            <span><?php esc_html_e( 'Lock aspect ratio', 'swiftlms' ); ?></span>
                        </label>
                    </div>
                </div>

                <!-- Actions -->
                <div class="canvas-actions">
                    <div class="template-name-input">
                        <label for="template-name"><?php esc_html_e( 'Template Name', 'swiftlms' ); ?></label>
                        <input type="text" id="template-name" value="<?php echo esc_attr( $template_name ); ?>" placeholder="<?php esc_attr_e( 'My Custom Template', 'swiftlms' ); ?>">
                    </div>
                    <button type="button" class="action-btn" id="btn-save-template">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e( 'Save Template', 'swiftlms' ); ?>
                    </button>
                    <button type="button" class="action-btn secondary" id="btn-preview">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php esc_html_e( 'Preview', 'swiftlms' ); ?>
                    </button>
                    <button type="button" class="action-btn secondary" id="btn-export-png">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e( 'Export PNG', 'swiftlms' ); ?>
                    </button>
                    <button type="button" class="action-btn secondary" id="btn-export-pdf">
                        <span class="dashicons dashicons-media-document"></span>
                        <?php esc_html_e( 'Export PDF', 'swiftlms' ); ?>
                    </button>
                </div>
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
        if ( ! isset( $_POST['sfls_certificate_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sfls_certificate_nonce'] ) ), 'sfls_certificate_settings' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Save JSON template ID.
        if ( isset( $_POST['sfls_json_template'] ) ) {
            update_post_meta( $post_id, '_sfls_json_template', sanitize_text_field( wp_unslash( $_POST['sfls_json_template'] ) ) );
        }

        // Save template customizations.
        if ( isset( $_POST['sfls_template_customizations'] ) ) {
            $customizations_raw = wp_unslash( $_POST['sfls_template_customizations'] );
            $customizations     = json_decode( $customizations_raw, true );

            if ( is_array( $customizations ) ) {
                // Sanitize each value.
                $sanitized = array();
                foreach ( $customizations as $key => $value ) {
                    $sanitized[ sanitize_key( $key ) ] = is_array( $value )
                        ? array_map( 'sanitize_text_field', $value )
                        : sanitize_text_field( $value );
                }
                update_post_meta( $post_id, '_sfls_template_customizations', $sanitized );
            }
        }

        // Legacy text fields (for backwards compatibility).
        $text_fields = array(
            'sfls_orientation', 'sfls_paper_size', 'sfls_bg_color', 'sfls_bg_image',
            'sfls_border_style', 'sfls_border_color', 'sfls_border_width',
            'sfls_title_text', 'sfls_title_font', 'sfls_title_size', 'sfls_title_color',
            'sfls_body_font', 'sfls_body_size', 'sfls_body_color',
            'sfls_logo_image', 'sfls_signature_image', 'sfls_signature_name', 'sfls_signature_title',
        );

        foreach ( $text_fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_post_meta( $post_id, '_' . $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
            }
        }

        // Textarea fields.
        if ( isset( $_POST['sfls_body_text'] ) ) {
            update_post_meta( $post_id, '_sfls_body_text', sanitize_textarea_field( wp_unslash( $_POST['sfls_body_text'] ) ) );
        }

        if ( isset( $_POST['sfls_footer_text'] ) ) {
            update_post_meta( $post_id, '_sfls_footer_text', sanitize_textarea_field( wp_unslash( $_POST['sfls_footer_text'] ) ) );
        }

        // Course IDs.
        if ( isset( $_POST['sfls_course_ids'] ) ) {
            $course_ids = array_map( 'absint', wp_unslash( $_POST['sfls_course_ids'] ) );
            update_post_meta( $post_id, '_sfls_course_ids', $course_ids );
        } else {
            update_post_meta( $post_id, '_sfls_course_ids', array() );
        }

        // Checkboxes.
        update_post_meta( $post_id, '_sfls_auto_generate', isset( $_POST['sfls_auto_generate'] ) ? '1' : '0' );
        update_post_meta( $post_id, '_sfls_show_qr', isset( $_POST['sfls_show_qr'] ) ? '1' : '0' );
        update_post_meta( $post_id, '_sfls_show_logo', isset( $_POST['sfls_show_logo'] ) ? '1' : '0' );
    }

    /**
     * Get certificate template for a course.
     *
     * @param int $course_id Course ID.
     * @return int|null Template ID or null.
     */
    public static function get_template_for_course( int $course_id ): ?int {
        global $wpdb;

        $template_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'sfls_certificate'
                AND p.post_status = 'publish'
                AND pm.meta_key = '_sfls_course_ids'
                AND pm.meta_value LIKE %s
                LIMIT 1",
                '%' . $wpdb->esc_like( serialize( $course_id ) ) . '%'
            )
        );

        // Fallback: check serialized array format.
        if ( ! $template_id ) {
            $templates = get_posts( array(
                'post_type'      => 'sfls_certificate',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
            ) );

            foreach ( $templates as $template ) {
                $course_ids = get_post_meta( $template->ID, '_sfls_course_ids', true );
                if ( is_array( $course_ids ) && in_array( $course_id, $course_ids, true ) ) {
                    return $template->ID;
                }
            }
        }

        return $template_id ? (int) $template_id : null;
    }

    /**
     * Get template design settings.
     *
     * @param int $template_id Template ID.
     * @return array
     */
    public static function get_template_settings( int $template_id ): array {
        return array(
            'orientation'     => get_post_meta( $template_id, '_sfls_orientation', true ) ?: 'landscape',
            'paper_size'      => get_post_meta( $template_id, '_sfls_paper_size', true ) ?: 'A4',
            'bg_color'        => get_post_meta( $template_id, '_sfls_bg_color', true ) ?: '#ffffff',
            'bg_image'        => get_post_meta( $template_id, '_sfls_bg_image', true ),
            'border_style'    => get_post_meta( $template_id, '_sfls_border_style', true ) ?: 'none',
            'border_color'    => get_post_meta( $template_id, '_sfls_border_color', true ) ?: '#c9a227',
            'border_width'    => get_post_meta( $template_id, '_sfls_border_width', true ) ?: 10,
            'title_text'      => get_post_meta( $template_id, '_sfls_title_text', true ) ?: 'Certificate of Completion',
            'title_font'      => get_post_meta( $template_id, '_sfls_title_font', true ) ?: 'Georgia',
            'title_size'      => get_post_meta( $template_id, '_sfls_title_size', true ) ?: 36,
            'title_color'     => get_post_meta( $template_id, '_sfls_title_color', true ) ?: '#333333',
            'body_text'       => get_post_meta( $template_id, '_sfls_body_text', true ) ?: '',
            'body_font'       => get_post_meta( $template_id, '_sfls_body_font', true ) ?: 'Arial',
            'body_size'       => get_post_meta( $template_id, '_sfls_body_size', true ) ?: 16,
            'body_color'      => get_post_meta( $template_id, '_sfls_body_color', true ) ?: '#333333',
            'footer_text'     => get_post_meta( $template_id, '_sfls_footer_text', true ) ?: '',
            'show_qr'         => get_post_meta( $template_id, '_sfls_show_qr', true ),
            'show_logo'       => get_post_meta( $template_id, '_sfls_show_logo', true ),
            'logo_image'      => get_post_meta( $template_id, '_sfls_logo_image', true ),
            'signature_image' => get_post_meta( $template_id, '_sfls_signature_image', true ),
            'signature_name'  => get_post_meta( $template_id, '_sfls_signature_name', true ),
            'signature_title' => get_post_meta( $template_id, '_sfls_signature_title', true ),
        );
    }
}
