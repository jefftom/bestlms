<?php
/**
 * Certificate PDF Generator
 *
 * Uses HTML to PDF generation for compatibility.
 * For production, consider TCPDF, FPDF, or Dompdf.
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
     * Template settings.
     *
     * @var array
     */
    private $settings;

    /**
     * Constructor.
     *
     * @param object $certificate Certificate data.
     * @param array  $settings    Template settings.
     */
    public function __construct( object $certificate, array $settings ) {
        $this->certificate = $certificate;
        $this->settings    = $settings;
    }

    /**
     * Generate PDF and save to file.
     *
     * @return string|false File path or false on failure.
     */
    public function generate(): string {
        $html = $this->generate_html();

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

        // Save HTML version (for now).
        // In production, use Dompdf or similar to convert to PDF.
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
        $s = $this->settings;

        // Get page dimensions based on paper size and orientation.
        $dimensions = $this->get_page_dimensions( $s['paper_size'], $s['orientation'] );

        // Process placeholders.
        $body_text   = $this->replace_placeholders( $s['body_text'] );
        $footer_text = $this->replace_placeholders( $s['footer_text'] );

        // Generate QR code if enabled.
        $qr_code = '';
        if ( $s['show_qr'] ) {
            $verify_url = $this->get_verify_url();
            $qr_code    = $this->generate_qr_code( $verify_url );
        }

        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo esc_html( $s['title_text'] ); ?></title>
    <style>
        @page {
            size: <?php echo esc_attr( $s['paper_size'] ); ?> <?php echo esc_attr( $s['orientation'] ); ?>;
            margin: 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: <?php echo esc_attr( $s['body_font'] ); ?>, sans-serif;
            color: <?php echo esc_attr( $s['body_color'] ); ?>;
            background: <?php echo esc_attr( $s['bg_color'] ); ?>;
            width: <?php echo esc_attr( $dimensions['width'] ); ?>;
            height: <?php echo esc_attr( $dimensions['height'] ); ?>;
            position: relative;
            overflow: hidden;
        }

        <?php if ( $s['bg_image'] ) : ?>
        body {
            background-image: url('<?php echo esc_url( $s['bg_image'] ); ?>');
            background-size: cover;
            background-position: center;
        }
        <?php endif; ?>

        .certificate {
            width: 100%;
            height: 100%;
            padding: 60px;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        <?php if ( 'solid' === $s['border_style'] ) : ?>
        .certificate {
            border: <?php echo esc_attr( $s['border_width'] ); ?>px solid <?php echo esc_attr( $s['border_color'] ); ?>;
        }
        <?php elseif ( 'double' === $s['border_style'] ) : ?>
        .certificate {
            border: <?php echo esc_attr( $s['border_width'] ); ?>px double <?php echo esc_attr( $s['border_color'] ); ?>;
        }
        <?php elseif ( 'ornate' === $s['border_style'] ) : ?>
        .certificate::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            bottom: 20px;
            border: 3px solid <?php echo esc_attr( $s['border_color'] ); ?>;
        }
        .certificate::after {
            content: '';
            position: absolute;
            top: 30px;
            left: 30px;
            right: 30px;
            bottom: 30px;
            border: 1px solid <?php echo esc_attr( $s['border_color'] ); ?>;
        }
        <?php endif; ?>

        .logo {
            max-width: 150px;
            max-height: 80px;
            margin-bottom: 30px;
        }

        .title {
            font-family: <?php echo esc_attr( $s['title_font'] ); ?>, serif;
            font-size: <?php echo esc_attr( $s['title_size'] ); ?>px;
            color: <?php echo esc_attr( $s['title_color'] ); ?>;
            margin-bottom: 30px;
            letter-spacing: 2px;
        }

        .body-content {
            font-size: <?php echo esc_attr( $s['body_size'] ); ?>px;
            line-height: 1.8;
            max-width: 80%;
            margin-bottom: 40px;
            white-space: pre-line;
        }

        .student-name {
            font-size: <?php echo (int) $s['body_size'] + 8; ?>px;
            font-weight: bold;
            font-style: italic;
            margin: 20px 0;
        }

        .course-title {
            font-size: <?php echo (int) $s['body_size'] + 4; ?>px;
            font-weight: bold;
            margin: 20px 0;
        }

        .signature-section {
            display: flex;
            justify-content: center;
            gap: 100px;
            margin-top: 40px;
        }

        .signature {
            text-align: center;
        }

        .signature-image {
            max-width: 150px;
            max-height: 60px;
            margin-bottom: 10px;
        }

        .signature-line {
            width: 200px;
            border-bottom: 1px solid #333;
            margin-bottom: 5px;
        }

        .signature-name {
            font-weight: bold;
        }

        .signature-title {
            font-size: 12px;
            color: #666;
        }

        .footer {
            position: absolute;
            bottom: 40px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 12px;
            color: #666;
            white-space: pre-line;
        }

        .qr-code {
            position: absolute;
            bottom: 40px;
            right: 60px;
        }

        .qr-code img {
            width: 80px;
            height: 80px;
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="certificate">
        <?php if ( $s['show_logo'] && $s['logo_image'] ) : ?>
            <img src="<?php echo esc_url( $s['logo_image'] ); ?>" alt="Logo" class="logo">
        <?php endif; ?>

        <h1 class="title"><?php echo esc_html( $s['title_text'] ); ?></h1>

        <div class="body-content">
            <?php echo nl2br( esc_html( $body_text ) ); ?>
        </div>

        <?php if ( $s['signature_image'] || $s['signature_name'] ) : ?>
        <div class="signature-section">
            <div class="signature">
                <?php if ( $s['signature_image'] ) : ?>
                    <img src="<?php echo esc_url( $s['signature_image'] ); ?>" alt="Signature" class="signature-image">
                <?php else : ?>
                    <div class="signature-line"></div>
                <?php endif; ?>
                <?php if ( $s['signature_name'] ) : ?>
                    <div class="signature-name"><?php echo esc_html( $s['signature_name'] ); ?></div>
                <?php endif; ?>
                <?php if ( $s['signature_title'] ) : ?>
                    <div class="signature-title"><?php echo esc_html( $s['signature_title'] ); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="footer">
            <?php echo nl2br( esc_html( $footer_text ) ); ?>
        </div>

        <?php if ( $qr_code ) : ?>
        <div class="qr-code">
            <?php echo $qr_code; // phpcs:ignore ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * Replace placeholders with actual values.
     *
     * @param string $text Text with placeholders.
     * @return string Processed text.
     */
    private function replace_placeholders( string $text ): string {
        $cert = $this->certificate;
        $meta = $cert->meta ?? array();

        $user   = get_userdata( $cert->user_id );
        $course = get_post( $cert->course_id );

        // Get instructor name.
        $instructor_id   = $course ? get_post_meta( $course->ID, '_sfls_instructor_id', true ) : 0;
        $instructor      = $instructor_id ? get_userdata( $instructor_id ) : null;
        $instructor_name = $instructor ? $instructor->display_name : '';

        // Get grade if quiz was completed.
        $grade = '';
        if ( isset( $meta['quiz_percentage'] ) ) {
            $grade = $meta['quiz_percentage'] . '%';
        }

        $replacements = array(
            '{student_name}'    => $user ? $user->display_name : ( $meta['student_name'] ?? '' ),
            '{course_title}'    => $course ? $course->post_title : ( $meta['course_title'] ?? '' ),
            '{completion_date}' => wp_date( get_option( 'date_format' ), strtotime( $cert->issued_at ) ),
            '{certificate_id}'  => $cert->certificate_code,
            '{instructor_name}' => $instructor_name,
            '{course_duration}' => $meta['course_duration'] ?? '',
            '{grade}'           => $grade,
            '{site_name}'       => get_bloginfo( 'name' ),
            '{current_date}'    => wp_date( get_option( 'date_format' ) ),
        );

        return str_replace( array_keys( $replacements ), array_values( $replacements ), $text );
    }

    /**
     * Get page dimensions.
     *
     * @param string $paper_size   Paper size.
     * @param string $orientation Orientation.
     * @return array Width and height.
     */
    private function get_page_dimensions( string $paper_size, string $orientation ): array {
        $sizes = array(
            'A4'     => array( '210mm', '297mm' ),
            'Letter' => array( '8.5in', '11in' ),
            'Legal'  => array( '8.5in', '14in' ),
        );

        $size = $sizes[ $paper_size ] ?? $sizes['A4'];

        if ( 'landscape' === $orientation ) {
            return array(
                'width'  => $size[1],
                'height' => $size[0],
            );
        }

        return array(
            'width'  => $size[0],
            'height' => $size[1],
        );
    }

    /**
     * Get verification URL.
     *
     * @return string
     */
    private function get_verify_url(): string {
        return add_query_arg(
            array(
                'swiftlms-verify' => $this->certificate->certificate_code,
            ),
            home_url( '/' )
        );
    }

    /**
     * Generate QR code HTML.
     *
     * Uses a simple QR code API. For production, use a library.
     *
     * @param string $url URL to encode.
     * @return string HTML.
     */
    private function generate_qr_code( string $url ): string {
        // Using Google Charts API (deprecated but still works) or QR Server.
        $qr_api = 'https://api.qrserver.com/v1/create-qr-code/';
        $qr_url = add_query_arg(
            array(
                'size' => '150x150',
                'data' => rawurlencode( $url ),
            ),
            $qr_api
        );

        return '<img src="' . esc_url( $qr_url ) . '" alt="QR Code">';
    }

    /**
     * Stream PDF to browser for download.
     *
     * @return void
     */
    public function stream(): void {
        $html = $this->generate_html();

        // For HTML output (without PDF library).
        header( 'Content-Type: text/html; charset=utf-8' );
        header( 'Content-Disposition: inline; filename="certificate-' . $this->certificate->certificate_code . '.html"' );
        echo $html;
        exit;
    }

    /**
     * Download certificate as PDF.
     *
     * Note: This requires a PDF library like Dompdf.
     * For now, outputs HTML that can be printed to PDF.
     *
     * @return void
     */
    public function download(): void {
        $html = $this->generate_html();

        // Check if Dompdf is available.
        if ( class_exists( '\Dompdf\Dompdf' ) ) {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml( $html );
            $dompdf->setPaper( $this->settings['paper_size'], $this->settings['orientation'] );
            $dompdf->render();
            $dompdf->stream( 'certificate-' . $this->certificate->certificate_code . '.pdf' );
            exit;
        }

        // Fallback to HTML with print dialog.
        header( 'Content-Type: text/html; charset=utf-8' );
        echo $html;
        echo '<script>window.onload = function() { window.print(); }</script>';
        exit;
    }
}
