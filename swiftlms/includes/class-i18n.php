<?php
/**
 * Internationalization class.
 *
 * @package SwiftLMS
 */

namespace SwiftLMS;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles internationalization functionality.
 */
class I18n {

    /**
     * Load the plugin text domain for translation.
     *
     * @return void
     */
    public function load_plugin_textdomain(): void {
        load_plugin_textdomain(
            'swiftlms',
            false,
            dirname( SWIFTLMS_PLUGIN_BASENAME ) . '/languages/'
        );
    }
}
