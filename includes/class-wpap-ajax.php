<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAP_Ajax {

	public function __construct() {
		// Public AJAX handlers (available to non-logged-in users)
		add_action( 'wp_ajax_nopriv_wpap_affiliate_registration', array( $this, 'handle_affiliate_registration' ) );
		add_action( 'wp_ajax_wpap_affiliate_registration', array( $this, 'handle_affiliate_registration' ) );
		
		// Logged-in user AJAX handlers
		add_action( 'wp_ajax_wpap_generate_affiliate_link', array( $this, 'generate_affiliate_link' ) );
		add_action( 'wp_ajax_wpap_get_affiliate_stats', array( $this, 'get_affiliate_stats' ) );
		add_action( 'wp_ajax_wpap_update_affiliate_profile', array( $this, 'update_affiliate_profile' ) );
		add_action( 'wp_ajax_wpap_get_commission_history', array( $this, 'get_commission_history' ) );
		add_action( 'wp_ajax_wpap_get_payment_history', array( $this, 'get_payment_history' ) );
		add_action( 'wp_ajax_wpap_request_payout', array( $this, 'request_payout' ) );
		
		// Admin AJAX handlers
		add_action( 'wp_ajax_wpap_process_payment', array( $this, 'process_payment' ) );
		add_action( 'wp_ajax_wpap_bulk_action_affiliates', array( $this, 'bulk_action_affiliates' ) );
		add_action( 'wp_ajax_wpap_bulk_action_commissions', array( $this, 'bulk_action_commissions' ) );
	}

	/**
	 * Handle affiliate registration via AJAX
	 */
	public function handle_affiliate_registration() {
		try {
			check_ajax_referer( 'wpap_registration_nonce', 'nonce' );

			$first_name = sanitize_text_field( $_POST['first_name'] ?? '' );
			$last_name = sanitize_text_field( $_POST['last_name'] ?? '' );
			$email = sanitize_email( $_POST['email'] ?? '' );
			$username = sanitize_user( $_POST['username'] ?? '' );
			$password = $_POST['password'] ?? '';
			$confirm_password = $_POST['confirm_password'] ?? '';
			$website = esc_url_raw( $_POST['website'] ?? '' );
			$payment_email = sanitize_email( $_POST['payment_email'] ?? '' );
			$terms_accepted = isset( $_POST['terms_accepted'] ) && $_POST['terms_accepted'];

			// Validation
			$errors = array();

			if ( empty( $first_name ) ) {
				$errors[] = __( 'First name is required.', 'wp-affiliate-pro' );
			}

			if ( empty( $last_name ) ) {
				$errors[] = __( 'Last name is required.', 'wp-affiliate-pro' );
			}

			if ( empty( $email ) || ! is_email( $email ) ) {
				$errors[] = __( 'Valid email address is required.', 'wp-affiliate-pro' );
			}

			if ( email_exists( $email ) ) {
				$errors[] = __( 'Email address already exists.', 'wp-affiliate-pro' );
			}

			if ( empty( $username ) ) {
				$errors[] = __( 'Username is required.', 'wp-affiliate-pro' );
			}

			if ( username_exists( $username ) ) {
				$errors[] = __( 'Username already exists.', 'wp-affiliate-pro' );
			}

			if ( empty( $password ) || strlen( $password ) < 6 ) {
				$errors[] = __( 'Password must be at least 6 characters long.', 'wp-affiliate-pro' );
			}

			if ( $password !== $confirm_password ) {
				$errors[] = __( 'Passwords do not match.', 'wp-affiliate-pro' );
			}

			if ( ! $terms_accepted ) {
				$errors[] = __( 'You must accept the terms and conditions.', 'wp-affiliate-pro' );
			}

			if ( ! empty( $errors ) ) {
				wp_send_json_error( array(
					'message' => implode( ' ', $errors )
				) );
			}

			// Check if registration is enabled
			$settings = get_option( 'wpap_general_settings', array() );
			if ( isset( $settings['affiliate_registration'] ) && $settings['affiliate_registration'] !== 'enabled' ) {
				wp_send_json_error( array(
					'message' => __( 'Affiliate registration is currently disabled.', 'wp-affiliate-pro' )
				) );
			}

			// Create user account
			$user_id = wp_create_user( $username, $password, $email );

			if ( is_wp_error( $user_id ) ) {
				wp_send_json_error( array(
					'message' => $user_id->get_error_message()
				) );
			}

			// Update user meta
			wp_update_user( array(
				'ID' => $user_id,
				'first_name' => $first_name,
				'last_name' => $last_name,
				'display_name' => $first_name . ' ' . $last_name,
				'user_url' => $website
			) );

			// Create affiliate account
			$affiliate_args = array(
				'user_id' => $user_id,
				'payment_email' => $payment_email ?: $email,
				'status' => isset( $settings['auto_approve_affiliates'] ) && $settings['auto_approve_affiliates'] === 'yes' ? 'active' : 'pending'
			);

			$affiliate_id = wpap()->affiliates->create( $affiliate_args );

			if ( is_wp_error( $affiliate_id ) ) {
				wp_delete_user( $user_id );
				wp_send_json_error( array(
					'message' => $affiliate_id->get_error_message()
				) );
			}

			// Auto-login if enabled
			$auto_login = isset( $settings['auto_login_after_registration'] ) && $settings['auto_login_after_registration'] === 'yes';
			
			if ( $auto_login ) {
				wp_set_current_user( $user_id );
				wp_set_auth_cookie( $user_id );
			}

			wp_send_json_success( array(
				'message' => __( 'Registration successful! Your affiliate application is under review.', 'wp-affiliate-pro' ),
				'redirect' => $auto_login ? $this->get_dashboard_url() : wp_login_url(),
				'auto_login' => $auto_login
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array(
				'message' => __( 'Registration failed. Please try again.', 'wp-affiliate-pro' )
			) );
		}
	}

	/**
	 * Generate affiliate link
	 */
	public function generate_affiliate_link() {
		check_ajax_referer( 'wpap_frontend_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(
				'message' => __( 'You must be logged in.', 'wp-affiliate-pro' )
			) );
		}

		$user_id = get_current_user_id();
		$affiliate = wpap()->affiliates->get_by_user_id( $user_id );

		if ( ! $affiliate || ! wpap()->affiliates->is_active( $affiliate->id ) ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid or inactive affiliate account.', 'wp-affiliate-pro' )
			) );
		}

		$url = esc_url_raw( $_POST['url'] ?? home_url() );
		$campaign = sanitize_text_field( $_POST['campaign'] ?? '' );
		$creative_id = sanitize_text_field( $_POST['creative_id'] ?? '' );

		$result = wpap()->links->generate_link( $affiliate->id, $url, array(
			'campaign' => $campaign,
			'creative_id' => $creative_id
		) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array(
				'message' => $result->get_error_message()
			) );
		}

		wp_send_json_success( array(
			'link' => $result['url'],
			'short_link' => $result['short_url'],
			'qr_code' => $result['qr_code'],
			'message' => __( 'Affiliate link generated successfully.', 'wp-affiliate-pro' )
		) );
	}

	/**
	 * Get affiliate statistics
	 */
	public function get_affiliate_stats() {
		check_ajax_referer( 'wpap_frontend_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(
				'message' => __( 'You must be logged in.', 'wp-affiliate-pro' )
			) );
		}

		$user_id = get_current_user_id();
		$affiliate = wpap()->affiliates->get_by_user_id( $user_id );

		if ( ! $affiliate ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid affiliate account.', 'wp-affiliate-pro' )
			) );
		}

		$period = sanitize_text_field( $_POST['period'] ?? 'all_time' );
		$start_date = null;
		$end_date = null;

		switch ( $period ) {
			case 'this_month':
				$start_date = date( 'Y-m-01 00:00:00' );
				$end_date = date( 'Y-m-t 23:59:59' );
				break;
			case 'last_month':
				$start_date = date( 'Y-m-01 00:00:00', strtotime( 'first day of last month' ) );
				$end_date = date( 'Y-m-t 23:59:59', strtotime( 'last day of last month' ) );
				break;
			case 'last_30_days':
				$start_date = date( 'Y-m-d 00:00:00', strtotime( '-30 days' ) );
				$end_date = date( 'Y-m-d 23:59:59' );
				break;
		}

		$stats = wpap()->affiliates->get_stats( $affiliate->id, $start_date, $end_date );

		wp_send_json_success( $stats );
	}

	/**
	 * Update affiliate profile
	 */
	public function update_affiliate_profile() {
		check_ajax_referer( 'wpap_frontend_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(
				'message' => __( 'You must be logged in.', 'wp-affiliate-pro' )
			) );
		}

		$user_id = get_current_user_id();
		$affiliate = wpap()->affiliates->get_by_user_id( $user_id );

		if ( ! $affiliate ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid affiliate account.', 'wp-affiliate-pro' )
			) );
		}

		$payment_email = sanitize_email( $_POST['payment_email'] ?? '' );
		$payment_method = sanitize_text_field( $_POST['payment_method'] ?? '' );
		$website = esc_url_raw( $_POST['website'] ?? '' );
		$bio = sanitize_textarea_field( $_POST['bio'] ?? '' );

		// Update affiliate data
		$affiliate_data = array();
		
		if ( ! empty( $payment_email ) ) {
			$affiliate_data['payment_email'] = $payment_email;
		}
		
		if ( ! empty( $payment_method ) ) {
			$affiliate_data['payment_method'] = $payment_method;
		}

		if ( ! empty( $affiliate_data ) ) {
			$result = wpap()->affiliates->update( $affiliate->id, $affiliate_data );
			
			if ( $result === false ) {
				wp_send_json_error( array(
					'message' => __( 'Failed to update affiliate profile.', 'wp-affiliate-pro' )
				) );
			}
		}

		// Update user data
		$user_data = array( 'ID' => $user_id );
		
		if ( ! empty( $website ) ) {
			$user_data['user_url'] = $website;
		}

		if ( count( $user_data ) > 1 ) {
			wp_update_user( $user_data );
		}

		// Update bio in user meta
		if ( ! empty( $bio ) ) {
			update_user_meta( $user_id, 'description', $bio );
		}

		wp_send_json_success( array(
			'message' => __( 'Profile updated successfully.', 'wp-affiliate-pro' )
		) );
	}

	/**
	 * Get commission history
	 */
	public function get_commission_history() {
		check_ajax_referer( 'wpap_frontend_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(
				'message' => __( 'You must be logged in.', 'wp-affiliate-pro' )
			) );
		}

		$user_id = get_current_user_id();
		$affiliate = wpap()->affiliates->get_by_user_id( $user_id );

		if ( ! $affiliate ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid affiliate account.', 'wp-affiliate-pro' )
			) );
		}

		$page = max( 1, intval( $_POST['page'] ?? 1 ) );
		$per_page = max( 1, min( 50, intval( $_POST['per_page'] ?? 10 ) ) );
		$status = sanitize_text_field( $_POST['status'] ?? '' );

		$args = array(
			'limit' => $per_page,
			'offset' => ( $page - 1 ) * $per_page,
			'status' => $status,
			'orderby' => 'date_created',
			'order' => 'DESC'
		);

		$commissions = wpap()->commissions->get_by_affiliate( $affiliate->id, $args );
		$total_count = wpap()->commissions->count( array_merge( $args, array( 'limit' => 0, 'offset' => 0 ) ) );

		wp_send_json_success( array(
			'commissions' => $commissions,
			'total' => $total_count,
			'page' => $page,
			'per_page' => $per_page,
			'total_pages' => ceil( $total_count / $per_page )
		) );
	}

	/**
	 * Get payment history
	 */
	public function get_payment_history() {
		check_ajax_referer( 'wpap_frontend_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(
				'message' => __( 'You must be logged in.', 'wp-affiliate-pro' )
			) );
		}

		$user_id = get_current_user_id();
		$affiliate = wpap()->affiliates->get_by_user_id( $user_id );

		if ( ! $affiliate ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid affiliate account.', 'wp-affiliate-pro' )
			) );
		}

		$page = max( 1, intval( $_POST['page'] ?? 1 ) );
		$per_page = max( 1, min( 50, intval( $_POST['per_page'] ?? 10 ) ) );

		$args = array(
			'affiliate_id' => $affiliate->id,
			'limit' => $per_page,
			'offset' => ( $page - 1 ) * $per_page,
			'orderby' => 'date_created',
			'order' => 'DESC'
		);

		$payments = wpap()->payments->get_payments( $args );

		wp_send_json_success( array(
			'payments' => $payments,
			'page' => $page,
			'per_page' => $per_page
		) );
	}

	/**
	 * Request payout
	 */
	public function request_payout() {
		check_ajax_referer( 'wpap_frontend_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(
				'message' => __( 'You must be logged in.', 'wp-affiliate-pro' )
			) );
		}

		$user_id = get_current_user_id();
		$affiliate = wpap()->affiliates->get_by_user_id( $user_id );

		if ( ! $affiliate || $affiliate->status !== 'active' ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid or inactive affiliate account.', 'wp-affiliate-pro' )
			) );
		}

		$amount = floatval( $_POST['amount'] ?? 0 );
		$method = sanitize_text_field( $_POST['method'] ?? $affiliate->payment_method );

		// Validate amount
		$minimum_payout = wpap_get_minimum_payout();
		if ( $amount < $minimum_payout ) {
			wp_send_json_error( array(
				'message' => sprintf( __( 'Minimum payout amount is %s.', 'wp-affiliate-pro' ), wpap_format_amount( $minimum_payout ) )
			) );
		}

		// Check if affiliate has enough unpaid commissions
		if ( $amount > $affiliate->total_unpaid ) {
			wp_send_json_error( array(
				'message' => __( 'Insufficient unpaid commissions for this payout amount.', 'wp-affiliate-pro' )
			) );
		}

		// Get unpaid commissions
		$unpaid_commissions = wpap()->commissions->get_by_affiliate( $affiliate->id, array(
			'status' => 'approved',
			'limit' => 1000
		) );

		$commission_total = 0;
		$commission_ids = array();

		foreach ( $unpaid_commissions as $commission ) {
			if ( $commission_total + $commission->commission_amount <= $amount ) {
				$commission_total += $commission->commission_amount;
				$commission_ids[] = $commission->id;
			}
			
			if ( $commission_total >= $amount ) {
				break;
			}
		}

		if ( empty( $commission_ids ) ) {
			wp_send_json_error( array(
				'message' => __( 'No eligible commissions found for payout.', 'wp-affiliate-pro' )
			) );
		}

		// Create payment request
		$payment_args = array(
			'affiliate_id' => $affiliate->id,
			'amount' => $commission_total,
			'method' => $method,
			'status' => 'pending',
			'commission_ids' => $commission_ids,
			'notes' => sprintf( __( 'Payout requested by affiliate on %s', 'wp-affiliate-pro' ), current_time( 'mysql' ) )
		);

		$payment_id = wpap()->payments->create_payment( $payment_args );

		if ( is_wp_error( $payment_id ) ) {
			wp_send_json_error( array(
				'message' => $payment_id->get_error_message()
			) );
		}

		wp_send_json_success( array(
			'message' => sprintf( __( 'Payout request for %s has been submitted successfully.', 'wp-affiliate-pro' ), wpap_format_amount( $commission_total ) ),
			'payment_id' => $payment_id,
			'amount' => $commission_total
		) );
	}

	/**
	 * Process payment (admin only)
	 */
	public function process_payment() {
		check_ajax_referer( 'wpap_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(
				'message' => __( 'You do not have permission to process payments.', 'wp-affiliate-pro' )
			) );
		}

		$payment_id = intval( $_POST['payment_id'] );
		$gateway = sanitize_text_field( $_POST['gateway'] ?? '' );

		$result = wpap()->payments->process_payment( $payment_id, $gateway );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array(
				'message' => $result->get_error_message()
			) );
		}

		wp_send_json_success( array(
			'message' => __( 'Payment processed successfully.', 'wp-affiliate-pro' ),
			'data' => $result
		) );
	}

	/**
	 * Bulk action for affiliates (admin only)
	 */
	public function bulk_action_affiliates() {
		check_ajax_referer( 'wpap_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(
				'message' => __( 'You do not have permission to perform bulk actions.', 'wp-affiliate-pro' )
			) );
		}

		$action = sanitize_text_field( $_POST['action'] ?? '' );
		$affiliate_ids = array_map( 'intval', $_POST['affiliate_ids'] ?? array() );

		if ( empty( $action ) || empty( $affiliate_ids ) ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid action or no affiliates selected.', 'wp-affiliate-pro' )
			) );
		}

		$processed = 0;
		$errors = 0;

		foreach ( $affiliate_ids as $affiliate_id ) {
			$result = false;
			
			switch ( $action ) {
				case 'approve':
					$result = wpap()->affiliates->approve( $affiliate_id );
					break;
				case 'reject':
					$reason = sanitize_text_field( $_POST['reason'] ?? '' );
					$result = wpap()->affiliates->reject( $affiliate_id, $reason );
					break;
				case 'delete':
					$result = wpap()->affiliates->delete( $affiliate_id );
					break;
			}
			
			if ( $result ) {
				$processed++;
			} else {
				$errors++;
			}
		}

		wp_send_json_success( array(
			'message' => sprintf( __( 'Bulk action completed. %d processed, %d errors.', 'wp-affiliate-pro' ), $processed, $errors ),
			'processed' => $processed,
			'errors' => $errors
		) );
	}

	/**
	 * Bulk action for commissions (admin only)
	 */
	public function bulk_action_commissions() {
		check_ajax_referer( 'wpap_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(
				'message' => __( 'You do not have permission to perform bulk actions.', 'wp-affiliate-pro' )
			) );
		}

		$action = sanitize_text_field( $_POST['action'] ?? '' );
		$commission_ids = array_map( 'intval', $_POST['commission_ids'] ?? array() );

		if ( empty( $action ) || empty( $commission_ids ) ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid action or no commissions selected.', 'wp-affiliate-pro' )
			) );
		}

		$processed = 0;
		$errors = 0;

		foreach ( $commission_ids as $commission_id ) {
			$result = false;
			
			switch ( $action ) {
				case 'approve':
					$result = wpap()->commissions->approve( $commission_id );
					break;
				case 'reject':
					$reason = sanitize_text_field( $_POST['reason'] ?? '' );
					$result = wpap()->commissions->reject( $commission_id, $reason );
					break;
				case 'delete':
					$result = wpap()->commissions->delete( $commission_id );
					break;
			}
			
			if ( $result ) {
				$processed++;
			} else {
				$errors++;
			}
		}

		wp_send_json_success( array(
			'message' => sprintf( __( 'Bulk action completed. %d processed, %d errors.', 'wp-affiliate-pro' ), $processed, $errors ),
			'processed' => $processed,
			'errors' => $errors
		) );
	}

	// Helper methods
	private function get_dashboard_url() {
		$page_settings = get_option( 'wpap_page_settings', array() );
		$dashboard_page_id = isset( $page_settings['affiliate_dashboard_page'] ) ? $page_settings['affiliate_dashboard_page'] : 0;
		
		if ( $dashboard_page_id ) {
			return get_permalink( $dashboard_page_id );
		}

		return home_url( 'affiliate-dashboard' );
	}
}