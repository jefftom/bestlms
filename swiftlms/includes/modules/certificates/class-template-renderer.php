<?php
/**
 * Certificate Template Renderer
 *
 * Converts JSON templates to printable HTML certificates.
 *
 * @package SwiftLMS\Modules\Certificates
 * @since 1.0.0
 */

namespace SwiftLMS\Modules\Certificates;

defined( 'ABSPATH' ) || exit;

/**
 * Template Renderer class.
 */
class TemplateRenderer {

    /**
     * Template data.
     *
     * @var array
     */
    private array $template;

    /**
     * Dynamic field values.
     *
     * @var array
     */
    private array $field_values = array();

    /**
     * Custom colors/fonts overrides.
     *
     * @var array
     */
    private array $customizations = array();

    /**
     * Google Fonts used in this template.
     *
     * @var array
     */
    private array $google_fonts = array();

    /**
     * Available dynamic fields and their descriptions.
     */
    const DYNAMIC_FIELDS = array(
        'student_name'         => 'Full name of the student',
        'student_first_name'   => 'First name only',
        'student_last_name'    => 'Last name only',
        'student_email'        => 'Student email address',
        'course_title'         => 'Full course name',
        'course_short'         => 'Short course name',
        'completion_date'      => 'Date completed (formatted)',
        'completion_date_short'=> 'Short date format',
        'enrollment_date'      => 'Date enrolled',
        'instructor_name'      => 'Course instructor name',
        'course_hours'         => 'Course duration in hours',
        'course_credits'       => 'CEU/CPE credits',
        'certificate_id'       => 'Unique certificate ID',
        'certificate_url'      => 'Verification URL',
        'site_name'            => 'Website name',
        'current_date'         => 'Today\'s date',
        'expiration_date'      => 'Certificate expiration date',
        'quiz_score'           => 'Quiz score percentage',
        'grade'                => 'Letter grade',
    );

    /**
     * Constructor.
     *
     * @param array|string $template Template data array or template ID.
     */
    public function __construct( $template ) {
        if ( is_string( $template ) ) {
            $this->template = $this->load_template( $template );
        } else {
            $this->template = $template;
        }
    }

    /**
     * Load template from JSON file.
     *
     * @param string $template_id Template ID.
     * @return array Template data.
     */
    public function load_template( string $template_id ): array {
        $template_path = $this->get_template_path( $template_id );

        if ( ! file_exists( $template_path ) ) {
            return array();
        }

        $json = file_get_contents( $template_path );
        return json_decode( $json, true ) ?: array();
    }

    /**
     * Get template file path.
     *
     * @param string $template_id Template ID.
     * @return string File path.
     */
    private function get_template_path( string $template_id ): string {
        // Check for custom template in uploads first.
        $upload_dir = wp_upload_dir();
        $custom_path = $upload_dir['basedir'] . '/swiftlms-certificates/templates/' . $template_id . '.json';

        if ( file_exists( $custom_path ) ) {
            return $custom_path;
        }

        // Fall back to bundled templates.
        return dirname( __FILE__ ) . '/templates/' . $template_id . '.json';
    }

    /**
     * Get all available templates.
     *
     * @return array Array of template metadata.
     */
    public static function get_available_templates(): array {
        $templates = array();

        // Load bundled templates.
        $bundled_path = dirname( __FILE__ ) . '/templates/';
        if ( is_dir( $bundled_path ) ) {
            foreach ( glob( $bundled_path . '*.json' ) as $file ) {
                $data = json_decode( file_get_contents( $file ), true );
                if ( $data ) {
                    $templates[ $data['id'] ] = array(
                        'id'          => $data['id'],
                        'name'        => $data['name'],
                        'description' => $data['description'] ?? '',
                        'category'    => $data['category'] ?? 'general',
                        'bundled'     => true,
                    );
                }
            }
        }

        // Load custom templates from uploads.
        $upload_dir = wp_upload_dir();
        $custom_path = $upload_dir['basedir'] . '/swiftlms-certificates/templates/';
        if ( is_dir( $custom_path ) ) {
            foreach ( glob( $custom_path . '*.json' ) as $file ) {
                $data = json_decode( file_get_contents( $file ), true );
                if ( $data && ! isset( $templates[ $data['id'] ] ) ) {
                    $templates[ $data['id'] ] = array(
                        'id'          => $data['id'],
                        'name'        => $data['name'],
                        'description' => $data['description'] ?? '',
                        'category'    => $data['category'] ?? 'custom',
                        'bundled'     => false,
                    );
                }
            }
        }

        return $templates;
    }

    /**
     * Set dynamic field values.
     *
     * @param array $values Key-value pairs of field values.
     * @return self
     */
    public function set_field_values( array $values ): self {
        $this->field_values = $values;
        return $this;
    }

    /**
     * Set field values from certificate data.
     *
     * @param object $certificate Certificate record from database.
     * @return self
     */
    public function set_certificate_data( object $certificate ): self {
        $user   = get_userdata( $certificate->user_id );
        $course = get_post( $certificate->course_id );
        $meta   = isset( $certificate->meta ) ? $certificate->meta : array();

        // Get instructor.
        $instructor_id   = $course ? get_post_meta( $course->ID, '_sfls_instructor_id', true ) : 0;
        $instructor      = $instructor_id ? get_userdata( $instructor_id ) : null;

        // Get course meta.
        $course_hours   = $course ? get_post_meta( $course->ID, '_sfls_duration_hours', true ) : '';
        $course_credits = $course ? get_post_meta( $course->ID, '_sfls_credits', true ) : '';

        // Parse user name.
        $full_name  = $user ? $user->display_name : ( $meta['student_name'] ?? 'Student Name' );
        $name_parts = explode( ' ', $full_name );
        $first_name = $name_parts[0] ?? '';
        $last_name  = count( $name_parts ) > 1 ? end( $name_parts ) : '';

        // Build verification URL.
        $verify_url = add_query_arg(
            array( 'swiftlms-verify' => $certificate->certificate_code ),
            home_url( '/' )
        );

        $this->field_values = array(
            'student_name'         => $full_name,
            'student_first_name'   => $first_name,
            'student_last_name'    => $last_name,
            'student_email'        => $user ? $user->user_email : '',
            'course_title'         => $course ? $course->post_title : ( $meta['course_title'] ?? 'Course Title' ),
            'course_short'         => $course ? ( get_post_meta( $course->ID, '_sfls_short_title', true ) ?: $course->post_title ) : '',
            'completion_date'      => wp_date( get_option( 'date_format' ), strtotime( $certificate->issued_at ) ),
            'completion_date_short'=> wp_date( 'm/d/Y', strtotime( $certificate->issued_at ) ),
            'enrollment_date'      => $meta['enrollment_date'] ?? '',
            'instructor_name'      => $instructor ? $instructor->display_name : ( $meta['instructor_name'] ?? '' ),
            'course_hours'         => $course_hours ?: ( $meta['course_hours'] ?? '' ),
            'course_credits'       => $course_credits ?: ( $meta['course_credits'] ?? '' ),
            'certificate_id'       => $certificate->certificate_code,
            'certificate_url'      => $verify_url,
            'site_name'            => get_bloginfo( 'name' ),
            'current_date'         => wp_date( get_option( 'date_format' ) ),
            'expiration_date'      => $certificate->expires_at
                ? wp_date( get_option( 'date_format' ), strtotime( $certificate->expires_at ) )
                : '',
            'quiz_score'           => $meta['quiz_score'] ?? '',
            'grade'                => $meta['grade'] ?? '',
        );

        return $this;
    }

    /**
     * Set customizations (colors, fonts).
     *
     * @param array $customizations Custom values.
     * @return self
     */
    public function set_customizations( array $customizations ): self {
        $this->customizations = $customizations;
        return $this;
    }

    /**
     * Render certificate as HTML.
     *
     * @return string HTML output.
     */
    public function render(): string {
        if ( empty( $this->template ) ) {
            return '<p>Template not found.</p>';
        }

        $size = $this->template['size'] ?? array( 'width' => 792, 'height' => 612 );
        $orientation = $this->template['orientation'] ?? 'landscape';
        $background = $this->template['background'] ?? array( 'type' => 'color', 'value' => '#ffffff' );

        // Collect Google Fonts from template.
        $this->collect_fonts();

        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Certificate</title>
    <?php echo $this->render_fonts_css(); ?>
    <style>
        @page {
            size: <?php echo $orientation === 'landscape' ? "{$size['width']}pt {$size['height']}pt" : "{$size['height']}pt {$size['width']}pt"; ?>;
            margin: 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: <?php echo esc_attr( $size['width'] ); ?>pt;
            height: <?php echo esc_attr( $size['height'] ); ?>pt;
            overflow: hidden;
        }

        body {
            font-family: sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .certificate-canvas {
            position: relative;
            width: <?php echo esc_attr( $size['width'] ); ?>pt;
            height: <?php echo esc_attr( $size['height'] ); ?>pt;
            <?php echo $this->render_background_css( $background ); ?>
        }

        .cert-element {
            position: absolute;
            display: flex;
        }

        .cert-text {
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        @media print {
            html, body {
                width: <?php echo esc_attr( $size['width'] ); ?>pt;
                height: <?php echo esc_attr( $size['height'] ); ?>pt;
            }

            .certificate-canvas {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="certificate-canvas">
        <?php
        foreach ( $this->template['elements'] ?? array() as $element ) {
            echo $this->render_element( $element );
        }
        ?>
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * Collect Google Fonts used in template.
     *
     * @return void
     */
    private function collect_fonts(): void {
        $this->google_fonts = array();

        // Get fonts from customizable settings.
        $customizable = $this->template['customizable'] ?? array();
        foreach ( array( 'titleFont', 'bodyFont', 'nameFont' ) as $font_key ) {
            if ( isset( $customizable[ $font_key ] ) ) {
                $font = $this->resolve_value( $customizable[ $font_key ] );
                if ( $font && ! $this->is_system_font( $font ) ) {
                    $this->google_fonts[] = $font;
                }
            }
        }

        // Get fonts from elements.
        foreach ( $this->template['elements'] ?? array() as $element ) {
            if ( isset( $element['fontFamily'] ) ) {
                $font = $this->resolve_value( $element['fontFamily'] );
                if ( $font && ! $this->is_system_font( $font ) ) {
                    $this->google_fonts[] = $font;
                }
            }
        }

        $this->google_fonts = array_unique( $this->google_fonts );
    }

    /**
     * Check if font is a system font.
     *
     * @param string $font Font name.
     * @return bool
     */
    private function is_system_font( string $font ): bool {
        $system_fonts = array(
            'serif', 'sans-serif', 'monospace', 'cursive', 'fantasy',
            'Arial', 'Helvetica', 'Times New Roman', 'Georgia', 'Verdana',
            'Courier New', 'Impact', 'Comic Sans MS',
        );

        return in_array( $font, $system_fonts, true );
    }

    /**
     * Render Google Fonts CSS link.
     *
     * @return string CSS link tags.
     */
    private function render_fonts_css(): string {
        if ( empty( $this->google_fonts ) ) {
            return '';
        }

        $fonts = array_map( function( $font ) {
            return str_replace( ' ', '+', $font ) . ':wght@300;400;500;600;700';
        }, $this->google_fonts );

        $url = 'https://fonts.googleapis.com/css2?family=' . implode( '&family=', $fonts ) . '&display=swap';

        return '<link rel="preconnect" href="https://fonts.googleapis.com">' .
               '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' .
               '<link href="' . esc_url( $url ) . '" rel="stylesheet">';
    }

    /**
     * Render background CSS.
     *
     * @param array $background Background settings.
     * @return string CSS properties.
     */
    private function render_background_css( array $background ): string {
        $css = '';

        switch ( $background['type'] ?? 'color' ) {
            case 'color':
                $css = 'background-color: ' . esc_attr( $this->resolve_value( $background['value'] ) ) . ';';
                break;

            case 'gradient':
                $css = 'background: ' . esc_attr( $background['value'] ) . ';';
                break;

            case 'image':
                $css = 'background-image: url(' . esc_url( $background['value'] ) . ');' .
                       'background-size: cover;' .
                       'background-position: center;';
                break;
        }

        return $css;
    }

    /**
     * Render single element.
     *
     * @param array $element Element data.
     * @return string HTML output.
     */
    private function render_element( array $element ): string {
        $type = $element['type'] ?? 'text';

        switch ( $type ) {
            case 'text':
                return $this->render_text_element( $element );

            case 'dynamic_field':
                return $this->render_dynamic_field( $element );

            case 'shape':
                return $this->render_shape_element( $element );

            case 'image':
                return $this->render_image_element( $element );

            case 'image_placeholder':
                return $this->render_image_placeholder( $element );

            case 'qr_code':
                return $this->render_qr_code( $element );

            default:
                return '';
        }
    }

    /**
     * Render text element.
     *
     * @param array $element Element data.
     * @return string HTML output.
     */
    private function render_text_element( array $element ): string {
        $content = $this->resolve_value( $element['content'] ?? '' );
        $styles = $this->get_element_styles( $element );

        return sprintf(
            '<div class="cert-element cert-text" style="%s">%s</div>',
            esc_attr( $styles ),
            esc_html( $content )
        );
    }

    /**
     * Render dynamic field element.
     *
     * @param array $element Element data.
     * @return string HTML output.
     */
    private function render_dynamic_field( array $element ): string {
        $field = $element['field'] ?? '';
        $value = $this->field_values[ $field ] ?? ( $element['fallback'] ?? '' );

        // Handle prefix/suffix.
        if ( ! empty( $value ) ) {
            $prefix = $element['prefix'] ?? '';
            $suffix = $element['suffix'] ?? '';
            $value = $prefix . $value . $suffix;
        }

        // Use fallback if empty.
        if ( empty( $value ) && isset( $element['fallback'] ) ) {
            $value = $element['fallback'];
        }

        $styles = $this->get_element_styles( $element );

        return sprintf(
            '<div class="cert-element cert-text cert-dynamic" data-field="%s" style="%s">%s</div>',
            esc_attr( $field ),
            esc_attr( $styles ),
            esc_html( $value )
        );
    }

    /**
     * Render shape element.
     *
     * @param array $element Element data.
     * @return string HTML output.
     */
    private function render_shape_element( array $element ): string {
        $shape = $element['shape'] ?? 'rectangle';
        $styles = $this->get_shape_styles( $element );

        return sprintf(
            '<div class="cert-element cert-shape cert-shape-%s" style="%s"></div>',
            esc_attr( $shape ),
            esc_attr( $styles )
        );
    }

    /**
     * Render image element.
     *
     * @param array $element Element data.
     * @return string HTML output.
     */
    private function render_image_element( array $element ): string {
        $src = $element['src'] ?? '';
        $styles = $this->get_element_styles( $element );

        if ( empty( $src ) ) {
            return '';
        }

        return sprintf(
            '<div class="cert-element cert-image" style="%s"><img src="%s" style="width: 100%%; height: 100%%; object-fit: contain;"></div>',
            esc_attr( $styles ),
            esc_url( $src )
        );
    }

    /**
     * Render image placeholder (for logo, signature, etc.).
     *
     * @param array $element Element data.
     * @return string HTML output.
     */
    private function render_image_placeholder( array $element ): string {
        $placeholder = $element['placeholder'] ?? 'image';
        $src = $this->customizations[ $placeholder ] ?? '';
        $styles = $this->get_element_styles( $element );

        if ( empty( $src ) ) {
            // Render empty placeholder in preview mode.
            return '';
        }

        return sprintf(
            '<div class="cert-element cert-image" style="%s"><img src="%s" style="width: 100%%; height: 100%%; object-fit: contain;"></div>',
            esc_attr( $styles ),
            esc_url( $src )
        );
    }

    /**
     * Render QR code element.
     *
     * @param array $element Element data.
     * @return string HTML output.
     */
    private function render_qr_code( array $element ): string {
        $field = $element['field'] ?? 'certificate_url';
        $url   = $this->field_values[ $field ] ?? '';
        $size  = $element['size'] ?? 80;
        $fg    = $this->resolve_value( $element['foreground'] ?? '#000000' );
        $bg    = $this->resolve_value( $element['background'] ?? '#ffffff' );

        if ( empty( $url ) ) {
            $url = home_url( '/verify/SAMPLE' );
        }

        // Generate QR code URL using public API.
        $qr_api = 'https://api.qrserver.com/v1/create-qr-code/';
        $qr_url = add_query_arg(
            array(
                'size'   => "{$size}x{$size}",
                'data'   => rawurlencode( $url ),
                'color'  => str_replace( '#', '', $fg ),
                'bgcolor'=> str_replace( '#', '', $bg ),
            ),
            $qr_api
        );

        $x = $element['x'] ?? 0;
        $y = $element['y'] ?? 0;

        $styles = sprintf(
            'left: %spt; top: %spt; width: %spt; height: %spt;',
            $x,
            $y,
            $size,
            $size
        );

        return sprintf(
            '<div class="cert-element cert-qr" style="%s"><img src="%s" style="width: 100%%; height: 100%%;"></div>',
            esc_attr( $styles ),
            esc_url( $qr_url )
        );
    }

    /**
     * Get CSS styles for element.
     *
     * @param array $element Element data.
     * @return string CSS styles.
     */
    private function get_element_styles( array $element ): string {
        $styles = array();

        // Position.
        $x = $element['x'] ?? 0;
        $y = $element['y'] ?? 0;
        $width = $element['width'] ?? 'auto';
        $height = $element['height'] ?? 'auto';

        $styles[] = "left: {$x}pt";
        $styles[] = "top: {$y}pt";

        if ( $width !== 'auto' ) {
            $styles[] = "width: {$width}pt";
        }

        if ( $height !== 'auto' ) {
            $styles[] = "height: {$height}pt";
        }

        // Typography.
        if ( isset( $element['fontSize'] ) ) {
            $styles[] = "font-size: {$element['fontSize']}pt";
        }

        if ( isset( $element['fontFamily'] ) ) {
            $font = $this->resolve_value( $element['fontFamily'] );
            $styles[] = "font-family: '{$font}', sans-serif";
        }

        if ( isset( $element['fontWeight'] ) ) {
            $styles[] = "font-weight: {$element['fontWeight']}";
        }

        if ( isset( $element['fontStyle'] ) ) {
            $styles[] = "font-style: {$element['fontStyle']}";
        }

        if ( isset( $element['fill'] ) ) {
            $color = $this->resolve_value( $element['fill'] );
            $styles[] = "color: {$color}";
        }

        if ( isset( $element['align'] ) ) {
            $align = $element['align'];
            $styles[] = "text-align: {$align}";

            // Adjust position for center/right alignment.
            if ( $align === 'center' && isset( $element['width'] ) ) {
                $styles[] = "transform: translateX(-50%)";
                $styles[] = "left: {$x}pt";
            } elseif ( $align === 'right' && isset( $element['width'] ) ) {
                $styles[] = "transform: translateX(-100%)";
            }
        }

        if ( isset( $element['letterSpacing'] ) ) {
            $styles[] = "letter-spacing: {$element['letterSpacing']}px";
        }

        if ( isset( $element['lineHeight'] ) ) {
            $styles[] = "line-height: {$element['lineHeight']}";
        }

        if ( isset( $element['textTransform'] ) ) {
            $styles[] = "text-transform: {$element['textTransform']}";
        }

        if ( isset( $element['rotation'] ) ) {
            $styles[] = "transform: rotate({$element['rotation']}deg)";
        }

        if ( isset( $element['opacity'] ) ) {
            $styles[] = "opacity: {$element['opacity']}";
        }

        return implode( '; ', $styles );
    }

    /**
     * Get CSS styles for shape element.
     *
     * @param array $element Element data.
     * @return string CSS styles.
     */
    private function get_shape_styles( array $element ): string {
        $styles = array();
        $shape = $element['shape'] ?? 'rectangle';

        // Position and size.
        $x = $element['x'] ?? 0;
        $y = $element['y'] ?? 0;
        $width = $element['width'] ?? 100;
        $height = $element['height'] ?? 100;

        $styles[] = "left: {$x}pt";
        $styles[] = "top: {$y}pt";
        $styles[] = "width: {$width}pt";
        $styles[] = "height: {$height}pt";

        // Fill.
        if ( isset( $element['fill'] ) ) {
            $fill = $this->resolve_value( $element['fill'] );
            if ( $fill !== 'transparent' ) {
                $styles[] = "background-color: {$fill}";
            }
        }

        // Stroke/border.
        if ( isset( $element['stroke'] ) ) {
            $stroke = $this->resolve_value( $element['stroke'] );
            $strokeWidth = $element['strokeWidth'] ?? 1;
            $styles[] = "border: {$strokeWidth}pt solid {$stroke}";
        }

        // Border radius for circles and rounded rectangles.
        if ( $shape === 'circle' ) {
            $styles[] = "border-radius: 50%";
        } elseif ( isset( $element['borderRadius'] ) ) {
            $styles[] = "border-radius: {$element['borderRadius']}pt";
        }

        // Line shape (thin rectangle).
        if ( $shape === 'line' ) {
            $styles[] = "height: {$height}pt";
        }

        if ( isset( $element['opacity'] ) ) {
            $styles[] = "opacity: {$element['opacity']}";
        }

        return implode( '; ', $styles );
    }

    /**
     * Resolve value - replace template variables with actual values.
     *
     * @param string $value Value that may contain variables.
     * @return string Resolved value.
     */
    private function resolve_value( string $value ): string {
        // Check for variable references like {titleFont}, {primaryColor}.
        if ( preg_match( '/^\{(\w+)\}$/', $value, $matches ) ) {
            $var = $matches[1];

            // Check customizations first.
            if ( isset( $this->customizations[ $var ] ) ) {
                return $this->customizations[ $var ];
            }

            // Check template customizable defaults.
            if ( isset( $this->template['customizable'][ $var ] ) ) {
                return $this->template['customizable'][ $var ];
            }

            // Check dynamic fields.
            if ( isset( $this->field_values[ $var ] ) ) {
                return $this->field_values[ $var ];
            }
        }

        // Replace inline variables in text content.
        if ( strpos( $value, '{' ) !== false ) {
            // Replace dynamic field placeholders.
            foreach ( $this->field_values as $field => $field_value ) {
                $value = str_replace( '{' . $field . '}', $field_value, $value );
            }

            // Replace customization placeholders.
            foreach ( $this->customizations as $key => $custom_value ) {
                $value = str_replace( '{' . $key . '}', $custom_value, $value );
            }

            // Replace template defaults.
            foreach ( $this->template['customizable'] ?? array() as $key => $default ) {
                $value = str_replace( '{' . $key . '}', $default, $value );
            }
        }

        return $value;
    }

    /**
     * Get template metadata.
     *
     * @return array
     */
    public function get_template_info(): array {
        return array(
            'id'          => $this->template['id'] ?? '',
            'name'        => $this->template['name'] ?? '',
            'description' => $this->template['description'] ?? '',
            'category'    => $this->template['category'] ?? '',
            'size'        => $this->template['size'] ?? array(),
            'orientation' => $this->template['orientation'] ?? 'landscape',
            'customizable'=> $this->template['customizable'] ?? array(),
        );
    }

    /**
     * Get customizable options for this template.
     *
     * @return array
     */
    public function get_customizable_options(): array {
        return $this->template['customizable'] ?? array();
    }
}
