<?php
/**
 * Module interface.
 *
 * @package SwiftLMS\Interfaces
 */

namespace SwiftLMS\Interfaces;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Interface for SwiftLMS modules.
 *
 * All SwiftLMS modules must implement this interface to ensure
 * consistent behavior across the plugin ecosystem.
 */
interface ModuleInterface {

    /**
     * Get the module slug.
     *
     * @return string The unique module identifier.
     */
    public function get_slug(): string;

    /**
     * Get the module name.
     *
     * @return string The human-readable module name.
     */
    public function get_name(): string;

    /**
     * Get the module version.
     *
     * @return string The module version number.
     */
    public function get_version(): string;

    /**
     * Get module dependencies.
     *
     * @return array<string> List of required module slugs.
     */
    public function get_dependencies(): array;

    /**
     * Check if the module is active.
     *
     * @return bool True if the module is active.
     */
    public function is_active(): bool;

    /**
     * Initialize the module.
     *
     * @return void
     */
    public function init(): void;

    /**
     * Activate the module.
     *
     * @return void
     */
    public function activate(): void;

    /**
     * Deactivate the module.
     *
     * @return void
     */
    public function deactivate(): void;
}
