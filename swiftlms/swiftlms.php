<?php
/**
 * SwiftLMS - Modern WordPress LMS
 *
 * @package     SwiftLMS
 * @author      SwiftLMS
 * @copyright   2024 SwiftLMS
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       SwiftLMS
 * Plugin URI:        https://swiftlms.com
 * Description:       A modern, modular Learning Management System for WordPress. Build courses, track progress, and deliver exceptional learning experiences.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            SwiftLMS
 * Author URI:        https://swiftlms.com
 * Text Domain:       swiftlms
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants.
define( 'SWIFTLMS_VERSION', '1.0.0' );
define( 'SWIFTLMS_PLUGIN_FILE', __FILE__ );
define( 'SWIFTLMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SWIFTLMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SWIFTLMS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SWIFTLMS_MINIMUM_WP_VERSION', '6.0' );
define( 'SWIFTLMS_MINIMUM_PHP_VERSION', '7.4' );

/**
 * Check minimum requirements before loading the plugin.
 *
 * @return bool True if requirements are met, false otherwise.
 */
function swiftlms_requirements_met(): bool {
    // Check PHP version.
    if ( version_compare( PHP_VERSION, SWIFTLMS_MINIMUM_PHP_VERSION, '<' ) ) {
        add_action( 'admin_notices', 'swiftlms_php_version_notice' );
        return false;
    }

    // Check WordPress version.
    if ( version_compare( get_bloginfo( 'version' ), SWIFTLMS_MINIMUM_WP_VERSION, '<' ) ) {
        add_action( 'admin_notices', 'swiftlms_wp_version_notice' );
        return false;
    }

    return true;
}

/**
 * Display PHP version notice.
 *
 * @return void
 */
function swiftlms_php_version_notice(): void {
    $message = sprintf(
        /* translators: 1: Required PHP version, 2: Current PHP version */
        esc_html__( 'SwiftLMS requires PHP version %1$s or higher. You are running version %2$s.', 'swiftlms' ),
        SWIFTLMS_MINIMUM_PHP_VERSION,
        PHP_VERSION
    );
    printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
}

/**
 * Display WordPress version notice.
 *
 * @return void
 */
function swiftlms_wp_version_notice(): void {
    $message = sprintf(
        /* translators: 1: Required WordPress version, 2: Current WordPress version */
        esc_html__( 'SwiftLMS requires WordPress version %1$s or higher. You are running version %2$s.', 'swiftlms' ),
        SWIFTLMS_MINIMUM_WP_VERSION,
        get_bloginfo( 'version' )
    );
    printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
}

// Check requirements before proceeding.
if ( ! swiftlms_requirements_met() ) {
    return;
}

// Load Composer autoloader.
if ( file_exists( SWIFTLMS_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once SWIFTLMS_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    // Fallback autoloader for development without Composer.
    spl_autoload_register( function ( $class ) {
        $prefix = 'SwiftLMS\\';
        $base_dir = SWIFTLMS_PLUGIN_DIR . 'includes/';

        $len = strlen( $prefix );
        if ( strncmp( $prefix, $class, $len ) !== 0 ) {
            return;
        }

        $relative_class = substr( $class, $len );

        // Convert namespace to file path.
        $path_parts = explode( '\\', $relative_class );
        $class_name = array_pop( $path_parts );

        // Convert class name to file name (e.g., SwiftLMS -> class-swiftlms.php).
        $file_name = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

        // Build directory path from namespace.
        $sub_dir = '';
        if ( ! empty( $path_parts ) ) {
            $sub_dir = strtolower( implode( '/', $path_parts ) ) . '/';
        }

        // Handle special directories.
        $sub_dir = str_replace(
            array( 'abstracts/', 'interfaces/', 'traits/' ),
            array( 'abstracts/', 'interfaces/', 'traits/' ),
            $sub_dir
        );

        // Handle abstract classes.
        if ( strpos( $class_name, 'Abstract' ) === 0 ) {
            $clean_name = str_replace( 'Abstract', '', $class_name );
            $file_name = 'abstract-' . strtolower( str_replace( '_', '-', $clean_name ) ) . '.php';
        }

        // Handle interfaces.
        if ( strpos( $class_name, 'Interface' ) !== false || substr( $class_name, -9 ) === 'Interface' ) {
            $clean_name = str_replace( 'Interface', '', $class_name );
            $file_name = 'interface-' . strtolower( str_replace( '_', '-', $clean_name ) ) . '.php';
        }

        // Handle traits.
        if ( strpos( $class_name, 'Trait' ) !== false ) {
            $clean_name = str_replace( 'Trait', '', $class_name );
            $file_name = 'trait-' . strtolower( str_replace( '_', '-', $clean_name ) ) . '.php';
        }

        $file = $base_dir . $sub_dir . $file_name;

        if ( file_exists( $file ) ) {
            require_once $file;
        }
    });
}

/**
 * Plugin activation hook.
 *
 * @return void
 */
function swiftlms_activate(): void {
    require_once SWIFTLMS_PLUGIN_DIR . 'includes/class-activator.php';
    SwiftLMS\Activator::activate();
}
register_activation_hook( __FILE__, 'swiftlms_activate' );

/**
 * Plugin deactivation hook.
 *
 * @return void
 */
function swiftlms_deactivate(): void {
    require_once SWIFTLMS_PLUGIN_DIR . 'includes/class-deactivator.php';
    SwiftLMS\Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'swiftlms_deactivate' );

/**
 * Get the main SwiftLMS instance.
 *
 * @return SwiftLMS\SwiftLMS The main plugin instance.
 */
function swiftlms(): SwiftLMS\SwiftLMS {
    return SwiftLMS\SwiftLMS::instance();
}

// Initialize the plugin.
add_action( 'plugins_loaded', function() {
    swiftlms()->init();
}, 10 );
