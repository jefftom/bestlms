<?php
/**
 * Instructor Earnings
 *
 * @package SwiftLMS\Modules\InstructorDashboard
 */

namespace SwiftLMS\Modules\InstructorDashboard;

defined( 'ABSPATH' ) || exit;

/**
 * Earnings class.
 *
 * Handles instructor earnings, commissions, and payouts.
 */
class Earnings {

    /**
     * Table name.
     *
     * @var string
     */
    private static $table_name = 'swiftlms_earnings';

    /**
     * Withdrawals table name.
     *
     * @var string
     */
    private static $withdrawals_table = 'swiftlms_withdrawals';

    /**
     * Initialize earnings tracking.
     */
    public static function init(): void {
        add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'process_order_earnings' ) );
        add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'handle_refund' ) );
    }

    /**
     * Create earnings tables.
     */
    public static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $earnings_table  = $wpdb->prefix . self::$table_name;
        $withdrawals_table = $wpdb->prefix . self::$withdrawals_table;

        $sql = "CREATE TABLE IF NOT EXISTS {$earnings_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            instructor_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned NOT NULL,
            order_item_id bigint(20) unsigned NOT NULL,
            student_id bigint(20) unsigned NOT NULL,
            gross_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            commission_rate decimal(5,2) NOT NULL DEFAULT 70.00,
            commission_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            platform_fee decimal(10,2) NOT NULL DEFAULT 0.00,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL,
            paid_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY instructor_id (instructor_id),
            KEY course_id (course_id),
            KEY order_id (order_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};

        CREATE TABLE IF NOT EXISTS {$withdrawals_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            instructor_id bigint(20) unsigned NOT NULL,
            amount decimal(10,2) NOT NULL,
            payment_method varchar(50) NOT NULL DEFAULT 'bank_transfer',
            payment_details text,
            status varchar(20) NOT NULL DEFAULT 'pending',
            transaction_id varchar(100) DEFAULT NULL,
            notes text,
            requested_at datetime NOT NULL,
            processed_at datetime DEFAULT NULL,
            processed_by bigint(20) unsigned DEFAULT NULL,
            PRIMARY KEY (id),
            KEY instructor_id (instructor_id),
            KEY status (status),
            KEY requested_at (requested_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Process earnings when order is completed.
     *
     * @param int $order_id Order ID.
     */
    public static function process_order_earnings( int $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Check if already processed.
        if ( get_post_meta( $order_id, '_sfls_earnings_processed', true ) ) {
            return;
        }

        foreach ( $order->get_items() as $item_id => $item ) {
            $product_id = $item->get_product_id();
            $course_id  = self::get_course_from_product( $product_id );

            if ( ! $course_id ) {
                continue;
            }

            $course = get_post( $course_id );
            if ( ! $course ) {
                continue;
            }

            $instructor_id   = (int) $course->post_author;
            $gross_amount    = (float) $item->get_total();
            $commission_rate = self::get_instructor_commission_rate( $instructor_id );
            $commission      = $gross_amount * ( $commission_rate / 100 );
            $platform_fee    = $gross_amount - $commission;

            self::record_earning( array(
                'instructor_id'     => $instructor_id,
                'course_id'         => $course_id,
                'order_id'          => $order_id,
                'order_item_id'     => $item_id,
                'student_id'        => $order->get_customer_id(),
                'gross_amount'      => $gross_amount,
                'commission_rate'   => $commission_rate,
                'commission_amount' => $commission,
                'platform_fee'      => $platform_fee,
                'status'            => 'available',
            ) );

            // Update instructor totals.
            self::update_instructor_totals( $instructor_id, $commission );
        }

        update_post_meta( $order_id, '_sfls_earnings_processed', true );
    }

    /**
     * Get course ID from product.
     *
     * @param int $product_id Product ID.
     * @return int
     */
    private static function get_course_from_product( int $product_id ): int {
        global $wpdb;

        $course_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_sfls_product_id' AND meta_value = %d
             LIMIT 1",
            $product_id
        ) );

        return $course_id ? (int) $course_id : 0;
    }

    /**
     * Get instructor commission rate.
     *
     * @param int $instructor_id Instructor ID.
     * @return float
     */
    public static function get_instructor_commission_rate( int $instructor_id ): float {
        $rate = get_user_meta( $instructor_id, '_sfls_commission_rate', true );
        return $rate ? (float) $rate : 70.0;
    }

    /**
     * Set instructor commission rate.
     *
     * @param int   $instructor_id Instructor ID.
     * @param float $rate          Commission rate (0-100).
     * @return bool
     */
    public static function set_instructor_commission_rate( int $instructor_id, float $rate ): bool {
        $rate = max( 0, min( 100, $rate ) );
        return (bool) update_user_meta( $instructor_id, '_sfls_commission_rate', $rate );
    }

    /**
     * Record an earning.
     *
     * @param array $data Earning data.
     * @return int|false
     */
    public static function record_earning( array $data ) {
        global $wpdb;

        $defaults = array(
            'instructor_id'     => 0,
            'course_id'         => 0,
            'order_id'          => 0,
            'order_item_id'     => 0,
            'student_id'        => 0,
            'gross_amount'      => 0,
            'commission_rate'   => 70,
            'commission_amount' => 0,
            'platform_fee'      => 0,
            'status'            => 'pending',
            'created_at'        => current_time( 'mysql' ),
        );

        $data = wp_parse_args( $data, $defaults );

        $result = $wpdb->insert(
            $wpdb->prefix . self::$table_name,
            $data,
            array( '%d', '%d', '%d', '%d', '%d', '%f', '%f', '%f', '%f', '%s', '%s' )
        );

        if ( $result ) {
            $earning_id = $wpdb->insert_id;
            do_action( 'swiftlms_earning_recorded', $earning_id, $data );
            return $earning_id;
        }

        return false;
    }

    /**
     * Update instructor earnings totals.
     *
     * @param int   $instructor_id Instructor ID.
     * @param float $amount        Amount to add.
     */
    private static function update_instructor_totals( int $instructor_id, float $amount ): void {
        $current = (float) get_user_meta( $instructor_id, '_sfls_total_earnings', true );
        update_user_meta( $instructor_id, '_sfls_total_earnings', $current + $amount );
    }

    /**
     * Handle order refund.
     *
     * @param int $order_id Order ID.
     */
    public static function handle_refund( int $order_id ): void {
        global $wpdb;

        $table = $wpdb->prefix . self::$table_name;

        // Get earnings for this order.
        $earnings = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE order_id = %d AND status != 'refunded'",
            $order_id
        ) );

        foreach ( $earnings as $earning ) {
            // Update status.
            $wpdb->update(
                $table,
                array( 'status' => 'refunded' ),
                array( 'id' => $earning->id ),
                array( '%s' ),
                array( '%d' )
            );

            // Subtract from totals if was available.
            if ( 'available' === $earning->status ) {
                $current = (float) get_user_meta( $earning->instructor_id, '_sfls_total_earnings', true );
                update_user_meta( $earning->instructor_id, '_sfls_total_earnings', max( 0, $current - $earning->commission_amount ) );
            }

            do_action( 'swiftlms_earning_refunded', $earning->id, $earning );
        }
    }

    /**
     * Get instructor earnings.
     *
     * @param int   $instructor_id Instructor ID.
     * @param array $args          Query args.
     * @return array
     */
    public static function get_instructor_earnings( int $instructor_id, array $args = array() ): array {
        global $wpdb;

        $defaults = array(
            'status'     => '',
            'course_id'  => 0,
            'start_date' => '',
            'end_date'   => '',
            'limit'      => 50,
            'offset'     => 0,
        );

        $args  = wp_parse_args( $args, $defaults );
        $table = $wpdb->prefix . self::$table_name;
        $where = array( 'instructor_id = %d' );
        $params = array( $instructor_id );

        if ( $args['status'] ) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }

        if ( $args['course_id'] ) {
            $where[] = 'course_id = %d';
            $params[] = $args['course_id'];
        }

        if ( $args['start_date'] ) {
            $where[] = 'created_at >= %s';
            $params[] = $args['start_date'];
        }

        if ( $args['end_date'] ) {
            $where[] = 'created_at <= %s';
            $params[] = $args['end_date'];
        }

        $where_sql = implode( ' AND ', $where );
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE {$where_sql}
             ORDER BY created_at DESC
             LIMIT %d OFFSET %d",
            $params
        ) );
    }

    /**
     * Get instructor earnings summary.
     *
     * @param int    $instructor_id Instructor ID.
     * @param string $period        Period (all, month, year).
     * @return array
     */
    public static function get_earnings_summary( int $instructor_id, string $period = 'all' ): array {
        global $wpdb;

        $table = $wpdb->prefix . self::$table_name;
        $where = 'instructor_id = %d';
        $params = array( $instructor_id );

        if ( 'month' === $period ) {
            $where .= ' AND created_at >= %s';
            $params[] = gmdate( 'Y-m-01 00:00:00' );
        } elseif ( 'year' === $period ) {
            $where .= ' AND created_at >= %s';
            $params[] = gmdate( 'Y-01-01 00:00:00' );
        }

        $results = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                COUNT(*) as total_sales,
                COALESCE(SUM(gross_amount), 0) as gross_revenue,
                COALESCE(SUM(commission_amount), 0) as total_earnings,
                COALESCE(SUM(CASE WHEN status = 'available' THEN commission_amount ELSE 0 END), 0) as available_earnings,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN commission_amount ELSE 0 END), 0) as paid_earnings,
                COALESCE(SUM(CASE WHEN status = 'refunded' THEN commission_amount ELSE 0 END), 0) as refunded_earnings
             FROM {$table}
             WHERE {$where}",
            $params
        ), ARRAY_A );

        // Get withdrawn amount.
        $withdrawn = (float) get_user_meta( $instructor_id, '_sfls_withdrawn_earnings', true );

        return array(
            'total_sales'        => (int) $results['total_sales'],
            'gross_revenue'      => (float) $results['gross_revenue'],
            'total_earnings'     => (float) $results['total_earnings'],
            'available_earnings' => (float) $results['available_earnings'],
            'paid_earnings'      => (float) $results['paid_earnings'],
            'refunded_earnings'  => (float) $results['refunded_earnings'],
            'withdrawn_earnings' => $withdrawn,
            'balance'            => (float) $results['available_earnings'] - $withdrawn,
        );
    }

    /**
     * Get earnings by course.
     *
     * @param int $instructor_id Instructor ID.
     * @return array
     */
    public static function get_earnings_by_course( int $instructor_id ): array {
        global $wpdb;

        $table = $wpdb->prefix . self::$table_name;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT
                course_id,
                COUNT(*) as sales_count,
                SUM(gross_amount) as gross_revenue,
                SUM(commission_amount) as earnings
             FROM {$table}
             WHERE instructor_id = %d AND status != 'refunded'
             GROUP BY course_id
             ORDER BY earnings DESC",
            $instructor_id
        ), ARRAY_A );
    }

    /**
     * Get monthly earnings trend.
     *
     * @param int $instructor_id Instructor ID.
     * @param int $months        Number of months.
     * @return array
     */
    public static function get_monthly_trend( int $instructor_id, int $months = 12 ): array {
        global $wpdb;

        $table = $wpdb->prefix . self::$table_name;
        $start_date = gmdate( 'Y-m-01', strtotime( "-{$months} months" ) );

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT
                DATE_FORMAT(created_at, '%%Y-%%m') as month,
                COUNT(*) as sales,
                SUM(commission_amount) as earnings
             FROM {$table}
             WHERE instructor_id = %d
               AND status != 'refunded'
               AND created_at >= %s
             GROUP BY DATE_FORMAT(created_at, '%%Y-%%m')
             ORDER BY month ASC",
            $instructor_id,
            $start_date
        ), ARRAY_A );
    }

    /**
     * Request withdrawal.
     *
     * @param int   $instructor_id Instructor ID.
     * @param float $amount        Amount to withdraw.
     * @param array $payment_info  Payment details.
     * @return int|WP_Error
     */
    public static function request_withdrawal( int $instructor_id, float $amount, array $payment_info = array() ) {
        global $wpdb;

        // Check available balance.
        $summary = self::get_earnings_summary( $instructor_id );

        if ( $amount > $summary['balance'] ) {
            return new \WP_Error( 'insufficient_balance', __( 'Insufficient balance for withdrawal.', 'swiftlms' ) );
        }

        // Minimum withdrawal amount.
        $minimum = (float) get_option( 'swiftlms_minimum_withdrawal', 50 );
        if ( $amount < $minimum ) {
            return new \WP_Error(
                'minimum_not_met',
                sprintf( __( 'Minimum withdrawal amount is %s.', 'swiftlms' ), wc_price( $minimum ) )
            );
        }

        // Check for pending withdrawal.
        $pending = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}{self::$withdrawals_table}
             WHERE instructor_id = %d AND status = 'pending'",
            $instructor_id
        ) );

        if ( $pending > 0 ) {
            return new \WP_Error( 'pending_withdrawal', __( 'You already have a pending withdrawal request.', 'swiftlms' ) );
        }

        // Create withdrawal request.
        $result = $wpdb->insert(
            $wpdb->prefix . self::$withdrawals_table,
            array(
                'instructor_id'   => $instructor_id,
                'amount'          => $amount,
                'payment_method'  => $payment_info['method'] ?? 'bank_transfer',
                'payment_details' => maybe_serialize( $payment_info ),
                'status'          => 'pending',
                'requested_at'    => current_time( 'mysql' ),
            ),
            array( '%d', '%f', '%s', '%s', '%s', '%s' )
        );

        if ( $result ) {
            $withdrawal_id = $wpdb->insert_id;

            // Update withdrawn amount.
            $current_withdrawn = (float) get_user_meta( $instructor_id, '_sfls_withdrawn_earnings', true );
            update_user_meta( $instructor_id, '_sfls_withdrawn_earnings', $current_withdrawn + $amount );

            do_action( 'swiftlms_withdrawal_requested', $withdrawal_id, $instructor_id, $amount );

            return $withdrawal_id;
        }

        return new \WP_Error( 'withdrawal_failed', __( 'Failed to create withdrawal request.', 'swiftlms' ) );
    }

    /**
     * Process withdrawal.
     *
     * @param int    $withdrawal_id Withdrawal ID.
     * @param string $status        New status.
     * @param string $transaction_id Transaction ID.
     * @param string $notes         Admin notes.
     * @return bool
     */
    public static function process_withdrawal( int $withdrawal_id, string $status, string $transaction_id = '', string $notes = '' ): bool {
        global $wpdb;

        $table = $wpdb->prefix . self::$withdrawals_table;

        $withdrawal = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $withdrawal_id
        ) );

        if ( ! $withdrawal ) {
            return false;
        }

        $result = $wpdb->update(
            $table,
            array(
                'status'         => $status,
                'transaction_id' => $transaction_id,
                'notes'          => $notes,
                'processed_at'   => current_time( 'mysql' ),
                'processed_by'   => get_current_user_id(),
            ),
            array( 'id' => $withdrawal_id ),
            array( '%s', '%s', '%s', '%s', '%d' ),
            array( '%d' )
        );

        if ( $result ) {
            // If rejected, refund the withdrawn amount.
            if ( 'rejected' === $status ) {
                $current_withdrawn = (float) get_user_meta( $withdrawal->instructor_id, '_sfls_withdrawn_earnings', true );
                update_user_meta( $withdrawal->instructor_id, '_sfls_withdrawn_earnings', max( 0, $current_withdrawn - $withdrawal->amount ) );
            }

            do_action( 'swiftlms_withdrawal_processed', $withdrawal_id, $status, $withdrawal );
            return true;
        }

        return false;
    }

    /**
     * Get instructor withdrawals.
     *
     * @param int   $instructor_id Instructor ID.
     * @param array $args          Query args.
     * @return array
     */
    public static function get_instructor_withdrawals( int $instructor_id, array $args = array() ): array {
        global $wpdb;

        $defaults = array(
            'status' => '',
            'limit'  => 20,
            'offset' => 0,
        );

        $args = wp_parse_args( $args, $defaults );
        $table = $wpdb->prefix . self::$withdrawals_table;

        $where = 'instructor_id = %d';
        $params = array( $instructor_id );

        if ( $args['status'] ) {
            $where .= ' AND status = %s';
            $params[] = $args['status'];
        }

        $params[] = $args['limit'];
        $params[] = $args['offset'];

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE {$where}
             ORDER BY requested_at DESC
             LIMIT %d OFFSET %d",
            $params
        ) );
    }

    /**
     * Get instructor payment settings.
     *
     * @param int $instructor_id Instructor ID.
     * @return array
     */
    public static function get_payment_settings( int $instructor_id ): array {
        return array(
            'method'       => get_user_meta( $instructor_id, '_sfls_payment_method', true ) ?: 'bank_transfer',
            'paypal_email' => get_user_meta( $instructor_id, '_sfls_paypal_email', true ),
            'bank_name'    => get_user_meta( $instructor_id, '_sfls_bank_name', true ),
            'bank_account' => get_user_meta( $instructor_id, '_sfls_bank_account', true ),
            'bank_routing' => get_user_meta( $instructor_id, '_sfls_bank_routing', true ),
            'bank_swift'   => get_user_meta( $instructor_id, '_sfls_bank_swift', true ),
        );
    }

    /**
     * Update instructor payment settings.
     *
     * @param int   $instructor_id Instructor ID.
     * @param array $settings      Payment settings.
     * @return bool
     */
    public static function update_payment_settings( int $instructor_id, array $settings ): bool {
        $allowed_fields = array(
            'payment_method' => '_sfls_payment_method',
            'paypal_email'   => '_sfls_paypal_email',
            'bank_name'      => '_sfls_bank_name',
            'bank_account'   => '_sfls_bank_account',
            'bank_routing'   => '_sfls_bank_routing',
            'bank_swift'     => '_sfls_bank_swift',
        );

        foreach ( $settings as $key => $value ) {
            if ( isset( $allowed_fields[ $key ] ) ) {
                update_user_meta( $instructor_id, $allowed_fields[ $key ], sanitize_text_field( $value ) );
            }
        }

        return true;
    }
}
