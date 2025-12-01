<?php
/**
 * Certificate Verification Template
 *
 * @package SwiftLMS
 * @var array $result Verification result from CertificatesTable::verify()
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="sfls-verification-page">
    <div class="sfls-verification-container">
        <?php if ( $result['valid'] ) : ?>
            <div class="sfls-verification-result sfls-valid">
                <div class="sfls-result-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <h1><?php esc_html_e( 'Certificate Verified', 'swiftlms' ); ?></h1>
                <p class="sfls-result-message"><?php echo esc_html( $result['message'] ); ?></p>

                <div class="sfls-certificate-details">
                    <div class="sfls-detail-row">
                        <span class="sfls-detail-label"><?php esc_html_e( 'Certificate ID:', 'swiftlms' ); ?></span>
                        <span class="sfls-detail-value"><?php echo esc_html( $result['cert']->certificate_code ); ?></span>
                    </div>
                    <div class="sfls-detail-row">
                        <span class="sfls-detail-label"><?php esc_html_e( 'Recipient:', 'swiftlms' ); ?></span>
                        <span class="sfls-detail-value"><?php echo esc_html( $result['student'] ); ?></span>
                    </div>
                    <div class="sfls-detail-row">
                        <span class="sfls-detail-label"><?php esc_html_e( 'Course:', 'swiftlms' ); ?></span>
                        <span class="sfls-detail-value"><?php echo esc_html( $result['course'] ); ?></span>
                    </div>
                    <div class="sfls-detail-row">
                        <span class="sfls-detail-label"><?php esc_html_e( 'Issue Date:', 'swiftlms' ); ?></span>
                        <span class="sfls-detail-value"><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $result['issued_date'] ) ) ); ?></span>
                    </div>
                </div>
            </div>
        <?php else : ?>
            <div class="sfls-verification-result sfls-invalid">
                <div class="sfls-result-icon">
                    <span class="dashicons dashicons-dismiss"></span>
                </div>
                <h1><?php esc_html_e( 'Verification Failed', 'swiftlms' ); ?></h1>
                <p class="sfls-result-message"><?php echo esc_html( $result['message'] ); ?></p>

                <?php if ( isset( $result['cert'] ) && 'revoked' === $result['cert']->status ) : ?>
                    <div class="sfls-revoked-notice">
                        <p><?php esc_html_e( 'This certificate was revoked on:', 'swiftlms' ); ?>
                        <?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $result['cert']->revoked_at ) ) ); ?></p>
                        <?php if ( $result['cert']->revoke_reason ) : ?>
                            <p><?php esc_html_e( 'Reason:', 'swiftlms' ); ?> <?php echo esc_html( $result['cert']->revoke_reason ); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="sfls-verification-footer">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="sfls-btn sfls-btn-secondary">
                <?php esc_html_e( 'Back to Home', 'swiftlms' ); ?>
            </a>

            <form method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" class="sfls-verify-another">
                <input type="text" name="swiftlms-verify" placeholder="<?php esc_attr_e( 'Enter another code', 'swiftlms' ); ?>">
                <button type="submit" class="sfls-btn sfls-btn-primary"><?php esc_html_e( 'Verify', 'swiftlms' ); ?></button>
            </form>
        </div>
    </div>
</div>

<style>
.sfls-verification-page {
    min-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    background: #f5f5f5;
}

.sfls-verification-container {
    max-width: 600px;
    width: 100%;
    background: #fff;
    border-radius: 12px;
    padding: 50px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    text-align: center;
}

.sfls-result-icon {
    margin-bottom: 20px;
}

.sfls-result-icon .dashicons {
    font-size: 80px;
    width: 80px;
    height: 80px;
}

.sfls-valid .sfls-result-icon .dashicons {
    color: #46b450;
}

.sfls-invalid .sfls-result-icon .dashicons {
    color: #dc3232;
}

.sfls-verification-result h1 {
    margin: 0 0 15px;
    font-size: 28px;
}

.sfls-result-message {
    font-size: 18px;
    color: #666;
    margin-bottom: 30px;
}

.sfls-certificate-details {
    text-align: left;
    background: #f9f9f9;
    border-radius: 8px;
    padding: 25px;
    margin: 30px 0;
}

.sfls-detail-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid #eee;
}

.sfls-detail-row:last-child {
    border-bottom: none;
}

.sfls-detail-label {
    font-weight: bold;
    color: #333;
}

.sfls-detail-value {
    color: #666;
}

.sfls-revoked-notice {
    background: #ffebee;
    border: 1px solid #ffcdd2;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
    text-align: left;
}

.sfls-revoked-notice p {
    margin: 0 0 10px;
}

.sfls-revoked-notice p:last-child {
    margin-bottom: 0;
}

.sfls-verification-footer {
    margin-top: 30px;
    padding-top: 30px;
    border-top: 1px solid #eee;
}

.sfls-verify-another {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.sfls-verify-another input {
    flex: 1;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 16px;
}

.sfls-btn {
    display: inline-block;
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    cursor: pointer;
    text-decoration: none;
}

.sfls-btn-primary {
    background: #0073aa;
    color: #fff;
}

.sfls-btn-secondary {
    background: #f0f0f0;
    color: #333;
}
</style>

<?php
get_footer();
