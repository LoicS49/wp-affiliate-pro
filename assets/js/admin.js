jQuery(document).ready(function($) {
    'use strict';

    // Admin functionality for WP Affiliate Pro
    var WPAPAdmin = {
        
        init: function() {
            this.bindEvents();
            this.initDatePickers();
            this.initTabs();
            this.initBulkActions();
        },

        bindEvents: function() {
            // Affiliate approval/rejection
            $('.wpap-approve-affiliate').on('click', this.approveAffiliate);
            $('.wpap-reject-affiliate').on('click', this.rejectAffiliate);
            
            // Payment processing
            $('.wpap-process-payment').on('click', this.processPayment);
            
            // Form confirmations
            $('.wpap-confirm').on('click', this.confirmAction);
            
            // AJAX form submissions
            $('.wpap-ajax-form').on('submit', this.submitAjaxForm);
            
            // Filter changes
            $('.wpap-filter').on('change', this.applyFilters);
        },

        initDatePickers: function() {
            if ($.fn.datepicker) {
                $('.wpap-datepicker').datepicker({
                    dateFormat: 'yy-mm-dd',
                    showButtonPanel: true,
                    changeMonth: true,
                    changeYear: true
                });
            }
        },

        initTabs: function() {
            $('.wpap-tabs nav a').on('click', function(e) {
                e.preventDefault();
                
                var target = $(this).attr('href');
                
                // Remove active class from all tabs and content
                $('.wpap-tabs nav a').removeClass('active');
                $('.wpap-tab-content').hide();
                
                // Add active class to clicked tab and show content
                $(this).addClass('active');
                $(target).show();
            });
            
            // Show first tab by default
            $('.wpap-tabs nav a:first').addClass('active');
            $('.wpap-tab-content:first').show();
        },

        initBulkActions: function() {
            $('#wpap-bulk-action-submit').on('click', function(e) {
                var action = $('#wpap-bulk-action').val();
                var selected = $('.wpap-bulk-checkbox:checked');
                
                if (action === '-1' || selected.length === 0) {
                    e.preventDefault();
                    alert(wpap_admin.strings.select_action_items);
                    return false;
                }
                
                if (!confirm(wpap_admin.strings.confirm_bulk_action)) {
                    e.preventDefault();
                    return false;
                }
            });
            
            $('#wpap-select-all').on('change', function() {
                $('.wpap-bulk-checkbox').prop('checked', $(this).prop('checked'));
            });
        },

        approveAffiliate: function(e) {
            e.preventDefault();
            
            if (!confirm(wpap_admin.strings.confirm_approve)) {
                return;
            }
            
            var $button = $(this);
            var affiliateId = $button.data('affiliate-id');
            
            $button.prop('disabled', true).append('<span class="wpap-spinner"></span>');
            
            $.ajax({
                url: wpap_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpap_approve_affiliate',
                    affiliate_id: affiliateId,
                    nonce: wpap_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPAPAdmin.showNotice('success', response.data.message);
                        location.reload();
                    } else {
                        WPAPAdmin.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    WPAPAdmin.showNotice('error', wpap_admin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false).find('.wpap-spinner').remove();
                }
            });
        },

        rejectAffiliate: function(e) {
            e.preventDefault();
            
            var reason = prompt(wpap_admin.strings.reject_reason);
            if (reason === null) return;
            
            var $button = $(this);
            var affiliateId = $button.data('affiliate-id');
            
            $button.prop('disabled', true).append('<span class="wpap-spinner"></span>');
            
            $.ajax({
                url: wpap_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpap_reject_affiliate',
                    affiliate_id: affiliateId,
                    reason: reason,
                    nonce: wpap_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPAPAdmin.showNotice('success', response.data.message);
                        location.reload();
                    } else {
                        WPAPAdmin.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    WPAPAdmin.showNotice('error', wpap_admin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false).find('.wpap-spinner').remove();
                }
            });
        },

        processPayment: function(e) {
            e.preventDefault();
            
            if (!confirm(wpap_admin.strings.confirm_process_payment)) {
                return;
            }
            
            var $button = $(this);
            var paymentId = $button.data('payment-id');
            var gateway = $button.data('gateway') || '';
            
            $button.prop('disabled', true).append('<span class="wpap-spinner"></span>');
            
            $.ajax({
                url: wpap_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpap_process_payment',
                    payment_id: paymentId,
                    gateway: gateway,
                    nonce: wpap_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPAPAdmin.showNotice('success', response.data.message);
                        location.reload();
                    } else {
                        WPAPAdmin.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    WPAPAdmin.showNotice('error', wpap_admin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false).find('.wpap-spinner').remove();
                }
            });
        },

        confirmAction: function(e) {
            var message = $(this).data('confirm') || wpap_admin.strings.confirm_delete;
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        },

        submitAjaxForm: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitButton = $form.find('[type="submit"]');
            
            $form.addClass('wpap-loading');
            $submitButton.prop('disabled', true);
            
            $.ajax({
                url: $form.attr('action') || wpap_admin.ajax_url,
                type: $form.attr('method') || 'POST',
                data: $form.serialize(),
                success: function(response) {
                    if (response.success) {
                        WPAPAdmin.showNotice('success', response.data.message);
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        }
                    } else {
                        WPAPAdmin.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    WPAPAdmin.showNotice('error', wpap_admin.strings.error);
                },
                complete: function() {
                    $form.removeClass('wpap-loading');
                    $submitButton.prop('disabled', false);
                }
            });
        },

        applyFilters: function() {
            var $form = $(this).closest('form');
            if ($form.length) {
                $form.submit();
            }
        },

        showNotice: function(type, message) {
            var $notice = $('<div class="wpap-notice wpap-notice-' + type + '">' + message + '</div>');
            $('.wrap h1').after($notice);
            
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        // Chart initialization for dashboard
        initChart: function(chartId, data, options) {
            if (typeof Chart === 'undefined') {
                return;
            }
            
            var ctx = document.getElementById(chartId);
            if (!ctx) {
                return;
            }
            
            var defaultOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toFixed(2);
                            }
                        }
                    }
                }
            };
            
            options = $.extend(true, defaultOptions, options || {});
            
            return new Chart(ctx.getContext('2d'), {
                type: 'line',
                data: data,
                options: options
            });
        },

        // Copy to clipboard functionality
        copyToClipboard: function(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    WPAPAdmin.showNotice('success', wpap_admin.strings.copied);
                });
            } else {
                // Fallback for older browsers
                var textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                WPAPAdmin.showNotice('success', wpap_admin.strings.copied);
            }
        },

        // Format currency
        formatCurrency: function(amount, currency) {
            currency = currency || 'USD';
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: currency
            }).format(amount);
        }
    };

    // Initialize admin functionality
    WPAPAdmin.init();

    // Make WPAPAdmin available globally
    window.WPAPAdmin = WPAPAdmin;

    // Copy link functionality
    $(document).on('click', '.wpap-copy-link', function(e) {
        e.preventDefault();
        var link = $(this).data('link') || $(this).attr('href');
        WPAPAdmin.copyToClipboard(link);
    });

    // Toggle password visibility
    $(document).on('click', '.wpap-toggle-password', function() {
        var $input = $(this).siblings('input');
        var type = $input.attr('type') === 'password' ? 'text' : 'password';
        $input.attr('type', type);
        $(this).text(type === 'password' ? 'Show' : 'Hide');
    });

    // Auto-refresh functionality for real-time data
    if ($('.wpap-auto-refresh').length) {
        setInterval(function() {
            $('.wpap-auto-refresh').each(function() {
                var $element = $(this);
                var action = $element.data('action');
                var interval = $element.data('interval') || 30000;
                
                if (action) {
                    $.post(wpap_admin.ajax_url, {
                        action: action,
                        nonce: wpap_admin.nonce
                    }, function(response) {
                        if (response.success && response.data.html) {
                            $element.html(response.data.html);
                        }
                    });
                }
            });
        }, 30000); // Default 30 seconds
    }
});

// Additional utility functions
function wpapFormatNumber(num, decimals) {
    decimals = decimals || 0;
    return parseFloat(num).toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
}

function wpapFormatCurrency(amount, currency) {
    currency = currency || 'USD';
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency
    }).format(amount);
}