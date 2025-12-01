<?php
/**
 * Plugin activator class.
 *
 * @package SwiftLMS
 */

namespace SwiftLMS;

use SwiftLMS\Database\Schema;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fired during plugin activation.
 */
class Activator {

    /**
     * Activate the plugin.
     *
     * Creates database tables, sets up default options, and flushes rewrite rules.
     *
     * @return void
     */
    public static function activate(): void {
        // Check minimum requirements.
        if ( ! self::check_requirements() ) {
            return;
        }

        // Create custom database tables.
        self::create_tables();

        // Set default options.
        self::set_default_options();

        // Set plugin version.
        update_option( 'swiftlms_version', SWIFTLMS_VERSION );

        // Set activation timestamp.
        if ( ! get_option( 'swiftlms_installed_at' ) ) {
            update_option( 'swiftlms_installed_at', time() );
        }

        // Schedule flush of rewrite rules.
        set_transient( 'swiftlms_flush_rewrite_rules', true, 30 );

        /**
         * Fires after SwiftLMS has been activated.
         */
        do_action( 'swiftlms_activated' );
    }

    /**
     * Check minimum requirements.
     *
     * @return bool True if requirements are met.
     */
    protected static function check_requirements(): bool {
        global $wp_version;

        if ( version_compare( PHP_VERSION, SWIFTLMS_MINIMUM_PHP_VERSION, '<' ) ) {
            deactivate_plugins( SWIFTLMS_PLUGIN_BASENAME );
            wp_die(
                sprintf(
                    /* translators: %s: Required PHP version */
                    esc_html__( 'SwiftLMS requires PHP version %s or higher.', 'swiftlms' ),
                    SWIFTLMS_MINIMUM_PHP_VERSION
                ),
                esc_html__( 'Plugin Activation Error', 'swiftlms' ),
                array( 'back_link' => true )
            );
        }

        if ( version_compare( $wp_version, SWIFTLMS_MINIMUM_WP_VERSION, '<' ) ) {
            deactivate_plugins( SWIFTLMS_PLUGIN_BASENAME );
            wp_die(
                sprintf(
                    /* translators: %s: Required WordPress version */
                    esc_html__( 'SwiftLMS requires WordPress version %s or higher.', 'swiftlms' ),
                    SWIFTLMS_MINIMUM_WP_VERSION
                ),
                esc_html__( 'Plugin Activation Error', 'swiftlms' ),
                array( 'back_link' => true )
            );
        }

        return true;
    }

    /**
     * Create custom database tables.
     *
     * @return void
     */
    protected static function create_tables(): void {
        require_once SWIFTLMS_PLUGIN_DIR . 'includes/database/class-schema.php';
        Schema::create_tables();
    }

    /**
     * Set default plugin options.
     *
     * @return void
     */
    protected static function set_default_options(): void {
        $defaults = array(
            'swiftlms_course_slug'          => 'courses',
            'swiftlms_lesson_slug'          => 'lessons',
            'swiftlms_topic_slug'           => 'topics',
            'swiftlms_enable_focus_mode'    => true,
            'swiftlms_video_sync_interval'  => 5,
            'swiftlms_completion_threshold' => 90,
            'swiftlms_enable_resume'        => true,
        );

        foreach ( $defaults as $key => $value ) {
            if ( get_option( $key ) === false ) {
                update_option( $key, $value );
            }
        }
    }
}
