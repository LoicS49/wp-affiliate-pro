jQuery(document).ready(function($) {
    'use strict';

    // Frontend functionality for WP Affiliate Pro
    var WPAPFrontend = {
        
        init: function() {
            this.bindEvents();
            this.initTabs();
            this.initCopyLinks();
            this.initAjaxForms();
            this.initStatsRefresh();
        },

        bindEvents: function() {
            // Registration form
            $('#wpap-registration-form').on('submit', this.handleRegistration);
            
            // Profile update form
            $('#wpap-profile-form').on('submit', this.handleProfileUpdate);
            
            // Link generation
            $('.wpap-generate-link').on('click', this.generateReferralLink);
            
            // Payout request
            $('.wpap-request-payout').on('click', this.requestPayout);
            
            // Stats period change
            $('.wpap-stats-period').on('change', this.refreshStats);
            
            // Commission history pagination
            $('.wpap-commission-pagination').on('click', 'a', this.loadCommissions);
            
            // Payment history pagination
            $('.wpap-payment-pagination').on('click', 'a', this.loadPayments);
        },

        initTabs: function() {
            $('.wpap-tab-link').on('click', function(e) {
                e.preventDefault();
                
                var target = $(this).attr('href');
                var $tabsContainer = $(this).closest('.wpap-tabs');
                
                // Remove active class from all tabs and content
                $tabsContainer.find('.wpap-tab-link').removeClass('active');
                $tabsContainer.find('.wpap-tab-content').removeClass('active');
                
                // Add active class to clicked tab and show content
                $(this).addClass('active');
                $(target).addClass('active');
                
                // Store active tab in localStorage
                localStorage.setItem('wpap_active_tab', target);
            });
            
            // Restore active tab from localStorage
            var activeTab = localStorage.getItem('wpap_active_tab');
            if (activeTab && $(activeTab).length) {
                $('.wpap-tab-link[href="' + activeTab + '"]').click();
            } else {
                // Show first tab by default
                $('.wpap-tab-link:first').click();
            }
        },

        initCopyLinks: function() {
            $('.wpap-copy-link').on('click', function(e) {
                e.preventDefault();
                
                var link = $(this).data('link') || $(this).siblings('input').val();
                
                if (link) {
                    WPAPFrontend.copyToClipboard(link);
                }
            });
        },

        initAjaxForms: function() {
            // Handle AJAX form submissions with proper loading states
            $('.wpap-ajax-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $submitButton = $form.find('[type="submit"]');
                var originalText = $submitButton.text();
                
                // Show loading state
                $form.addClass('wpap-loading');
                $submitButton.prop('disabled', true).text(wpap_frontend.strings.loading);
                
                // Get form data
                var formData = new FormData(this);
                formData.append('action', $form.data('action'));
                formData.append('nonce', wpap_frontend.nonce);
                
                // Submit via AJAX
                $.ajax({
                    url: wpap_frontend.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            WPAPFrontend.showNotice(response.data.message, 'success');
                            
                            // Handle redirect
                            if (response.data.redirect) {
                                setTimeout(function() {
                                    window.location.href = response.data.redirect;
                                }, 1500);
                            }
                            
                            // Reset form if needed
                            if (response.data.reset_form) {
                                $form[0].reset();
                            }
                        } else {
                            WPAPFrontend.showNotice(response.data.message, 'error');
                        }
                    },
                    error: function() {
                        WPAPFrontend.showNotice(wpap_frontend.strings.error, 'error');
                    },
                    complete: function() {
                        // Remove loading state
                        $form.removeClass('wpap-loading');
                        $submitButton.prop('disabled', false).text(originalText);
                    }
                });
            });
        },

        initStatsRefresh: function() {
            // Auto-refresh stats every 30 seconds if on dashboard
            if ($('.wpap-dashboard').length && $('.wpap-auto-refresh').length) {
                setInterval(function() {
                    WPAPFrontend.refreshStats();
                }, 30000);
            }
        },

        handleRegistration: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitButton = $form.find('[type="submit"]');
            var originalText = $submitButton.text();
            
            // Validate passwords match
            var password = $form.find('[name="password"]').val();
            var confirmPassword = $form.find('[name="confirm_password"]').val();
            
            if (password !== confirmPassword) {
                WPAPFrontend.showNotice('Passwords do not match.', 'error');
                return false;
            }
            
            // Show loading state
            $form.addClass('wpap-loading');
            $submitButton.prop('disabled', true).text(wpap_frontend.strings.loading);
            
            // Prepare data
            var formData = $form.serialize();
            formData += '&action=wpap_affiliate_registration';
            formData += '&nonce=' + wpap_frontend.nonce;
            
            // Submit registration
            $.post(wpap_frontend.ajax_url, formData, function(response) {
                if (response.success) {
                    WPAPFrontend.showNotice(response.data.message, 'success');
                    
                    // Redirect after successful registration
                    if (response.data.redirect) {
                        setTimeout(function() {
                            window.location.href = response.data.redirect;
                        }, 2000);
                    }
                } else {
                    WPAPFrontend.showNotice(response.data.message, 'error');
                }
            }).fail(function() {
                WPAPFrontend.showNotice(wpap_frontend.strings.error, 'error');
            }).always(function() {
                $form.removeClass('wpap-loading');
                $submitButton.prop('disabled', false).text(originalText);
            });
        },

        handleProfileUpdate: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitButton = $form.find('[type="submit"]');
            var originalText = $submitButton.text();
            
            // Show loading state
            $form.addClass('wpap-loading');
            $submitButton.prop('disabled', true).text(wpap_frontend.strings.loading);
            
            // Prepare data
            var formData = $form.serialize();
            formData += '&action=wpap_update_affiliate_profile';
            formData += '&nonce=' + wpap_frontend.nonce;
            
            // Submit update
            $.post(wpap_frontend.ajax_url, formData, function(response) {
                if (response.success) {
                    WPAPFrontend.showNotice(response.data.message, 'success');
                } else {
                    WPAPFrontend.showNotice(response.data.message, 'error');
                }
            }).fail(function() {
                WPAPFrontend.showNotice(wpap_frontend.strings.error, 'error');
            }).always(function() {
                $form.removeClass('wpap-loading');
                $submitButton.prop('disabled', false).text(originalText);
            });
        },

        generateReferralLink: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $container = $button.closest('.wpap-link-generator');
            var url = $container.find('[name="url"]').val() || window.location.origin;
            var campaign = $container.find('[name="campaign"]').val() || '';
            
            // Show loading state
            $button.prop('disabled', true).append('<span class="wpap-spinner"></span>');
            
            // Generate link
            $.post(wpap_frontend.ajax_url, {
                action: 'wpap_generate_affiliate_link',
                url: url,
                campaign: campaign,
                nonce: wpap_frontend.nonce
            }, function(response) {
                if (response.success) {
                    // Update link display
                    $container.find('.wpap-generated-link').val(response.data.link).show();
                    $container.find('.wpap-copy-link').data('link', response.data.link).show();
                    
                    // Show QR code if available
                    if (response.data.qr_code) {
                        $container.find('.wpap-qr-code').html('<img src="' + response.data.qr_code + '" alt="QR Code">').show();
                    }
                    
                    WPAPFrontend.showNotice(response.data.message, 'success');
                } else {
                    WPAPFrontend.showNotice(response.data.message, 'error');
                }
            }).fail(function() {
                WPAPFrontend.showNotice(wpap_frontend.strings.error, 'error');
            }).always(function() {
                $button.prop('disabled', false).find('.wpap-spinner').remove();
            });
        },

        requestPayout: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var amount = $button.data('amount') || $('#wpap-payout-amount').val();
            var method = $button.data('method') || $('#wpap-payout-method').val();
            
            if (!amount || parseFloat(amount) <= 0) {
                WPAPFrontend.showNotice('Please enter a valid payout amount.', 'error');
                return;
            }
            
            if (!confirm('Are you sure you want to request a payout of $' + parseFloat(amount).toFixed(2) + '?')) {
                return;
            }
            
            // Show loading state
            $button.prop('disabled', true).append('<span class="wpap-spinner"></span>');
            
            // Request payout
            $.post(wpap_frontend.ajax_url, {
                action: 'wpap_request_payout',
                amount: amount,
                method: method,
                nonce: wpap_frontend.nonce
            }, function(response) {
                if (response.success) {
                    WPAPFrontend.showNotice(response.data.message, 'success');
                    
                    // Refresh stats and payment history
                    WPAPFrontend.refreshStats();
                    WPAPFrontend.loadPayments();
                    
                    // Reset form
                    $('#wpap-payout-amount').val('');
                } else {
                    WPAPFrontend.showNotice(response.data.message, 'error');
                }
            }).fail(function() {
                WPAPFrontend.showNotice(wpap_frontend.strings.error, 'error');
            }).always(function() {
                $button.prop('disabled', false).find('.wpap-spinner').remove();
            });
        },

        refreshStats: function(period) {
            var $statsContainer = $('.wpap-stats-container');
            if (!$statsContainer.length) return;
            
            // Show loading state
            $statsContainer.addClass('wpap-loading');
            
            // Get selected period
            period = period || $('.wpap-stats-period').val() || 'all_time';
            
            $.post(wpap_frontend.ajax_url, {
                action: 'wpap_get_affiliate_stats',
                period: period,
                nonce: wpap_frontend.nonce
            }, function(response) {
                if (response.success) {
                    var stats = response.data;
                    
                    // Update stat values
                    $('.wpap-stat-earnings .wpap-stat-value').text('$' + parseFloat(stats.total_earnings || 0).toFixed(2));
                    $('.wpap-stat-unpaid .wpap-stat-value').text('$' + parseFloat(stats.unpaid_earnings || 0).toFixed(2));
                    $('.wpap-stat-visits .wpap-stat-value').text(parseInt(stats.total_visits || 0).toLocaleString());
                    $('.wpap-stat-conversions .wpap-stat-value').text(parseInt(stats.total_conversions || 0).toLocaleString());
                    $('.wpap-stat-rate .wpap-stat-value').text(parseFloat(stats.conversion_rate || 0).toFixed(1) + '%');
                    $('.wpap-stat-commissions .wpap-stat-value').text(parseInt(stats.total_commissions || 0).toLocaleString());
                }
            }).fail(function() {
                console.log('Failed to refresh stats');
            }).always(function() {
                $statsContainer.removeClass('wpap-loading');
            });
        },

        loadCommissions: function(e) {
            e.preventDefault();
            
            var $link = $(this);
            var page = $link.data('page') || 1;
            var status = $('.wpap-commission-filter').val() || '';
            var $container = $('.wpap-commissions-container');
            
            // Show loading
            $container.addClass('wpap-loading');
            
            $.post(wpap_frontend.ajax_url, {
                action: 'wpap_get_commission_history',
                page: page,
                status: status,
                per_page: 10,
                nonce: wpap_frontend.nonce
            }, function(response) {
                if (response.success) {
                    // Update commissions list
                    var html = '';
                    $.each(response.data.commissions, function(index, commission) {
                        html += WPAPFrontend.renderCommissionItem(commission);
                    });
                    
                    $('.wpap-commissions-list').html(html);
                    
                    // Update pagination
                    WPAPFrontend.updatePagination('.wpap-commission-pagination', response.data);
                }
            }).always(function() {
                $container.removeClass('wpap-loading');
            });
        },

        loadPayments: function(e) {
            if (e) e.preventDefault();
            
            var page = e ? $(e.target).data('page') : 1;
            var $container = $('.wpap-payments-container');
            
            // Show loading
            $container.addClass('wpap-loading');
            
            $.post(wpap_frontend.ajax_url, {
                action: 'wpap_get_payment_history',
                page: page,
                per_page: 10,
                nonce: wpap_frontend.nonce
            }, function(response) {
                if (response.success) {
                    // Update payments list
                    var html = '';
                    $.each(response.data.payments, function(index, payment) {
                        html += WPAPFrontend.renderPaymentItem(payment);
                    });
                    
                    $('.wpap-payments-list').html(html);
                    
                    // Update pagination
                    WPAPFrontend.updatePagination('.wpap-payment-pagination', response.data);
                }
            }).always(function() {
                $container.removeClass('wpap-loading');
            });
        },

        renderCommissionItem: function(commission) {
            var statusClass = 'wpap-status-' + commission.status;
            var statusText = commission.status.charAt(0).toUpperCase() + commission.status.slice(1);
            var date = new Date(commission.date_created).toLocaleDateString();
            var amount = '$' + parseFloat(commission.commission_amount).toFixed(2);
            
            return '<div class="wpap-commission-item">' +
                '<div class="wpap-commission-details">' +
                    '<h4>' + (commission.description || 'Commission #' + commission.id) + '</h4>' +
                    '<p>' + date + ' • <span class="wpap-status ' + statusClass + '">' + statusText + '</span></p>' +
                '</div>' +
                '<div class="wpap-commission-amount">' + amount + '</div>' +
            '</div>';
        },

        renderPaymentItem: function(payment) {
            var statusClass = 'wpap-status-' + payment.status;
            var statusText = payment.status.charAt(0).toUpperCase() + payment.status.slice(1);
            var date = new Date(payment.date_created).toLocaleDateString();
            var amount = '$' + parseFloat(payment.amount).toFixed(2);
            var method = payment.method.charAt(0).toUpperCase() + payment.method.slice(1).replace('_', ' ');
            
            return '<div class="wpap-commission-item">' +
                '<div class="wpap-commission-details">' +
                    '<h4>Payment #' + payment.id + '</h4>' +
                    '<p>' + date + ' • ' + method + ' • <span class="wpap-status ' + statusClass + '">' + statusText + '</span></p>' +
                '</div>' +
                '<div class="wpap-commission-amount">' + amount + '</div>' +
            '</div>';
        },

        updatePagination: function(selector, data) {
            var $pagination = $(selector);
            if (!$pagination.length || !data.total_pages || data.total_pages <= 1) {
                $pagination.hide();
                return;
            }
            
            var html = '';
            var currentPage = data.page;
            var totalPages = data.total_pages;
            
            // Previous link
            if (currentPage > 1) {
                html += '<a href="#" data-page="' + (currentPage - 1) + '">&laquo; Previous</a>';
            }
            
            // Page numbers
            var startPage = Math.max(1, currentPage - 2);
            var endPage = Math.min(totalPages, currentPage + 2);
            
            for (var i = startPage; i <= endPage; i++) {
                if (i === currentPage) {
                    html += '<span class="current">' + i + '</span>';
                } else {
                    html += '<a href="#" data-page="' + i + '">' + i + '</a>';
                }
            }
            
            // Next link
            if (currentPage < totalPages) {
                html += '<a href="#" data-page="' + (currentPage + 1) + '">Next &raquo;</a>';
            }
            
            $pagination.html(html).show();
        },

        copyToClipboard: function(text) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    WPAPFrontend.showNotice(wpap_frontend.strings.copied, 'success');
                }).catch(function() {
                    WPAPFrontend.fallbackCopyToClipboard(text);
                });
            } else {
                WPAPFrontend.fallbackCopyToClipboard(text);
            }
        },

        fallbackCopyToClipboard: function(text) {
            var textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                WPAPFrontend.showNotice(wpap_frontend.strings.copied, 'success');
            } catch (err) {
                WPAPFrontend.showNotice('Failed to copy link. Please copy manually.', 'error');
                console.error('Fallback copy failed:', err);
            }
            
            document.body.removeChild(textArea);
        },

        showNotice: function(message, type) {
            type = type || 'info';
            
            // Remove existing notices
            $('.wpap-notice-temp').remove();
            
            // Create new notice
            var $notice = $('<div class="wpap-notice wpap-notice-' + type + ' wpap-notice-temp"><p>' + message + '</p></div>');
            
            // Find best location to insert notice
            var $target = $('.wpap-dashboard, .wpap-form, .wpap-container').first();
            if ($target.length) {
                $target.prepend($notice);
            } else {
                $('body').prepend($notice);
            }
            
            // Auto-remove after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Scroll to notice
            $('html, body').animate({
                scrollTop: $notice.offset().top - 20
            }, 300);
        },

        formatCurrency: function(amount, currency) {
            currency = currency || 'USD';
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: currency
            }).format(amount);
        },

        formatNumber: function(num, decimals) {
            decimals = decimals || 0;
            return parseFloat(num).toLocaleString('en-US', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        },

        debounce: function(func, wait, immediate) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                var later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                var callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        }
    };

    // Initialize frontend functionality
    WPAPFrontend.init();

    // Make WPAPFrontend available globally
    window.WPAPFrontend = WPAPFrontend;

    // Form validation helpers
    $.fn.wpapValidate = function() {
        return this.each(function() {
            var $form = $(this);
            
            $form.find('input[required], select[required], textarea[required]').on('blur', function() {
                var $field = $(this);
                var value = $field.val().trim();
                
                if (!value) {
                    $field.addClass('wpap-field-error');
                    WPAPFrontend.showFieldError($field, 'This field is required.');
                } else {
                    $field.removeClass('wpap-field-error');
                    WPAPFrontend.hideFieldError($field);
                }
            });
            
            $form.find('input[type="email"]').on('blur', function() {
                var $field = $(this);
                var value = $field.val().trim();
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (value && !emailRegex.test(value)) {
                    $field.addClass('wpap-field-error');
                    WPAPFrontend.showFieldError($field, 'Please enter a valid email address.');
                } else {
                    $field.removeClass('wpap-field-error');
                    WPAPFrontend.hideFieldError($field);
                }
            });
        });
    };

    WPAPFrontend.showFieldError = function($field, message) {
        var $error = $field.siblings('.wpap-form-error');
        if (!$error.length) {
            $error = $('<div class="wpap-form-error"></div>');
            $field.after($error);
        }
        $error.text(message);
    };

    WPAPFrontend.hideFieldError = function($field) {
        $field.siblings('.wpap-form-error').remove();
    };

    // Initialize form validation
    $('.wpap-form').wpapValidate();

    // Real-time search/filter
    $('.wpap-search-input').on('keyup', WPAPFrontend.debounce(function() {
        var searchTerm = $(this).val().toLowerCase();
        var $items = $(this).data('target') ? $($(this).data('target')) : $('.wpap-searchable-item');
        
        $items.each(function() {
            var text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(searchTerm) !== -1);
        });
    }, 300));

    // Smooth scrolling for anchor links
    $('a[href^="#"]').on('click', function(e) {
        var target = $(this.getAttribute('href'));
        if (target.length) {
            e.preventDefault();
            $('html, body').animate({
                scrollTop: target.offset().top - 100
            }, 500);
        }
    });

    // Auto-save draft functionality for forms
    $('.wpap-auto-save').on('input', WPAPFrontend.debounce(function() {
        var $form = $(this).closest('form');
        var formData = $form.serialize();
        var formId = $form.attr('id') || 'wpap_form';
        
        localStorage.setItem('wpap_draft_' + formId, formData);
        
        // Show save indicator
        var $indicator = $form.find('.wpap-save-indicator');
        if (!$indicator.length) {
            $indicator = $('<span class="wpap-save-indicator" style="color: #646970; font-size: 12px;">Draft saved</span>');
            $form.find('.wpap-form-actions').prepend($indicator);
        }
        $indicator.show().delay(2000).fadeOut();
    }, 1000));

    // Restore draft on page load
    $('.wpap-auto-save').closest('form').each(function() {
        var $form = $(this);
        var formId = $form.attr('id') || 'wpap_form';
        var draft = localStorage.getItem('wpap_draft_' + formId);
        
        if (draft && confirm('Restore unsaved changes?')) {
            var params = new URLSearchParams(draft);
            params.forEach(function(value, name) {
                var $field = $form.find('[name="' + name + '"]');
                if ($field.is(':radio, :checkbox')) {
                    $field.filter('[value="' + value + '"]').prop('checked', true);
                } else {
                    $field.val(value);
                }
            });
        }
    });

    // Clear draft on successful form submission
    $(document).on('wpap_form_success', function(e, formId) {
        localStorage.removeItem('wpap_draft_' + formId);
    });
});

// Utility functions available globally
window.wpapFormatCurrency = function(amount, currency) {
    return window.WPAPFrontend ? window.WPAPFrontend.formatCurrency(amount, currency) : '$' + parseFloat(amount).toFixed(2);
};

window.wpapFormatNumber = function(num, decimals) {
    return window.WPAPFrontend ? window.WPAPFrontend.formatNumber(num, decimals) : parseFloat(num).toLocaleString();
};