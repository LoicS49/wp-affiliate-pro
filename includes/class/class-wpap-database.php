<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAP_Database {

	protected $wpdb;

	public function __construct() {
		global $wpdb;
		
		// Ensure $wpdb is available and properly initialized
		if ( ! $wpdb ) {
			throw new Exception( 'WordPress database object not available' );
		}
		
		$this->wpdb = $wpdb;
	}

	public function get_affiliates_table() {
		return $this->wpdb->prefix . 'wpap_affiliates';
	}

	public function get_commissions_table() {
		return $this->wpdb->prefix . 'wpap_commissions';
	}

	public function get_links_table() {
		return $this->wpdb->prefix . 'wpap_affiliate_links';
	}

	public function get_visits_table() {
		return $this->wpdb->prefix . 'wpap_visits';
	}

	public function get_payments_table() {
		return $this->wpdb->prefix . 'wpap_payments';
	}

	public function get_creative_assets_table() {
		return $this->wpdb->prefix . 'wpap_creative_assets';
	}

	public function get_email_logs_table() {
		return $this->wpdb->prefix . 'wpap_email_logs';
	}

	public function insert( $table, $data, $format = null ) {
		if ( ! $this->wpdb ) {
			throw new Exception( 'Database connection not available' );
		}
		
		$result = $this->wpdb->insert( $table, $data, $format );
		
		if ( false === $result ) {
			$error_msg = $this->wpdb->last_error ?: 'Unknown database error';
			if ( function_exists( 'wpap' ) && wpap() ) {
				wpap()->log( 'Database insert error: ' . $error_msg, 'error' );
			}
			return false;
		}

		return $this->wpdb->insert_id;
	}

	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		if ( ! $this->wpdb ) {
			throw new Exception( 'Database connection not available' );
		}
		
		$result = $this->wpdb->update( $table, $data, $where, $format, $where_format );
		
		if ( false === $result ) {
			if ( function_exists( 'wpap' ) && wpap() ) {
				wpap()->log( 'Database update error: ' . $this->wpdb->last_error, 'error' );
			}
			return false;
		}

		return $result;
	}

	public function delete( $table, $where, $where_format = null ) {
		$result = $this->wpdb->delete( $table, $where, $where_format );
		
		if ( false === $result ) {
			if ( function_exists( 'wpap' ) && wpap() ) {
				wpap()->log( 'Database delete error: ' . $this->wpdb->last_error, 'error' );
			}
			return false;
		}

		return $result;
	}

	public function get_row( $query, $output = OBJECT ) {
		if ( ! $this->wpdb ) {
			throw new Exception( 'Database connection not available' );
		}
		
		$result = $this->wpdb->get_row( $query, $output );
		
		if ( $this->wpdb->last_error ) {
			if ( function_exists( 'wpap' ) && wpap() ) {
				wpap()->log( 'Database get_row error: ' . $this->wpdb->last_error, 'error' );
			}
			return false;
		}

		return $result;
	}

	public function get_results( $query, $output = OBJECT ) {
		if ( ! $this->wpdb ) {
			throw new Exception( 'Database connection not available' );
		}
		
		$result = $this->wpdb->get_results( $query, $output );
		
		if ( $this->wpdb->last_error ) {
			if ( function_exists( 'wpap' ) && wpap() ) {
				wpap()->log( 'Database get_results error: ' . $this->wpdb->last_error, 'error' );
			}
			return false;
		}

		return $result;
	}

	public function get_var( $query ) {
		if ( ! $this->wpdb ) {
			throw new Exception( 'Database connection not available' );
		}
		
		$result = $this->wpdb->get_var( $query );
		
		if ( $this->wpdb->last_error ) {
			if ( function_exists( 'wpap' ) && wpap() ) {
				wpap()->log( 'Database get_var error: ' . $this->wpdb->last_error, 'error' );
			}
			return false;
		}

		return $result;
	}

	public function query( $query ) {
		if ( ! $this->wpdb ) {
			throw new Exception( 'Database connection not available' );
		}
		
		$result = $this->wpdb->query( $query );
		
		if ( false === $result ) {
			if ( function_exists( 'wpap' ) && wpap() ) {
				wpap()->log( 'Database query error: ' . $this->wpdb->last_error, 'error' );
			}
			return false;
		}

		return $result;
	}

	public function prepare( $query, ...$args ) {
		if ( ! $this->wpdb ) {
			throw new Exception( 'Database connection not available' );
		}
		
		return $this->wpdb->prepare( $query, ...$args );
	}

	public function get_affiliate_stats( $affiliate_id, $start_date = null, $end_date = null ) {
		$where_clause = "WHERE affiliate_id = %d";
		$params = array( $affiliate_id );

		if ( $start_date ) {
			$where_clause .= " AND date_created >= %s";
			$params[] = $start_date;
		}

		if ( $end_date ) {
			$where_clause .= " AND date_created <= %s";
			$params[] = $end_date;
		}

		$commissions_table = $this->get_commissions_table();
		$visits_table = $this->get_visits_table();

		$stats = array();

		$stats['total_earnings'] = $this->get_var( 
			$this->prepare( 
				"SELECT SUM(commission_amount) FROM {$commissions_table} {$where_clause} AND status = 'paid'", 
				...$params 
			) 
		) ?: 0;

		$stats['unpaid_earnings'] = $this->get_var( 
			$this->prepare( 
				"SELECT SUM(commission_amount) FROM {$commissions_table} {$where_clause} AND status = 'pending'", 
				...$params 
			) 
		) ?: 0;

		$stats['total_commissions'] = $this->get_var( 
			$this->prepare( 
				"SELECT COUNT(*) FROM {$commissions_table} {$where_clause}", 
				...$params 
			) 
		) ?: 0;

		$stats['total_visits'] = $this->get_var( 
			$this->prepare( 
				"SELECT COUNT(*) FROM {$visits_table} {$where_clause}", 
				...$params 
			) 
		) ?: 0;

		$stats['total_conversions'] = $this->get_var( 
			$this->prepare( 
				"SELECT COUNT(*) FROM {$visits_table} {$where_clause} AND converted = 1", 
				...$params 
			) 
		) ?: 0;

		$stats['conversion_rate'] = $stats['total_visits'] > 0 ? 
			round( ( $stats['total_conversions'] / $stats['total_visits'] ) * 100, 2 ) : 0;

		return $stats;
	}

	public function get_top_affiliates( $limit = 10, $period = 'all_time' ) {
		$where_clause = '';
		$params = array();

		if ( 'this_month' === $period ) {
			$where_clause = "WHERE MONTH(date_created) = MONTH(CURDATE()) AND YEAR(date_created) = YEAR(CURDATE())";
		} elseif ( 'last_month' === $period ) {
			$where_clause = "WHERE MONTH(date_created) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(date_created) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
		} elseif ( 'this_year' === $period ) {
			$where_clause = "WHERE YEAR(date_created) = YEAR(CURDATE())";
		}

		$affiliates_table = $this->get_affiliates_table();
		$commissions_table = $this->get_commissions_table();

		$query = $this->prepare(
			"SELECT a.*, SUM(c.commission_amount) as total_earnings, COUNT(c.id) as total_commissions
			FROM {$affiliates_table} a
			LEFT JOIN {$commissions_table} c ON a.id = c.affiliate_id AND c.status = 'paid' {$where_clause}
			WHERE a.status = 'active'
			GROUP BY a.id
			ORDER BY total_earnings DESC
			LIMIT %d",
			$limit
		);

		return $this->get_results( $query );
	}

	public function get_commission_summary( $start_date = null, $end_date = null ) {
		$where_clause = "WHERE 1=1";
		$params = array();

		if ( $start_date ) {
			$where_clause .= " AND date_created >= %s";
			$params[] = $start_date;
		}

		if ( $end_date ) {
			$where_clause .= " AND date_created <= %s";
			$params[] = $end_date;
		}

		$commissions_table = $this->get_commissions_table();

		$summary = array();

		$summary['total_commissions'] = $this->get_var( 
			$this->prepare( 
				"SELECT SUM(commission_amount) FROM {$commissions_table} {$where_clause}", 
				...$params 
			) 
		) ?: 0;

		$summary['paid_commissions'] = $this->get_var( 
			$this->prepare( 
				"SELECT SUM(commission_amount) FROM {$commissions_table} {$where_clause} AND status = 'paid'", 
				...$params 
			) 
		) ?: 0;

		$summary['pending_commissions'] = $this->get_var( 
			$this->prepare( 
				"SELECT SUM(commission_amount) FROM {$commissions_table} {$where_clause} AND status = 'pending'", 
				...$params 
			) 
		) ?: 0;

		$summary['rejected_commissions'] = $this->get_var( 
			$this->prepare( 
				"SELECT SUM(commission_amount) FROM {$commissions_table} {$where_clause} AND status = 'rejected'", 
				...$params 
			) 
		) ?: 0;

		$summary['commission_count'] = $this->get_var( 
			$this->prepare( 
				"SELECT COUNT(*) FROM {$commissions_table} {$where_clause}", 
				...$params 
			) 
		) ?: 0;

		return $summary;
	}

	public function cleanup_old_visits( $days = 90 ) {
		$visits_table = $this->get_visits_table();
		
		$query = $this->prepare(
			"DELETE FROM {$visits_table} WHERE date_created < DATE_SUB(NOW(), INTERVAL %d DAY) AND converted = 0",
			$days
		);

		return $this->query( $query );
	}

	public function optimize_tables() {
		$tables = array(
			$this->get_affiliates_table(),
			$this->get_commissions_table(),
			$this->get_links_table(),
			$this->get_visits_table(),
			$this->get_payments_table(),
			$this->get_creative_assets_table(),
			$this->get_email_logs_table()
		);

		foreach ( $tables as $table ) {
			$this->query( "OPTIMIZE TABLE {$table}" );
		}

		return true;
	}
}