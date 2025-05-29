<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAP_Links {

	private $db;
	private $table;
	private $visits_table;

	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	public function init() {
		$this->init_db();
		add_action( 'init', array( $this, 'add_rewrite_rules' ), 20 );
		add_action( 'template_redirect', array( $this, 'handle_affiliate_link' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
	}

	private function init_db() {
		if ( ! $this->db ) {
			// Check if WordPress is fully loaded
			if ( ! function_exists( 'wpap' ) || ! wpap() || ! wpap()->database ) {
				throw new Exception( 'WordPress and plugin not fully loaded' );
			}
			
			// Get database instance
			$this->db = wpap()->database;
			
			// Verify database instance is valid
			if ( ! $this->db || ! $this->db->wpdb ) {
				throw new Exception( 'Database connection not available' );
			}
			
			$this->table = $this->db->get_links_table();
			$this->visits_table = $this->db->get_visits_table();
		}
		
		// Double-check database connection is still valid
		if ( ! $this->db || ! $this->db->wpdb ) {
			throw new Exception( 'Database connection lost' );
		}
	}

	public function add_rewrite_rules() {
		add_rewrite_rule( '^affiliate/([^/]+)/?', 'index.php?wpap_ref=$matches[1]', 'top' );
		add_rewrite_rule( '^go/([^/]+)/?', 'index.php?wpap_ref=$matches[1]', 'top' );
		
		if ( get_option( 'wpap_flush_rewrite_rules' ) === 'yes' ) {
			flush_rewrite_rules();
			delete_option( 'wpap_flush_rewrite_rules' );
		}
	}

	public function add_query_vars( $vars ) {
		$vars[] = 'wpap_ref';
		return $vars;
	}

	public function handle_affiliate_link() {
		$ref = get_query_var( 'wpap_ref' );
		
		if ( empty( $ref ) ) {
			return;
		}

		$affiliate = wpap()->affiliates->get_by_referral_code( $ref );
		
		if ( ! $affiliate || ! wpap()->affiliates->is_active( $affiliate->id ) ) {
			wp_redirect( home_url() );
			exit;
		}

		$this->track_visit( $affiliate->id, $ref );
		$this->set_affiliate_cookie( $affiliate->id );

		$redirect_url = $this->get_redirect_url( $ref );
		
		if ( $this->is_fraud_attempt( $affiliate->id ) ) {
			wpap()->log( 'Potential fraud attempt detected for affiliate: ' . $affiliate->id, 'warning' );
			wp_redirect( home_url() );
			exit;
		}

		wp_redirect( $redirect_url );
		exit;
	}

	public function generate_link( $affiliate_id, $url, $args = array() ) {
		$this->init_db();
		
		$defaults = array(
			'campaign' => '',
			'creative_id' => '',
			'custom_slug' => '',
			'short_url' => true,
			'deep_link' => true
		);

		$args = wp_parse_args( $args, $defaults );

		$affiliate = wpap()->affiliates->get( $affiliate_id );
		if ( ! $affiliate ) {
			return new WP_Error( 'invalid_affiliate', __( 'Invalid affiliate ID.', 'wp-affiliate-pro' ) );
		}

		$base_url = $this->get_affiliate_base_url( $affiliate->referral_code );
		
		$link_data = array(
			'affiliate_id' => $affiliate_id,
			'url' => esc_url( $url ),
			'campaign' => sanitize_text_field( $args['campaign'] ),
			'creative_id' => sanitize_text_field( $args['creative_id'] ),
			'status' => 'active',
			'date_created' => current_time( 'mysql' )
		);

		if ( ! empty( $args['custom_slug'] ) ) {
			$custom_slug = sanitize_title( $args['custom_slug'] );
			if ( ! $this->slug_exists( $custom_slug ) ) {
				$base_url = site_url( 'go/' . $custom_slug );
				$link_data['meta'] = serialize( array( 'custom_slug' => $custom_slug ) );
			}
		}

		$link_id = $this->db->insert( $this->table, $link_data );

		if ( ! $link_id ) {
			return new WP_Error( 'link_creation_failed', __( 'Failed to create affiliate link.', 'wp-affiliate-pro' ) );
		}

		$final_url = $this->add_tracking_params( $base_url, $args );

		do_action( 'wpap_link_generated', $link_id, $final_url, $args );

		return array(
			'link_id' => $link_id,
			'url' => $final_url,
			'short_url' => $args['short_url'] ? $this->create_short_url( $final_url ) : $final_url,
			'qr_code' => $this->generate_qr_code( $final_url )
		);
	}

	public function track_visit( $affiliate_id, $ref_code, $link_id = null ) {
		$this->init_db();
		
		$visit_data = array(
			'affiliate_id' => $affiliate_id,
			'link_id' => $link_id,
			'ip_address' => $this->get_visitor_ip(),
			'user_agent' => $this->get_user_agent(),
			'referrer' => $this->get_referrer(),
			'landing_page' => $this->get_current_url(),
			'campaign' => $this->get_campaign_from_url(),
			'date_created' => current_time( 'mysql' )
		);

		if ( $this->should_track_visit( $visit_data ) ) {
			$visit_id = $this->db->insert( $this->visits_table, $visit_data );
			
			if ( $visit_id && $link_id ) {
				$this->increment_link_clicks( $link_id );
			}

			do_action( 'wpap_visit_tracked', $visit_id, $visit_data );

			return $visit_id;
		}

		return false;
	}

	public function attribute_conversion( $order_id, $order_total, $attribution_method = 'last_click' ) {
		$this->init_db();
		
		$affiliate_id = $this->get_attributed_affiliate( $attribution_method );
		
		if ( ! $affiliate_id ) {
			return false;
		}

		$visit_id = $this->get_conversion_visit( $affiliate_id );
		
		if ( $visit_id ) {
			$this->db->update( 
				$this->visits_table,
				array( 
					'converted' => 1,
					'conversion_id' => $order_id
				),
				array( 'id' => $visit_id ),
				array( '%d', '%d' ),
				array( '%d' )
			);

			$link_id = $this->get_visit_link_id( $visit_id );
			if ( $link_id ) {
				$this->increment_link_conversions( $link_id );
			}
		}

		return array(
			'affiliate_id' => $affiliate_id,
			'visit_id' => $visit_id,
			'link_id' => $link_id
		);
	}

	public function get_link_stats( $link_id ) {
		$this->init_db();
		
		$link = $this->get_link( $link_id );
		if ( ! $link ) {
			return false;
		}

		$stats = array(
			'clicks' => $link->clicks,
			'conversions' => $link->conversions,
			'conversion_rate' => $link->conversion_rate,
			'earnings' => 0
		);

		$earnings = $this->db->get_var( $this->db->prepare(
			"SELECT SUM(c.commission_amount) 
			FROM {$this->db->get_commissions_table()} c
			INNER JOIN {$this->visits_table} v ON c.visit_id = v.id
			WHERE v.link_id = %d AND c.status IN ('approved', 'paid')",
			$link_id
		) );

		$stats['earnings'] = $earnings ?: 0;

		return $stats;
	}

	public function create_short_url( $url ) {
		$hash = substr( md5( $url . time() ), 0, 6 );
		$short_url = site_url( 'go/' . $hash );
		
		update_option( 'wpap_short_url_' . $hash, $url );
		
		return $short_url;
	}

	public function generate_qr_code( $url, $size = 200 ) {
		$qr_api_url = add_query_arg( array(
			'chs' => $size . 'x' . $size,
			'cht' => 'qr',
			'chl' => urlencode( $url ),
			'choe' => 'UTF-8'
		), 'https://chart.googleapis.com/chart' );

		return $qr_api_url;
	}

	public function get_affiliate_links( $affiliate_id, $args = array() ) {
		$this->init_db();
		
		$defaults = array(
			'status' => 'active',
			'orderby' => 'date_created',
			'order' => 'DESC',
			'limit' => 20,
			'offset' => 0
		);

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = array( 'affiliate_id = %d' );
		$where_values = array( $affiliate_id );

		if ( ! empty( $args['status'] ) ) {
			$where_clauses[] = 'status = %s';
			$where_values[] = $args['status'];
		}

		$where_clause = implode( ' AND ', $where_clauses );
		$order_clause = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );

		$query = "SELECT * FROM {$this->table} WHERE {$where_clause}";
		
		if ( $order_clause ) {
			$query .= " ORDER BY {$order_clause}";
		}

		if ( $args['limit'] > 0 ) {
			$query .= $this->db->prepare( " LIMIT %d OFFSET %d", $args['limit'], $args['offset'] );
		}

		$query = $this->db->prepare( $query, ...$where_values );

		return $this->db->get_results( $query );
	}

	private function get_link( $link_id ) {
		$this->init_db();
		
		$query = $this->db->prepare( 
			"SELECT * FROM {$this->table} WHERE id = %d", 
			$link_id 
		);

		return $this->db->get_row( $query );
	}

	private function get_affiliate_base_url( $referral_code ) {
		return site_url( 'affiliate/' . $referral_code );
	}

	private function add_tracking_params( $url, $args ) {
		$params = array();

		if ( ! empty( $args['campaign'] ) ) {
			$params['wpap_campaign'] = $args['campaign'];
		}

		if ( ! empty( $args['creative_id'] ) ) {
			$params['wpap_creative'] = $args['creative_id'];
		}

		if ( ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		}

		return $url;
	}

	private function get_redirect_url( $ref_code ) {
		$redirect_url = isset( $_GET['wpap_redirect'] ) ? esc_url_raw( $_GET['wpap_redirect'] ) : '';
		
		if ( empty( $redirect_url ) ) {
			$redirect_url = home_url();
		}

		return apply_filters( 'wpap_redirect_url', $redirect_url, $ref_code );
	}

	private function set_affiliate_cookie( $affiliate_id ) {
		$cookie_duration = $this->get_cookie_duration();
		$cookie_name = 'wpap_affiliate_id';
		$cookie_value = $affiliate_id;

		setcookie( $cookie_name, $cookie_value, time() + $cookie_duration, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		$_COOKIE[$cookie_name] = $cookie_value;
	}

	private function get_cookie_duration() {
		$settings = get_option( 'wpap_general_settings', array() );
		$days = isset( $settings['cookie_duration'] ) ? intval( $settings['cookie_duration'] ) : 30;
		return $days * DAY_IN_SECONDS;
	}

	private function should_track_visit( $visit_data ) {
		if ( $this->is_bot_visit( $visit_data['user_agent'] ) ) {
			return false;
		}

		if ( $this->is_duplicate_visit( $visit_data ) ) {
			return false;
		}

		$settings = get_option( 'wpap_general_settings', array() );
		if ( isset( $settings['track_logged_in_users'] ) && 'no' === $settings['track_logged_in_users'] && is_user_logged_in() ) {
			return false;
		}

		return true;
	}

	private function is_bot_visit( $user_agent ) {
		$bot_patterns = array(
			'bot', 'crawl', 'spider', 'search', 'facebook', 'google', 'yahoo', 'bing'
		);

		$user_agent = strtolower( $user_agent );

		foreach ( $bot_patterns as $pattern ) {
			if ( strpos( $user_agent, $pattern ) !== false ) {
				return true;
			}
		}

		return false;
	}

	private function is_duplicate_visit( $visit_data ) {
		$this->init_db();
		
		$query = $this->db->prepare(
			"SELECT COUNT(*) FROM {$this->visits_table} 
			WHERE affiliate_id = %d AND ip_address = %s AND DATE(date_created) = DATE(%s)",
			$visit_data['affiliate_id'],
			$visit_data['ip_address'],
			$visit_data['date_created']
		);

		return $this->db->get_var( $query ) > 0;
	}

	private function is_fraud_attempt( $affiliate_id ) {
		$this->init_db();
		
		$ip = $this->get_visitor_ip();
		
		$recent_clicks = $this->db->get_var( $this->db->prepare(
			"SELECT COUNT(*) FROM {$this->visits_table} 
			WHERE affiliate_id = %d AND ip_address = %s AND date_created > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
			$affiliate_id,
			$ip
		) );

		return $recent_clicks > 10;
	}

	private function get_attributed_affiliate( $attribution_method = 'last_click' ) {
		$this->init_db();
		
		$affiliate_id = isset( $_COOKIE['wpap_affiliate_id'] ) ? intval( $_COOKIE['wpap_affiliate_id'] ) : 0;

		if ( 'first_click' === $attribution_method ) {
			$ip = $this->get_visitor_ip();
			$first_visit = $this->db->get_row( $this->db->prepare(
				"SELECT affiliate_id FROM {$this->visits_table} 
				WHERE ip_address = %s ORDER BY date_created ASC LIMIT 1",
				$ip
			) );

			if ( $first_visit ) {
				$affiliate_id = $first_visit->affiliate_id;
			}
		}

		return $affiliate_id;
	}

	private function get_conversion_visit( $affiliate_id ) {
		$this->init_db();
		
		$ip = $this->get_visitor_ip();
		
		$visit = $this->db->get_row( $this->db->prepare(
			"SELECT id FROM {$this->visits_table} 
			WHERE affiliate_id = %d AND ip_address = %s AND converted = 0 
			ORDER BY date_created DESC LIMIT 1",
			$affiliate_id,
			$ip
		) );

		return $visit ? $visit->id : null;
	}

	private function get_visit_link_id( $visit_id ) {
		$this->init_db();
		
		$visit = $this->db->get_row( $this->db->prepare(
			"SELECT link_id FROM {$this->visits_table} WHERE id = %d",
			$visit_id
		) );

		return $visit ? $visit->link_id : null;
	}

	private function increment_link_clicks( $link_id ) {
		$this->init_db();
		
		$this->db->query( $this->db->prepare(
			"UPDATE {$this->table} SET clicks = clicks + 1 WHERE id = %d",
			$link_id
		) );
	}

	private function increment_link_conversions( $link_id ) {
		$this->init_db();
		
		$this->db->query( $this->db->prepare(
			"UPDATE {$this->table} SET conversions = conversions + 1,
			conversion_rate = (conversions + 1) / clicks WHERE id = %d",
			$link_id
		) );
	}

	private function slug_exists( $slug ) {
		return get_option( 'wpap_short_url_' . $slug ) !== false;
	}

	private function get_visitor_ip() {
		$ip_keys = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' );
		
		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) && ! empty( $_SERVER[$key] ) ) {
				$ip = $_SERVER[$key];
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = explode( ',', $ip )[0];
				}
				$ip = trim( $ip );
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return '127.0.0.1';
	}

	private function get_user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( $_SERVER['HTTP_USER_AGENT'], 0, 255 ) : '';
	}

	private function get_referrer() {
		return isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) : '';
	}

	private function get_current_url() {
		$protocol = is_ssl() ? 'https://' : 'http://';
		return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	}

	private function get_campaign_from_url() {
		return isset( $_GET['wpap_campaign'] ) ? sanitize_text_field( $_GET['wpap_campaign'] ) : '';
	}
}