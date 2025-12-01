<?php
/**
 * Singleton trait.
 *
 * @package SwiftLMS\Traits
 */

namespace SwiftLMS\Traits;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Singleton pattern implementation.
 *
 * Provides a consistent way to implement the singleton pattern
 * across SwiftLMS classes.
 */
trait SingletonTrait {

    /**
     * The singleton instance.
     *
     * @var static|null
     */
    protected static ?self $instance = null;

    /**
     * Get the singleton instance.
     *
     * @return static
     */
    public static function instance(): self {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Prevent cloning.
     *
     * @return void
     */
    protected function __clone(): void {
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning is forbidden.', 'swiftlms' ), '1.0.0' );
    }

    /**
     * Prevent unserializing.
     *
     * @return void
     */
    public function __wakeup(): void {
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing instances of this class is forbidden.', 'swiftlms' ), '1.0.0' );
    }
}
