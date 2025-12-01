<?php
/**
 * WooCommerce Integration Module
 *
 * @package SwiftLMS\Modules\WooCommerce
 * @since 1.0.0
 */

namespace SwiftLMS\Modules\WooCommerce;

use SwiftLMS\Abstracts\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Module class.
 */
class WooCommerceModule extends AbstractModule {

    /**
     * Get module ID.
     *
     * @return string
     */
    public function get_id(): string {
        return 'woocommerce';
    }

    /**
     * Get module name.
     *
     * @return string
     */
    public function get_name(): string {
        return __( 'WooCommerce Integration', 'swiftlms' );
    }

    /**
     * Get module description.
     *
     * @return string
     */
    public function get_description(): string {
        return __( 'Sell courses through WooCommerce with support for subscriptions and memberships.', 'swiftlms' );
    }

    /**
     * Get module version.
     *
     * @return string
     */
    public function get_version(): string {
        return '1.0.0';
    }

    /**
     * Get module dependencies.
     *
     * @return array
     */
    public function get_dependencies(): array {
        return array( 'woocommerce' );
    }

    /**
     * Check if dependencies are met.
     *
     * @return bool
     */
    public function check_dependencies(): bool {
        return class_exists( 'WooCommerce' );
    }

    /**
     * Initialize module.
     *
     * @return void
     */
    public function init(): void {
        if ( ! $this->check_dependencies() ) {
            add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
            return;
        }

        // Product meta box for course linking.
        add_action( 'add_meta_boxes', array( $this, 'add_product_meta_box' ) );
        add_action( 'save_post_product', array( $this, 'save_product_meta' ) );

        // Order hooks.
        add_action( 'woocommerce_order_status_completed', array( $this, 'handle_order_completed' ) );
        add_action( 'woocommerce_order_status_processing', array( $this, 'handle_order_processing' ) );
        add_action( 'woocommerce_order_status_refunded', array( $this, 'handle_order_refunded' ) );
        add_action( 'woocommerce_order_status_cancelled', array( $this, 'handle_order_cancelled' ) );

        // Subscription hooks (WooCommerce Subscriptions).
        add_action( 'woocommerce_subscription_status_active', array( $this, 'handle_subscription_active' ) );
        add_action( 'woocommerce_subscription_status_expired', array( $this, 'handle_subscription_expired' ) );
        add_action( 'woocommerce_subscription_status_cancelled', array( $this, 'handle_subscription_cancelled' ) );
        add_action( 'woocommerce_subscription_status_on-hold', array( $this, 'handle_subscription_on_hold' ) );

        // Display course access on product page.
        add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'display_course_info' ) );

        // My Account integration.
        add_filter( 'woocommerce_account_menu_items', array( $this, 'add_account_menu_item' ) );
        add_action( 'woocommerce_account_courses_endpoint', array( $this, 'courses_endpoint_content' ) );
        add_action( 'init', array( $this, 'add_endpoints' ) );

        // Prevent duplicate enrollment.
        add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_cart_addition' ), 10, 3 );

        // Show enrollment status in cart.
        add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_course' ), 10, 2 );

        // Admin columns.
        add_filter( 'manage_edit-product_columns', array( $this, 'add_product_columns' ) );
        add_action( 'manage_product_posts_custom_column', array( $this, 'render_product_columns' ), 10, 2 );
    }

    /**
     * Activate module.
     *
     * @return void
     */
    public function activate(): void {
        $this->add_endpoints();
        flush_rewrite_rules();
    }

    /**
     * Deactivate module.
     *
     * @return void
     */
    public function deactivate(): void {
        flush_rewrite_rules();
    }

    /**
     * Show dependency notice.
     *
     * @return void
     */
    public function dependency_notice(): void {
        ?>
        <div class="notice notice-warning">
            <p>
                <?php
                printf(
                    /* translators: %s: WooCommerce plugin link */
                    esc_html__( 'SwiftLMS WooCommerce integration requires %s to be installed and active.', 'swiftlms' ),
                    '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Add product meta box.
     *
     * @return void
     */
    public function add_product_meta_box(): void {
        add_meta_box(
            'sfls_product_course',
            __( 'SwiftLMS Course Access', 'swiftlms' ),
            array( $this, 'render_product_meta_box' ),
            'product',
            'side',
            'high'
        );
    }

    /**
     * Render product meta box.
     *
     * @param \WP_Post $post Current post.
     * @return void
     */
    public function render_product_meta_box( $post ): void {
        wp_nonce_field( 'sfls_product_course', 'sfls_product_course_nonce' );

        $course_ids   = get_post_meta( $post->ID, '_sfls_course_ids', true ) ?: array();
        $access_type  = get_post_meta( $post->ID, '_sfls_access_type', true ) ?: 'lifetime';
        $access_days  = get_post_meta( $post->ID, '_sfls_access_days', true ) ?: '';

        $courses = get_posts( array(
            'post_type'      => 'sfls_course',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ) );
        ?>
        <p>
            <label for="sfls_course_ids"><strong><?php esc_html_e( 'Linked Courses', 'swiftlms' ); ?></strong></label>
            <select name="sfls_course_ids[]" id="sfls_course_ids" multiple style="width: 100%; height: 150px;">
                <?php foreach ( $courses as $course ) : ?>
                    <option value="<?php echo esc_attr( $course->ID ); ?>"
                            <?php echo in_array( $course->ID, (array) $course_ids, true ) ? 'selected' : ''; ?>>
                        <?php echo esc_html( $course->post_title ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="description"><?php esc_html_e( 'Hold Ctrl/Cmd to select multiple courses.', 'swiftlms' ); ?></span>
        </p>

        <p>
            <label for="sfls_access_type"><strong><?php esc_html_e( 'Access Type', 'swiftlms' ); ?></strong></label>
            <select name="sfls_access_type" id="sfls_access_type" style="width: 100%;">
                <option value="lifetime" <?php selected( $access_type, 'lifetime' ); ?>><?php esc_html_e( 'Lifetime Access', 'swiftlms' ); ?></option>
                <option value="limited" <?php selected( $access_type, 'limited' ); ?>><?php esc_html_e( 'Limited Time', 'swiftlms' ); ?></option>
                <option value="subscription" <?php selected( $access_type, 'subscription' ); ?>><?php esc_html_e( 'Subscription-based', 'swiftlms' ); ?></option>
            </select>
        </p>

        <p id="sfls_access_days_wrap" style="<?php echo 'limited' !== $access_type ? 'display:none;' : ''; ?>">
            <label for="sfls_access_days"><strong><?php esc_html_e( 'Access Duration (days)', 'swiftlms' ); ?></strong></label>
            <input type="number" name="sfls_access_days" id="sfls_access_days" value="<?php echo esc_attr( $access_days ); ?>"
                   min="1" style="width: 100%;">
        </p>

        <script>
        jQuery(function($) {
            $('#sfls_access_type').on('change', function() {
                $('#sfls_access_days_wrap').toggle($(this).val() === 'limited');
            });
        });
        </script>
        <?php
    }

    /**
     * Save product meta.
     *
     * @param int $post_id Post ID.
     * @return void
     */
    public function save_product_meta( int $post_id ): void {
        if ( ! isset( $_POST['sfls_product_course_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sfls_product_course_nonce'] ) ), 'sfls_product_course' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Course IDs.
        if ( isset( $_POST['sfls_course_ids'] ) ) {
            $course_ids = array_map( 'absint', wp_unslash( $_POST['sfls_course_ids'] ) );
            update_post_meta( $post_id, '_sfls_course_ids', $course_ids );
        } else {
            delete_post_meta( $post_id, '_sfls_course_ids' );
        }

        // Access type.
        if ( isset( $_POST['sfls_access_type'] ) ) {
            update_post_meta( $post_id, '_sfls_access_type', sanitize_text_field( wp_unslash( $_POST['sfls_access_type'] ) ) );
        }

        // Access days.
        if ( isset( $_POST['sfls_access_days'] ) ) {
            update_post_meta( $post_id, '_sfls_access_days', absint( $_POST['sfls_access_days'] ) );
        }
    }

    /**
     * Handle order completed.
     *
     * @param int $order_id Order ID.
     * @return void
     */
    public function handle_order_completed( int $order_id ): void {
        $this->process_order_enrollment( $order_id );
    }

    /**
     * Handle order processing (for virtual products).
     *
     * @param int $order_id Order ID.
     * @return void
     */
    public function handle_order_processing( int $order_id ): void {
        $order = wc_get_order( $order_id );

        // Only process if order only contains virtual products.
        if ( $order && $this->order_is_virtual( $order ) ) {
            $this->process_order_enrollment( $order_id );
        }
    }

    /**
     * Check if order contains only virtual products.
     *
     * @param \WC_Order $order Order object.
     * @return bool
     */
    private function order_is_virtual( \WC_Order $order ): bool {
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( $product && ! $product->is_virtual() ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Process order enrollment.
     *
     * @param int $order_id Order ID.
     * @return void
     */
    private function process_order_enrollment( int $order_id ): void {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        // Check if already processed.
        if ( $order->get_meta( '_sfls_enrollment_processed' ) ) {
            return;
        }

        $user_id = $order->get_user_id();

        if ( ! $user_id ) {
            return;
        }

        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $course_ids = get_post_meta( $product_id, '_sfls_course_ids', true );

            if ( empty( $course_ids ) ) {
                continue;
            }

            $access_type = get_post_meta( $product_id, '_sfls_access_type', true ) ?: 'lifetime';
            $access_days = get_post_meta( $product_id, '_sfls_access_days', true );

            // Calculate expiry date.
            $expiry = null;
            if ( 'limited' === $access_type && $access_days ) {
                $expiry = gmdate( 'Y-m-d H:i:s', strtotime( "+{$access_days} days" ) );
            }

            foreach ( $course_ids as $course_id ) {
                \SwiftLMS\Core\Enrollment::enroll(
                    $user_id,
                    $course_id,
                    array(
                        'source'     => 'woocommerce',
                        'order_id'   => $order_id,
                        'product_id' => $product_id,
                        'expiry'     => $expiry,
                    )
                );
            }
        }

        // Mark as processed.
        $order->update_meta_data( '_sfls_enrollment_processed', current_time( 'mysql' ) );
        $order->save();

        /**
         * Fires after WooCommerce order enrollments are processed.
         *
         * @param int       $order_id Order ID.
         * @param \WC_Order $order    Order object.
         */
        do_action( 'swiftlms_woocommerce_enrollment_processed', $order_id, $order );
    }

    /**
     * Handle order refunded.
     *
     * @param int $order_id Order ID.
     * @return void
     */
    public function handle_order_refunded( int $order_id ): void {
        $this->revoke_order_access( $order_id, 'refunded' );
    }

    /**
     * Handle order cancelled.
     *
     * @param int $order_id Order ID.
     * @return void
     */
    public function handle_order_cancelled( int $order_id ): void {
        $this->revoke_order_access( $order_id, 'cancelled' );
    }

    /**
     * Revoke access for an order.
     *
     * @param int    $order_id Order ID.
     * @param string $reason   Reason for revocation.
     * @return void
     */
    private function revoke_order_access( int $order_id, string $reason = '' ): void {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        $user_id = $order->get_user_id();

        if ( ! $user_id ) {
            return;
        }

        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $course_ids = get_post_meta( $product_id, '_sfls_course_ids', true );

            if ( empty( $course_ids ) ) {
                continue;
            }

            foreach ( $course_ids as $course_id ) {
                \SwiftLMS\Core\Enrollment::update_status( $user_id, $course_id, 'cancelled' );
            }
        }

        /**
         * Fires after WooCommerce order access is revoked.
         *
         * @param int    $order_id Order ID.
         * @param string $reason   Reason for revocation.
         */
        do_action( 'swiftlms_woocommerce_access_revoked', $order_id, $reason );
    }

    /**
     * Handle subscription activated.
     *
     * @param \WC_Subscription $subscription Subscription object.
     * @return void
     */
    public function handle_subscription_active( $subscription ): void {
        if ( ! class_exists( 'WC_Subscription' ) ) {
            return;
        }

        $user_id = $subscription->get_user_id();

        foreach ( $subscription->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $course_ids = get_post_meta( $product_id, '_sfls_course_ids', true );

            if ( empty( $course_ids ) ) {
                continue;
            }

            foreach ( $course_ids as $course_id ) {
                // Check if already enrolled.
                $enrollment = \SwiftLMS\Core\Enrollment::get( $user_id, $course_id );

                if ( $enrollment ) {
                    // Reactivate if was cancelled/expired.
                    \SwiftLMS\Core\Enrollment::update_status( $user_id, $course_id, 'active' );
                } else {
                    // New enrollment.
                    \SwiftLMS\Core\Enrollment::enroll(
                        $user_id,
                        $course_id,
                        array(
                            'source'          => 'woocommerce_subscription',
                            'subscription_id' => $subscription->get_id(),
                            'product_id'      => $product_id,
                        )
                    );
                }
            }
        }
    }

    /**
     * Handle subscription expired.
     *
     * @param \WC_Subscription $subscription Subscription object.
     * @return void
     */
    public function handle_subscription_expired( $subscription ): void {
        $this->handle_subscription_inactive( $subscription, 'expired' );
    }

    /**
     * Handle subscription cancelled.
     *
     * @param \WC_Subscription $subscription Subscription object.
     * @return void
     */
    public function handle_subscription_cancelled( $subscription ): void {
        $this->handle_subscription_inactive( $subscription, 'cancelled' );
    }

    /**
     * Handle subscription on hold.
     *
     * @param \WC_Subscription $subscription Subscription object.
     * @return void
     */
    public function handle_subscription_on_hold( $subscription ): void {
        $this->handle_subscription_inactive( $subscription, 'on_hold' );
    }

    /**
     * Handle subscription becoming inactive.
     *
     * @param \WC_Subscription $subscription Subscription object.
     * @param string           $status       New status.
     * @return void
     */
    private function handle_subscription_inactive( $subscription, string $status ): void {
        if ( ! class_exists( 'WC_Subscription' ) ) {
            return;
        }

        $user_id = $subscription->get_user_id();

        foreach ( $subscription->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $course_ids = get_post_meta( $product_id, '_sfls_course_ids', true );

            if ( empty( $course_ids ) ) {
                continue;
            }

            $enrollment_status = 'on_hold' === $status ? 'expired' : $status;

            foreach ( $course_ids as $course_id ) {
                \SwiftLMS\Core\Enrollment::update_status( $user_id, $course_id, $enrollment_status );
            }
        }
    }

    /**
     * Display course info on product page.
     *
     * @return void
     */
    public function display_course_info(): void {
        global $product;

        $course_ids = get_post_meta( $product->get_id(), '_sfls_course_ids', true );

        if ( empty( $course_ids ) ) {
            return;
        }

        echo '<div class="sfls-product-courses">';
        echo '<h4>' . esc_html__( 'Courses Included:', 'swiftlms' ) . '</h4>';
        echo '<ul>';

        foreach ( $course_ids as $course_id ) {
            $course = get_post( $course_id );
            if ( $course ) {
                echo '<li><a href="' . esc_url( get_permalink( $course_id ) ) . '">' . esc_html( $course->post_title ) . '</a></li>';
            }
        }

        echo '</ul>';
        echo '</div>';
    }

    /**
     * Add My Account menu item.
     *
     * @param array $items Menu items.
     * @return array
     */
    public function add_account_menu_item( array $items ): array {
        $new_items = array();

        foreach ( $items as $key => $label ) {
            $new_items[ $key ] = $label;

            if ( 'orders' === $key ) {
                $new_items['courses'] = __( 'My Courses', 'swiftlms' );
            }
        }

        return $new_items;
    }

    /**
     * Add rewrite endpoints.
     *
     * @return void
     */
    public function add_endpoints(): void {
        add_rewrite_endpoint( 'courses', EP_ROOT | EP_PAGES );
    }

    /**
     * Courses endpoint content.
     *
     * @return void
     */
    public function courses_endpoint_content(): void {
        $user_id     = get_current_user_id();
        $enrollments = \SwiftLMS\Core\Enrollment::get_user_enrollments( $user_id, 'active' );

        if ( empty( $enrollments ) ) {
            echo '<p>' . esc_html__( 'You are not enrolled in any courses.', 'swiftlms' ) . '</p>';
            return;
        }

        echo '<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Course', 'swiftlms' ) . '</th>';
        echo '<th>' . esc_html__( 'Progress', 'swiftlms' ) . '</th>';
        echo '<th>' . esc_html__( 'Status', 'swiftlms' ) . '</th>';
        echo '<th>' . esc_html__( 'Actions', 'swiftlms' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $enrollments as $enrollment ) {
            $course   = get_post( $enrollment->course_id );
            $progress = \SwiftLMS\Core\Progress::get_course_progress( $user_id, $enrollment->course_id );

            echo '<tr>';
            echo '<td data-title="' . esc_attr__( 'Course', 'swiftlms' ) . '">';
            echo esc_html( $course ? $course->post_title : __( 'Unknown Course', 'swiftlms' ) );
            echo '</td>';
            echo '<td data-title="' . esc_attr__( 'Progress', 'swiftlms' ) . '">';
            echo '<div class="sfls-progress-bar" style="width:100px;height:10px;background:#eee;border-radius:5px;">';
            echo '<div style="width:' . esc_attr( $progress['percentage'] ) . '%;height:100%;background:#0073aa;border-radius:5px;"></div>';
            echo '</div>';
            echo '<span>' . esc_html( $progress['percentage'] ) . '%</span>';
            echo '</td>';
            echo '<td data-title="' . esc_attr__( 'Status', 'swiftlms' ) . '">';
            echo '<span class="sfls-status sfls-status-' . esc_attr( $enrollment->status ) . '">';
            echo esc_html( ucfirst( $enrollment->status ) );
            echo '</span>';
            echo '</td>';
            echo '<td data-title="' . esc_attr__( 'Actions', 'swiftlms' ) . '">';
            echo '<a href="' . esc_url( get_permalink( $enrollment->course_id ) ) . '" class="button">';
            echo esc_html__( 'Continue', 'swiftlms' );
            echo '</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * Validate cart addition to prevent duplicate enrollment.
     *
     * @param bool $passed     Validation result.
     * @param int  $product_id Product ID.
     * @param int  $quantity   Quantity.
     * @return bool
     */
    public function validate_cart_addition( bool $passed, int $product_id, int $quantity ): bool {
        if ( ! is_user_logged_in() ) {
            return $passed;
        }

        $course_ids = get_post_meta( $product_id, '_sfls_course_ids', true );

        if ( empty( $course_ids ) ) {
            return $passed;
        }

        $user_id = get_current_user_id();

        foreach ( $course_ids as $course_id ) {
            if ( \SwiftLMS\Core\Enrollment::is_enrolled( $user_id, $course_id ) ) {
                $course = get_post( $course_id );
                wc_add_notice(
                    sprintf(
                        /* translators: %s: course title */
                        __( 'You are already enrolled in "%s".', 'swiftlms' ),
                        $course ? $course->post_title : __( 'this course', 'swiftlms' )
                    ),
                    'error'
                );
                return false;
            }
        }

        return $passed;
    }

    /**
     * Display course info in cart.
     *
     * @param array $item_data Item data.
     * @param array $cart_item Cart item.
     * @return array
     */
    public function display_cart_item_course( array $item_data, array $cart_item ): array {
        $product_id = $cart_item['product_id'];
        $course_ids = get_post_meta( $product_id, '_sfls_course_ids', true );

        if ( empty( $course_ids ) ) {
            return $item_data;
        }

        $course_names = array();
        foreach ( $course_ids as $course_id ) {
            $course = get_post( $course_id );
            if ( $course ) {
                $course_names[] = $course->post_title;
            }
        }

        if ( ! empty( $course_names ) ) {
            $item_data[] = array(
                'key'   => __( 'Course Access', 'swiftlms' ),
                'value' => implode( ', ', $course_names ),
            );
        }

        return $item_data;
    }

    /**
     * Add product columns.
     *
     * @param array $columns Existing columns.
     * @return array
     */
    public function add_product_columns( array $columns ): array {
        $new_columns = array();

        foreach ( $columns as $key => $label ) {
            $new_columns[ $key ] = $label;

            if ( 'product_cat' === $key ) {
                $new_columns['sfls_courses'] = __( 'Linked Courses', 'swiftlms' );
            }
        }

        return $new_columns;
    }

    /**
     * Render product columns.
     *
     * @param string $column  Column name.
     * @param int    $post_id Post ID.
     * @return void
     */
    public function render_product_columns( string $column, int $post_id ): void {
        if ( 'sfls_courses' !== $column ) {
            return;
        }

        $course_ids = get_post_meta( $post_id, '_sfls_course_ids', true );

        if ( empty( $course_ids ) ) {
            echo '—';
            return;
        }

        $course_names = array();
        foreach ( $course_ids as $course_id ) {
            $course = get_post( $course_id );
            if ( $course ) {
                $course_names[] = '<a href="' . esc_url( get_edit_post_link( $course_id ) ) . '">' . esc_html( $course->post_title ) . '</a>';
            }
        }

        echo implode( ', ', $course_names );
    }
}
