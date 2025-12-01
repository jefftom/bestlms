<?php
/**
 * Hook/Filter loader class.
 *
 * @package SwiftLMS
 */

namespace SwiftLMS;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers all actions and filters for the plugin.
 *
 * Maintains a list of all hooks that are registered throughout the plugin,
 * and registers them with the WordPress API.
 */
class Loader {

    /**
     * The array of actions registered with WordPress.
     *
     * @var array<int, array{hook: string, component: object|null, callback: string, priority: int, accepted_args: int}>
     */
    protected array $actions = array();

    /**
     * The array of filters registered with WordPress.
     *
     * @var array<int, array{hook: string, component: object|null, callback: string, priority: int, accepted_args: int}>
     */
    protected array $filters = array();

    /**
     * Add a new action to the collection.
     *
     * @param string      $hook          The name of the WordPress action.
     * @param object|null $component     A reference to the instance of the object.
     * @param string      $callback      The name of the function definition on the $component.
     * @param int         $priority      Optional. The priority at which the function should be fired. Default 10.
     * @param int         $accepted_args Optional. The number of arguments the function accepts. Default 1.
     * @return self
     */
    public function add_action( string $hook, ?object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): self {
        $this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
        return $this;
    }

    /**
     * Add a new filter to the collection.
     *
     * @param string      $hook          The name of the WordPress filter.
     * @param object|null $component     A reference to the instance of the object.
     * @param string      $callback      The name of the function definition on the $component.
     * @param int         $priority      Optional. The priority at which the function should be fired. Default 10.
     * @param int         $accepted_args Optional. The number of arguments the function accepts. Default 1.
     * @return self
     */
    public function add_filter( string $hook, ?object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): self {
        $this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
        return $this;
    }

    /**
     * Remove an action from the collection.
     *
     * @param string      $hook      The name of the WordPress action.
     * @param object|null $component A reference to the instance of the object.
     * @param string      $callback  The name of the function.
     * @param int         $priority  The priority of the action.
     * @return bool Whether the action was removed.
     */
    public function remove_action( string $hook, ?object $component, string $callback, int $priority = 10 ): bool {
        if ( $component ) {
            return remove_action( $hook, array( $component, $callback ), $priority );
        }
        return remove_action( $hook, $callback, $priority );
    }

    /**
     * Remove a filter from the collection.
     *
     * @param string      $hook      The name of the WordPress filter.
     * @param object|null $component A reference to the instance of the object.
     * @param string      $callback  The name of the function.
     * @param int         $priority  The priority of the filter.
     * @return bool Whether the filter was removed.
     */
    public function remove_filter( string $hook, ?object $component, string $callback, int $priority = 10 ): bool {
        if ( $component ) {
            return remove_filter( $hook, array( $component, $callback ), $priority );
        }
        return remove_filter( $hook, $callback, $priority );
    }

    /**
     * Add a hook to the collection.
     *
     * @param array<int, array{hook: string, component: object|null, callback: string, priority: int, accepted_args: int}> $hooks
     *     The collection of hooks.
     * @param string      $hook          The name of the hook.
     * @param object|null $component     A reference to the instance of the object.
     * @param string      $callback      The name of the callback function.
     * @param int         $priority      The priority of the hook.
     * @param int         $accepted_args The number of accepted arguments.
     * @return array<int, array{hook: string, component: object|null, callback: string, priority: int, accepted_args: int}>
     */
    protected function add( array $hooks, string $hook, ?object $component, string $callback, int $priority, int $accepted_args ): array {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        );

        return $hooks;
    }

    /**
     * Register the filters and actions with WordPress.
     *
     * @return void
     */
    public function run(): void {
        foreach ( $this->filters as $hook ) {
            $callback = $hook['component'] ? array( $hook['component'], $hook['callback'] ) : $hook['callback'];
            add_filter( $hook['hook'], $callback, $hook['priority'], $hook['accepted_args'] );
        }

        foreach ( $this->actions as $hook ) {
            $callback = $hook['component'] ? array( $hook['component'], $hook['callback'] ) : $hook['callback'];
            add_action( $hook['hook'], $callback, $hook['priority'], $hook['accepted_args'] );
        }
    }
}
