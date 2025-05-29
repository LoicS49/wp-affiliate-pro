<?php
/**
 * Affiliate Dashboard Main Template
 * 
 * This template is loaded by the frontend shortcode
 * 
 * @var object $affiliate Current affiliate data
 * @var object $user Current user data  
 * @var array $stats Affiliate statistics
 * @var array $recent_commissions Recent commissions
 * @var array $recent_payments Recent payments
 * @var array $affiliate_links Affiliate links
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Display notices
if ( method_exists( wpap()->frontend, 'display_notices' ) ) {
    wpap()->frontend->display_notices();
}
?>

<div class="wpap-dashboard wpap-container">
    <!-- Dashboard Header -->
    <div class="wpap-dashboard-header">
        <div>
            <h1 class="wpap-dashboard-welcome">
                <?php printf( __( 'Welcome back, %s!', 'wp-affiliate-pro' ), esc_html( $user->display_name ) ); ?>
            </h1>
            <p class="wpap-text-muted">
                <?php printf( __( 'Referral Code: %s', 'wp-affiliate-pro' ), '<strong>' . esc_html( $affiliate->referral_code ) . '</strong>' ); ?>
            </p>
        </div>
        <div>
            <span class="wpap-dashboard-status wpap-status wpap-status-<?php echo esc_attr( $affiliate->status ); ?>">
                <?php echo esc_html( ucfirst( $affiliate->status ) ); ?>
            </span>
        </div>
    </div>

    <?php if ( $affiliate->status === 'pending' ) : ?>
        <div class="wpap-notice wpap-notice-warning">
            <p><?php _e( 'Your affiliate application is currently under review. You will be notified once it has been approved.', 'wp-affiliate-pro' ); ?></p>
        </div>
    <?php elseif ( $affiliate->status === 'rejected' ) : ?>
        <div class="wpap-notice wpap-notice-error">
            <p><?php _e( 'Your affiliate application was not approved. Please contact support for more information.', 'wp-affiliate-pro' ); ?></p>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <?php if ( $atts['show_stats'] === 'yes' && $affiliate->status === 'active' ) : ?>
        <div class="wpap-stats-container">
            <div class="wpap-stats-grid">
                <div class="wpap-stat-card wpap-stat-earnings">
                    <span class="wpap-stat-value"><?php echo wpap_format_amount( $stats['total_earnings'] ?? 0 ); ?></span>
                    <span class="wpap-stat-label"><?php _e( 'Total Earnings', 'wp-affiliate-pro' ); ?></span>
                </div>
                
                <div class="wpap-stat-card wpap-stat-unpaid">
                    <span class="wpap-stat-value"><?php echo wpap_format_amount( $stats['unpaid_earnings'] ?? 0 ); ?></span>
                    <span class="wpap-stat-label"><?php _e( 'Unpaid Earnings', 'wp-affiliate-pro' ); ?></span>
                </div>
                
                <div class="wpap-stat-card wpap-stat-visits">
                    <span class="wpap-stat-value"><?php echo number_format( $stats['total_visits'] ?? 0 ); ?></span>
                    <span class="wpap-stat-label"><?php _e( 'Total Visits', 'wp-affiliate-pro' ); ?></span>
                </div>
                
                <div class="wpap-stat-card wpap-stat-conversions">
                    <span class="wpap-stat-value"><?php echo number_format( $stats['total_conversions'] ?? 0 ); ?></span>
                    <span class="wpap-stat-label"><?php _e( 'Conversions', 'wp-affiliate-pro' ); ?></span>
                </div>
                
                <div class="wpap-stat-card wpap-stat-rate">
                    <span class="wpap-stat-value"><?php echo number_format( $stats['conversion_rate'] ?? 0, 1 ); ?>%</span>
                    <span class="wpap-stat-label"><?php _e( 'Conversion Rate', 'wp-affiliate-pro' ); ?></span>
                </div>
                
                <div class="wpap-stat-card wpap-stat-commissions">
                    <span class="wpap-stat-value"><?php echo number_format( $stats['total_commissions'] ?? 0 ); ?></span>
                    <span class="wpap-stat-label"><?php _e( 'Total Commissions', 'wp-affiliate-pro' ); ?></span>
                </div>
            </div>

            <!-- Stats Period Filter -->
            <div style="text-align: center; margin-bottom: 30px;">
                <select class="wpap-stats-period wpap-form-select" style="width: auto;">
                    <option value="all_time"><?php _e( 'All Time', 'wp-affiliate-pro' ); ?></option>
                    <option value="this_month"><?php _e( 'This Month', 'wp-affiliate-pro' ); ?></option>
                    <option value="last_month"><?php _e( 'Last Month', 'wp-affiliate-pro' ); ?></option>
                    <option value="last_30_days"><?php _e( 'Last 30 Days', 'wp-affiliate-pro' ); ?></option>
                </select>
            </div>
        </div>
    <?php endif; ?>

    <!-- Dashboard Tabs -->
    <?php if ( $affiliate->status === 'active' ) : ?>
        <div class="wpap-tabs">
            <nav class="wpap-tabs-nav">
                <?php if ( $atts['show_links'] === 'yes' ) : ?>
                    <a href="#wpap-links" class="wpap-tab-link"><?php _e( 'Referral Links', 'wp-affiliate-pro' ); ?></a>
                <?php endif; ?>
                
                <?php if ( $atts['show_commissions'] === 'yes' ) : ?>
                    <a href="#wpap-commissions" class="wpap-tab-link"><?php _e( 'Commissions', 'wp-affiliate-pro' ); ?></a>
                <?php endif; ?>
                
                <?php if ( $atts['show_payments'] === 'yes' ) : ?>
                    <a href="#wpap-payments" class="wpap-tab-link"><?php _e( 'Payments', 'wp-affiliate-pro' ); ?></a>
                <?php endif; ?>
                
                <a href="#wpap-profile" class="wpap-tab-link"><?php _e( 'Profile', 'wp-affiliate-pro' ); ?></a>
            </nav>

            <!-- Referral Links Tab -->
            <?php if ( $atts['show_links'] === 'yes' ) : ?>
                <div id="wpap-links" class="wpap-tab-content">
                    <div class="wpap-row">
                        <div class="wpap-col-half">
                            <div class="wpap-card">
                                <div class="wpap-card-header">
                                    <h3 class="wpap-card-title"><?php _e( 'Generate Referral Link', 'wp-affiliate-pro' ); ?></h3>
                                </div>
                                
                                <div class="wpap-link-generator">
                                    <div class="wpap-form-group">
                                        <label class="wpap-form-label"><?php _e( 'URL to promote', 'wp-affiliate-pro' ); ?></label>
                                        <input type="url" name="url" class="wpap-form-input" value="<?php echo esc_attr( home_url() ); ?>" placeholder="<?php esc_attr_e( 'Enter URL to promote', 'wp-affiliate-pro' ); ?>">
                                    </div>
                                    
                                    <div class="wpap-form-group">
                                        <label class="wpap-form-label"><?php _e( 'Campaign Name (Optional)', 'wp-affiliate-pro' ); ?></label>
                                        <input type="text" name="campaign" class="wpap-form-input" placeholder="<?php esc_attr_e( 'e.g., summer-sale', 'wp-affiliate-pro' ); ?>">
                                    </div>
                                    
                                    <button type="button" class="wpap-button wpap-generate-link"><?php _e( 'Generate Link', 'wp-affiliate-pro' ); ?></button>
                                    
                                    <div class="wpap-form-group" style="margin-top: 20px;">
                                        <label class="wpap-form-label"><?php _e( 'Your Referral Link', 'wp-affiliate-pro' ); ?></label>
                                        <div class="wpap-referral-link">
                                            <input type="text" class="wpap-generated-link wpap-form-input" readonly style="display: none;">
                                            <button type="button" class="wpap-copy-link wpap-button-secondary" style="display: none;"><?php _e( 'Copy', 'wp-affiliate-pro' ); ?></button>
                                        </div>
                                        <div class="wpap-qr-code" style="display: none; text-align: center; margin-top: 15px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="wpap-col-half">
                            <div class="wpap-card">
                                <div class="wpap-card-header">
                                    <h3 class="wpap-card-title"><?php _e( 'Your Default Referral Link', 'wp-affiliate-pro' ); ?></h3>
                                </div>
                                
                                <?php 
                                $default_referral_url = wpap_get_referral_url( home_url(), $affiliate->id );
                                ?>
                                <div class="wpap-referral-link">
                                    <input type="text" value="<?php echo esc_attr( $default_referral_url ); ?>" readonly class="wpap-form-input">
                                    <button type="button" class="wpap-copy-link wpap-button-secondary" data-link="<?php echo esc_attr( $default_referral_url ); ?>"><?php _e( 'Copy', 'wp-affiliate-pro' ); ?></button>
                                </div>
                                
                                <p class="wpap-form-help"><?php _e( 'Use this link to promote our website and earn commissions on sales.', 'wp-affiliate-pro' ); ?></p>
                            </div>
                            
                            <?php if ( ! empty( $affiliate_links ) ) : ?>
                                <div class="wpap-card">
                                    <div class="wpap-card-header">
                                        <h3 class="wpap-card-title"><?php _e( 'Recent Links', 'wp-affiliate-pro' ); ?></h3>
                                    </div>
                                    
                                    <div class="wpap-table-responsive">
                                        <table class="wpap-table">
                                            <thead>
                                                <tr>
                                                    <th><?php _e( 'Campaign', 'wp-affiliate-pro' ); ?></th>
                                                    <th><?php _e( 'Clicks', 'wp-affiliate-pro' ); ?></th>
                                                    <th><?php _e( 'Conversions', 'wp-affiliate-pro' ); ?></th>
                                                    <th><?php _e( 'Rate', 'wp-affiliate-pro' ); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ( $affiliate_links as $link ) : ?>
                                                    <tr>
                                                        <td><?php echo esc_html( $link->campaign ?: __( 'Default', 'wp-affiliate-pro' ) ); ?></td>
                                                        <td><?php echo number_format( $link->clicks ); ?></td>
                                                        <td><?php echo number_format( $link->conversions ); ?></td>
                                                        <td><?php echo $link->clicks > 0 ? number_format( ( $link->conversions / $link->clicks ) * 100, 1 ) : '0'; ?>%</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Commissions Tab -->
            <?php if ( $atts['show_commissions'] === 'yes' ) : ?>
                <div id="wpap-commissions" class="wpap-tab-content">
                    <div class="wpap-card">
                        <div class="wpap-card-header">
                            <h3 class="wpap-card-title"><?php _e( 'Commission History', 'wp-affiliate-pro' ); ?></h3>
                            <select class="wpap-commission-filter wpap-form-select" style="width: auto;">
                                <option value=""><?php _e( 'All Statuses', 'wp-affiliate-pro' ); ?></option>
                                <option value="pending"><?php _e( 'Pending', 'wp-affiliate-pro' ); ?></option>
                                <option value="approved"><?php _e( 'Approved', 'wp-affiliate-pro' ); ?></option>
                                <option value="paid"><?php _e( 'Paid', 'wp-affiliate-pro' ); ?></option>
                                <option value="rejected"><?php _e( 'Rejected', 'wp-affiliate-pro' ); ?></option>
                            </select>
                        </div>
                        
                        <div class="wpap-commissions-container">
                            <div class="wpap-commissions-list">
                                <?php if ( ! empty( $recent_commissions ) ) : ?>
                                    <?php foreach ( $recent_commissions as $commission ) : ?>
                                        <div class="wpap-commission-item">
                                            <div class="wpap-commission-details">
                                                <h4><?php echo esc_html( $commission->description ?: sprintf( __( 'Commission #%d', 'wp-affiliate-pro' ), $commission->id ) ); ?></h4>
                                                <p>
                                                    <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $commission->date_created ) ) ); ?>
                                                    • 
                                                    <span class="wpap-status wpap-status-<?php echo esc_attr( $commission->status ); ?>">
                                                        <?php echo esc_html( ucfirst( $commission->status ) ); ?>
                                                    </span>
                                                    <?php if ( $commission->type !== 'sale' ) : ?>
                                                        • <?php echo esc_html( ucfirst( str_replace( '_', ' ', $commission->type ) ) ); ?>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                            <div class="wpap-commission-amount">
                                                <?php echo wpap_format_amount( $commission->commission_amount ); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <div class="wpap-text-center" style="padding: 40px;">
                                        <p class="wpap-text-muted"><?php _e( 'No commissions found.', 'wp-affiliate-pro' ); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="wpap-commission-pagination wpap-pagination"></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Payments Tab -->
            <?php if ( $atts['show_payments'] === 'yes' ) : ?>
                <div id="wpap-payments" class="wpap-tab-content">
                    <div class="wpap-row">
                        <div class="wpap-col-half">
                            <div class="wpap-card">
                                <div class="wpap-card-header">
                                    <h3 class="wpap-card-title"><?php _e( 'Request Payout', 'wp-affiliate-pro' ); ?></h3>
                                </div>
                                
                                <?php if ( ( $stats['unpaid_earnings'] ?? 0 ) >= wpap_get_minimum_payout() ) : ?>
                                    <div class="wpap-form-group">
                                        <label class="wpap-form-label"><?php _e( 'Available Balance', 'wp-affiliate-pro' ); ?></label>
                                        <div style="font-size: 24px; font-weight: 600; color: #00a32a; margin-bottom: 15px;">
                                            <?php echo wpap_format_amount( $stats['unpaid_earnings'] ?? 0 ); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="wpap-form-group">
                                        <label class="wpap-form-label"><?php _e( 'Payout Amount', 'wp-affiliate-pro' ); ?></label>
                                        <input type="number" id="wpap-payout-amount" class="wpap-form-input" 
                                               min="<?php echo esc_attr( wpap_get_minimum_payout() ); ?>" 
                                               max="<?php echo esc_attr( $stats['unpaid_earnings'] ?? 0 ); ?>" 
                                               step="0.01" 
                                               placeholder="<?php echo esc_attr( wpap_format_amount( wpap_get_minimum_payout() ) ); ?>">
                                        <div class="wpap-form-help">
                                            <?php printf( __( 'Minimum payout: %s', 'wp-affiliate-pro' ), wpap_format_amount( wpap_get_minimum_payout() ) ); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="wpap-form-group">
                                        <label class="wpap-form-label"><?php _e( 'Payment Method', 'wp-affiliate-pro' ); ?></label>
                                        <select id="wpap-payout-method" class="wpap-form-select">
                                            <option value="paypal" <?php selected( $affiliate->payment_method, 'paypal' ); ?>><?php _e( 'PayPal', 'wp-affiliate-pro' ); ?></option>
                                            <option value="bank_transfer" <?php selected( $affiliate->payment_method, 'bank_transfer' ); ?>><?php _e( 'Bank Transfer', 'wp-affiliate-pro' ); ?></option>
                                            <option value="stripe" <?php selected( $affiliate->payment_method, 'stripe' ); ?>><?php _e( 'Stripe', 'wp-affiliate-pro' ); ?></option>
                                        </select>
                                    </div>
                                    
                                    <button type="button" class="wpap-button wpap-request-payout"><?php _e( 'Request Payout', 'wp-affiliate-pro' ); ?></button>
                                <?php else : ?>
                                    <div class="wpap-notice wpap-notice-info">
                                        <p>
                                            <?php printf( 
                                                __( 'You need at least %s in unpaid earnings to request a payout. Current balance: %s', 'wp-affiliate-pro' ), 
                                                wpap_format_amount( wpap_get_minimum_payout() ),
                                                wpap_format_amount( $stats['unpaid_earnings'] ?? 0 )
                                            ); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="wpap-col-half">
                            <div class="wpap-card">
                                <div class="wpap-card-header">
                                    <h3 class="wpap-card-title"><?php _e( 'Payment History', 'wp-affiliate-pro' ); ?></h3>
                                </div>
                                
                                <div class="wpap-payments-container">
                                    <div class="wpap-payments-list">
                                        <?php if ( ! empty( $recent_payments ) ) : ?>
                                            <?php foreach ( $recent_payments as $payment ) : ?>
                                                <div class="wpap-commission-item">
                                                    <div class="wpap-commission-details">
                                                        <h4><?php printf( __( 'Payment #%d', 'wp-affiliate-pro' ), $payment->id ); ?></h4>
                                                        <p>
                                                            <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $payment->date_created ) ) ); ?>
                                                            • 
                                                            <?php echo esc_html( ucfirst( str_replace( '_', ' ', $payment->method ) ) ); ?>
                                                            • 
                                                            <span class="wpap-status wpap-status-<?php echo esc_attr( $payment->status ); ?>">
                                                                <?php echo esc_html( ucfirst( $payment->status ) ); ?>
                                                            </span>
                                                        </p>
                                                    </div>
                                                    <div class="wpap-commission-amount">
                                                        <?php echo wpap_format_amount( $payment->amount ); ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else : ?>
                                            <div class="wpap-text-center" style="padding: 40px;">
                                                <p class="wpap-text-muted"><?php _e( 'No payments found.', 'wp-affiliate-pro' ); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="wpap-payment-pagination wpap-pagination"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Profile Tab -->
            <div id="wpap-profile" class="wpap-tab-content">
                <div class="wpap-row">
                    <div class="wpap-col-half">
                        <div class="wpap-card">
                            <div class="wpap-card-header">
                                <h3 class="wpap-card-title"><?php _e( 'Affiliate Information', 'wp-affiliate-pro' ); ?></h3>
                            </div>
                            
                            <div class="wpap-form-group">
                                <label class="wpap-form-label"><?php _e( 'Name', 'wp-affiliate-pro' ); ?></label>
                                <div class="wpap-form-static"><?php echo esc_html( $user->display_name ); ?></div>
                            </div>
                            
                            <div class="wpap-form-group">
                                <label class="wpap-form-label"><?php _e( 'Email', 'wp-affiliate-pro' ); ?></label>
                                <div class="wpap-form-static"><?php echo esc_html( $user->user_email ); ?></div>
                            </div>
                            
                            <div class="wpap-form-group">
                                <label class="wpap-form-label"><?php _e( 'Referral Code', 'wp-affiliate-pro' ); ?></label>
                                <div class="wpap-form-static"><?php echo esc_html( $affiliate->referral_code ); ?></div>
                            </div>
                            
                            <div class="wpap-form-group">
                                <label class="wpap-form-label"><?php _e( 'Commission Rate', 'wp-affiliate-pro' ); ?></label>
                                <div class="wpap-form-static">
                                    <?php 
                                    if ( $affiliate->commission_type === 'percentage' ) {
                                        echo esc_html( $affiliate->commission_rate . '%' );
                                    } else {
                                        echo wpap_format_amount( $affiliate->commission_rate );
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <div class="wpap-form-group">
                                <label class="wpap-form-label"><?php _e( 'Registration Date', 'wp-affiliate-pro' ); ?></label>
                                <div class="wpap-form-static"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $affiliate->date_registered ) ) ); ?></div>
                            </div>
                            
                            <?php if ( $affiliate->date_approved ) : ?>
                                <div class="wpap-form-group">
                                    <label class="wpap-form-label"><?php _e( 'Approval Date', 'wp-affiliate-pro' ); ?></label>
                                    <div class="wpap-form-static"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $affiliate->date_approved ) ) ); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="wpap-col-half">
                        <form id="wpap-profile-form" class="wpap-card">
                            <div class="wpap-card-header">
                                <h3 class="wpap-card-title"><?php _e( 'Update Payment Information', 'wp-affiliate-pro' ); ?></h3>
                            </div>
                            
                            <?php wp_nonce_field( 'wpap_update_profile', 'wpap_nonce' ); ?>
                            
                            <div class="wpap-form-group">
                                <label class="wpap-form-label" for="payment_email"><?php _e( 'Payment Email', 'wp-affiliate-pro' ); ?></label>
                                <input type="email" id="payment_email" name="payment_email" class="wpap-form-input" 
                                       value="<?php echo esc_attr( $affiliate->payment_email ); ?>" required>
                                <div class="wpap-form-help"><?php _e( 'Email address where payments will be sent.', 'wp-affiliate-pro' ); ?></div>
                            </div>
                            
                            <div class="wpap-form-group">
                                <label class="wpap-form-label" for="payment_method"><?php _e( 'Preferred Payment Method', 'wp-affiliate-pro' ); ?></label>
                                <select id="payment_method" name="payment_method" class="wpap-form-select">
                                    <option value="paypal" <?php selected( $affiliate->payment_method, 'paypal' ); ?>><?php _e( 'PayPal', 'wp-affiliate-pro' ); ?></option>
                                    <option value="bank_transfer" <?php selected( $affiliate->payment_method, 'bank_transfer' ); ?>><?php _e( 'Bank Transfer', 'wp-affiliate-pro' ); ?></option>
                                    <option value="stripe" <?php selected( $affiliate->payment_method, 'stripe' ); ?>><?php _e( 'Stripe', 'wp-affiliate-pro' ); ?></option>
                                </select>
                            </div>
                            
                            <div class="wpap-form-group">
                                <label class="wpap-form-label" for="website"><?php _e( 'Website', 'wp-affiliate-pro' ); ?></label>
                                <input type="url" id="website" name="website" class="wpap-form-input" 
                                       value="<?php echo esc_attr( $user->user_url ); ?>" 
                                       placeholder="https://example.com">
                            </div>
                            
                            <div class="wpap-form-group">
                                <label class="wpap-form-label" for="bio"><?php _e( 'Bio', 'wp-affiliate-pro' ); ?></label>
                                <textarea id="bio" name="bio" class="wpap-form-textarea" rows="4" 
                                          placeholder="<?php esc_attr_e( 'Tell us about yourself and how you plan to promote our products...', 'wp-affiliate-pro' ); ?>"><?php echo esc_textarea( get_user_meta( $user->ID, 'description', true ) ); ?></textarea>
                            </div>
                            
                            <div class="wpap-form-actions">
                                <button type="submit" class="wpap-button"><?php _e( 'Update Profile', 'wp-affiliate-pro' ); ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Initialize dashboard functionality
    if (typeof WPAPFrontend !== 'undefined') {
        // Load initial commission history
        if ($('#wpap-commissions').length) {
            WPAPFrontend.loadCommissions();
        }
        
        // Load initial payment history  
        if ($('#wpap-payments').length) {
            WPAPFrontend.loadPayments();
        }
        
        // Handle stats period change
        $('.wpap-stats-period').on('change', function() {
            WPAPFrontend.refreshStats($(this).val());
        });
        
        // Handle commission filter change
        $('.wpap-commission-filter').on('change', function() {
            WPAPFrontend.loadCommissions();
        });
        
        // Handle profile form submission
        $('#wpap-profile-form').on('submit', function(e) {
            e.preventDefault();
            WPAPFrontend.handleProfileUpdate.call(this, e);
        });
    }
    
    // Quick payout buttons
    $('.wpap-quick-payout').on('click', function() {
        var amount = $(this).data('amount');
        $('#wpap-payout-amount').val(amount);
    });
    
    // Auto-fill max payout amount
    $('#wpap-payout-max').on('click', function(e) {
        e.preventDefault();
        var maxAmount = <?php echo json_encode( $stats['unpaid_earnings'] ?? 0 ); ?>;
        $('#wpap-payout-amount').val(maxAmount);
    });
});
</script>

<style>
.wpap-form-static {
    padding: 12px 16px;
    background: #f9f9f9;
    border: 1px solid #e1e1e1;
    border-radius: 4px;
    color: #3c434a;
    font-weight: 500;
}

.wpap-form-actions {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #f1f1f1;
}

.wpap-table-responsive {
    overflow-x: auto;
}

@media (max-width: 768px) {
    .wpap-table-responsive {
        font-size: 14px;
    }
    
    .wpap-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .wpap-stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>