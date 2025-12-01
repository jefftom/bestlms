<?php
/**
 * Post Type interface.
 *
 * @package SwiftLMS\Interfaces
 */

namespace SwiftLMS\Interfaces;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Interface for custom post type implementations.
 */
interface PostTypeInterface {

    /**
     * Get the post type slug.
     *
     * @return string The post type slug.
     */
    public function get_post_type(): string;

    /**
     * Register the post type.
     *
     * @return void
     */
    public function register(): void;

    /**
     * Get the post type labels.
     *
     * @return array<string, string> The post type labels.
     */
    public function get_labels(): array;

    /**
     * Get the post type arguments.
     *
     * @return array<string, mixed> The post type arguments.
     */
    public function get_args(): array;
}
