<?php
/**
 * Dashboard Certificates Tab
 *
 * @package SwiftLMS
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="sfls-dashboard-certificates">
    <div class="sfls-tab-header">
        <h2 class="sfls-dashboard-title"><?php esc_html_e( 'My Certificates', 'swiftlms' ); ?></h2>
    </div>

    <?php if ( empty( $data['certificates'] ) ) : ?>
        <div class="sfls-empty-state">
            <span class="dashicons dashicons-awards"></span>
            <h3><?php esc_html_e( 'No certificates yet', 'swiftlms' ); ?></h3>
            <p><?php esc_html_e( 'Complete courses to earn certificates. They will appear here.', 'swiftlms' ); ?></p>
            <a href="<?php echo esc_url( home_url( '/dashboard/courses/' ) ); ?>" class="sfls-primary-btn">
                <?php esc_html_e( 'View My Courses', 'swiftlms' ); ?>
            </a>
        </div>
    <?php else : ?>
        <div class="sfls-certificates-grid">
            <?php foreach ( $data['certificates'] as $cert ) : ?>
                <div class="sfls-certificate-card">
                    <div class="sfls-certificate-preview">
                        <?php if ( ! empty( $cert['preview_image'] ) ) : ?>
                            <img src="<?php echo esc_url( $cert['preview_image'] ); ?>" alt="<?php echo esc_attr( $cert['course_title'] ); ?>">
                        <?php else : ?>
                            <div class="sfls-certificate-placeholder">
                                <span class="dashicons dashicons-awards"></span>
                                <span class="sfls-placeholder-text"><?php esc_html_e( 'Certificate', 'swiftlms' ); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="sfls-certificate-badge">
                            <span class="dashicons dashicons-yes"></span>
                        </div>
                    </div>

                    <div class="sfls-certificate-content">
                        <h3 class="sfls-certificate-title"><?php echo esc_html( $cert['course_title'] ); ?></h3>

                        <div class="sfls-certificate-meta">
                            <div class="sfls-meta-item">
                                <span class="sfls-meta-label"><?php esc_html_e( 'Issued On', 'swiftlms' ); ?></span>
                                <span class="sfls-meta-value"><?php echo esc_html( $cert['date'] ); ?></span>
                            </div>
                            <div class="sfls-meta-item">
                                <span class="sfls-meta-label"><?php esc_html_e( 'Certificate ID', 'swiftlms' ); ?></span>
                                <span class="sfls-meta-value sfls-cert-id"><?php echo esc_html( $cert['certificate_number'] ); ?></span>
                            </div>
                        </div>

                        <div class="sfls-certificate-actions">
                            <a href="<?php echo esc_url( $cert['download_url'] ); ?>" class="sfls-primary-btn" download>
                                <span class="dashicons dashicons-download"></span>
                                <?php esc_html_e( 'Download PDF', 'swiftlms' ); ?>
                            </a>
                            <a href="<?php echo esc_url( $cert['verify_url'] ); ?>" class="sfls-secondary-btn" target="_blank">
                                <span class="dashicons dashicons-external"></span>
                                <?php esc_html_e( 'Verify', 'swiftlms' ); ?>
                            </a>
                            <button type="button" class="sfls-icon-btn sfls-share-btn" data-url="<?php echo esc_url( $cert['verify_url'] ); ?>" title="<?php esc_attr_e( 'Share', 'swiftlms' ); ?>">
                                <span class="dashicons dashicons-share"></span>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
