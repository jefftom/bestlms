<?php
/**
 * Hooks trait.
 *
 * @package SwiftLMS\Traits
 */

namespace SwiftLMS\Traits;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provides hook management utilities.
 *
 * Helper methods for registering and managing WordPress hooks
 * within SwiftLMS classes.
 */
trait HooksTrait {

    /**
     * Registered action hooks.
     *
     * @var array<string, array{callback: callable, priority: int, accepted_args: int}>
     */
    protected array $registered_actions = array();

    /**
     * Registered filter hooks.
     *
     * @var array<string, array{callback: callable, priority: int, accepted_args: int}>
     */
    protected array $registered_filters = array();

    /**
     * Add an action hook.
     *
     * @param string   $hook          The action hook name.
     * @param callable $callback      The callback function.
     * @param int      $priority      Optional. Priority. Default 10.
     * @param int      $accepted_args Optional. Number of accepted arguments. Default 1.
     * @return void
     */
    protected function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
        add_action( $hook, $callback, $priority, $accepted_args );

        $this->registered_actions[ $hook ] = array(
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        );
    }

    /**
     * Add a filter hook.
     *
     * @param string   $hook          The filter hook name.
     * @param callable $callback      The callback function.
     * @param int      $priority      Optional. Priority. Default 10.
     * @param int      $accepted_args Optional. Number of accepted arguments. Default 1.
     * @return void
     */
    protected function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
        add_filter( $hook, $callback, $priority, $accepted_args );

        $this->registered_filters[ $hook ] = array(
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        );
    }

    /**
     * Remove an action hook.
     *
     * @param string   $hook     The action hook name.
     * @param callable $callback The callback function.
     * @param int      $priority Optional. Priority. Default 10.
     * @return bool Whether the function was removed.
     */
    protected function remove_action( string $hook, callable $callback, int $priority = 10 ): bool {
        $result = remove_action( $hook, $callback, $priority );

        if ( $result && isset( $this->registered_actions[ $hook ] ) ) {
            unset( $this->registered_actions[ $hook ] );
        }

        return $result;
    }

    /**
     * Remove a filter hook.
     *
     * @param string   $hook     The filter hook name.
     * @param callable $callback The callback function.
     * @param int      $priority Optional. Priority. Default 10.
     * @return bool Whether the function was removed.
     */
    protected function remove_filter( string $hook, callable $callback, int $priority = 10 ): bool {
        $result = remove_filter( $hook, $callback, $priority );

        if ( $result && isset( $this->registered_filters[ $hook ] ) ) {
            unset( $this->registered_filters[ $hook ] );
        }

        return $result;
    }

    /**
     * Execute an action hook.
     *
     * @param string $hook  The action hook name.
     * @param mixed  ...$args Arguments to pass to the hook.
     * @return void
     */
    protected function do_action( string $hook, ...$args ): void {
        do_action( $hook, ...$args );
    }

    /**
     * Apply filters to a value.
     *
     * @param string $hook  The filter hook name.
     * @param mixed  $value The value to filter.
     * @param mixed  ...$args Additional arguments.
     * @return mixed The filtered value.
     */
    protected function apply_filters( string $hook, $value, ...$args ) {
        return apply_filters( $hook, $value, ...$args );
    }

    /**
     * Check if an action has been fired.
     *
     * @param string $hook The action hook name.
     * @return int The number of times the action has fired.
     */
    protected function did_action( string $hook ): int {
        return did_action( $hook );
    }

    /**
     * Check if a hook has any callbacks registered.
     *
     * @param string        $hook     The hook name.
     * @param callable|bool $callback Optional. The callback to check for. Default false.
     * @return bool|int
     */
    protected function has_action( string $hook, $callback = false ) {
        return has_action( $hook, $callback );
    }

    /**
     * Check if a filter has any callbacks registered.
     *
     * @param string        $hook     The hook name.
     * @param callable|bool $callback Optional. The callback to check for. Default false.
     * @return bool|int
     */
    protected function has_filter( string $hook, $callback = false ) {
        return has_filter( $hook, $callback );
    }

    /**
     * Remove all registered hooks.
     *
     * Useful for cleanup during testing.
     *
     * @return void
     */
    protected function remove_all_hooks(): void {
        foreach ( $this->registered_actions as $hook => $data ) {
            remove_action( $hook, $data['callback'], $data['priority'] );
        }

        foreach ( $this->registered_filters as $hook => $data ) {
            remove_filter( $hook, $data['callback'], $data['priority'] );
        }

        $this->registered_actions = array();
        $this->registered_filters = array();
    }
}
