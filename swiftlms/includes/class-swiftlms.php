<?php
/**
 * Main SwiftLMS class.
 *
 * @package SwiftLMS
 */

namespace SwiftLMS;

use SwiftLMS\Core\Course;
use SwiftLMS\Core\Lesson;
use SwiftLMS\Core\Topic;
use SwiftLMS\Core\Enrollment;
use SwiftLMS\Core\Progress;
use SwiftLMS\Database\Schema;
use SwiftLMS\Api\RestApi;
use SwiftLMS\Traits\SingletonTrait;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main plugin class.
 *
 * Orchestrates all plugin functionality and manages module loading.
 */
final class SwiftLMS {

    use SingletonTrait;

    /**
     * Plugin version.
     *
     * @var string
     */
    public const VERSION = '1.0.0';

    /**
     * Minimum required WordPress version.
     *
     * @var string
     */
    public const MIN_WP_VERSION = '6.0';

    /**
     * Minimum required PHP version.
     *
     * @var string
     */
    public const MIN_PHP_VERSION = '7.4';

    /**
     * The loader for registering hooks.
     *
     * @var Loader
     */
    protected Loader $loader;

    /**
     * Registered modules.
     *
     * @var array<string, Abstracts\AbstractModule>
     */
    protected array $modules = array();

    /**
     * Core components.
     *
     * @var array<string, object>
     */
    protected array $components = array();

    /**
     * Whether the plugin has been initialized.
     *
     * @var bool
     */
    protected bool $initialized = false;

    /**
     * Constructor.
     *
     * @return void
     */
    protected function __construct() {
        $this->loader = new Loader();
    }

    /**
     * Initialize the plugin.
     *
     * @return void
     */
    public function init(): void {
        if ( $this->initialized ) {
            return;
        }

        $this->set_locale();
        $this->init_components();
        $this->init_hooks();
        $this->loader->run();

        $this->initialized = true;

        /**
         * Fires after SwiftLMS has been fully initialized.
         *
         * Use this hook to register modules or extend functionality.
         *
         * @param SwiftLMS $swiftlms The main plugin instance.
         */
        do_action( 'swiftlms_loaded', $this );
    }

    /**
     * Set the plugin text domain for translations.
     *
     * @return void
     */
    protected function set_locale(): void {
        $i18n = new I18n();
        $this->loader->add_action( 'plugins_loaded', $i18n, 'load_plugin_textdomain' );
    }

    /**
     * Initialize core components.
     *
     * @return void
     */
    protected function init_components(): void {
        // Initialize database schema.
        $this->components['schema'] = new Schema();

        // Initialize post types.
        $this->components['course']  = new Course();
        $this->components['lesson']  = new Lesson();
        $this->components['topic']   = new Topic();

        // Initialize enrollment system.
        $this->components['enrollment'] = new Enrollment();

        // Initialize progress tracking.
        $this->components['progress'] = new Progress();

        // Initialize REST API.
        $this->components['rest_api'] = new RestApi();
    }

    /**
     * Initialize WordPress hooks.
     *
     * @return void
     */
    protected function init_hooks(): void {
        // Register post types.
        $this->loader->add_action( 'init', $this->components['course'], 'register' );
        $this->loader->add_action( 'init', $this->components['lesson'], 'register' );
        $this->loader->add_action( 'init', $this->components['topic'], 'register' );

        // Initialize REST API.
        $this->loader->add_action( 'rest_api_init', $this->components['rest_api'], 'register_routes' );

        // Admin hooks.
        if ( is_admin() ) {
            $admin = new Admin\Admin();
            $this->loader->add_action( 'admin_menu', $admin, 'add_menu_pages' );
            $this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_styles' );
            $this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_scripts' );
        }

        // Frontend hooks.
        if ( ! is_admin() || wp_doing_ajax() ) {
            $public = new Frontend\Frontend();
            $this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_styles' );
            $this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_scripts' );
            $this->loader->add_filter( 'template_include', $public, 'template_loader' );
        }

        // Module registration hook.
        $this->loader->add_action( 'swiftlms_loaded', $this, 'init_modules', 20 );
    }

    /**
     * Initialize registered modules.
     *
     * @return void
     */
    public function init_modules(): void {
        /**
         * Fires when modules should register themselves.
         *
         * @param SwiftLMS $swiftlms The main plugin instance.
         */
        do_action( 'swiftlms_register_modules', $this );

        foreach ( $this->modules as $module ) {
            if ( $module->is_active() ) {
                $module->init();
            }
        }
    }

    /**
     * Register a module.
     *
     * @param string                     $slug   The module slug.
     * @param Abstracts\AbstractModule   $module The module instance.
     * @return void
     */
    public function register_module( string $slug, Abstracts\AbstractModule $module ): void {
        $this->modules[ $slug ] = $module;

        /**
         * Fires when a module is registered.
         *
         * @param string                   $slug   The module slug.
         * @param Abstracts\AbstractModule $module The module instance.
         */
        do_action( 'swiftlms_module_registered', $slug, $module );
    }

    /**
     * Check if a module is registered and active.
     *
     * @param string $slug The module slug.
     * @return bool True if the module exists and is active.
     */
    public function has_module( string $slug ): bool {
        return isset( $this->modules[ $slug ] ) && $this->modules[ $slug ]->is_active();
    }

    /**
     * Get a registered module.
     *
     * @param string $slug The module slug.
     * @return Abstracts\AbstractModule|null The module instance or null.
     */
    public function get_module( string $slug ): ?Abstracts\AbstractModule {
        return $this->modules[ $slug ] ?? null;
    }

    /**
     * Get all registered modules.
     *
     * @return array<string, Abstracts\AbstractModule>
     */
    public function get_modules(): array {
        return $this->modules;
    }

    /**
     * Get a core component.
     *
     * @param string $name The component name.
     * @return object|null The component instance or null.
     */
    public function get_component( string $name ): ?object {
        return $this->components[ $name ] ?? null;
    }

    /**
     * Get the enrollment component.
     *
     * @return Enrollment
     */
    public function enrollment(): Enrollment {
        return $this->components['enrollment'];
    }

    /**
     * Get the progress component.
     *
     * @return Progress
     */
    public function progress(): Progress {
        return $this->components['progress'];
    }

    /**
     * Get the loader.
     *
     * @return Loader
     */
    public function get_loader(): Loader {
        return $this->loader;
    }

    /**
     * Get the plugin directory path.
     *
     * @return string
     */
    public function plugin_path(): string {
        return SWIFTLMS_PLUGIN_DIR;
    }

    /**
     * Get the plugin URL.
     *
     * @return string
     */
    public function plugin_url(): string {
        return SWIFTLMS_PLUGIN_URL;
    }

    /**
     * Get the template directory path.
     *
     * @return string
     */
    public function template_path(): string {
        return SWIFTLMS_PLUGIN_DIR . 'templates/';
    }
}
