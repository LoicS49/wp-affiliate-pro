<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAP_Install {

	public static function install() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		self::create_tables();
		self::create_options();
		self::create_pages();
		self::create_roles_and_capabilities();
		self::schedule_events();
		self::set_default_settings();

		update_option( 'wpap_version', WPAP_VERSION );
		update_option( 'wpap_install_date', current_time( 'mysql' ) );

		flush_rewrite_rules();

		do_action( 'wpap_installed' );
	}

	public static function activate() {
		self::install();
	}

	private static function create_tables() {
		global $wpdb;

		$wpdb->hide_errors();

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$collate = '';
		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		$tables = "
		CREATE TABLE {$wpdb->prefix}wpap_affiliates (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			affiliate_id varchar(32) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			commission_rate decimal(10,4) NOT NULL DEFAULT '0.0000',
			commission_type varchar(20) NOT NULL DEFAULT 'percentage',
			payment_email varchar(100) NOT NULL,
			payment_method varchar(50) NOT NULL DEFAULT 'paypal',
			referral_code varchar(50) NOT NULL,
			parent_affiliate_id bigint(20) DEFAULT NULL,
			total_earnings decimal(15,4) NOT NULL DEFAULT '0.0000',
			total_paid decimal(15,4) NOT NULL DEFAULT '0.0000',
			total_unpaid decimal(15,4) NOT NULL DEFAULT '0.0000',
			total_referrals int(11) NOT NULL DEFAULT 0,
			total_visits int(11) NOT NULL DEFAULT 0,
			conversion_rate decimal(5,4) NOT NULL DEFAULT '0.0000',
			date_registered datetime NOT NULL,
			date_approved datetime DEFAULT NULL,
			notes text,
			meta longtext,
			PRIMARY KEY (id),
			UNIQUE KEY affiliate_id (affiliate_id),
			UNIQUE KEY user_id (user_id),
			UNIQUE KEY referral_code (referral_code),
			KEY status (status),
			KEY parent_affiliate_id (parent_affiliate_id),
			KEY date_registered (date_registered)
		) $collate;

		CREATE TABLE {$wpdb->prefix}wpap_commissions (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			affiliate_id bigint(20) NOT NULL,
			order_id bigint(20) DEFAULT NULL,
			order_total decimal(15,4) NOT NULL DEFAULT '0.0000',
			commission_amount decimal(15,4) NOT NULL DEFAULT '0.0000',
			commission_rate decimal(10,4) NOT NULL DEFAULT '0.0000',
			commission_type varchar(20) NOT NULL DEFAULT 'percentage',
			currency varchar(3) NOT NULL DEFAULT 'USD',
			status varchar(20) NOT NULL DEFAULT 'pending',
			type varchar(50) NOT NULL DEFAULT 'sale',
			description text,
			reference varchar(255) DEFAULT NULL,
			visit_id bigint(20) DEFAULT NULL,
			parent_commission_id bigint(20) DEFAULT NULL,
			level int(2) NOT NULL DEFAULT 1,
			date_created datetime NOT NULL,
			date_paid datetime DEFAULT NULL,
			payment_id bigint(20) DEFAULT NULL,
			meta longtext,
			PRIMARY KEY (id),
			KEY affiliate_id (affiliate_id),
			KEY order_id (order_id),
			KEY status (status),
			KEY type (type),
			KEY date_created (date_created),
			KEY visit_id (visit_id),
			KEY payment_id (payment_id),
			KEY parent_commission_id (parent_commission_id)
		) $collate;

		CREATE TABLE {$wpdb->prefix}wpap_affiliate_links (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			affiliate_id bigint(20) NOT NULL,
			url longtext NOT NULL,
			campaign varchar(100) DEFAULT NULL,
			creative_id varchar(100) DEFAULT NULL,
			clicks int(11) NOT NULL DEFAULT 0,
			conversions int(11) NOT NULL DEFAULT 0,
			conversion_rate decimal(5,4) NOT NULL DEFAULT '0.0000',
			status varchar(20) NOT NULL DEFAULT 'active',
			date_created datetime NOT NULL,
			meta longtext,
			PRIMARY KEY (id),
			KEY affiliate_id (affiliate_id),
			KEY campaign (campaign),
			KEY status (status),
			KEY date_created (date_created)
		) $collate;

		CREATE TABLE {$wpdb->prefix}wpap_visits (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			affiliate_id bigint(20) NOT NULL,
			link_id bigint(20) DEFAULT NULL,
			ip_address varchar(45) NOT NULL,
			user_agent text,
			referrer longtext,
			landing_page longtext,
			campaign varchar(100) DEFAULT NULL,
			converted tinyint(1) NOT NULL DEFAULT 0,
			conversion_id bigint(20) DEFAULT NULL,
			date_created datetime NOT NULL,
			meta longtext,
			PRIMARY KEY (id),
			KEY affiliate_id (affiliate_id),
			KEY link_id (link_id),
			KEY ip_address (ip_address),
			KEY converted (converted),
			KEY date_created (date_created),
			KEY conversion_id (conversion_id)
		) $collate;

		CREATE TABLE {$wpdb->prefix}wpap_payments (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			affiliate_id bigint(20) NOT NULL,
			amount decimal(15,4) NOT NULL DEFAULT '0.0000',
			currency varchar(3) NOT NULL DEFAULT 'USD',
			method varchar(50) NOT NULL DEFAULT 'paypal',
			status varchar(20) NOT NULL DEFAULT 'pending',
			transaction_id varchar(255) DEFAULT NULL,
			payment_date datetime DEFAULT NULL,
			notes text,
			commission_ids longtext,
			date_created datetime NOT NULL,
			meta longtext,
			PRIMARY KEY (id),
			KEY affiliate_id (affiliate_id),
			KEY status (status),
			KEY method (method),
			KEY payment_date (payment_date),
			KEY date_created (date_created)
		) $collate;

		CREATE TABLE {$wpdb->prefix}wpap_creative_assets (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			type varchar(50) NOT NULL,
			file_url longtext NOT NULL,
			preview_url longtext,
			description text,
			dimensions varchar(20) DEFAULT NULL,
			file_size int(11) DEFAULT NULL,
			clicks int(11) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			date_created datetime NOT NULL,
			meta longtext,
			PRIMARY KEY (id),
			KEY type (type),
			KEY status (status),
			KEY date_created (date_created)
		) $collate;

		CREATE TABLE {$wpdb->prefix}wpap_email_logs (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			affiliate_id bigint(20) DEFAULT NULL,
			email_to varchar(255) NOT NULL,
			email_subject varchar(255) NOT NULL,
			email_content longtext NOT NULL,
			email_type varchar(50) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'sent',
			date_sent datetime NOT NULL,
			meta longtext,
			PRIMARY KEY (id),
			KEY affiliate_id (affiliate_id),
			KEY email_type (email_type),
			KEY status (status),
			KEY date_sent (date_sent)
		) $collate;
		";

		dbDelta( $tables );
	}

	private static function create_options() {
		$default_options = array(
			'wpap_general_settings' => array(
				'affiliate_registration' => 'enabled',
				'auto_approve_affiliates' => 'no',
				'auto_login_after_registration' => 'yes',
				'require_approval' => 'yes',
				'cookie_duration' => 30,
				'commission_rate' => 10,
				'commission_type' => 'percentage',
				'minimum_payout' => 50,
				'currency' => 'USD',
				'enable_multi_level' => 'no',
				'max_levels' => 3,
				'level_2_rate' => 5,
				'level_3_rate' => 2,
				'fraud_protection' => 'yes',
				'track_logged_in_users' => 'no',
				'payment_frequency' => 'monthly'
			),
			'wpap_email_settings' => array(
				'enable_emails' => 'yes',
				'from_name' => get_bloginfo( 'name' ),
				'from_email' => get_option( 'admin_email' ),
				'new_affiliate_notification' => 'yes',
				'affiliate_approval_notification' => 'yes',
				'new_commission_notification' => 'yes',
				'payment_notification' => 'yes',
				'footer_text' => sprintf( __( 'You are receiving this email because you are part of the %s affiliate program.', 'wp-affiliate-pro' ), get_bloginfo( 'name' ) )
			),
			'wpap_page_settings' => array(
				'affiliate_dashboard_page' => 0,
				'affiliate_registration_page' => 0,
				'terms_and_conditions_page' => 0
			)
		);

		foreach ( $default_options as $option_name => $option_value ) {
			add_option( $option_name, $option_value );
		}
	}

	private static function create_pages() {
		$pages = array(
			'affiliate_dashboard' => array(
				'title' => __( 'Affiliate Dashboard', 'wp-affiliate-pro' ),
				'content' => '[wpap_affiliate_dashboard]'
			),
			'affiliate_registration' => array(
				'title' => __( 'Affiliate Registration', 'wp-affiliate-pro' ),
				'content' => '[wpap_affiliate_registration]'
			),
			'affiliate_login' => array(
				'title' => __( 'Affiliate Login', 'wp-affiliate-pro' ),
				'content' => '[wpap_affiliate_login]'
			)
		);

		$page_settings = get_option( 'wpap_page_settings', array() );

		foreach ( $pages as $slug => $page ) {
			// Check if page already exists
			$existing_page = get_page_by_title( $page['title'] );
			
			if ( ! $existing_page ) {
				$page_id = wp_insert_post( array(
					'post_title' => $page['title'],
					'post_content' => $page['content'],
					'post_status' => 'publish',
					'post_type' => 'page',
					'post_name' => $slug,
					'comment_status' => 'closed',
					'ping_status' => 'closed'
				) );

				if ( $page_id && ! is_wp_error( $page_id ) ) {
					$page_settings[$slug . '_page'] = $page_id;
				}
			} else {
				$page_settings[$slug . '_page'] = $existing_page->ID;
			}
		}

		update_option( 'wpap_page_settings', $page_settings );
	}

	private static function create_roles_and_capabilities() {
		// Create affiliate role
		$affiliate_role = get_role( 'affiliate' );
		
		if ( ! $affiliate_role ) {
			add_role( 'affiliate', __( 'Affiliate', 'wp-affiliate-pro' ), array(
				'read' => true,
				'edit_posts' => false,
				'delete_posts' => false,
				'publish_posts' => false,
				'upload_files' => false
			) );
		}

		// Add capabilities to administrator
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_capabilities = array(
				'manage_affiliates',
				'edit_affiliate',
				'delete_affiliate',
				'view_affiliate_reports',
				'process_affiliate_payments'
			);

			foreach ( $admin_capabilities as $cap ) {
				$admin_role->add_cap( $cap );
			}
		}
	}

	private static function schedule_events() {
		// Daily cleanup
		if ( ! wp_next_scheduled( 'wpap_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'wpap_daily_cleanup' );
		}

		// Commission processing
		if ( ! wp_next_scheduled( 'wpap_commission_processing' ) ) {
			wp_schedule_event( time(), 'hourly', 'wpap_commission_processing' );
		}

		// Weekly stats calculation
		if ( ! wp_next_scheduled( 'wpap_weekly_stats' ) ) {
			wp_schedule_event( time(), 'weekly', 'wpap_weekly_stats' );
		}

		// Monthly payment reminders
		if ( ! wp_next_scheduled( 'wpap_monthly_payment_reminders' ) ) {
			wp_schedule_event( time(), 'monthly', 'wpap_monthly_payment_reminders' );
		}
	}

	private static function set_default_settings() {
		add_option( 'wpap_flush_rewrite_rules', 'yes' );
		add_option( 'wpap_installed', 'yes' );
		add_option( 'wpap_db_version', '1.0.0' );
		
		// Set default permalink structure if needed
		$permalink_structure = get_option( 'permalink_structure' );
		if ( empty( $permalink_structure ) ) {
			update_option( 'permalink_structure', '/%postname%/' );
			flush_rewrite_rules();
		}
	}

	/**
	 * Upgrade database if needed
	 */
	public static function maybe_upgrade() {
		$installed_version = get_option( 'wpap_version', '0.0.0' );
		
		if ( version_compare( $installed_version, WPAP_VERSION, '<' ) ) {
			self::upgrade( $installed_version );
		}
	}

	/**
	 * Upgrade from older versions
	 */
	private static function upgrade( $from_version ) {
		global $wpdb;

		// Upgrade to 1.0.1 - Add missing columns
		if ( version_compare( $from_version, '1.0.1', '<' ) ) {
			// Add any new columns to existing tables
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}wpap_affiliates ADD COLUMN IF NOT EXISTS total_unpaid decimal(15,4) NOT NULL DEFAULT '0.0000' AFTER total_paid" );
		}

		// Update version
		update_option( 'wpap_version', WPAP_VERSION );
		
		do_action( 'wpap_upgraded', $from_version, WPAP_VERSION );
	}

	/**
	 * Plugin uninstall
	 */
	public static function uninstall() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Check if we should keep data
		$keep_data = get_option( 'wpap_keep_data_on_uninstall', false );
		if ( $keep_data ) {
			return;
		}

		global $wpdb;

		// Drop all plugin tables
		$tables = array(
			$wpdb->prefix . 'wpap_affiliates',
			$wpdb->prefix . 'wpap_commissions',
			$wpdb->prefix . 'wpap_affiliate_links',
			$wpdb->prefix . 'wpap_visits',
			$wpdb->prefix . 'wpap_payments',
			$wpdb->prefix . 'wpap_creative_assets',
			$wpdb->prefix . 'wpap_email_logs'
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		// Delete all plugin options
		$options = array(
			'wpap_general_settings',
			'wpap_email_settings',
			'wpap_page_settings',
			'wpap_version',
			'wpap_db_version',
			'wpap_install_date',
			'wpap_flush_rewrite_rules',
			'wpap_installed',
			'wpap_keep_data_on_uninstall'
		);

		foreach ( $options as $option ) {
			delete_option( $option );
		}

		// Delete all short URL options
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wpap_short_url_%'" );

		// Clear scheduled hooks
		wp_clear_scheduled_hook( 'wpap_daily_cleanup' );
		wp_clear_scheduled_hook( 'wpap_commission_processing' );
		wp_clear_scheduled_hook( 'wpap_weekly_stats' );
		wp_clear_scheduled_hook( 'wpap_monthly_payment_reminders' );

		// Remove custom role
		remove_role( 'affiliate' );

		// Remove capabilities from admin role
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_capabilities = array(
				'manage_affiliates',
				'edit_affiliate',
				'delete_affiliate',
				'view_affiliate_reports',
				'process_affiliate_payments'
			);

			foreach ( $admin_capabilities as $cap ) {
				$admin_role->remove_cap( $cap );
			}
		}

		// Delete plugin pages (optional)
		$page_settings = get_option( 'wpap_page_settings', array() );
		foreach ( $page_settings as $page_id ) {
			if ( $page_id ) {
				wp_delete_post( $page_id, true );
			}
		}

		// Clear rewrite rules
		flush_rewrite_rules();

		do_action( 'wpap_uninstalled' );
	}

	/**
	 * Create sample data for testing
	 */
	public static function create_sample_data() {
		// Only in development mode
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		// Create sample affiliate
		$user_id = wp_create_user( 'affiliate_demo', 'demo123', 'affiliate@example.com' );
		
		if ( ! is_wp_error( $user_id ) ) {
			wp_update_user( array(
				'ID' => $user_id,
				'first_name' => 'Demo',
				'last_name' => 'Affiliate',
				'display_name' => 'Demo Affiliate'
			) );

			// Create affiliate record
			if ( function_exists( 'wpap' ) && wpap()->affiliates ) {
				wpap()->affiliates->create( array(
					'user_id' => $user_id,
					'status' => 'active',
					'payment_email' => 'affiliate@example.com'
				) );
			}
		}
	}
}