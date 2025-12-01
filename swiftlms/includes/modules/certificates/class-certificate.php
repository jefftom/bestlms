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
     * Render design meta box.
     *
     * @param \WP_Post $post Current post.
     * @return void
     */
    public function render_design_meta_box( $post ): void {
        wp_nonce_field( 'sfls_certificate_settings', 'sfls_certificate_nonce' );

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

        // Text fields.
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
