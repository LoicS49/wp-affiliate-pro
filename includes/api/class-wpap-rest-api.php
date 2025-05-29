<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAP_REST_API {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( 'wpap/v1', '/stats', array(
			'methods' => 'GET',
			'callback' => array( $this, 'get_stats' ),
			'permission_callback' => array( $this, 'check_permissions' )
		) );

		register_rest_route( 'wpap/v1', '/affiliates', array(
			'methods' => 'GET',
			'callback' => array( $this, 'get_affiliates' ),
			'permission_callback' => array( $this, 'check_permissions' )
		) );

		register_rest_route( 'wpap/v1', '/commissions', array(
			'methods' => 'GET',
			'callback' => array( $this, 'get_commissions' ),
			'permission_callback' => array( $this, 'check_permissions' )
		) );
	}

	public function check_permissions( $request ) {
		return current_user_can( 'manage_options' );
	}

	public function get_stats( $request ) {
		$stats = array(
			'total_affiliates' => wpap()->affiliates->count(),
			'active_affiliates' => wpap()->affiliates->count( array( 'status' => 'active' ) ),
			'total_commissions' => wpap()->database->get_commission_summary(),
			'total_payments' => wpap()->payments->get_payment_summary()
		);

		return rest_ensure_response( $stats );
	}

	public function get_affiliates( $request ) {
		$page = $request->get_param( 'page' ) ?: 1;
		$per_page = $request->get_param( 'per_page' ) ?: 20;
		$status = $request->get_param( 'status' ) ?: '';

		$args = array(
			'limit' => $per_page,
			'offset' => ( $page - 1 ) * $per_page,
			'status' => $status
		);

		$affiliates = wpap()->affiliates->get_all( $args );
		$total = wpap()->affiliates->count( $args );

		$response = rest_ensure_response( $affiliates );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', ceil( $total / $per_page ) );

		return $response;
	}

	public function get_commissions( $request ) {
		$page = $request->get_param( 'page' ) ?: 1;
		$per_page = $request->get_param( 'per_page' ) ?: 20;
		$affiliate_id = $request->get_param( 'affiliate_id' ) ?: 0;
		$status = $request->get_param( 'status' ) ?: '';

		$args = array(
			'limit' => $per_page,
			'offset' => ( $page - 1 ) * $per_page,
			'affiliate_id' => $affiliate_id,
			'status' => $status
		);

		$commissions = wpap()->commissions->get_all( $args );
		$total = wpap()->commissions->count( $args );

		$response = rest_ensure_response( $commissions );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', ceil( $total / $per_page ) );

		return $response;
	}
}