<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAP_Affiliates {

	private $db;
	private $table;

	public function __construct() {
		// Delay initialization until WordPress is fully loaded
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
			
			$this->table = $this->db->get_affiliates_table();
		}
		
		// Double-check database connection is still valid
		if ( ! $this->db || ! $this->db->wpdb ) {
			throw new Exception( 'Database connection lost' );
		}
	}

	public function create( $args ) {
		$this->init_db();
		
		$defaults = array(
			'user_id' => 0,
			'status' => 'pending',
			'commission_rate' => $this->get_default_commission_rate(),
			'commission_type' => $this->get_default_commission_type(),
			'payment_email' => '',
			'payment_method' => 'paypal',
			'parent_affiliate_id' => null,
			'date_registered' => current_time( 'mysql' ),
			'notes' => '',
			'meta' => ''
		);

		$args = wp_parse_args( $args, $defaults );

		if ( empty( $args['user_id'] ) ) {
			return new WP_Error( 'missing_user_id', __( 'User ID is required.', 'wp-affiliate-pro' ) );
		}

		if ( $this->affiliate_exists( $args['user_id'] ) ) {
			return new WP_Error( 'affiliate_exists', __( 'Affiliate already exists for this user.', 'wp-affiliate-pro' ) );
		}

		$args['affiliate_id'] = $this->generate_affiliate_id();
		$args['referral_code'] = $this->generate_referral_code( $args['user_id'] );

		if ( empty( $args['payment_email'] ) ) {
			$user = get_user_by( 'id', $args['user_id'] );
			$args['payment_email'] = $user ? $user->user_email : '';
		}

		if ( is_array( $args['meta'] ) ) {
			$args['meta'] = serialize( $args['meta'] );
		}

		$affiliate_id = $this->db->insert( $this->table, $args );

		if ( ! $affiliate_id ) {
			return new WP_Error( 'insert_failed', __( 'Failed to create affiliate.', 'wp-affiliate-pro' ) );
		}

		$this->update_user_role( $args['user_id'] );

		do_action( 'wpap_affiliate_created', $affiliate_id, $args );

		return $affiliate_id;
	}

	public function get( $affiliate_id ) {
		$this->init_db();
		$query = $this->db->prepare( 
			"SELECT * FROM {$this->table} WHERE id = %d", 
			$affiliate_id 
		);

		$affiliate = $this->db->get_row( $query );

		if ( $affiliate && ! empty( $affiliate->meta ) ) {
			$affiliate->meta = maybe_unserialize( $affiliate->meta );
		}

		return $affiliate;
	}

	public function get_by_user_id( $user_id ) {
		$this->init_db();
		$query = $this->db->prepare( 
			"SELECT * FROM {$this->table} WHERE user_id = %d", 
			$user_id 
		);

		$affiliate = $this->db->get_row( $query );

		if ( $affiliate && ! empty( $affiliate->meta ) ) {
			$affiliate->meta = maybe_unserialize( $affiliate->meta );
		}

		return $affiliate;
	}

	public function get_by_referral_code( $referral_code ) {
		$this->init_db();
		$query = $this->db->prepare( 
			"SELECT * FROM {$this->table} WHERE referral_code = %s", 
			$referral_code 
		);

		$affiliate = $this->db->get_row( $query );

		if ( $affiliate && ! empty( $affiliate->meta ) ) {
			$affiliate->meta = maybe_unserialize( $affiliate->meta );
		}

		return $affiliate;
	}

	public function update( $affiliate_id, $args ) {
		$this->init_db();
		
		if ( isset( $args['meta'] ) && is_array( $args['meta'] ) ) {
			$args['meta'] = serialize( $args['meta'] );
		}

		$result = $this->db->update( 
			$this->table, 
			$args, 
			array( 'id' => $affiliate_id ),
			null,
			array( '%d' )
		);

		if ( false !== $result ) {
			do_action( 'wpap_affiliate_updated', $affiliate_id, $args );
		}

		return $result;
	}

	public function delete( $affiliate_id ) {
		$this->init_db();
		
		$affiliate = $this->get( $affiliate_id );
		if ( ! $affiliate ) {
			return false;
		}

		$result = $this->db->delete( 
			$this->table, 
			array( 'id' => $affiliate_id ),
			array( '%d' )
		);

		if ( $result ) {
			$this->remove_user_role( $affiliate->user_id );
			do_action( 'wpap_affiliate_deleted', $affiliate_id, $affiliate );
		}

		return $result;
	}

	public function approve( $affiliate_id ) {
		$this->init_db();
		
		$result = $this->update( $affiliate_id, array(
			'status' => 'active',
			'date_approved' => current_time( 'mysql' )
		) );

		if ( $result ) {
			do_action( 'wpap_affiliate_approved', $affiliate_id );
		}

		return $result;
	}

	public function reject( $affiliate_id, $reason = '' ) {
		$this->init_db();
		
		$args = array( 'status' => 'rejected' );
		
		if ( ! empty( $reason ) ) {
			$args['notes'] = $reason;
		}

		$result = $this->update( $affiliate_id, $args );

		if ( $result ) {
			do_action( 'wpap_affiliate_rejected', $affiliate_id, $reason );
		}

		return $result;
	}

	public function get_all( $args = array() ) {
		$this->init_db();
		
		$defaults = array(
			'status' => '',
			'orderby' => 'date_registered',
			'order' => 'DESC',
			'limit' => 20,
			'offset' => 0,
			'search' => ''
		);

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = array( '1=1' );
		$where_values = array();

		if ( ! empty( $args['status'] ) ) {
			$where_clauses[] = 'status = %s';
			$where_values[] = $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where_clauses[] = '(user_id IN (SELECT ID FROM ' . $this->db->wpdb->users . ' WHERE user_login LIKE %s OR user_email LIKE %s OR display_name LIKE %s) OR referral_code LIKE %s)';
			$search_term = '%' . $args['search'] . '%';
			$where_values[] = $search_term;
			$where_values[] = $search_term;
			$where_values[] = $search_term;
			$where_values[] = $search_term;
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

		if ( ! empty( $where_values ) ) {
			$query = $this->db->prepare( $query, ...$where_values );
		}

		$affiliates = $this->db->get_results( $query );

		foreach ( $affiliates as $affiliate ) {
			if ( ! empty( $affiliate->meta ) ) {
				$affiliate->meta = maybe_unserialize( $affiliate->meta );
			}
		}

		return $affiliates;
	}

	public function count( $args = array() ) {
		$this->init_db();
		
		$defaults = array(
			'status' => '',
			'search' => ''
		);

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = array( '1=1' );
		$where_values = array();

		if ( ! empty( $args['status'] ) ) {
			$where_clauses[] = 'status = %s';
			$where_values[] = $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where_clauses[] = '(user_id IN (SELECT ID FROM ' . $this->db->wpdb->users . ' WHERE user_login LIKE %s OR user_email LIKE %s OR display_name LIKE %s) OR referral_code LIKE %s)';
			$search_term = '%' . $args['search'] . '%';
			$where_values[] = $search_term;
			$where_values[] = $search_term;
			$where_values[] = $search_term;
			$where_values[] = $search_term;
		}

		$where_clause = implode( ' AND ', $where_clauses );

		$query = "SELECT COUNT(*) FROM {$this->table} WHERE {$where_clause}";

		if ( ! empty( $where_values ) ) {
			$query = $this->db->prepare( $query, ...$where_values );
		}

		return $this->db->get_var( $query );
	}

	public function affiliate_exists( $user_id ) {
		$this->init_db();
		
		$query = $this->db->prepare( 
			"SELECT COUNT(*) FROM {$this->table} WHERE user_id = %d", 
			$user_id 
		);

		return $this->db->get_var( $query ) > 0;
	}

	public function is_active( $affiliate_id ) {
		$this->init_db();
		
		$affiliate = $this->get( $affiliate_id );
		return $affiliate && 'active' === $affiliate->status;
	}

	public function get_stats( $affiliate_id ) {
		$this->init_db();
		
		return $this->db->get_affiliate_stats( $affiliate_id );
	}

	public function update_stats( $affiliate_id ) {
		$this->init_db();
		
		$stats = $this->get_stats( $affiliate_id );

		$this->update( $affiliate_id, array(
			'total_earnings' => $stats['total_earnings'],
			'total_unpaid' => $stats['unpaid_earnings'],
			'total_paid' => $stats['total_earnings'] - $stats['unpaid_earnings'],
			'total_referrals' => $stats['total_commissions'],
			'total_visits' => $stats['total_visits'],
			'conversion_rate' => $stats['conversion_rate'] / 100
		) );
	}

	private function generate_affiliate_id() {
		return wp_generate_uuid4();
	}

	private function generate_referral_code( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		$base_code = $user ? sanitize_title( $user->user_login ) : 'affiliate';
		
		$referral_code = $base_code;
		$counter = 1;

		while ( $this->referral_code_exists( $referral_code ) ) {
			$referral_code = $base_code . $counter;
			$counter++;
		}

		return strtolower( $referral_code );
	}

	private function referral_code_exists( $referral_code ) {
		$this->init_db();
		
		$query = $this->db->prepare( 
			"SELECT COUNT(*) FROM {$this->table} WHERE referral_code = %s", 
			$referral_code 
		);

		return $this->db->get_var( $query ) > 0;
	}

	private function update_user_role( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		if ( $user && ! in_array( 'affiliate', $user->roles ) ) {
			$user->add_role( 'affiliate' );
		}
	}

	private function remove_user_role( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		if ( $user && in_array( 'affiliate', $user->roles ) ) {
			$user->remove_role( 'affiliate' );
		}
	}

	private function get_default_commission_rate() {
		$settings = get_option( 'wpap_general_settings', array() );
		return isset( $settings['commission_rate'] ) ? floatval( $settings['commission_rate'] ) : 10.0000;
	}

	private function get_default_commission_type() {
		$settings = get_option( 'wpap_general_settings', array() );
		return isset( $settings['commission_type'] ) ? $settings['commission_type'] : 'percentage';
	}
}