<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAP_Commissions {

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
			
			$this->table = $this->db->get_commissions_table();
		}
		
		// Double-check database connection is still valid
		if ( ! $this->db || ! $this->db->wpdb ) {
			throw new Exception( 'Database connection lost' );
		}
	}

	public function create( $args ) {
		$this->init_db();
		
		$defaults = array(
			'affiliate_id' => 0,
			'order_id' => null,
			'order_total' => 0.0000,
			'commission_amount' => 0.0000,
			'commission_rate' => 0.0000,
			'commission_type' => 'percentage',
			'currency' => $this->get_default_currency(),
			'status' => 'pending',
			'type' => 'sale',
			'description' => '',
			'reference' => '',
			'visit_id' => null,
			'parent_commission_id' => null,
			'level' => 1,
			'date_created' => current_time( 'mysql' ),
			'meta' => ''
		);

		$args = wp_parse_args( $args, $defaults );

		if ( empty( $args['affiliate_id'] ) ) {
			return new WP_Error( 'missing_affiliate_id', __( 'Affiliate ID is required.', 'wp-affiliate-pro' ) );
		}

		if ( ! wpap()->affiliates->is_active( $args['affiliate_id'] ) ) {
			return new WP_Error( 'inactive_affiliate', __( 'Affiliate is not active.', 'wp-affiliate-pro' ) );
		}

		if ( $this->commission_exists( $args['affiliate_id'], $args['order_id'], $args['type'] ) ) {
			return new WP_Error( 'commission_exists', __( 'Commission already exists for this order.', 'wp-affiliate-pro' ) );
		}

		if ( empty( $args['commission_amount'] ) ) {
			$args['commission_amount'] = $this->calculate_commission( 
				$args['affiliate_id'], 
				$args['order_total'], 
				$args['commission_rate'], 
				$args['commission_type'] 
			);
		}

		if ( is_array( $args['meta'] ) ) {
			$args['meta'] = serialize( $args['meta'] );
		}

		$commission_id = $this->db->insert( $this->table, $args );

		if ( ! $commission_id ) {
			return new WP_Error( 'insert_failed', __( 'Failed to create commission.', 'wp-affiliate-pro' ) );
		}

		$this->create_multi_level_commissions( $commission_id, $args );

		do_action( 'wpap_commission_created', $commission_id, $args );

		wpap()->affiliates->update_stats( $args['affiliate_id'] );

		return $commission_id;
	}

	public function get( $commission_id ) {
		$this->init_db();
		
		$query = $this->db->prepare( 
			"SELECT * FROM {$this->table} WHERE id = %d", 
			$commission_id 
		);

		$commission = $this->db->get_row( $query );

		if ( $commission && ! empty( $commission->meta ) ) {
			$commission->meta = maybe_unserialize( $commission->meta );
		}

		return $commission;
	}

	public function update( $commission_id, $args ) {
		$this->init_db();
		
		if ( isset( $args['meta'] ) && is_array( $args['meta'] ) ) {
			$args['meta'] = serialize( $args['meta'] );
		}

		$result = $this->db->update( 
			$this->table, 
			$args, 
			array( 'id' => $commission_id ),
			null,
			array( '%d' )
		);

		if ( false !== $result ) {
			$commission = $this->get( $commission_id );
			if ( $commission ) {
				wpap()->affiliates->update_stats( $commission->affiliate_id );
			}
			do_action( 'wpap_commission_updated', $commission_id, $args );
		}

		return $result;
	}

	public function delete( $commission_id ) {
		$this->init_db();
		
		$commission = $this->get( $commission_id );
		if ( ! $commission ) {
			return false;
		}

		$result = $this->db->delete( 
			$this->table, 
			array( 'id' => $commission_id ),
			array( '%d' )
		);

		if ( $result ) {
			wpap()->affiliates->update_stats( $commission->affiliate_id );
			do_action( 'wpap_commission_deleted', $commission_id, $commission );
		}

		return $result;
	}

	public function approve( $commission_id ) {
		$this->init_db();
		
		$result = $this->update( $commission_id, array( 'status' => 'approved' ) );

		if ( $result ) {
			do_action( 'wpap_commission_approved', $commission_id );
		}

		return $result;
	}

	public function reject( $commission_id, $reason = '' ) {
		$this->init_db();
		
		$args = array( 'status' => 'rejected' );
		
		if ( ! empty( $reason ) ) {
			$commission = $this->get( $commission_id );
			if ( $commission ) {
				$meta = $commission->meta ?: array();
				$meta['rejection_reason'] = $reason;
				$args['meta'] = $meta;
			}
		}

		$result = $this->update( $commission_id, $args );

		if ( $result ) {
			do_action( 'wpap_commission_rejected', $commission_id, $reason );
		}

		return $result;
	}

	public function mark_paid( $commission_id, $payment_id = null ) {
		$this->init_db();
		
		$args = array(
			'status' => 'paid',
			'date_paid' => current_time( 'mysql' )
		);

		if ( $payment_id ) {
			$args['payment_id'] = $payment_id;
		}

		$result = $this->update( $commission_id, $args );

		if ( $result ) {
			do_action( 'wpap_commission_paid', $commission_id, $payment_id );
		}

		return $result;
	}

	public function get_by_affiliate( $affiliate_id, $args = array() ) {
		$this->init_db();
		
		$defaults = array(
			'status' => '',
			'type' => '',
			'orderby' => 'date_created',
			'order' => 'DESC',
			'limit' => 20,
			'offset' => 0,
			'start_date' => '',
			'end_date' => ''
		);

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = array( 'affiliate_id = %d' );
		$where_values = array( $affiliate_id );

		if ( ! empty( $args['status'] ) ) {
			$where_clauses[] = 'status = %s';
			$where_values[] = $args['status'];
		}

		if ( ! empty( $args['type'] ) ) {
			$where_clauses[] = 'type = %s';
			$where_values[] = $args['type'];
		}

		if ( ! empty( $args['start_date'] ) ) {
			$where_clauses[] = 'date_created >= %s';
			$where_values[] = $args['start_date'];
		}

		if ( ! empty( $args['end_date'] ) ) {
			$where_clauses[] = 'date_created <= %s';
			$where_values[] = $args['end_date'];
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

		$commissions = $this->db->get_results( $query );

		foreach ( $commissions as $commission ) {
			if ( ! empty( $commission->meta ) ) {
				$commission->meta = maybe_unserialize( $commission->meta );
			}
		}

		return $commissions;
	}

	public function get_all( $args = array() ) {
		$this->init_db();
		
		$defaults = array(
			'status' => '',
			'type' => '',
			'affiliate_id' => 0,
			'orderby' => 'date_created',
			'order' => 'DESC',
			'limit' => 20,
			'offset' => 0,
			'start_date' => '',
			'end_date' => '',
			'search' => ''
		);

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = array( '1=1' );
		$where_values = array();

		if ( ! empty( $args['affiliate_id'] ) ) {
			$where_clauses[] = 'affiliate_id = %d';
			$where_values[] = $args['affiliate_id'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where_clauses[] = 'status = %s';
			$where_values[] = $args['status'];
		}

		if ( ! empty( $args['type'] ) ) {
			$where_clauses[] = 'type = %s';
			$where_values[] = $args['type'];
		}

		if ( ! empty( $args['start_date'] ) ) {
			$where_clauses[] = 'date_created >= %s';
			$where_values[] = $args['start_date'];
		}

		if ( ! empty( $args['end_date'] ) ) {
			$where_clauses[] = 'date_created <= %s';
			$where_values[] = $args['end_date'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where_clauses[] = '(reference LIKE %s OR description LIKE %s OR order_id = %s)';
			$search_term = '%' . $args['search'] . '%';
			$where_values[] = $search_term;
			$where_values[] = $search_term;
			$where_values[] = is_numeric( $args['search'] ) ? intval( $args['search'] ) : 0;
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

		$commissions = $this->db->get_results( $query );

		foreach ( $commissions as $commission ) {
			if ( ! empty( $commission->meta ) ) {
				$commission->meta = maybe_unserialize( $commission->meta );
			}
		}

		return $commissions;
	}

	public function count( $args = array() ) {
		$this->init_db();
		
		$defaults = array(
			'status' => '',
			'type' => '',
			'affiliate_id' => 0,
			'start_date' => '',
			'end_date' => '',
			'search' => ''
		);

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = array( '1=1' );
		$where_values = array();

		if ( ! empty( $args['affiliate_id'] ) ) {
			$where_clauses[] = 'affiliate_id = %d';
			$where_values[] = $args['affiliate_id'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where_clauses[] = 'status = %s';
			$where_values[] = $args['status'];
		}

		if ( ! empty( $args['type'] ) ) {
			$where_clauses[] = 'type = %s';
			$where_values[] = $args['type'];
		}

		if ( ! empty( $args['start_date'] ) ) {
			$where_clauses[] = 'date_created >= %s';
			$where_values[] = $args['start_date'];
		}

		if ( ! empty( $args['end_date'] ) ) {
			$where_clauses[] = 'date_created <= %s';
			$where_values[] = $args['end_date'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where_clauses[] = '(reference LIKE %s OR description LIKE %s OR order_id = %s)';
			$search_term = '%' . $args['search'] . '%';
			$where_values[] = $search_term;
			$where_values[] = $search_term;
			$where_values[] = is_numeric( $args['search'] ) ? intval( $args['search'] ) : 0;
		}

		$where_clause = implode( ' AND ', $where_clauses );

		$query = "SELECT COUNT(*) FROM {$this->table} WHERE {$where_clause}";

		if ( ! empty( $where_values ) ) {
			$query = $this->db->prepare( $query, ...$where_values );
		}

		return $this->db->get_var( $query );
	}

	public function calculate_commission( $affiliate_id, $order_total, $rate = null, $type = null ) {
		$this->init_db();
		
		if ( null === $rate || null === $type ) {
			$affiliate = wpap()->affiliates->get( $affiliate_id );
			if ( ! $affiliate ) {
				return 0;
			}
			
			$rate = $rate ?: $affiliate->commission_rate;
			$type = $type ?: $affiliate->commission_type;
		}

		$commission = 0;

		switch ( $type ) {
			case 'percentage':
				$commission = ( $order_total * $rate ) / 100;
				break;
			case 'fixed':
				$commission = $rate;
				break;
			case 'tiered':
				$commission = $this->calculate_tiered_commission( $affiliate_id, $order_total );
				break;
		}

		return round( $commission, 4 );
	}

	private function calculate_tiered_commission( $affiliate_id, $order_total ) {
		$affiliate = wpap()->affiliates->get( $affiliate_id );
		if ( ! $affiliate || ! isset( $affiliate->meta['tier_rates'] ) ) {
			return 0;
		}

		$tier_rates = $affiliate->meta['tier_rates'];
		$total_sales = $affiliate->total_earnings;

		foreach ( $tier_rates as $tier ) {
			if ( $total_sales >= $tier['min_sales'] && ( ! isset( $tier['max_sales'] ) || $total_sales <= $tier['max_sales'] ) ) {
				return ( $order_total * $tier['rate'] ) / 100;
			}
		}

		return 0;
	}

	private function commission_exists( $affiliate_id, $order_id, $type ) {
		if ( empty( $order_id ) ) {
			return false;
		}

		$this->init_db();

		$query = $this->db->prepare( 
			"SELECT COUNT(*) FROM {$this->table} WHERE affiliate_id = %d AND order_id = %d AND type = %s", 
			$affiliate_id, $order_id, $type 
		);

		return $this->db->get_var( $query ) > 0;
	}

	private function create_multi_level_commissions( $commission_id, $args ) {
		$settings = get_option( 'wpap_general_settings', array() );
		
		if ( empty( $settings['enable_multi_level'] ) || 'yes' !== $settings['enable_multi_level'] ) {
			return;
		}

		$affiliate = wpap()->affiliates->get( $args['affiliate_id'] );
		if ( ! $affiliate || ! $affiliate->parent_affiliate_id ) {
			return;
		}

		$parent_affiliate_id = $affiliate->parent_affiliate_id;
		$level = 2;
		$max_levels = isset( $settings['max_levels'] ) ? intval( $settings['max_levels'] ) : 3;

		while ( $parent_affiliate_id && $level <= $max_levels ) {
			$parent_affiliate = wpap()->affiliates->get( $parent_affiliate_id );
			if ( ! $parent_affiliate || ! wpap()->affiliates->is_active( $parent_affiliate_id ) ) {
				break;
			}

			$level_rate_key = 'level_' . $level . '_rate';
			$level_rate = isset( $settings[$level_rate_key] ) ? floatval( $settings[$level_rate_key] ) : 0;

			if ( $level_rate > 0 ) {
				$parent_commission_amount = $this->calculate_commission( 
					$parent_affiliate_id, 
					$args['order_total'], 
					$level_rate, 
					'percentage' 
				);

				if ( $parent_commission_amount > 0 ) {
					$parent_args = array(
						'affiliate_id' => $parent_affiliate_id,
						'order_id' => $args['order_id'],
						'order_total' => $args['order_total'],
						'commission_amount' => $parent_commission_amount,
						'commission_rate' => $level_rate,
						'commission_type' => 'percentage',
						'currency' => $args['currency'],
						'status' => 'pending',
						'type' => 'multi_level',
						'description' => sprintf( __( 'Level %d commission from affiliate %s', 'wp-affiliate-pro' ), $level, $affiliate->referral_code ),
						'reference' => $args['reference'],
						'visit_id' => $args['visit_id'],
						'parent_commission_id' => $commission_id,
						'level' => $level,
						'date_created' => $args['date_created']
					);

					$this->db->insert( $this->table, $parent_args );
				}
			}

			$parent_affiliate_id = $parent_affiliate->parent_affiliate_id;
			$level++;
		}
	}

	private function get_default_currency() {
		$settings = get_option( 'wpap_general_settings', array() );
		return isset( $settings['currency'] ) ? $settings['currency'] : 'USD';
	}
}