<?php
/**
 * Certificate PDF Generator
 *
 * Generates PDF certificates from JSON templates using HTML rendering.
 * Works with browser print-to-PDF or Dompdf if available.
 *
 * @package SwiftLMS\Modules\Certificates
 * @since 1.0.0
 */

namespace SwiftLMS\Modules\Certificates;

defined( 'ABSPATH' ) || exit;

/**
 * PDF Generator class.
 */
class PDFGenerator {

    /**
     * Certificate data.
     *
     * @var object
     */
    private $certificate;

    /**
     * Template ID or template data.
     *
     * @var string|array
     */
    private $template;

    /**
     * Customizations (colors, fonts, images).
     *
     * @var array
     */
    private array $customizations = array();

    /**
     * Template renderer instance.
     *
     * @var TemplateRenderer|null
     */
    private ?TemplateRenderer $renderer = null;

    /**
     * Constructor.
     *
     * @param object            $certificate    Certificate data from database.
     * @param string|array|null $template       Template ID or template data. Null to auto-detect.
     * @param array             $customizations Custom colors/fonts/images.
     */
    public function __construct( object $certificate, $template = null, array $customizations = array() ) {
        $this->certificate    = $certificate;
        $this->customizations = $customizations;

        // Determine template.
        if ( $template === null ) {
            // Get template from certificate's template_id.
            $this->template = $this->get_template_from_certificate();
        } else {
            $this->template = $template;
        }

        // Initialize renderer.
        $this->init_renderer();
    }

    /**
     * Get template ID from certificate record.
     *
     * @return string Template ID.
     */
    private function get_template_from_certificate(): string {
        // Check if certificate has a JSON template stored.
        if ( ! empty( $this->certificate->template_id ) ) {
            // Get template settings from post meta.
            $template_id = get_post_meta( $this->certificate->template_id, '_sfls_json_template', true );
            if ( $template_id ) {
                return $template_id;
            }
        }

        // Default template.
        return 'modern-minimal';
    }

    /**
     * Initialize the template renderer.
     *
     * @return void
     */
    private function init_renderer(): void {
        $this->renderer = new TemplateRenderer( $this->template );
        $this->renderer->set_certificate_data( $this->certificate );

        if ( ! empty( $this->customizations ) ) {
            $this->renderer->set_customizations( $this->customizations );
        }

        // Also load customizations from template post if available.
        if ( ! empty( $this->certificate->template_id ) ) {
            $saved_customizations = get_post_meta( $this->certificate->template_id, '_sfls_template_customizations', true );
            if ( is_array( $saved_customizations ) ) {
                $merged = array_merge( $saved_customizations, $this->customizations );
                $this->renderer->set_customizations( $merged );
            }
        }
    }

    /**
     * Generate PDF and save to file.
     *
     * @return string File path to generated certificate.
     */
    public function generate(): string {
        $html = $this->renderer->render();

        // Create uploads directory for certificates.
        $upload_dir = wp_upload_dir();
        $cert_dir   = $upload_dir['basedir'] . '/swiftlms-certificates/' . gmdate( 'Y/m' );

        if ( ! file_exists( $cert_dir ) ) {
            wp_mkdir_p( $cert_dir );

            // Add index.php for security.
            file_put_contents( $cert_dir . '/index.php', '<?php // Silence is golden.' );
        }

        $filename = sanitize_file_name( 'certificate-' . $this->certificate->certificate_code . '.html' );
        $filepath = $cert_dir . '/' . $filename;

        // Save HTML version.
        file_put_contents( $filepath, $html );

        // Update certificate with path.
        $relative_path = str_replace( $upload_dir['basedir'], '', $filepath );
        CertificatesTable::update_pdf_path( $this->certificate->id, $relative_path );

        return $filepath;
    }

    /**
     * Generate certificate HTML.
     *
     * @return string HTML content.
     */
    public function generate_html(): string {
        return $this->renderer->render();
    }

    /**
     * Stream certificate to browser (for preview).
     *
     * @return void
     */
    public function stream(): void {
        $html = $this->renderer->render();

        header( 'Content-Type: text/html; charset=utf-8' );
        header( 'Content-Disposition: inline; filename="certificate-' . $this->certificate->certificate_code . '.html"' );
        echo $html;
        exit;
    }

    /**
     * Download certificate.
     *
     * Uses Dompdf if available, otherwise outputs HTML with print dialog.
     *
     * @return void
     */
    public function download(): void {
        $html = $this->renderer->render();

        // Try Dompdf first.
        if ( class_exists( '\Dompdf\Dompdf' ) ) {
            $this->download_with_dompdf( $html );
            return;
        }

        // Try TCPDF.
        if ( class_exists( '\TCPDF' ) ) {
            $this->download_with_tcpdf( $html );
            return;
        }

        // Fallback to HTML with print dialog.
        $this->download_with_print( $html );
    }

    /**
     * Download using Dompdf.
     *
     * @param string $html HTML content.
     * @return void
     */
    private function download_with_dompdf( string $html ): void {
        $template_info = $this->renderer->get_template_info();
        $size = $template_info['size'] ?? array( 'width' => 792, 'height' => 612 );
        $orientation = $template_info['orientation'] ?? 'landscape';

        $dompdf = new \Dompdf\Dompdf( array(
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
        ) );

        $dompdf->loadHtml( $html );

        // Custom paper size in points.
        $dompdf->setPaper( array( 0, 0, $size['width'], $size['height'] ) );

        $dompdf->render();
        $dompdf->stream( 'certificate-' . $this->certificate->certificate_code . '.pdf', array(
            'Attachment' => true,
        ) );
        exit;
    }

    /**
     * Download using TCPDF.
     *
     * @param string $html HTML content.
     * @return void
     */
    private function download_with_tcpdf( string $html ): void {
        $template_info = $this->renderer->get_template_info();
        $size = $template_info['size'] ?? array( 'width' => 792, 'height' => 612 );
        $orientation = $template_info['orientation'] ?? 'landscape';

        // Convert points to mm for TCPDF.
        $width_mm  = $size['width'] * 0.352778;
        $height_mm = $size['height'] * 0.352778;

        $pdf = new \TCPDF(
            $orientation === 'landscape' ? 'L' : 'P',
            'mm',
            array( $width_mm, $height_mm ),
            true,
            'UTF-8'
        );

        $pdf->SetCreator( 'SwiftLMS' );
        $pdf->SetAuthor( get_bloginfo( 'name' ) );
        $pdf->SetTitle( 'Certificate - ' . $this->certificate->certificate_code );

        $pdf->SetMargins( 0, 0, 0 );
        $pdf->SetAutoPageBreak( false );
        $pdf->AddPage();

        // Write HTML.
        $pdf->writeHTML( $html, true, false, true, false, '' );

        $pdf->Output( 'certificate-' . $this->certificate->certificate_code . '.pdf', 'D' );
        exit;
    }

    /**
     * Download with browser print dialog.
     *
     * @param string $html HTML content.
     * @return void
     */
    private function download_with_print( string $html ): void {
        // Add print script.
        $html = str_replace(
            '</body>',
            '<script>
                window.onload = function() {
                    // Small delay to ensure fonts are loaded.
                    setTimeout(function() {
                        window.print();
                    }, 500);
                };

                // Close window after print (optional).
                window.onafterprint = function() {
                    // window.close();
                };
            </script>
            </body>',
            $html
        );

        header( 'Content-Type: text/html; charset=utf-8' );
        echo $html;
        exit;
    }

    /**
     * Generate preview for a template (without actual certificate data).
     *
     * @param string $template_id   Template ID.
     * @param array  $customizations Custom options.
     * @return string HTML content.
     */
    public static function preview_template( string $template_id, array $customizations = array() ): string {
        $renderer = new TemplateRenderer( $template_id );

        // Set sample data.
        $renderer->set_field_values( array(
            'student_name'         => 'John Smith',
            'student_first_name'   => 'John',
            'student_last_name'    => 'Smith',
            'student_email'        => 'john.smith@example.com',
            'course_title'         => 'Advanced Web Development',
            'course_short'         => 'Web Dev',
            'completion_date'      => wp_date( get_option( 'date_format' ) ),
            'completion_date_short'=> wp_date( 'm/d/Y' ),
            'enrollment_date'      => wp_date( get_option( 'date_format' ), strtotime( '-30 days' ) ),
            'instructor_name'      => 'Jane Doe',
            'course_hours'         => '24',
            'course_credits'       => '2.4',
            'certificate_id'       => 'CERT-' . gmdate( 'Y' ) . '-SAMPLE',
            'certificate_url'      => home_url( '/certificate/verify/SAMPLE' ),
            'site_name'            => get_bloginfo( 'name' ),
            'current_date'         => wp_date( get_option( 'date_format' ) ),
            'expiration_date'      => wp_date( get_option( 'date_format' ), strtotime( '+1 year' ) ),
            'quiz_score'           => '92%',
            'grade'                => 'A',
        ) );

        if ( ! empty( $customizations ) ) {
            $renderer->set_customizations( $customizations );
        }

        return $renderer->render();
    }

    /**
     * Stream preview to browser.
     *
     * @param string $template_id   Template ID.
     * @param array  $customizations Custom options.
     * @return void
     */
    public static function stream_preview( string $template_id, array $customizations = array() ): void {
        $html = self::preview_template( $template_id, $customizations );

        header( 'Content-Type: text/html; charset=utf-8' );
        header( 'Content-Disposition: inline; filename="certificate-preview.html"' );
        echo $html;
        exit;
    }
}
