<?php
/**
 * Abstract Module class.
 *
 * @package SwiftLMS\Abstracts
 */

namespace SwiftLMS\Abstracts;

use SwiftLMS\Interfaces\ModuleInterface;
use SwiftLMS\SwiftLMS;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Base class for all SwiftLMS modules.
 *
 * Provides common functionality for module registration, activation,
 * and dependency management. All module plugins should extend this class.
 */
abstract class AbstractModule implements ModuleInterface {

    /**
     * The module slug.
     *
     * @var string
     */
    protected string $slug = '';

    /**
     * The module name.
     *
     * @var string
     */
    protected string $name = '';

    /**
     * The module version.
     *
     * @var string
     */
    protected string $version = '1.0.0';

    /**
     * The module description.
     *
     * @var string
     */
    protected string $description = '';

    /**
     * Required dependencies (other module slugs).
     *
     * @var array<string>
     */
    protected array $dependencies = array();

    /**
     * The module directory path.
     *
     * @var string
     */
    protected string $plugin_dir = '';

    /**
     * The module URL.
     *
     * @var string
     */
    protected string $plugin_url = '';

    /**
     * Whether the module is initialized.
     *
     * @var bool
     */
    protected bool $initialized = false;

    /**
     * Constructor.
     *
     * @param string $plugin_file The main plugin file path.
     */
    public function __construct( string $plugin_file = '' ) {
        if ( $plugin_file ) {
            $this->plugin_dir = plugin_dir_path( $plugin_file );
            $this->plugin_url = plugin_dir_url( $plugin_file );
        }

        // Register with core if SwiftLMS is loaded.
        add_action( 'swiftlms_register_modules', array( $this, 'register_with_core' ) );
    }

    /**
     * Register this module with the core plugin.
     *
     * @param SwiftLMS $swiftlms The main plugin instance.
     * @return void
     */
    public function register_with_core( SwiftLMS $swiftlms ): void {
        $swiftlms->register_module( $this->get_slug(), $this );
    }

    /**
     * Get the module slug.
     *
     * @return string
     */
    public function get_slug(): string {
        return $this->slug;
    }

    /**
     * Get the module name.
     *
     * @return string
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Get the module version.
     *
     * @return string
     */
    public function get_version(): string {
        return $this->version;
    }

    /**
     * Get the module description.
     *
     * @return string
     */
    public function get_description(): string {
        return $this->description;
    }

    /**
     * Get module dependencies.
     *
     * @return array<string>
     */
    public function get_dependencies(): array {
        return $this->dependencies;
    }

    /**
     * Check if the module is active.
     *
     * @return bool
     */
    public function is_active(): bool {
        // Check if all dependencies are met.
        if ( ! $this->dependencies_met() ) {
            return false;
        }

        // Check if module is enabled in settings.
        $disabled_modules = get_option( 'swiftlms_disabled_modules', array() );
        if ( in_array( $this->slug, $disabled_modules, true ) ) {
            return false;
        }

        return true;
    }

    /**
     * Check if all dependencies are met.
     *
     * @return bool
     */
    public function dependencies_met(): bool {
        if ( empty( $this->dependencies ) ) {
            return true;
        }

        $swiftlms = swiftlms();

        foreach ( $this->dependencies as $dependency ) {
            $module = $swiftlms->get_module( $dependency );
            if ( ! $module || ! $module->is_active() ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get missing dependencies.
     *
     * @return array<string>
     */
    public function get_missing_dependencies(): array {
        $missing   = array();
        $swiftlms  = swiftlms();

        foreach ( $this->dependencies as $dependency ) {
            $module = $swiftlms->get_module( $dependency );
            if ( ! $module || ! $module->is_active() ) {
                $missing[] = $dependency;
            }
        }

        return $missing;
    }

    /**
     * Initialize the module.
     *
     * @return void
     */
    public function init(): void {
        if ( $this->initialized ) {
            return;
        }

        // Register hooks.
        $this->register_hooks();

        // Load module-specific functionality.
        $this->load();

        $this->initialized = true;

        /**
         * Fires after a module has been initialized.
         *
         * @param AbstractModule $module The module instance.
         */
        do_action( 'swiftlms_module_initialized', $this );
        do_action( "swiftlms_module_{$this->slug}_initialized", $this );
    }

    /**
     * Register WordPress hooks.
     *
     * Override in child classes to register module-specific hooks.
     *
     * @return void
     */
    protected function register_hooks(): void {
        // Override in child classes.
    }

    /**
     * Load module functionality.
     *
     * Override in child classes to load module-specific components.
     *
     * @return void
     */
    protected function load(): void {
        // Override in child classes.
    }

    /**
     * Activate the module.
     *
     * @return void
     */
    public function activate(): void {
        // Create database tables if needed.
        $this->create_tables();

        // Set default options.
        $this->set_defaults();

        // Store module version.
        update_option( "swiftlms_{$this->slug}_version", $this->version );

        /**
         * Fires after a module has been activated.
         *
         * @param AbstractModule $module The module instance.
         */
        do_action( 'swiftlms_module_activated', $this );
        do_action( "swiftlms_module_{$this->slug}_activated", $this );
    }

    /**
     * Deactivate the module.
     *
     * @return void
     */
    public function deactivate(): void {
        // Clear scheduled events.
        $this->clear_scheduled_events();

        /**
         * Fires after a module has been deactivated.
         *
         * @param AbstractModule $module The module instance.
         */
        do_action( 'swiftlms_module_deactivated', $this );
        do_action( "swiftlms_module_{$this->slug}_deactivated", $this );
    }

    /**
     * Create database tables for the module.
     *
     * Override in child classes if the module needs custom tables.
     *
     * @return void
     */
    protected function create_tables(): void {
        // Override in child classes.
    }

    /**
     * Set default options for the module.
     *
     * Override in child classes to set module-specific defaults.
     *
     * @return void
     */
    protected function set_defaults(): void {
        // Override in child classes.
    }

    /**
     * Clear scheduled cron events for the module.
     *
     * Override in child classes if the module has scheduled events.
     *
     * @return void
     */
    protected function clear_scheduled_events(): void {
        // Override in child classes.
    }

    /**
     * Get the module's plugin directory path.
     *
     * @return string
     */
    public function plugin_path(): string {
        return $this->plugin_dir;
    }

    /**
     * Get the module's plugin URL.
     *
     * @return string
     */
    public function plugin_url(): string {
        return $this->plugin_url;
    }

    /**
     * Check if the core plugin is active.
     *
     * @return bool
     */
    public static function is_core_active(): bool {
        return function_exists( 'swiftlms' );
    }

    /**
     * Get minimum required core version.
     *
     * Override in child classes to specify version requirement.
     *
     * @return string
     */
    public function get_min_core_version(): string {
        return '1.0.0';
    }

    /**
     * Check if the core version is compatible.
     *
     * @return bool
     */
    public function is_core_compatible(): bool {
        if ( ! self::is_core_active() ) {
            return false;
        }

        return version_compare( SwiftLMS::VERSION, $this->get_min_core_version(), '>=' );
    }
}
