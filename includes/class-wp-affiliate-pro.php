<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WP_Affiliate_Pro {

	public $version = '1.0.0';
	protected static $_instance = null;

	// Core components
	public $database;
	public $affiliates;
	public $commissions;
	public $links;
	public $payments;
	public $emails;
	
	// Interface components
	public $admin;
	public $frontend;
	public $api;
	public $ajax;

	/**
	 * Get singleton instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->define_constants();
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Define plugin constants
	 */
	private function define_constants() {
		$this->define( 'WPAP_ABSPATH', dirname( WPAP_PLUGIN_FILE ) . '/' );
	}

	/**
	 * Define constant if not already set
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init_components' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Include required files
	 */
	public function includes() {
		// Core classes
		$this->include_file( 'includes/class-wpap-install.php' );
		$this->include_file( 'includes/class-wpap-database.php' );
		$this->include_file( 'includes/class-wpap-affiliates.php' );
		$this->include_file( 'includes/class-wpap-commissions.php' );
		$this->include_file( 'includes/class-wpap-links.php' );
		$this->include_file( 'includes/class-wpap-payments.php' );
		$this->include_file( 'includes/class-wpap-emails.php' );

		// Interface classes
		if ( $this->is_request( 'admin' ) ) {
			$this->include_file( 'includes/admin/class-wpap-admin.php' );
		}

		if ( $this->is_request( 'frontend' ) ) {
			$this->include_file( 'includes/class-wpap-frontend.php' );
		}

		if ( $this->is_request( 'ajax' ) ) {
			$this->include_file( 'includes/class-wpap-ajax.php' );
		}

		// API
		$this->include_file( 'includes/class-wpap-rest-api.php' );
	}

	/**
	 * Include file with error handling
	 */
	private function include_file( $file ) {
		$file_path = WPAP_PLUGIN_DIR . $file;
		if ( file_exists( $file_path ) ) {
			include_once $file_path;
		} else {
			$this->log( 'File not found: ' . $file_path, 'error' );
		}
	}

	/**
	 * Initialize plugin components
	 */
	public function init_components() {
		if ( ! $this->check_requirements() ) {
			return;
		}

		try {
			// Initialize database first
			if ( class_exists( 'WPAP_Database' ) ) {
				$this->database = new WPAP_Database();
			}

			// Initialize core business logic
			if ( class_exists( 'WPAP_Affiliates' ) ) {
				$this->affiliates = new WPAP_Affiliates();
			}
			
			if ( class_exists( 'WPAP_Commissions' ) ) {
				$this->commissions = new WPAP_Commissions();
			}
			
			if ( class_exists( 'WPAP_Links' ) ) {
				$this->links = new WPAP_Links();
			}
			
			if ( class_exists( 'WPAP_Payments' ) ) {
				$this->payments = new WPAP_Payments();
			}
			
			if ( class_exists( 'WPAP_Emails' ) ) {
				$this->emails = new WPAP_Emails();
			}

			// Initialize interfaces
			if ( $this->is_request( 'admin' ) && class_exists( 'WPAP_Admin' ) ) {
				$this->admin = new WPAP_Admin();
			}

			if ( $this->is_request( 'frontend' ) && class_exists( 'WPAP_Frontend' ) ) {
				$this->frontend = new WPAP_Frontend();
			}

			if ( $this->is_request( 'ajax' ) && class_exists( 'WPAP_Ajax' ) ) {
				$this->ajax = new WPAP_Ajax();
			}

			// Initialize API
			if ( class_exists( 'WPAP_REST_API' ) ) {
				$this->api = new WPAP_REST_API();
			}

			do_action( 'wpap_init' );

		} catch ( Exception $e ) {
			$this->log( 'Component initialization error: ' . $e->getMessage(), 'error' );
			
			if ( is_admin() ) {
				add_action( 'admin_notices', array( $this, 'initialization_error_notice' ) );
			}
		}
	}

	/**
	 * Check plugin requirements
	 */
	private function check_requirements() {
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			add_action( 'admin_notices', array( $this, 'php_version_notice' ) );
			return false;
		}

		if ( version_compare( get_bloginfo( 'version' ), '5.0', '<' ) ) {
			add_action( 'admin_notices', array( $this, 'wp_version_notice' ) );
			return false;
		}

		global $wpdb;
		if ( ! $wpdb ) {
			add_action( 'admin_notices', array( $this, 'database_error_notice' ) );
			return false;
		}

		return true;
	}

	/**
	 * Enqueue frontend scripts and styles
	 */
	public function enqueue_frontend_scripts() {
		// Only enqueue on pages with affiliate content
		if ( ! $this->is_affiliate_page() ) {
			return;
		}

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
	 * Enqueue admin scripts and styles
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Only enqueue on plugin admin pages
		if ( strpos( $hook, 'wpap' ) === false ) {
			return;
		}

		wp_enqueue_style( 
			'wpap-admin', 
			WPAP_PLUGIN_URL . 'assets/css/admin.css', 
			array(), 
			WPAP_VERSION 
		);
		
		wp_enqueue_script( 
			'wpap-admin', 
			WPAP_PLUGIN_URL . 'assets/js/admin.js', 
			array( 'jquery', 'jquery-ui-datepicker' ), 
			WPAP_VERSION, 
			true 
		);

		wp_localize_script( 'wpap-admin', 'wpap_admin', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'wpap_admin_nonce' ),
			'strings' => array(
				'loading' => __( 'Loading...', 'wp-affiliate-pro' ),
				'error' => __( 'An error occurred. Please try again.', 'wp-affiliate-pro' ),
				'success' => __( 'Success!', 'wp-affiliate-pro' ),
				'copied' => __( 'Copied to clipboard!', 'wp-affiliate-pro' ),
				'confirm_delete' => __( 'Are you sure you want to delete this item?', 'wp-affiliate-pro' ),
				'confirm_approve' => __( 'Are you sure you want to approve this affiliate?', 'wp-affiliate-pro' ),
				'confirm_process_payment' => __( 'Are you sure you want to process this payment?', 'wp-affiliate-pro' ),
				'reject_reason' => __( 'Please enter a reason for rejection (optional):', 'wp-affiliate-pro' ),
				'select_action_items' => __( 'Please select an action and at least one item.', 'wp-affiliate-pro' ),
				'confirm_bulk_action' => __( 'Are you sure you want to perform this bulk action?', 'wp-affiliate-pro' )
			)
		) );

		// Enqueue Chart.js for dashboard charts
		if ( strpos( $hook, 'wpap-dashboard' ) !== false ) {
			wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true );
		}
	}

	/**
	 * Load plugin textdomain
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'wp-affiliate-pro', false, dirname( WPAP_PLUGIN_BASENAME ) . '/languages/' );
	}

	/**
	 * Determine request type
	 */
	private function is_request( $type ) {
		switch ( $type ) {
			case 'admin':
				return is_admin();
			case 'ajax':
				return defined( 'DOING_AJAX' ) && DOING_AJAX;
			case 'cron':
				return defined( 'DOING_CRON' ) && DOING_CRON;
			case 'frontend':
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' ) && ! defined( 'REST_REQUEST' );
		}
		return false;
	}

	/**
	 * Check if current page has affiliate content
	 */
	private function is_affiliate_page() {
		global $post;
		
		if ( ! $post ) {
			return false;
		}

		// Check if page contains affiliate shortcodes
		$affiliate_shortcodes = array( 
			'wpap_affiliate_dashboard', 
			'wpap_affiliate_registration', 
			'wpap_affiliate_login',
			'wpap_referral_link'
		);
		
		foreach ( $affiliate_shortcodes as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				return true;
			}
		}

		// Check if it's an affiliate link
		if ( get_query_var( 'wpap_ref' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Plugin deactivation
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'wpap_daily_cleanup' );
		wp_clear_scheduled_hook( 'wpap_commission_processing' );
		flush_rewrite_rules();
	}

	/**
	 * Log messages
	 */
	public function log( $message, $level = 'info' ) {
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			error_log( sprintf( 'WP Affiliate Pro [%s]: %s', strtoupper( $level ), $message ) );
		}
	}

	/**
	 * Get template path
	 */
	public function get_template_path() {
		return apply_filters( 'wpap_template_path', 'wp-affiliate-pro/' );
	}

	/**
	 * Get plugin URL
	 */
	public function plugin_url() {
		return untrailingslashit( plugins_url( '/', WPAP_PLUGIN_FILE ) );
	}

	/**
	 * Get plugin path
	 */
	public function plugin_path() {
		return untrailingslashit( plugin_dir_path( WPAP_PLUGIN_FILE ) );
	}

	/**
	 * Get AJAX URL
	 */
	public function ajax_url() {
		return admin_url( 'admin-ajax.php', 'relative' );
	}

	// Error notices
	public function php_version_notice() {
		echo '<div class="notice notice-error"><p>' . sprintf( 
			esc_html__( 'WP Affiliate Pro requires PHP 7.4 or higher. You are running version %s.', 'wp-affiliate-pro' ), 
			PHP_VERSION 
		) . '</p></div>';
	}

	public function wp_version_notice() {
		echo '<div class="notice notice-error"><p>' . sprintf( 
			esc_html__( 'WP Affiliate Pro requires WordPress 5.0 or higher. You are running version %s.', 'wp-affiliate-pro' ), 
			get_bloginfo( 'version' ) 
		) . '</p></div>';
	}

	public function database_error_notice() {
		echo '<div class="notice notice-error"><p>' . 
			esc_html__( 'WP Affiliate Pro: Database connection error. Please check your WordPress configuration.', 'wp-affiliate-pro' ) . 
		'</p></div>';
	}

	public function initialization_error_notice() {
		echo '<div class="notice notice-error"><p>' . 
			esc_html__( 'WP Affiliate Pro encountered an initialization error. Please check your server error logs for details.', 'wp-affiliate-pro' ) . 
		'</p></div>';
	}
}