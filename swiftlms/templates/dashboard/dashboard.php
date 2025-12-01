<?php
/**
 * Student Dashboard Template
 *
 * @package SwiftLMS
 */

defined( 'ABSPATH' ) || exit;

get_header();

$user_id     = get_current_user_id();
$current_tab = get_query_var( 'sfls_dashboard_tab' ) ?: 'overview';
$tabs        = \SwiftLMS\Core\Dashboard::get_tabs();
$data        = \SwiftLMS\Core\Dashboard::get_dashboard_data( $user_id );
?>

<div class="sfls-dashboard">
    <div class="sfls-dashboard-sidebar">
        <div class="sfls-user-card">
            <img src="<?php echo esc_url( $data['user']['avatar'] ); ?>" alt="<?php echo esc_attr( $data['user']['name'] ); ?>" class="sfls-user-avatar">
            <h3 class="sfls-user-name"><?php echo esc_html( $data['user']['name'] ); ?></h3>
            <p class="sfls-user-since"><?php printf( esc_html__( 'Member for %s', 'swiftlms' ), esc_html( $data['user']['member_since'] ) ); ?></p>
        </div>

        <nav class="sfls-dashboard-nav">
            <?php foreach ( $tabs as $tab_key => $tab ) : ?>
                <a href="<?php echo esc_url( home_url( "/dashboard/{$tab_key}/" ) ); ?>"
                   class="sfls-nav-item <?php echo $current_tab === $tab_key ? 'active' : ''; ?>">
                    <span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
                    <?php echo esc_html( $tab['label'] ); ?>
                </a>
            <?php endforeach; ?>
            <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="sfls-nav-item sfls-logout">
                <span class="dashicons dashicons-exit"></span>
                <?php esc_html_e( 'Logout', 'swiftlms' ); ?>
            </a>
        </nav>
    </div>

    <div class="sfls-dashboard-main">
        <?php
        $template_file = SWIFTLMS_PLUGIN_DIR . "templates/dashboard/tabs/{$current_tab}.php";
        if ( file_exists( $template_file ) ) {
            include $template_file;
        } else {
            include SWIFTLMS_PLUGIN_DIR . 'templates/dashboard/tabs/overview.php';
        }
        ?>
    </div>
</div>

<?php
get_footer();
