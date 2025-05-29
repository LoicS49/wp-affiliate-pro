<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAP_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_filter( 'plugin_action_links_' . WPAP_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
		
		// AJAX handlers
		add_action( 'wp_ajax_wpap_dashboard_stats', array( $this, 'ajax_dashboard_stats' ) );
		add_action( 'wp_ajax_wpap_approve_affiliate', array( $this, 'ajax_approve_affiliate' ) );
		add_action( 'wp_ajax_wpap_reject_affiliate', array( $this, 'ajax_reject_affiliate' ) );
	}

	public function add_admin_menu() {
		// Menu principal
		add_menu_page(
			'WP Affiliate Pro',
			'Affiliate Pro',
			'manage_options',
			'wpap-dashboard',
			array( $this, 'dashboard_page' ),
			'dashicons-networking',
			30
		);

		// Sous-menus
		add_submenu_page(
			'wpap-dashboard',
			'Dashboard',
			'Dashboard',
			'manage_options',
			'wpap-dashboard',
			array( $this, 'dashboard_page' )
		);

		add_submenu_page(
			'wpap-dashboard',
			'Affiliates',
			'Affiliates',
			'manage_options',
			'wpap-affiliates',
			array( $this, 'affiliates_page' )
		);

		add_submenu_page(
			'wpap-dashboard',
			'Commissions',
			'Commissions',
			'manage_options',
			'wpap-commissions',
			array( $this, 'commissions_page' )
		);

		add_submenu_page(
			'wpap-dashboard',
			'Payments',
			'Payments',
			'manage_options',
			'wpap-payments',
			array( $this, 'payments_page' )
		);

		add_submenu_page(
			'wpap-dashboard',
			'Reports',
			'Reports',
			'manage_options',
			'wpap-reports',
			array( $this, 'reports_page' )
		);

		add_submenu_page(
			'wpap-dashboard',
			'Settings',
			'Settings',
			'manage_options',
			'wpap-settings',
			array( $this, 'settings_page' )
		);
	}

	public function admin_init() {
		if ( isset( $_GET['wpap_action'] ) ) {
			$this->handle_admin_actions();
		}

		// Display admin notices
		if ( isset( $_GET['message'] ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		}
	}

	public function plugin_action_links( $links ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=wpap-settings' ) . '">' . __( 'Settings', 'wp-affiliate-pro' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	public function dashboard_page() {
		// Get dashboard stats
		$stats = $this->get_dashboard_stats();
		$recent_affiliates = $this->get_recent_affiliates();
		$top_affiliates = $this->get_top_affiliates();

		// Load dashboard template if it exists
		$template_file = WPAP_PLUGIN_DIR . 'templates/admin/dashboard.php';
		if ( file_exists( $template_file ) ) {
			include $template_file;
		} else {
			// Fallback dashboard
			$this->render_fallback_dashboard( $stats, $recent_affiliates, $top_affiliates );
		}
	}

	public function affiliates_page() {
		echo '<div class="wrap">';
		echo '<h1>' . __( 'Manage Affiliates', 'wp-affiliate-pro' ) . '</h1>';
		
		// Get affiliates list
		$affiliates = $this->get_affiliates_list();
		
		if ( ! empty( $affiliates ) ) {
			echo '<div class="wpap-affiliates-table">';
			echo '<table class="wp-list-table widefat fixed striped">';
			echo '<thead>';
			echo '<tr>';
			echo '<th>' . __( 'Name', 'wp-affiliate-pro' ) . '</th>';
			echo '<th>' . __( 'Email', 'wp-affiliate-pro' ) . '</th>';
			echo '<th>' . __( 'Referral Code', 'wp-affiliate-pro' ) . '</th>';
			echo '<th>' . __( 'Status', 'wp-affiliate-pro' ) . '</th>';
			echo '<th>' . __( 'Earnings', 'wp-affiliate-pro' ) . '</th>';
			echo '<th>' . __( 'Registered', 'wp-affiliate-pro' ) . '</th>';
			echo '<th>' . __( 'Actions', 'wp-affiliate-pro' ) . '</th>';
			echo '</tr>';
			echo '</thead>';
			echo '<tbody>';
			
			foreach ( $affiliates as $affiliate ) {
				$user = get_user_by( 'id', $affiliate->user_id );
				echo '<tr>';
				echo '<td>' . esc_html( $user ? $user->display_name : 'Unknown' ) . '</td>';
				echo '<td>' . esc_html( $user ? $user->user_email : 'Unknown' ) . '</td>';
				echo '<td><code>' . esc_html( $affiliate->referral_code ) . '</code></td>';
				echo '<td><span class="wpap-status wpap-status-' . esc_attr( $affiliate->status ) . '">' . esc_html( ucfirst( $affiliate->status ) ) . '</span></td>';
				echo '<td>' . wpap_format_amount( $affiliate->total_earnings ?: 0 ) . '</td>';
				echo '<td>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $affiliate->date_registered ) ) ) . '</td>';
				echo '<td>';
				if ( $affiliate->status === 'pending' ) {
					echo '<button class="button button-small wpap-approve-affiliate" data-affiliate-id="' . esc_attr( $affiliate->id ) . '">' . __( 'Approve', 'wp-affiliate-pro' ) . '</button> ';
					echo '<button class="button button-small wpap-reject-affiliate" data-affiliate-id="' . esc_attr( $affiliate->id ) . '">' . __( 'Reject', 'wp-affiliate-pro' ) . '</button>';
				} elseif ( $affiliate->status === 'active' ) {
					echo '<span class="button button-small button-disabled">' . __( 'Active', 'wp-affiliate-pro' ) . '</span>';
				}
				echo '</td>';
				echo '</tr>';
			}
			
			echo '</tbody>';
			echo '</table>';
			echo '</div>';
		} else {
			echo '<div class="notice notice-info"><p>' . __( 'No affiliates found. New affiliates will appear here after registration.', 'wp-affiliate-pro' ) . '</p></div>';
		}
		
		echo '</div>';
	}

	public function commissions_page() {
		echo '<div class="wrap">';
		echo '<h1>' . __( 'Manage Commissions', 'wp-affiliate-pro' ) . '</h1>';
		
		// Get commissions list
		$commissions = $this->get_commissions_list();
		
		if ( ! empty( $commissions ) ) {
			echo '<div class="wpap-commissions-table">';
			echo '<table class="wp-list-table widefat fixed striped">';
			echo '<thead>';
			echo '<tr>';
			echo '<th>' . __( 'Affiliate', 'wp-affiliate-pro' ) . '</th>';
			echo '<th>' . __( 'Amount', 'wp-affiliate-pro' ) . '</th>';
			echo '<th>' . __( 'Type', 'wp-affiliate-pro' ) . '</th>';
			echo '<th>' . __( 'Status', 'wp-affiliate-pro' ) . '</th>';
			echo '<th>' . __( 'Date', 'wp-affiliate-pro' ) . '</th>';
			echo '</tr>';
			echo '</thead>';
			echo '<tbody>';
			
			foreach ( $commissions as $commission ) {
				$affiliate = wpap()->affiliates->get( $commission->affiliate_id );
				$user = $affiliate ? get_user_by( 'id', $affiliate->user_id ) : null;
				
				echo '<tr>';
				echo '<td>' . esc_html( $user ? $user->display_name : 'Unknown' ) . '</td>';
				echo '<td>' . wpap_format_amount( $commission->commission_amount ) . '</td>';
				echo '<td>' . esc_html( ucfirst( $commission->type ) ) . '</td>';
				echo '<td><span class="wpap-status wpap-status-' . esc_attr( $commission->status ) . '">' . esc_html( ucfirst( $commission->status ) ) . '</span></td>';
				echo '<td>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $commission->date_created ) ) ) . '</td>';
				echo '</tr>';
			}
			
			echo '</tbody>';
			echo '</table>';
			echo '</div>';
		} else {
			echo '<div class="notice notice-info"><p>' . __( 'No commissions found.', 'wp-affiliate-pro' ) . '</p></div>';
		}
		
		echo '</div>';
	}

	public function payments_page() {
		echo '<div class="wrap">';
		echo '<h1>' . __( 'Manage Payments', 'wp-affiliate-pro' ) . '</h1>';
		echo '<div class="notice notice-info"><p>' . __( 'Payment management interface coming soon.', 'wp-affiliate-pro' ) . '</p></div>';
		echo '</div>';
	}

	public function reports_page() {
		echo '<div class="wrap">';
		echo '<h1>' . __( 'Reports & Analytics', 'wp-affiliate-pro' ) . '</h1>';
		echo '<div class="notice notice-info"><p>' . __( 'Reports interface coming soon.', 'wp-affiliate-pro' ) . '</p></div>';
		echo '</div>';
	}

	public function settings_page() {
		if ( isset( $_POST['wpap_save_settings'] ) ) {
			$this->save_settings();
		}

		$general_settings = get_option( 'wpap_general_settings', array() );
		$email_settings = get_option( 'wpap_email_settings', array() );

		echo '<div class="wrap">';
		echo '<h1>' . __( 'WP Affiliate Pro Settings', 'wp-affiliate-pro' ) . '</h1>';
		
		echo '<div class="wpap-tabs">';
		echo '<nav class="nav-tab-wrapper">';
		echo '<a href="#general" class="nav-tab nav-tab-active">' . __( 'General', 'wp-affiliate-pro' ) . '</a>';
		echo '<a href="#emails" class="nav-tab">' . __( 'Emails', 'wp-affiliate-pro' ) . '</a>';
		echo '<a href="#pages" class="nav-tab">' . __( 'Pages', 'wp-affiliate-pro' ) . '</a>';
		echo '</nav>';
		
		echo '<form method="post" action="">';
		wp_nonce_field( 'wpap_save_settings' );
		
		// General Settings Tab
		echo '<div id="general" class="wpap-tab-content">';
		echo '<h2>' . __( 'General Settings', 'wp-affiliate-pro' ) . '</h2>';
		echo '<table class="form-table">';
		
		echo '<tr>';
		echo '<th scope="row">' . __( 'Default Commission Rate (%)', 'wp-affiliate-pro' ) . '</th>';
		echo '<td><input type="number" name="general[commission_rate]" value="' . esc_attr( $general_settings['commission_rate'] ?? 10 ) . '" min="0" max="100" step="0.01" class="regular-text" /></td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">' . __( 'Minimum Payout Amount', 'wp-affiliate-pro' ) . '</th>';
		echo '<td><input type="number" name="general[minimum_payout]" value="' . esc_attr( $general_settings['minimum_payout'] ?? 50 ) . '" min="1" step="0.01" class="regular-text" /></td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">' . __( 'Cookie Duration (days)', 'wp-affiliate-pro' ) . '</th>';
		echo '<td><input type="number" name="general[cookie_duration]" value="' . esc_attr( $general_settings['cookie_duration'] ?? 30 ) . '" min="1" max="365" class="regular-text" /></td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">' . __( 'Currency', 'wp-affiliate-pro' ) . '</th>';
		echo '<td>';
		echo '<select name="general[currency]">';
		$currencies = array( 'USD' => 'USD ($)', 'EUR' => 'EUR (€)', 'GBP' => 'GBP (£)' );
		foreach ( $currencies as $code => $label ) {
			echo '<option value="' . esc_attr( $code ) . '"' . selected( $general_settings['currency'] ?? 'USD', $code, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '</td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">' . __( 'Auto-approve Affiliates', 'wp-affiliate-pro' ) . '</th>';
		echo '<td>';
		echo '<label><input type="radio" name="general[auto_approve_affiliates]" value="yes" ' . checked( $general_settings['auto_approve_affiliates'] ?? 'no', 'yes', false ) . ' /> ' . __( 'Yes', 'wp-affiliate-pro' ) . '</label><br>';
		echo '<label><input type="radio" name="general[auto_approve_affiliates]" value="no" ' . checked( $general_settings['auto_approve_affiliates'] ?? 'no', 'no', false ) . ' /> ' . __( 'No', 'wp-affiliate-pro' ) . '</label>';
		echo '</td>';
		echo '</tr>';
		
		echo '</table>';
		echo '</div>';
		
		// Email Settings Tab
		echo '<div id="emails" class="wpap-tab-content" style="display: none;">';
		echo '<h2>' . __( 'Email Settings', 'wp-affiliate-pro' ) . '</h2>';
		echo '<table class="form-table">';
		
		echo '<tr>';
		echo '<th scope="row">' . __( 'Enable Email Notifications', 'wp-affiliate-pro' ) . '</th>';
		echo '<td>';
		echo '<label><input type="radio" name="email[enable_emails]" value="yes" ' . checked( $email_settings['enable_emails'] ?? 'yes', 'yes', false ) . ' /> ' . __( 'Yes', 'wp-affiliate-pro' ) . '</label><br>';
		echo '<label><input type="radio" name="email[enable_emails]" value="no" ' . checked( $email_settings['enable_emails'] ?? 'yes', 'no', false ) . ' /> ' . __( 'No', 'wp-affiliate-pro' ) . '</label>';
		echo '</td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">' . __( 'From Name', 'wp-affiliate-pro' ) . '</th>';
		echo '<td><input type="text" name="email[from_name]" value="' . esc_attr( $email_settings['from_name'] ?? get_bloginfo( 'name' ) ) . '" class="regular-text" /></td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">' . __( 'From Email', 'wp-affiliate-pro' ) . '</th>';
		echo '<td><input type="email" name="email[from_email]" value="' . esc_attr( $email_settings['from_email'] ?? get_option( 'admin_email' ) ) . '" class="regular-text" /></td>';
		echo '</tr>';
		
		echo '</table>';
		echo '</div>';
		
		// Pages Tab
		echo '<div id="pages" class="wpap-tab-content" style="display: none;">';
		echo '<h2>' . __( 'Page Configuration', 'wp-affiliate-pro' ) . '</h2>';
		echo '<p>' . __( 'The following pages were created automatically during installation:', 'wp-affiliate-pro' ) . '</p>';
		
		$page_settings = get_option( 'wpap_page_settings', array() );
		
		if ( ! empty( $page_settings['affiliate_dashboard_page'] ) ) {
			$page = get_post( $page_settings['affiliate_dashboard_page'] );
			if ( $page ) {
				echo '<p><strong>' . __( 'Affiliate Dashboard:', 'wp-affiliate-pro' ) . '</strong> <a href="' . get_permalink( $page->ID ) . '" target="_blank">' . esc_html( $page->post_title ) . '</a></p>';
			}
		}
		
		if ( ! empty( $page_settings['affiliate_registration_page'] ) ) {
			$page = get_post( $page_settings['affiliate_registration_page'] );
			if ( $page ) {
				echo '<p><strong>' . __( 'Affiliate Registration:', 'wp-affiliate-pro' ) . '</strong> <a href="' . get_permalink( $page->ID ) . '" target="_blank">' . esc_html( $page->post_title ) . '</a></p>';
			}
		}
		
		echo '</div>';
		
		echo '<p class="submit">';
		echo '<input type="submit" name="wpap_save_settings" class="button-primary" value="' . __( 'Save Settings', 'wp-affiliate-pro' ) . '" />';
		echo '</p>';
		echo '</form>';
		
		echo '</div>';
		echo '</div>';
		
		// Add tab switching JavaScript
		echo '<script>
		jQuery(document).ready(function($) {
			$(".nav-tab").click(function(e) {
				e.preventDefault();
				$(".nav-tab").removeClass("nav-tab-active");
				$(this).addClass("nav-tab-active");
				$(".wpap-tab-content").hide();
				$($(this).attr("href")).show();
			});
		});
		</script>';
	}

	public function admin_notices() {
		$message = sanitize_text_field( $_GET['message'] );
		
		switch ( $message ) {
			case 'affiliate_approved':
				echo '<div class="notice notice-success"><p>' . __( 'Affiliate approved successfully.', 'wp-affiliate-pro' ) . '</p></div>';
				break;
			case 'affiliate_rejected':
				echo '<div class="notice notice-success"><p>' . __( 'Affiliate rejected successfully.', 'wp-affiliate-pro' ) . '</p></div>';
				break;
			case 'affiliate_approval_failed':
				echo '<div class="notice notice-error"><p>' . __( 'Failed to approve affiliate.', 'wp-affiliate-pro' ) . '</p></div>';
				break;
			case 'affiliate_rejection_failed':
				echo '<div class="notice notice-error"><p>' . __( 'Failed to reject affiliate.', 'wp-affiliate-pro' ) . '</p></div>';
				break;
			case 'system_not_ready':
				echo '<div class="notice notice-error"><p>' . __( 'System not ready. Please try again later.', 'wp-affiliate-pro' ) . '</p></div>';
				break;
		}
	}

	// Helper methods
	private function get_dashboard_stats() {
		$stats = array();
		
		try {
			if ( wpap() && wpap()->affiliates ) {
				$stats['total_affiliates'] = wpap()->affiliates->count();
				$stats['active_affiliates'] = wpap()->affiliates->count( array( 'status' => 'active' ) );
				$stats['pending_affiliates'] = wpap()->affiliates->count( array( 'status' => 'pending' ) );
			} else {
				$stats['total_affiliates'] = 0;
				$stats['active_affiliates'] = 0;
				$stats['pending_affiliates'] = 0;
			}
			
			if ( wpap() && wpap()->database ) {
				$commission_summary = wpap()->database->get_commission_summary();
				$stats['total_commissions'] = $commission_summary['total_commissions'];
				$stats['pending_commissions'] = $commission_summary['pending_commissions'];
				$stats['paid_commissions'] = $commission_summary['paid_commissions'];
			} else {
				$stats['total_commissions'] = 0;
				$stats['pending_commissions'] = 0;
				$stats['paid_commissions'] = 0;
			}
			
			if ( wpap() && wpap()->payments ) {
				$payment_summary = wpap()->payments->get_payment_summary();
				$stats['total_payments'] = $payment_summary['total_payments'];
				$stats['pending_payments'] = $payment_summary['pending_payments'];
			} else {
				$stats['total_payments'] = 0;
				$stats['pending_payments'] = 0;
			}
		} catch ( Exception $e ) {
			// Fallback stats if database isn't ready
			$stats = array(
				'total_affiliates' => 0,
				'active_affiliates' => 0,
				'pending_affiliates' => 0,
				'total_commissions' => 0,
				'pending_commissions' => 0,
				'paid_commissions' => 0,
				'total_payments' => 0,
				'pending_payments' => 0
			);
		}

		return $stats;
	}

	private function get_recent_affiliates( $limit = 5 ) {
		try {
			if ( wpap() && wpap()->affiliates ) {
				return wpap()->affiliates->get_all( array(
					'limit' => $limit,
					'orderby' => 'date_registered',
					'order' => 'DESC'
				) );
			}
		} catch ( Exception $e ) {
			// Return empty array if not ready
		}
		
		return array();
	}

	private function get_top_affiliates( $limit = 5 ) {
		try {
			if ( wpap() && wpap()->database ) {
				return wpap()->database->get_top_affiliates( $limit );
			}
		} catch ( Exception $e ) {
			// Return empty array if not ready
		}
		
		return array();
	}

	private function get_affiliates_list() {
		try {
			if ( wpap() && wpap()->affiliates ) {
				return wpap()->affiliates->get_all( array( 'limit' => 50 ) );
			}
		} catch ( Exception $e ) {
			// Return empty array if not ready
		}
		
		return array();
	}

	private function get_commissions_list() {
		try {
			if ( wpap() && wpap()->commissions ) {
				return wpap()->commissions->get_all( array( 'limit' => 50 ) );
			}
		} catch ( Exception $e ) {
			// Return empty array if not ready
		}
		
		return array();
	}

	private function render_fallback_dashboard( $stats, $recent_affiliates = array(), $top_affiliates = array() ) {
		echo '<div class="wrap">';
		echo '<h1>' . __( 'WP Affiliate Pro Dashboard', 'wp-affiliate-pro' ) . '</h1>';
		
		echo '<div class="wpap-dashboard-stats">';
		echo '<div class="wpap-stat-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">';
		
		echo '<div class="wpap-stat-card" style="background: white; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
		echo '<h3 style="margin: 0; font-size: 32px; color: #0073aa;">' . number_format( $stats['total_affiliates'] ) . '</h3>';
		echo '<p style="margin: 5px 0 0 0; color: #646970; text-transform: uppercase; font-size: 12px; font-weight: 600;">' . __( 'Total Affiliates', 'wp-affiliate-pro' ) . '</p>';
		echo '<small style="color: #8c8f94;">' . sprintf( __( '%d Active | %d Pending', 'wp-affiliate-pro' ), $stats['active_affiliates'], $stats['pending_affiliates'] ) . '</small>';
		echo '</div>';
		
		echo '<div class="wpap-stat-card" style="background: white; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
		echo '<h3 style="margin: 0; font-size: 32px; color: #00a32a;">' . wpap_format_amount( $stats['total_commissions'] ) . '</h3>';
		echo '<p style="margin: 5px 0 0 0; color: #646970; text-transform: uppercase; font-size: 12px; font-weight: 600;">' . __( 'Total Commissions', 'wp-affiliate-pro' ) . '</p>';
		echo '<small style="color: #8c8f94;">' . sprintf( __( '%s Pending | %s Paid', 'wp-affiliate-pro' ), wpap_format_amount( $stats['pending_commissions'] ), wpap_format_amount( $stats['paid_commissions'] ) ) . '</small>';
		echo '</div>';
		
		echo '<div class="wpap-stat-card" style="background: white; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
		echo '<h3 style="margin: 0; font-size: 32px; color: #d63638;">' . wpap_format_amount( $stats['total_payments'] ) . '</h3>';
		echo '<p style="margin: 5px 0 0 0; color: #646970; text-transform: uppercase; font-size: 12px; font-weight: 600;">' . __( 'Total Payments', 'wp-affiliate-pro' ) . '</p>';
		echo '<small style="color: #8c8f94;">' . sprintf( __( '%s Pending', 'wp-affiliate-pro' ), wpap_format_amount( $stats['pending_payments'] ) ) . '</small>';
		echo '</div>';
		
		echo '</div>';
		echo '</div>';
		
		// Recent Affiliates
		if ( ! empty( $recent_affiliates ) ) {
			echo '<div class="wpap-widget" style="background: white; border: 1px solid #ccd0d4; border-radius: 4px; margin: 20px 0;">';
			echo '<div class="wpap-widget-header" style="padding: 15px 20px; border-bottom: 1px solid #ccd0d4; font-weight: 600;">' . __( 'Recent Affiliates', 'wp-affiliate-pro' ) . '</div>';
			echo '<div class="wpap-widget-content" style="padding: 20px;">';
			
			foreach ( $recent_affiliates as $affiliate ) {
				$user = get_user_by( 'id', $affiliate->user_id );
				echo '<div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f1f1f1;">';
				echo '<div>';
				echo '<strong>' . esc_html( $user ? $user->display_name : 'Unknown' ) . '</strong><br>';
				echo '<small style="color: #646970;">' . esc_html( $user ? $user->user_email : 'Unknown' ) . '</small>';
				echo '</div>';
				echo '<span class="wpap-status wpap-status-' . esc_attr( $affiliate->status ) . '">' . esc_html( ucfirst( $affiliate->status ) ) . '</span>';
				echo '</div>';
			}
			
			echo '</div>';
			echo '</div>';
		}
		
		echo '<div class="notice notice-success"><p><strong>' . __( 'Status:', 'wp-affiliate-pro' ) . '</strong> ' . __( 'Extension installed and operational!', 'wp-affiliate-pro' ) . '</p></div>';
		echo '<p><strong>' . __( 'Version:', 'wp-affiliate-pro' ) . '</strong> ' . WPAP_VERSION . '</p>';
		echo '<p><strong>' . __( 'Next Steps:', 'wp-affiliate-pro' ) . '</strong></p>';
		echo '<ul>';
		echo '<li>' . __( 'Configure your settings', 'wp-affiliate-pro' ) . ' → <a href="' . admin_url( 'admin.php?page=wpap-settings' ) . '">' . __( 'Settings', 'wp-affiliate-pro' ) . '</a></li>';
		echo '<li>' . __( 'Create affiliate registration page with shortcode:', 'wp-affiliate-pro' ) . ' <code>[wpap_affiliate_registration]</code></li>';
		echo '<li>' . __( 'Create affiliate dashboard page with shortcode:', 'wp-affiliate-pro' ) . ' <code>[wpap_affiliate_dashboard]</code></li>';
		echo '</ul>';
		
		echo '</div>';
	}

	private function save_settings() {
		check_admin_referer( 'wpap_save_settings' );

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$updated = false;

		// General settings
		if ( isset( $_POST['general'] ) ) {
			$current_settings = get_option( 'wpap_general_settings', array() );
			$general_settings = $current_settings;
			
			$fields = array( 'commission_rate', 'minimum_payout', 'cookie_duration', 'auto_approve_affiliates', 'currency' );
			
			foreach ( $fields as $field ) {
				if ( isset( $_POST['general'][$field] ) ) {
					$general_settings[$field] = sanitize_text_field( $_POST['general'][$field] );
				}
			}
			
			update_option( 'wpap_general_settings', $general_settings );
			$updated = true;
		}

		// Email settings
		if ( isset( $_POST['email'] ) ) {
			$current_settings = get_option( 'wpap_email_settings', array() );
			$email_settings = $current_settings;
			
			$fields = array( 'enable_emails', 'from_name', 'from_email' );
			
			foreach ( $fields as $field ) {
				if ( isset( $_POST['email'][$field] ) ) {
					if ( $field === 'from_email' ) {
						$email_settings[$field] = sanitize_email( $_POST['email'][$field] );
					} else {
						$email_settings[$field] = sanitize_text_field( $_POST['email'][$field] );
					}
				}
			}
			
			update_option( 'wpap_email_settings', $email_settings );
			$updated = true;
		}

		if ( $updated ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Settings saved successfully.', 'wp-affiliate-pro' ) . '</p></div>';
			} );
		}
	}

	private function handle_admin_actions() {
		$action = sanitize_text_field( $_GET['wpap_action'] );
		$nonce = $_GET['_wpnonce'] ?? '';

		if ( ! wp_verify_nonce( $nonce, 'wpap_admin_action' ) ) {
			wp_die( __( 'Security check failed.', 'wp-affiliate-pro' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'wp-affiliate-pro' ) );
		}

		switch ( $action ) {
			case 'approve_affiliate':
				$affiliate_id = intval( $_GET['affiliate_id'] );
				if ( wpap() && wpap()->affiliates ) {
					$result = wpap()->affiliates->approve( $affiliate_id );
					$message = $result ? 'affiliate_approved' : 'affiliate_approval_failed';
				} else {
					$message = 'system_not_ready';
				}
				break;

			case 'reject_affiliate':
				$affiliate_id = intval( $_GET['affiliate_id'] );
				$reason = isset( $_GET['reason'] ) ? sanitize_text_field( $_GET['reason'] ) : '';
				if ( wpap() && wpap()->affiliates ) {
					$result = wpap()->affiliates->reject( $affiliate_id, $reason );
					$message = $result ? 'affiliate_rejected' : 'affiliate_rejection_failed';
				} else {
					$message = 'system_not_ready';
				}
				break;

			default:
				$message = 'unknown_action';
				break;
		}

		wp_redirect( add_query_arg( 'message', $message, wp_get_referer() ) );
		exit;
	}

	// AJAX handlers
	public function ajax_dashboard_stats() {
		check_ajax_referer( 'wpap_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to view this data.', 'wp-affiliate-pro' ) );
		}

		$stats = $this->get_dashboard_stats();

		wp_send_json_success( array(
			'stats' => $stats
		) );
	}

	public function ajax_approve_affiliate() {
		check_ajax_referer( 'wpap_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to approve affiliates.', 'wp-affiliate-pro' ) );
		}

		$affiliate_id = intval( $_POST['affiliate_id'] );
		
		if ( wpap() && wpap()->affiliates ) {
			$result = wpap()->affiliates->approve( $affiliate_id );

			if ( $result ) {
				wp_send_json_success( array(
					'message' => __( 'Affiliate approved successfully.', 'wp-affiliate-pro' )
				) );
			} else {
				wp_send_json_error( array(
					'message' => __( 'Failed to approve affiliate.', 'wp-affiliate-pro' )
				) );
			}
		} else {
			wp_send_json_error( array(
				'message' => __( 'System not ready. Please try again later.', 'wp-affiliate-pro' )
			) );
		}
	}

	public function ajax_reject_affiliate() {
		check_ajax_referer( 'wpap_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to reject affiliates.', 'wp-affiliate-pro' ) );
		}

		$affiliate_id = intval( $_POST['affiliate_id'] );
		$reason = sanitize_text_field( $_POST['reason'] ?? '' );
		
		if ( wpap() && wpap()->affiliates ) {
			$result = wpap()->affiliates->reject( $affiliate_id, $reason );

			if ( $result ) {
				wp_send_json_success( array(
					'message' => __( 'Affiliate rejected successfully.', 'wp-affiliate-pro' )
				) );
			} else {
				wp_send_json_error( array(
					'message' => __( 'Failed to reject affiliate.', 'wp-affiliate-pro' )
				) );
			}
		} else {
			wp_send_json_error( array(
				'message' => __( 'System not ready. Please try again later.', 'wp-affiliate-pro' )
			) );
		}
	}
}