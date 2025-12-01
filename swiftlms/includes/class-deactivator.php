<?php
/**
 * Plugin deactivator class.
 *
 * @package SwiftLMS
 */

namespace SwiftLMS;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fired during plugin deactivation.
 */
class Deactivator {

    /**
     * Deactivate the plugin.
     *
     * Clears scheduled events and flushes rewrite rules.
     * Does NOT delete data - that happens on uninstall if user chooses.
     *
     * @return void
     */
    public static function deactivate(): void {
        // Clear any scheduled cron events.
        self::clear_scheduled_events();

        // Flush rewrite rules.
        flush_rewrite_rules();

        /**
         * Fires after SwiftLMS has been deactivated.
         */
        do_action( 'swiftlms_deactivated' );
    }

    /**
     * Clear scheduled cron events.
     *
     * @return void
     */
    protected static function clear_scheduled_events(): void {
        $scheduled_events = array(
            'swiftlms_daily_cleanup',
            'swiftlms_sync_progress',
            'swiftlms_expire_enrollments',
        );

        foreach ( $scheduled_events as $event ) {
            $timestamp = wp_next_scheduled( $event );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, $event );
            }
        }
    }
}
