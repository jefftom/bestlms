<?php
/**
 * Instructor Dashboard - Earnings View
 *
 * @package SwiftLMS\Modules\InstructorDashboard
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap sfls-instructor-wrap">
    <h1><?php esc_html_e( 'Earnings', 'swiftlms' ); ?></h1>

    <!-- Earnings Overview -->
    <div class="sfls-earnings-overview">
        <div class="sfls-earnings-card sfls-earnings-total">
            <div class="sfls-earnings-icon">
                <span class="dashicons dashicons-chart-area"></span>
            </div>
            <div class="sfls-earnings-content">
                <span class="sfls-earnings-value"><?php echo wc_price( $summary['total_earnings'] ); ?></span>
                <span class="sfls-earnings-label"><?php esc_html_e( 'Total Earnings', 'swiftlms' ); ?></span>
            </div>
        </div>

        <div class="sfls-earnings-card sfls-earnings-available">
            <div class="sfls-earnings-icon">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <div class="sfls-earnings-content">
                <span class="sfls-earnings-value"><?php echo wc_price( $summary['balance'] ); ?></span>
                <span class="sfls-earnings-label"><?php esc_html_e( 'Available Balance', 'swiftlms' ); ?></span>
            </div>
        </div>

        <div class="sfls-earnings-card">
            <div class="sfls-earnings-icon">
                <span class="dashicons dashicons-download"></span>
            </div>
            <div class="sfls-earnings-content">
                <span class="sfls-earnings-value"><?php echo wc_price( $summary['withdrawn_earnings'] ); ?></span>
                <span class="sfls-earnings-label"><?php esc_html_e( 'Total Withdrawn', 'swiftlms' ); ?></span>
            </div>
        </div>

        <div class="sfls-earnings-card">
            <div class="sfls-earnings-icon">
                <span class="dashicons dashicons-cart"></span>
            </div>
            <div class="sfls-earnings-content">
                <span class="sfls-earnings-value"><?php echo esc_html( $summary['total_sales'] ); ?></span>
                <span class="sfls-earnings-label"><?php esc_html_e( 'Total Sales', 'swiftlms' ); ?></span>
            </div>
        </div>
    </div>

    <div class="sfls-earnings-columns">
        <!-- Left Column -->
        <div class="sfls-earnings-main">
            <!-- Monthly Trend Chart -->
            <div class="sfls-card">
                <div class="sfls-card-header">
                    <h3><?php esc_html_e( 'Earnings Trend', 'swiftlms' ); ?></h3>
                </div>
                <div class="sfls-card-body">
                    <canvas id="earningsTrendChart" height="300"></canvas>
                </div>
            </div>

            <!-- Earnings by Course -->
            <div class="sfls-card">
                <div class="sfls-card-header">
                    <h3><?php esc_html_e( 'Earnings by Course', 'swiftlms' ); ?></h3>
                </div>
                <div class="sfls-card-body">
                    <?php if ( empty( $by_course ) ) : ?>
                        <p class="sfls-no-data"><?php esc_html_e( 'No earnings data available yet.', 'swiftlms' ); ?></p>
                    <?php else : ?>
                        <table class="sfls-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Course', 'swiftlms' ); ?></th>
                                    <th><?php esc_html_e( 'Sales', 'swiftlms' ); ?></th>
                                    <th><?php esc_html_e( 'Revenue', 'swiftlms' ); ?></th>
                                    <th><?php esc_html_e( 'Earnings', 'swiftlms' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $by_course as $course ) : ?>
                                <tr>
                                    <td><?php echo esc_html( get_the_title( $course['course_id'] ) ); ?></td>
                                    <td><?php echo esc_html( $course['sales_count'] ); ?></td>
                                    <td><?php echo wc_price( $course['gross_revenue'] ); ?></td>
                                    <td><strong><?php echo wc_price( $course['earnings'] ); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="sfls-card">
                <div class="sfls-card-header">
                    <h3><?php esc_html_e( 'Recent Transactions', 'swiftlms' ); ?></h3>
                </div>
                <div class="sfls-card-body">
                    <?php if ( empty( $recent_earnings ) ) : ?>
                        <p class="sfls-no-data"><?php esc_html_e( 'No transactions yet.', 'swiftlms' ); ?></p>
                    <?php else : ?>
                        <table class="sfls-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Date', 'swiftlms' ); ?></th>
                                    <th><?php esc_html_e( 'Course', 'swiftlms' ); ?></th>
                                    <th><?php esc_html_e( 'Gross', 'swiftlms' ); ?></th>
                                    <th><?php esc_html_e( 'Commission', 'swiftlms' ); ?></th>
                                    <th><?php esc_html_e( 'Status', 'swiftlms' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $recent_earnings as $earning ) : ?>
                                <tr>
                                    <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $earning->created_at ) ) ); ?></td>
                                    <td><?php echo esc_html( get_the_title( $earning->course_id ) ); ?></td>
                                    <td><?php echo wc_price( $earning->gross_amount ); ?></td>
                                    <td><strong><?php echo wc_price( $earning->commission_amount ); ?></strong></td>
                                    <td>
                                        <span class="sfls-status-badge sfls-status-<?php echo esc_attr( $earning->status ); ?>">
                                            <?php echo esc_html( ucfirst( $earning->status ) ); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="sfls-earnings-sidebar">
            <!-- Withdraw Funds -->
            <div class="sfls-card">
                <div class="sfls-card-header">
                    <h3><?php esc_html_e( 'Withdraw Funds', 'swiftlms' ); ?></h3>
                </div>
                <div class="sfls-card-body">
                    <?php
                    $minimum = (float) get_option( 'swiftlms_minimum_withdrawal', 50 );
                    $can_withdraw = $summary['balance'] >= $minimum;
                    ?>

                    <div class="sfls-withdraw-balance">
                        <span class="sfls-withdraw-label"><?php esc_html_e( 'Available Balance', 'swiftlms' ); ?></span>
                        <span class="sfls-withdraw-amount"><?php echo wc_price( $summary['balance'] ); ?></span>
                    </div>

                    <?php if ( $can_withdraw ) : ?>
                        <form id="sfls-withdraw-form" class="sfls-withdraw-form">
                            <?php wp_nonce_field( 'sfls_instructor_nonce', 'nonce' ); ?>
                            <div class="sfls-form-field">
                                <label for="withdraw_amount"><?php esc_html_e( 'Amount', 'swiftlms' ); ?></label>
                                <input type="number" id="withdraw_amount" name="amount" min="<?php echo esc_attr( $minimum ); ?>" max="<?php echo esc_attr( $summary['balance'] ); ?>" step="0.01" value="<?php echo esc_attr( $summary['balance'] ); ?>">
                            </div>
                            <button type="submit" class="button button-primary">
                                <?php esc_html_e( 'Request Withdrawal', 'swiftlms' ); ?>
                            </button>
                        </form>
                    <?php else : ?>
                        <p class="sfls-withdraw-notice">
                            <?php printf( esc_html__( 'Minimum withdrawal amount is %s.', 'swiftlms' ), wc_price( $minimum ) ); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payment Settings -->
            <div class="sfls-card">
                <div class="sfls-card-header">
                    <h3><?php esc_html_e( 'Payment Settings', 'swiftlms' ); ?></h3>
                </div>
                <div class="sfls-card-body">
                    <form id="sfls-payment-settings-form" class="sfls-payment-form">
                        <?php wp_nonce_field( 'sfls_instructor_nonce', 'nonce' ); ?>

                        <div class="sfls-form-field">
                            <label for="payment_method"><?php esc_html_e( 'Payment Method', 'swiftlms' ); ?></label>
                            <select id="payment_method" name="payment_method">
                                <option value="bank_transfer" <?php selected( $payment_settings['method'], 'bank_transfer' ); ?>><?php esc_html_e( 'Bank Transfer', 'swiftlms' ); ?></option>
                                <option value="paypal" <?php selected( $payment_settings['method'], 'paypal' ); ?>><?php esc_html_e( 'PayPal', 'swiftlms' ); ?></option>
                            </select>
                        </div>

                        <div class="sfls-payment-fields sfls-paypal-fields" style="<?php echo $payment_settings['method'] !== 'paypal' ? 'display:none;' : ''; ?>">
                            <div class="sfls-form-field">
                                <label for="paypal_email"><?php esc_html_e( 'PayPal Email', 'swiftlms' ); ?></label>
                                <input type="email" id="paypal_email" name="paypal_email" value="<?php echo esc_attr( $payment_settings['paypal_email'] ); ?>">
                            </div>
                        </div>

                        <div class="sfls-payment-fields sfls-bank-fields" style="<?php echo $payment_settings['method'] === 'paypal' ? 'display:none;' : ''; ?>">
                            <div class="sfls-form-field">
                                <label for="bank_name"><?php esc_html_e( 'Bank Name', 'swiftlms' ); ?></label>
                                <input type="text" id="bank_name" name="bank_name" value="<?php echo esc_attr( $payment_settings['bank_name'] ); ?>">
                            </div>
                            <div class="sfls-form-field">
                                <label for="bank_account"><?php esc_html_e( 'Account Number', 'swiftlms' ); ?></label>
                                <input type="text" id="bank_account" name="bank_account" value="<?php echo esc_attr( $payment_settings['bank_account'] ); ?>">
                            </div>
                            <div class="sfls-form-field">
                                <label for="bank_routing"><?php esc_html_e( 'Routing Number', 'swiftlms' ); ?></label>
                                <input type="text" id="bank_routing" name="bank_routing" value="<?php echo esc_attr( $payment_settings['bank_routing'] ); ?>">
                            </div>
                            <div class="sfls-form-field">
                                <label for="bank_swift"><?php esc_html_e( 'SWIFT/BIC', 'swiftlms' ); ?></label>
                                <input type="text" id="bank_swift" name="bank_swift" value="<?php echo esc_attr( $payment_settings['bank_swift'] ); ?>">
                            </div>
                        </div>

                        <button type="submit" class="button">
                            <?php esc_html_e( 'Save Settings', 'swiftlms' ); ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Recent Withdrawals -->
            <?php if ( ! empty( $withdrawals ) ) : ?>
            <div class="sfls-card">
                <div class="sfls-card-header">
                    <h3><?php esc_html_e( 'Withdrawal History', 'swiftlms' ); ?></h3>
                </div>
                <div class="sfls-card-body">
                    <div class="sfls-withdrawals-list">
                        <?php foreach ( $withdrawals as $withdrawal ) : ?>
                        <div class="sfls-withdrawal-item">
                            <div class="sfls-withdrawal-info">
                                <span class="sfls-withdrawal-amount"><?php echo wc_price( $withdrawal->amount ); ?></span>
                                <span class="sfls-withdrawal-date"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $withdrawal->requested_at ) ) ); ?></span>
                            </div>
                            <span class="sfls-status-badge sfls-status-<?php echo esc_attr( $withdrawal->status ); ?>">
                                <?php echo esc_html( ucfirst( $withdrawal->status ) ); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Earnings Trend Chart
    var trendData = <?php echo wp_json_encode( $monthly_trend ); ?>;
    var ctx = document.getElementById('earningsTrendChart');

    if (ctx && trendData.length > 0) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: trendData.map(function(item) {
                    var date = new Date(item.month + '-01');
                    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: '<?php esc_html_e( 'Earnings', 'swiftlms' ); ?>',
                    data: trendData.map(function(item) { return parseFloat(item.earnings); }),
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    // Payment method toggle
    $('#payment_method').on('change', function() {
        if ($(this).val() === 'paypal') {
            $('.sfls-paypal-fields').show();
            $('.sfls-bank-fields').hide();
        } else {
            $('.sfls-paypal-fields').hide();
            $('.sfls-bank-fields').show();
        }
    });

    // Withdrawal form
    $('#sfls-withdraw-form').on('submit', function(e) {
        e.preventDefault();

        if (!confirm(swiftlms_instructor.i18n.confirm_withdraw)) {
            return;
        }

        var $btn = $(this).find('button[type="submit"]');
        $btn.prop('disabled', true).text(swiftlms_instructor.i18n.loading);

        $.ajax({
            url: swiftlms_instructor.ajax_url,
            type: 'POST',
            data: {
                action: 'sfls_instructor_request_withdrawal',
                nonce: $(this).find('[name="nonce"]').val(),
                amount: $('#withdraw_amount').val()
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || swiftlms_instructor.i18n.error);
                    $btn.prop('disabled', false).text('<?php esc_html_e( 'Request Withdrawal', 'swiftlms' ); ?>');
                }
            },
            error: function() {
                alert(swiftlms_instructor.i18n.error);
                $btn.prop('disabled', false).text('<?php esc_html_e( 'Request Withdrawal', 'swiftlms' ); ?>');
            }
        });
    });

    // Payment settings form
    $('#sfls-payment-settings-form').on('submit', function(e) {
        e.preventDefault();

        var $btn = $(this).find('button[type="submit"]');
        $btn.prop('disabled', true).text(swiftlms_instructor.i18n.loading);

        $.ajax({
            url: swiftlms_instructor.ajax_url,
            type: 'POST',
            data: {
                action: 'sfls_instructor_update_payment_settings',
                nonce: $(this).find('[name="nonce"]').val(),
                payment_method: $('#payment_method').val(),
                paypal_email: $('#paypal_email').val(),
                bank_name: $('#bank_name').val(),
                bank_account: $('#bank_account').val(),
                bank_routing: $('#bank_routing').val(),
                bank_swift: $('#bank_swift').val()
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert(response.data.message || swiftlms_instructor.i18n.error);
                }
                $btn.prop('disabled', false).text('<?php esc_html_e( 'Save Settings', 'swiftlms' ); ?>');
            },
            error: function() {
                alert(swiftlms_instructor.i18n.error);
                $btn.prop('disabled', false).text('<?php esc_html_e( 'Save Settings', 'swiftlms' ); ?>');
            }
        });
    });
});
</script>
