<?php
/**
 * Abstract Post Type class.
 *
 * @package SwiftLMS\Abstracts
 */

namespace SwiftLMS\Abstracts;

use SwiftLMS\Interfaces\PostTypeInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Base class for custom post types.
 *
 * Provides common functionality for registering and managing
 * custom post types in SwiftLMS.
 */
abstract class AbstractPostType implements PostTypeInterface {

    /**
     * The post type slug.
     *
     * @var string
     */
    protected string $post_type = '';

    /**
     * The singular name.
     *
     * @var string
     */
    protected string $singular = '';

    /**
     * The plural name.
     *
     * @var string
     */
    protected string $plural = '';

    /**
     * Whether the post type supports hierarchy.
     *
     * @var bool
     */
    protected bool $hierarchical = false;

    /**
     * Whether the post type is public.
     *
     * @var bool
     */
    protected bool $public = true;

    /**
     * The menu icon.
     *
     * @var string
     */
    protected string $menu_icon = 'dashicons-admin-post';

    /**
     * Menu position.
     *
     * @var int
     */
    protected int $menu_position = 25;

    /**
     * Supported features.
     *
     * @var array<string>
     */
    protected array $supports = array( 'title', 'editor', 'thumbnail', 'excerpt', 'author' );

    /**
     * Get the post type slug.
     *
     * @return string
     */
    public function get_post_type(): string {
        return $this->post_type;
    }

    /**
     * Register the post type.
     *
     * @return void
     */
    public function register(): void {
        if ( post_type_exists( $this->post_type ) ) {
            return;
        }

        $args = $this->get_args();
        register_post_type( $this->post_type, $args );

        // Register taxonomies.
        $this->register_taxonomies();

        // Register meta boxes.
        add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );

        // Save meta data.
        add_action( 'save_post_' . $this->post_type, array( $this, 'save_meta' ), 10, 2 );

        // Flush rewrite rules if needed.
        if ( get_transient( 'swiftlms_flush_rewrite_rules' ) ) {
            flush_rewrite_rules();
            delete_transient( 'swiftlms_flush_rewrite_rules' );
        }
    }

    /**
     * Get the post type labels.
     *
     * @return array<string, string>
     */
    public function get_labels(): array {
        return array(
            'name'                  => $this->plural,
            'singular_name'         => $this->singular,
            'menu_name'             => $this->plural,
            'name_admin_bar'        => $this->singular,
            'archives'              => sprintf(
                /* translators: %s: Post type singular name */
                __( '%s Archives', 'swiftlms' ),
                $this->singular
            ),
            'attributes'            => sprintf(
                /* translators: %s: Post type singular name */
                __( '%s Attributes', 'swiftlms' ),
                $this->singular
            ),
            'parent_item_colon'     => sprintf(
                /* translators: %s: Post type singular name */
                __( 'Parent %s:', 'swiftlms' ),
                $this->singular
            ),
            'all_items'             => sprintf(
                /* translators: %s: Post type plural name */
                __( 'All %s', 'swiftlms' ),
                $this->plural
            ),
            'add_new_item'          => sprintf(
                /* translators: %s: Post type singular name */
                __( 'Add New %s', 'swiftlms' ),
                $this->singular
            ),
            'add_new'               => __( 'Add New', 'swiftlms' ),
            'new_item'              => sprintf(
                /* translators: %s: Post type singular name */
                __( 'New %s', 'swiftlms' ),
                $this->singular
            ),
            'edit_item'             => sprintf(
                /* translators: %s: Post type singular name */
                __( 'Edit %s', 'swiftlms' ),
                $this->singular
            ),
            'update_item'           => sprintf(
                /* translators: %s: Post type singular name */
                __( 'Update %s', 'swiftlms' ),
                $this->singular
            ),
            'view_item'             => sprintf(
                /* translators: %s: Post type singular name */
                __( 'View %s', 'swiftlms' ),
                $this->singular
            ),
            'view_items'            => sprintf(
                /* translators: %s: Post type plural name */
                __( 'View %s', 'swiftlms' ),
                $this->plural
            ),
            'search_items'          => sprintf(
                /* translators: %s: Post type plural name */
                __( 'Search %s', 'swiftlms' ),
                $this->plural
            ),
            'not_found'             => __( 'Not found', 'swiftlms' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'swiftlms' ),
            'featured_image'        => __( 'Featured Image', 'swiftlms' ),
            'set_featured_image'    => __( 'Set featured image', 'swiftlms' ),
            'remove_featured_image' => __( 'Remove featured image', 'swiftlms' ),
            'use_featured_image'    => __( 'Use as featured image', 'swiftlms' ),
            'insert_into_item'      => sprintf(
                /* translators: %s: Post type singular name */
                __( 'Insert into %s', 'swiftlms' ),
                strtolower( $this->singular )
            ),
            'uploaded_to_this_item' => sprintf(
                /* translators: %s: Post type singular name */
                __( 'Uploaded to this %s', 'swiftlms' ),
                strtolower( $this->singular )
            ),
            'items_list'            => sprintf(
                /* translators: %s: Post type plural name */
                __( '%s list', 'swiftlms' ),
                $this->plural
            ),
            'items_list_navigation' => sprintf(
                /* translators: %s: Post type plural name */
                __( '%s list navigation', 'swiftlms' ),
                $this->plural
            ),
            'filter_items_list'     => sprintf(
                /* translators: %s: Post type plural name */
                __( 'Filter %s list', 'swiftlms' ),
                strtolower( $this->plural )
            ),
        );
    }

    /**
     * Get the post type arguments.
     *
     * @return array<string, mixed>
     */
    public function get_args(): array {
        $args = array(
            'label'               => $this->plural,
            'labels'              => $this->get_labels(),
            'description'         => '',
            'public'              => $this->public,
            'publicly_queryable'  => $this->public,
            'show_ui'             => true,
            'show_in_menu'        => 'swiftlms',
            'show_in_nav_menus'   => true,
            'show_in_admin_bar'   => true,
            'show_in_rest'        => true,
            'rest_base'           => $this->post_type,
            'rest_namespace'      => 'swiftlms/v1',
            'menu_icon'           => $this->menu_icon,
            'menu_position'       => $this->menu_position,
            'capability_type'     => 'post',
            'hierarchical'        => $this->hierarchical,
            'supports'            => $this->supports,
            'has_archive'         => $this->public,
            'rewrite'             => $this->get_rewrite_args(),
            'query_var'           => true,
            'can_export'          => true,
            'delete_with_user'    => false,
        );

        /**
         * Filter the post type arguments.
         *
         * @param array  $args      The post type arguments.
         * @param string $post_type The post type slug.
         */
        return apply_filters( "swiftlms_{$this->post_type}_post_type_args", $args, $this->post_type );
    }

    /**
     * Get rewrite arguments.
     *
     * @return array<string, mixed>|false
     */
    protected function get_rewrite_args() {
        if ( ! $this->public ) {
            return false;
        }

        $slug = get_option( "swiftlms_{$this->post_type}_slug", $this->post_type );

        return array(
            'slug'       => $slug,
            'with_front' => false,
            'pages'      => true,
            'feeds'      => true,
        );
    }

    /**
     * Register taxonomies for this post type.
     *
     * Override in child classes to register taxonomies.
     *
     * @return void
     */
    protected function register_taxonomies(): void {
        // Override in child classes.
    }

    /**
     * Register meta boxes.
     *
     * Override in child classes to add meta boxes.
     *
     * @return void
     */
    public function register_meta_boxes(): void {
        // Override in child classes.
    }

    /**
     * Save meta data.
     *
     * @param int      $post_id The post ID.
     * @param \WP_Post $post    The post object.
     * @return void
     */
    public function save_meta( int $post_id, \WP_Post $post ): void {
        // Verify nonce.
        $nonce_key = "swiftlms_{$this->post_type}_nonce";
        if ( ! isset( $_POST[ $nonce_key ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ $nonce_key ] ), "swiftlms_save_{$this->post_type}" ) ) {
            return;
        }

        // Check autosave.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check permissions.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Save the meta data.
        $this->save_post_meta( $post_id, $post );
    }

    /**
     * Save post meta data.
     *
     * Override in child classes to save specific meta fields.
     *
     * @param int      $post_id The post ID.
     * @param \WP_Post $post    The post object.
     * @return void
     */
    protected function save_post_meta( int $post_id, \WP_Post $post ): void {
        // Override in child classes.
    }

    /**
     * Get a post by ID.
     *
     * @param int $post_id The post ID.
     * @return \WP_Post|null The post object or null.
     */
    public function get( int $post_id ): ?\WP_Post {
        $post = get_post( $post_id );

        if ( ! $post || $post->post_type !== $this->post_type ) {
            return null;
        }

        return $post;
    }

    /**
     * Query posts of this type.
     *
     * @param array $args Query arguments.
     * @return \WP_Post[] Array of posts.
     */
    public function query( array $args = array() ): array {
        $defaults = array(
            'post_type'      => $this->post_type,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        );

        $args = wp_parse_args( $args, $defaults );

        return get_posts( $args );
    }

    /**
     * Create a new post.
     *
     * @param array $data The post data.
     * @return int|\WP_Error The post ID or error.
     */
    public function create( array $data ) {
        $defaults = array(
            'post_type'   => $this->post_type,
            'post_status' => 'draft',
        );

        $data = wp_parse_args( $data, $defaults );

        return wp_insert_post( $data, true );
    }

    /**
     * Update a post.
     *
     * @param int   $post_id The post ID.
     * @param array $data    The post data.
     * @return int|\WP_Error The post ID or error.
     */
    public function update( int $post_id, array $data ) {
        $data['ID']        = $post_id;
        $data['post_type'] = $this->post_type;

        return wp_update_post( $data, true );
    }

    /**
     * Delete a post.
     *
     * @param int  $post_id      The post ID.
     * @param bool $force_delete Whether to bypass trash.
     * @return \WP_Post|false|null The deleted post or false/null on failure.
     */
    public function delete( int $post_id, bool $force_delete = false ) {
        $post = $this->get( $post_id );

        if ( ! $post ) {
            return false;
        }

        return wp_delete_post( $post_id, $force_delete );
    }
}
