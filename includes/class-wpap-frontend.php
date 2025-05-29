<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAP_Frontend {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'init', array( $this, 'init' ) );
		
		// Shortcodes
		add_shortcode( 'wpap_affiliate_dashboard', array( $this, 'affiliate_dashboard_shortcode' ) );
		add_shortcode( 'wpap_affiliate_registration', array( $this, 'affiliate_registration_shortcode' ) );
		add_shortcode( 'wpap_affiliate_login', array( $this, 'affiliate_login_shortcode' ) );
		add_shortcode( 'wpap_referral_link', array( $this, 'referral_link_shortcode' ) );
	}

	public function init() {
		// Handle form submissions
		if ( isset( $_POST['wpap_action'] ) ) {
			$this->handle_form_submission();
		}
	}

	public function enqueue_scripts() {
		// Only enqueue on pages with affiliate content
		if ( ! $this->is_affiliate_page() ) {
			return;
		}

		wp_enqueue_script( 'jquery' );
		
		wp_enqueue_style( 
			'wpap-frontend', 
			WPAP_PLUGIN_URL . 'assets/css/frontend.css', 
			array(), 
			WPAP_VERSION 
		);
		
		wp_enqueue_script( 
			'wpap-frontend', 
			WPAP_PLUGIN_URL . 'assets/js/frontend.js', 
			array( 'jquery' ), 
			WPAP_VERSION, 
			true 
		);

		wp_localize_script( 'wpap-frontend', 'wpap_frontend', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'wpap_frontend_nonce' ),
			'strings' => array(
				'loading' => __( 'Loading...', 'wp-affiliate-pro' ),
				'error' => __( 'An error occurred. Please try again.', 'wp-affiliate-pro' ),
				'success' => __( 'Success!', 'wp-affiliate-pro' ),
				'copied' => __( 'Link copied to clipboard!', 'wp-affiliate-pro' ),
				'confirm_action' => __( 'Are you sure?', 'wp-affiliate-pro' )
			)
		) );
	}

	/**
	 * Affiliate Dashboard Shortcode
	 */
	public function affiliate_dashboard_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'show_stats' => 'yes',
			'show_links' => 'yes',
			'show_commissions' => 'yes',
			'show_payments' => 'yes'
		), $atts );

		if ( ! is_user_logged_in() ) {
			return $this->get_login_form();
		}

		$user_id = get_current_user_id();
		
		// Simple fallback if database not ready
		try {
			if ( ! wpap() || ! wpap()->affiliates ) {
				return '<div class="wpap-notice wpap-notice-warning"><p>' . __( 'Affiliate system is being set up. Please try again in a few moments.', 'wp-affiliate-pro' ) . '</p></div>';
			}
			
			$affiliate = wpap()->affiliates->get_by_user_id( $user_id );
		} catch ( Exception $e ) {
			return '<div class="wpap-notice wpap-notice-error"><p>' . __( 'Unable to load affiliate data. Please contact support.', 'wp-affiliate-pro' ) . '</p></div>';
		}

		if ( ! $affiliate ) {
			return $this->get_registration_prompt();
		}

		ob_start();
		$this->render_affiliate_dashboard( $affiliate, $atts );
		return ob_get_clean();
	}

	/**
	 * Affiliate Registration Shortcode
	 */
	public function affiliate_registration_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'redirect' => ''
		), $atts );

		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			
			try {
				if ( wpap() && wpap()->affiliates ) {
					$affiliate = wpap()->affiliates->get_by_user_id( $user_id );
					if ( $affiliate ) {
						return '<div class="wpap-notice wpap-notice-info"><p>' . 
							__( 'You are already registered as an affiliate.', 'wp-affiliate-pro' ) . 
							' <a href="' . $this->get_dashboard_url() . '">' . __( 'View Dashboard', 'wp-affiliate-pro' ) . '</a></p></div>';
					}
				}
			} catch ( Exception $e ) {
				// Continue to show registration form
			}
		}

		ob_start();
		$this->render_registration_form( $atts );
		return ob_get_clean();
	}

	/**
	 * Affiliate Login Shortcode
	 */
	public function affiliate_login_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'redirect' => ''
		), $atts );

		if ( is_user_logged_in() ) {
			return '<div class="wpap-notice wpap-notice-info"><p>' . 
				__( 'You are already logged in.', 'wp-affiliate-pro' ) . 
				' <a href="' . $this->get_dashboard_url() . '">' . __( 'View Dashboard', 'wp-affiliate-pro' ) . '</a></p></div>';
		}

		ob_start();
		$this->render_login_form( $atts );
		return ob_get_clean();
	}

	/**
	 * Referral Link Shortcode
	 */
	public function referral_link_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'url' => home_url(),
			'text' => __( 'Get Referral Link', 'wp-affiliate-pro' ),
			'button' => 'no'
		), $atts );

		if ( ! is_user_logged_in() ) {
			return '<p>' . __( 'Please log in to get your referral link.', 'wp-affiliate-pro' ) . '</p>';
		}

		try {
			if ( ! wpap() || ! wpap()->affiliates ) {
				return '<p>' . __( 'Affiliate system not ready.', 'wp-affiliate-pro' ) . '</p>';
			}
			
			$affiliate = wpap()->affiliates->get_by_user_id( get_current_user_id() );
			if ( ! $affiliate || $affiliate->status !== 'active' ) {
				return '<p>' . __( 'You must be an active affiliate to generate referral links.', 'wp-affiliate-pro' ) . '</p>';
			}
		} catch ( Exception $e ) {
			return '<p>' . __( 'Unable to generate referral link.', 'wp-affiliate-pro' ) . '</p>';
		}

		$referral_url = wpap_get_referral_url( $atts['url'], $affiliate->id );
		
		if ( $atts['button'] === 'yes' ) {
			return sprintf(
				'<button type="button" class="wpap-copy-link" data-link="%s">%s</button>',
				esc_attr( $referral_url ),
				esc_html( $atts['text'] )
			);
		}

		return sprintf(
			'<div class="wpap-referral-link">
				<input type="text" value="%s" readonly onclick="this.select();">
				<button type="button" class="wpap-copy-link" data-link="%s">%s</button>
			</div>',
			esc_attr( $referral_url ),
			esc_attr( $referral_url ),
			__( 'Copy', 'wp-affiliate-pro' )
		);
	}

	/**
	 * Render affiliate dashboard
	 */
	private function render_affiliate_dashboard( $affiliate, $atts ) {
		$user = wp_get_current_user();
		
		// Simple stats fallback
		$stats = array(
			'total_earnings' => $affiliate->total_earnings ?? 0,
			'unpaid_earnings' => $affiliate->total_unpaid ?? 0,
			'total_visits' => $affiliate->total_visits ?? 0,
			'total_conversions' => 0,
			'conversion_rate' => 0,
			'total_commissions' => $affiliate->total_referrals ?? 0
		);
		
		$recent_commissions = array();
		$recent_payments = array();
		$affiliate_links = array();

		// Simple dashboard template
		echo '<div class="wpap-dashboard wpap-container">';
		echo '<div class="wpap-dashboard-header">';
		echo '<h1>' . sprintf( __( 'Welcome back, %s!', 'wp-affiliate-pro' ), esc_html( $user->display_name ) ) . '</h1>';
		echo '<p>' . sprintf( __( 'Referral Code: %s', 'wp-affiliate-pro' ), '<strong>' . esc_html( $affiliate->referral_code ) . '</strong>' ) . '</p>';
		echo '<span class="wpap-status wpap-status-' . esc_attr( $affiliate->status ) . '">' . esc_html( ucfirst( $affiliate->status ) ) . '</span>';
		echo '</div>';

		if ( $affiliate->status === 'pending' ) {
			echo '<div class="wpap-notice wpap-notice-warning"><p>' . __( 'Your affiliate application is currently under review.', 'wp-affiliate-pro' ) . '</p></div>';
		} elseif ( $affiliate->status === 'rejected' ) {
			echo '<div class="wpap-notice wpap-notice-error"><p>' . __( 'Your affiliate application was not approved. Please contact support.', 'wp-affiliate-pro' ) . '</p></div>';
		}

		if ( $affiliate->status === 'active' ) {
			echo '<div class="wpap-stats-grid">';
			echo '<div class="wpap-stat-card"><span class="wpap-stat-value">' . wpap_format_amount( $stats['total_earnings'] ) . '</span><span class="wpap-stat-label">' . __( 'Total Earnings', 'wp-affiliate-pro' ) . '</span></div>';
			echo '<div class="wpap-stat-card"><span class="wpap-stat-value">' . wpap_format_amount( $stats['unpaid_earnings'] ) . '</span><span class="wpap-stat-label">' . __( 'Unpaid Earnings', 'wp-affiliate-pro' ) . '</span></div>';
			echo '<div class="wpap-stat-card"><span class="wpap-stat-value">' . number_format( $stats['total_visits'] ) . '</span><span class="wpap-stat-label">' . __( 'Total Visits', 'wp-affiliate-pro' ) . '</span></div>';
			echo '</div>';

			$referral_url = wpap_get_referral_url( home_url(), $affiliate->id );
			echo '<div class="wpap-card">';
			echo '<h3>' . __( 'Your Referral Link', 'wp-affiliate-pro' ) . '</h3>';
			echo '<div class="wpap-referral-link">';
			echo '<input type="text" value="' . esc_attr( $referral_url ) . '" readonly>';
			echo '<button type="button" class="wpap-copy-link" data-link="' . esc_attr( $referral_url ) . '">' . __( 'Copy', 'wp-affiliate-pro' ) . '</button>';
			echo '</div>';
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Render registration form
	 */
	private function render_registration_form( $atts ) {
		$redirect_url = ! empty( $atts['redirect'] ) ? $atts['redirect'] : $this->get_dashboard_url();
		
		echo '<div class="wpap-registration-form">';
		echo '<h2>' . __( 'Affiliate Registration', 'wp-affiliate-pro' ) . '</h2>';
		echo '<form method="post" action="">';
		wp_nonce_field( 'wpap_register_affiliate', 'wpap_nonce' );
		echo '<input type="hidden" name="wpap_action" value="register_affiliate">';
		
		echo '<div class="wpap-form-group">';
		echo '<label>' . __( 'First Name', 'wp-affiliate-pro' ) . ' *</label>';
		echo '<input type="text" name="first_name" required>';
		echo '</div>';
		
		echo '<div class="wpap-form-group">';
		echo '<label>' . __( 'Last Name', 'wp-affiliate-pro' ) . ' *</label>';
		echo '<input type="text" name="last_name" required>';
		echo '</div>';
		
		echo '<div class="wpap-form-group">';
		echo '<label>' . __( 'Email', 'wp-affiliate-pro' ) . ' *</label>';
		echo '<input type="email" name="email" required>';
		echo '</div>';
		
		echo '<div class="wpap-form-group">';
		echo '<label>' . __( 'Username', 'wp-affiliate-pro' ) . ' *</label>';
		echo '<input type="text" name="username" required>';
		echo '</div>';
		
		echo '<div class="wpap-form-group">';
		echo '<label>' . __( 'Password', 'wp-affiliate-pro' ) . ' *</label>';
		echo '<input type="password" name="password" required>';
		echo '</div>';
		
		echo '<div class="wpap-form-group">';
		echo '<label>' . __( 'Confirm Password', 'wp-affiliate-pro' ) . ' *</label>';
		echo '<input type="password" name="confirm_password" required>';
		echo '</div>';
		
		echo '<div class="wpap-form-group">';
		echo '<label>' . __( 'Website (Optional)', 'wp-affiliate-pro' ) . '</label>';
		echo '<input type="url" name="website">';
		echo '</div>';
		
		echo '<div class="wpap-form-group">';
		echo '<label><input type="checkbox" name="terms_accepted" required> ' . __( 'I accept the terms and conditions', 'wp-affiliate-pro' ) . ' *</label>';
		echo '</div>';
		
		echo '<button type="submit" class="wpap-button">' . __( 'Register as Affiliate', 'wp-affiliate-pro' ) . '</button>';
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render login form
	 */
	private function render_login_form( $atts ) {
		$redirect_url = ! empty( $atts['redirect'] ) ? $atts['redirect'] : $this->get_dashboard_url();
		
		echo '<div class="wpap-login-form">';
		echo '<h2>' . __( 'Affiliate Login', 'wp-affiliate-pro' ) . '</h2>';
		echo '<p><a href="' . wp_login_url( $redirect_url ) . '">' . __( 'Login to your account', 'wp-affiliate-pro' ) . '</a></p>';
		echo '</div>';
	}

	/**
	 * Handle form submissions
	 */
	private function handle_form_submission() {
		$action = sanitize_text_field( $_POST['wpap_action'] );

		switch ( $action ) {
			case 'register_affiliate':
				$this->handle_affiliate_registration();
				break;
		}
	}

	/**
	 * Handle affiliate registration
	 */
	private function handle_affiliate_registration() {
		if ( ! wp_verify_nonce( $_POST['wpap_nonce'], 'wpap_register_affiliate' ) ) {
			wp_die( __( 'Security check failed.', 'wp-affiliate-pro' ) );
		}

		$first_name = sanitize_text_field( $_POST['first_name'] );
		$last_name = sanitize_text_field( $_POST['last_name'] );
		$email = sanitize_email( $_POST['email'] );
		$username = sanitize_user( $_POST['username'] );
		$password = $_POST['password'];
		$confirm_password = $_POST['confirm_password'];
		$website = esc_url_raw( $_POST['website'] );
		$terms_accepted = isset( $_POST['terms_accepted'] );

		// Basic validation
		if ( empty( $first_name ) || empty( $last_name ) || empty( $email ) || empty( $username ) || empty( $password ) ) {
			wp_die( __( 'Please fill in all required fields.', 'wp-affiliate-pro' ) );
		}

		if ( $password !== $confirm_password ) {
			wp_die( __( 'Passwords do not match.', 'wp-affiliate-pro' ) );
		}

		if ( ! $terms_accepted ) {
			wp_die( __( 'You must accept the terms and conditions.', 'wp-affiliate-pro' ) );
		}

		if ( email_exists( $email ) || username_exists( $username ) ) {
			wp_die( __( 'Email or username already exists.', 'wp-affiliate-pro' ) );
		}

		// Create user
		$user_id = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			wp_die( $user_id->get_error_message() );
		}

		// Update user meta
		wp_update_user( array(
			'ID' => $user_id,
			'first_name' => $first_name,
			'last_name' => $last_name,
			'display_name' => $first_name . ' ' . $last_name,
			'user_url' => $website
		) );

		// Simple success message for now
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );
		
		wp_redirect( add_query_arg( 'registered', '1', $this->get_dashboard_url() ) );
		exit;
	}

	// Helper methods
	private function is_affiliate_page() {
		global $post;
		
		if ( ! $post ) {
			return false;
		}

		// Check if page contains affiliate shortcodes
		$affiliate_shortcodes = array( 'wpap_affiliate_dashboard', 'wpap_affiliate_registration', 'wpap_affiliate_login' );
		
		foreach ( $affiliate_shortcodes as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				return true;
			}
		}

		return false;
	}

	private function get_login_form() {
		return '<div class="wpap-login-prompt">
			<p>' . __( 'Please log in to access your affiliate dashboard.', 'wp-affiliate-pro' ) . '</p>
			<a href="' . wp_login_url( get_permalink() ) . '" class="wpap-button">' . __( 'Log In', 'wp-affiliate-pro' ) . '</a>
		</div>';
	}

	private function get_registration_prompt() {
		return '<div class="wpap-registration-prompt">
			<p>' . __( 'You are not registered as an affiliate.', 'wp-affiliate-pro' ) . '</p>
			<a href="' . $this->get_registration_url() . '" class="wpap-button">' . __( 'Register Now', 'wp-affiliate-pro' ) . '</a>
		</div>';
	}

	private function get_dashboard_url() {
		$page_settings = get_option( 'wpap_page_settings', array() );
		$dashboard_page_id = isset( $page_settings['affiliate_dashboard_page'] ) ? $page_settings['affiliate_dashboard_page'] : 0;
		
		if ( $dashboard_page_id ) {
			return get_permalink( $dashboard_page_id );
		}

		return home_url( 'affiliate-dashboard' );
	}

	private function get_registration_url() {
		$page_settings = get_option( 'wpap_page_settings', array() );
		$registration_page_id = isset( $page_settings['affiliate_registration_page'] ) ? $page_settings['affiliate_registration_page'] : 0;
		
		if ( $registration_page_id ) {
			return get_permalink( $registration_page_id );
		}

		return home_url( 'affiliate-registration' );
	}
}