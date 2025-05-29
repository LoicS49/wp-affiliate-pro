<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAP_Emails {

	private $from_name;
	private $from_email;
	private $settings;

	public function __construct() {
		$this->settings = get_option( 'wpap_email_settings', array() );
		$this->from_name = $this->get_from_name();
		$this->from_email = $this->get_from_email();

		add_action( 'wpap_affiliate_created', array( $this, 'send_new_affiliate_notification' ) );
		add_action( 'wpap_affiliate_approved', array( $this, 'send_affiliate_approval_notification' ) );
		add_action( 'wpap_affiliate_rejected', array( $this, 'send_affiliate_rejection_notification' ) );
		add_action( 'wpap_commission_created', array( $this, 'send_new_commission_notification' ) );
		add_action( 'wpap_payment_completed', array( $this, 'send_payment_notification' ) );

		add_filter( 'wp_mail_from', array( $this, 'get_from_email' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'get_from_name' ) );
	}

	public function send_email( $to, $subject, $message, $type = 'general', $affiliate_id = null ) {
		if ( ! $this->emails_enabled() ) {
			return false;
		}

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $this->from_name . ' <' . $this->from_email . '>'
		);

		$message = $this->get_email_template( $message, $subject, $type );

		$sent = wp_mail( $to, $subject, $message, $headers );

		$this->log_email( $to, $subject, $message, $type, $sent ? 'sent' : 'failed', $affiliate_id );

		return $sent;
	}

	public function send_new_affiliate_notification( $affiliate_id ) {
		if ( ! $this->is_notification_enabled( 'new_affiliate_notification' ) ) {
			return;
		}

		$affiliate = wpap()->affiliates->get( $affiliate_id );
		if ( ! $affiliate ) {
			return;
		}

		$user = get_user_by( 'id', $affiliate->user_id );
		if ( ! $user ) {
			return;
		}

		$admin_email = get_option( 'admin_email' );
		$subject = sprintf( __( 'New Affiliate Registration: %s', 'wp-affiliate-pro' ), $user->display_name );
		
		$message = $this->get_template_content( 'new-affiliate-admin', array(
			'affiliate' => $affiliate,
			'user' => $user,
			'approve_url' => $this->get_admin_action_url( 'approve_affiliate', $affiliate_id ),
			'reject_url' => $this->get_admin_action_url( 'reject_affiliate', $affiliate_id )
		) );

		$this->send_email( $admin_email, $subject, $message, 'new_affiliate_admin', $affiliate_id );

		$user_subject = __( 'Thank you for your affiliate application', 'wp-affiliate-pro' );
		$user_message = $this->get_template_content( 'new-affiliate-user', array(
			'affiliate' => $affiliate,
			'user' => $user
		) );

		$this->send_email( $user->user_email, $user_subject, $user_message, 'new_affiliate_user', $affiliate_id );
	}

	public function send_affiliate_approval_notification( $affiliate_id ) {
		if ( ! $this->is_notification_enabled( 'affiliate_approval_notification' ) ) {
			return;
		}

		$affiliate = wpap()->affiliates->get( $affiliate_id );
		if ( ! $affiliate ) {
			return;
		}

		$user = get_user_by( 'id', $affiliate->user_id );
		if ( ! $user ) {
			return;
		}

		$subject = __( 'Your affiliate application has been approved!', 'wp-affiliate-pro' );
		$message = $this->get_template_content( 'affiliate-approved', array(
			'affiliate' => $affiliate,
			'user' => $user,
			'dashboard_url' => $this->get_affiliate_dashboard_url(),
			'referral_url' => site_url( 'affiliate/' . $affiliate->referral_code )
		) );

		$this->send_email( $user->user_email, $subject, $message, 'affiliate_approved', $affiliate_id );
	}

	public function send_affiliate_rejection_notification( $affiliate_id, $reason = '' ) {
		if ( ! $this->is_notification_enabled( 'affiliate_approval_notification' ) ) {
			return;
		}

		$affiliate = wpap()->affiliates->get( $affiliate_id );
		if ( ! $affiliate ) {
			return;
		}

		$user = get_user_by( 'id', $affiliate->user_id );
		if ( ! $user ) {
			return;
		}

		$subject = __( 'Your affiliate application status', 'wp-affiliate-pro' );
		$message = $this->get_template_content( 'affiliate-rejected', array(
			'affiliate' => $affiliate,
			'user' => $user,
			'reason' => $reason
		) );

		$this->send_email( $user->user_email, $subject, $message, 'affiliate_rejected', $affiliate_id );
	}

	public function send_new_commission_notification( $commission_id ) {
		if ( ! $this->is_notification_enabled( 'new_commission_notification' ) ) {
			return;
		}

		$commission = wpap()->commissions->get( $commission_id );
		if ( ! $commission ) {
			return;
		}

		$affiliate = wpap()->affiliates->get( $commission->affiliate_id );
		if ( ! $affiliate ) {
			return;
		}

		$user = get_user_by( 'id', $affiliate->user_id );
		if ( ! $user ) {
			return;
		}

		$subject = sprintf( __( 'New commission earned: %s', 'wp-affiliate-pro' ), wpap_format_amount( $commission->commission_amount ) );
		$message = $this->get_template_content( 'new-commission', array(
			'commission' => $commission,
			'affiliate' => $affiliate,
			'user' => $user,
			'dashboard_url' => $this->get_affiliate_dashboard_url()
		) );

		$this->send_email( $user->user_email, $subject, $message, 'new_commission', $affiliate->id );
	}

	public function send_payment_notification( $payment_id ) {
		if ( ! $this->is_notification_enabled( 'payment_notification' ) ) {
			return;
		}

		$payment = wpap()->payments->get_payment( $payment_id );
		if ( ! $payment ) {
			return;
		}

		$affiliate = wpap()->affiliates->get( $payment->affiliate_id );
		if ( ! $affiliate ) {
			return;
		}

		$user = get_user_by( 'id', $affiliate->user_id );
		if ( ! $user ) {
			return;
		}

		$subject = sprintf( __( 'Payment processed: %s', 'wp-affiliate-pro' ), wpap_format_amount( $payment->amount ) );
		$message = $this->get_template_content( 'payment-processed', array(
			'payment' => $payment,
			'affiliate' => $affiliate,
			'user' => $user,
			'dashboard_url' => $this->get_affiliate_dashboard_url()
		) );

		$this->send_email( $user->user_email, $subject, $message, 'payment_processed', $affiliate->id );
	}

	public function send_welcome_email( $affiliate_id ) {
		$affiliate = wpap()->affiliates->get( $affiliate_id );
		if ( ! $affiliate ) {
			return false;
		}

		$user = get_user_by( 'id', $affiliate->user_id );
		if ( ! $user ) {
			return false;
		}

		$subject = sprintf( __( 'Welcome to %s Affiliate Program', 'wp-affiliate-pro' ), get_bloginfo( 'name' ) );
		$message = $this->get_template_content( 'welcome', array(
			'affiliate' => $affiliate,
			'user' => $user,
			'dashboard_url' => $this->get_affiliate_dashboard_url(),
			'referral_url' => site_url( 'affiliate/' . $affiliate->referral_code )
		) );

		return $this->send_email( $user->user_email, $subject, $message, 'welcome', $affiliate_id );
	}

	public function send_payment_reminder( $affiliate_id ) {
		$affiliate = wpap()->affiliates->get( $affiliate_id );
		if ( ! $affiliate ) {
			return false;
		}

		$user = get_user_by( 'id', $affiliate->user_id );
		if ( ! $user ) {
			return false;
		}

		$unpaid_amount = $affiliate->total_unpaid;
		$minimum_payout = wpap()->payments->get_minimum_payout();

		if ( $unpaid_amount < $minimum_payout ) {
			return false;
		}

		$subject = __( 'Payment reminder - Funds available for withdrawal', 'wp-affiliate-pro' );
		$message = $this->get_template_content( 'payment-reminder', array(
			'affiliate' => $affiliate,
			'user' => $user,
			'unpaid_amount' => $unpaid_amount,
			'minimum_payout' => $minimum_payout,
			'dashboard_url' => $this->get_affiliate_dashboard_url()
		) );

		return $this->send_email( $user->user_email, $subject, $message, 'payment_reminder', $affiliate_id );
	}

	public function get_email_template( $content, $subject, $type = 'general' ) {
		$template = $this->get_base_template();
		
		$replacements = array(
			'{subject}' => $subject,
			'{content}' => $content,
			'{site_name}' => get_bloginfo( 'name' ),
			'{site_url}' => home_url(),
			'{logo_url}' => $this->get_logo_url(),
			'{footer_text}' => $this->get_footer_text(),
			'{unsubscribe_url}' => $this->get_unsubscribe_url()
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
	}

	private function get_template_content( $template_name, $args = array() ) {
		$template_file = WPAP_PLUGIN_DIR . 'templates/emails/' . $template_name . '.php';
		
		if ( file_exists( $template_file ) ) {
			ob_start();
			extract( $args );
			include $template_file;
			return ob_get_clean();
		}

		return $this->get_default_template_content( $template_name, $args );
	}

	private function get_default_template_content( $template_name, $args ) {
		switch ( $template_name ) {
			case 'new-affiliate-admin':
				return sprintf(
					__( 'A new affiliate has registered:<br><br>Name: %s<br>Email: %s<br>Referral Code: %s<br><br><a href="%s">Approve</a> | <a href="%s">Reject</a>', 'wp-affiliate-pro' ),
					$args['user']->display_name,
					$args['user']->user_email,
					$args['affiliate']->referral_code,
					$args['approve_url'],
					$args['reject_url']
				);

			case 'new-affiliate-user':
				return sprintf(
					__( 'Thank you for applying to our affiliate program!<br><br>Your application is being reviewed and you will be notified once it has been processed.<br><br>Referral Code: %s', 'wp-affiliate-pro' ),
					$args['affiliate']->referral_code
				);

			case 'affiliate-approved':
				return sprintf(
					__( 'Congratulations! Your affiliate application has been approved.<br><br>Your referral code: %s<br>Your referral URL: %s<br><br><a href="%s">Access your dashboard</a>', 'wp-affiliate-pro' ),
					$args['affiliate']->referral_code,
					$args['referral_url'],
					$args['dashboard_url']
				);

			case 'affiliate-rejected':
				$message = __( 'We regret to inform you that your affiliate application was not approved at this time.', 'wp-affiliate-pro' );
				if ( ! empty( $args['reason'] ) ) {
					$message .= '<br><br>' . sprintf( __( 'Reason: %s', 'wp-affiliate-pro' ), $args['reason'] );
				}
				return $message;

			case 'new-commission':
				return sprintf(
					__( 'Great news! You have earned a new commission.<br><br>Amount: %s<br>Type: %s<br>Status: %s<br><br><a href="%s">View your dashboard</a>', 'wp-affiliate-pro' ),
					wpap_format_amount( $args['commission']->commission_amount ),
					ucfirst( $args['commission']->type ),
					ucfirst( $args['commission']->status ),
					$args['dashboard_url']
				);

			case 'payment-processed':
				return sprintf(
					__( 'Your payment has been processed!<br><br>Amount: %s<br>Method: %s<br>Transaction ID: %s<br><br><a href="%s">View payment details</a>', 'wp-affiliate-pro' ),
					wpap_format_amount( $args['payment']->amount ),
					ucfirst( str_replace( '_', ' ', $args['payment']->method ) ),
					$args['payment']->transaction_id ?: __( 'N/A', 'wp-affiliate-pro' ),
					$args['dashboard_url']
				);

			default:
				return __( 'Thank you for being part of our affiliate program!', 'wp-affiliate-pro' );
		}
	}

	private function get_base_template() {
		$template = '
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title>{subject}</title>
			<style>
				body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
				.container { max-width: 600px; margin: 0 auto; padding: 20px; }
				.header { text-align: center; border-bottom: 2px solid #0073aa; padding-bottom: 20px; margin-bottom: 30px; }
				.logo { max-width: 200px; height: auto; }
				.content { margin-bottom: 30px; }
				.footer { border-top: 1px solid #ddd; padding-top: 20px; text-align: center; font-size: 12px; color: #666; }
				.button { display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 5px; }
			</style>
		</head>
		<body>
			<div class="container">
				<div class="header">
					<img src="{logo_url}" alt="{site_name}" class="logo">
					<h1>{site_name} Affiliate Program</h1>
				</div>
				<div class="content">
					{content}
				</div>
				<div class="footer">
					<p>{footer_text}</p>
					<p><a href="{unsubscribe_url}">Unsubscribe</a> | <a href="{site_url}">Visit Website</a></p>
				</div>
			</div>
		</body>
		</html>';

		return apply_filters( 'wpap_email_template', $template );
	}

	private function log_email( $to, $subject, $content, $type, $status, $affiliate_id = null ) {
		$data = array(
			'affiliate_id' => $affiliate_id,
			'email_to' => $to,
			'email_subject' => $subject,
			'email_content' => $content,
			'email_type' => $type,
			'status' => $status,
			'date_sent' => current_time( 'mysql' )
		);

		wpap()->database->insert( wpap()->database->get_email_logs_table(), $data );
	}

	private function emails_enabled() {
		return isset( $this->settings['enable_emails'] ) && 'yes' === $this->settings['enable_emails'];
	}

	private function is_notification_enabled( $notification_type ) {
		return $this->emails_enabled() && 
			   isset( $this->settings[$notification_type] ) && 
			   'yes' === $this->settings[$notification_type];
	}

	public function get_from_name() {
		return isset( $this->settings['from_name'] ) ? $this->settings['from_name'] : get_bloginfo( 'name' );
	}

	public function get_from_email() {
		return isset( $this->settings['from_email'] ) ? $this->settings['from_email'] : get_option( 'admin_email' );
	}

	private function get_logo_url() {
		$logo_url = isset( $this->settings['logo_url'] ) ? $this->settings['logo_url'] : '';
		return $logo_url ?: WPAP_PLUGIN_URL . 'assets/images/logo.png';
	}

	private function get_footer_text() {
		return isset( $this->settings['footer_text'] ) ? 
			$this->settings['footer_text'] : 
			sprintf( __( 'You are receiving this email because you are part of the %s affiliate program.', 'wp-affiliate-pro' ), get_bloginfo( 'name' ) );
	}

	private function get_unsubscribe_url() {
		return add_query_arg( array(
			'wpap_action' => 'unsubscribe',
			'nonce' => wp_create_nonce( 'wpap_unsubscribe' )
		), home_url() );
	}

	private function get_affiliate_dashboard_url() {
		$page_settings = get_option( 'wpap_page_settings', array() );
		$dashboard_page_id = isset( $page_settings['affiliate_dashboard_page'] ) ? $page_settings['affiliate_dashboard_page'] : 0;
		
		if ( $dashboard_page_id ) {
			return get_permalink( $dashboard_page_id );
		}

		return home_url( 'affiliate-dashboard' );
	}

	private function get_admin_action_url( $action, $affiliate_id ) {
		return wp_nonce_url(
			admin_url( 'admin.php?page=wpap-affiliates&wpap_action=' . $action . '&affiliate_id=' . $affiliate_id ),
			'wpap_admin_action'
		);
	}
}