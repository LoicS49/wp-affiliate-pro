<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPAP_Payments {

    /** @var WPAP_Database */
    private $db;

    /** @var string */
    private $table;

    /** @var array */
    private $gateways;

    public function __construct() {
        $this->gateways = array();
        $this->load_payment_gateways();
        add_action( 'wpap_process_scheduled_payments', array( $this, 'process_scheduled_payments' ) );
        add_action( 'wp_ajax_wpap_process_payment',    array( $this, 'ajax_process_payment' ) );
    }

    /**
     * Initialise l’objet base de données et la propriété $table
     *
     * @throws Exception Si WordPress ou la DB ne sont pas chargés
     */
    private function init_db() {
        if ( ! $this->db ) {
            if ( ! function_exists( 'wpap' ) || ! wpap() || ! wpap()->database ) {
                throw new Exception( __( 'WordPress et WP Affiliate Pro ne sont pas entièrement chargés.', 'wp-affiliate-pro' ) );
            }
            $this->db    = wpap()->database;
            if ( ! $this->db || ! $this->db->wpdb ) {
                throw new Exception( __( 'La connexion à la base de données n’est pas disponible.', 'wp-affiliate-pro' ) );
            }
            $this->table = $this->db->get_payments_table();
        }

        // Re-vérification
        if ( ! $this->db || ! $this->db->wpdb ) {
            throw new Exception( __( 'La connexion à la base de données a été perdue.', 'wp-affiliate-pro' ) );
        }
    }

    /**
     * Crée un paiement
     */
    public function create_payment( $args ) {
        $this->init_db();

        $defaults = array(
            'affiliate_id'   => 0,
            'amount'         => 0.00,
            'currency'       => $this->get_default_currency(),
            'method'         => 'paypal',
            'status'         => 'pending',
            'transaction_id' => '',
            'payment_date'   => null,
            'notes'          => '',
            'commission_ids' => array(),
            'date_created'   => current_time( 'mysql' ),
            'meta'           => array(),
        );

        $args = wp_parse_args( $args, $defaults );

        if ( empty( $args['affiliate_id'] ) ) {
            return new WP_Error( 'missing_affiliate_id', __( 'Affiliate ID is required.', 'wp-affiliate-pro' ) );
        }
        if ( $args['amount'] <= 0 ) {
            return new WP_Error( 'invalid_amount', __( 'Payment amount must be greater than zero.', 'wp-affiliate-pro' ) );
        }

        $affiliate = wpap()->affiliates->get( $args['affiliate_id'] );
        if ( ! $affiliate ) {
            return new WP_Error( 'invalid_affiliate', __( 'Invalid affiliate ID.', 'wp-affiliate-pro' ) );
        }
        if ( ! $this->meets_minimum_payout( $args['amount'] ) ) {
            return new WP_Error( 'below_minimum', __( 'Payment amount is below minimum payout threshold.', 'wp-affiliate-pro' ) );
        }

        if ( is_array( $args['commission_ids'] ) ) {
            $args['commission_ids'] = implode( ',', array_map( 'intval', $args['commission_ids'] ) );
        }
        if ( is_array( $args['meta'] ) ) {
            $args['meta'] = serialize( $args['meta'] );
        }

        $payment_id = $this->db->insert( $this->table, $args );
        if ( ! is_wp_error( $payment_id ) ) {
            do_action( 'wpap_payment_created', $payment_id, $args );
        }

        return $payment_id;
    }

    /**
     * Récupère un résumé des paiements (dashboard admin)
     *
     * @param array $args {
     *     @type string $start_date Date de début (YYYY-MM-DD)
     *     @type string $end_date   Date de fin   (YYYY-MM-DD)
     * }
     * @return array [
     *   'total_payments'   => float,
     *   'pending_payments' => float,
     *   'failed_payments'  => float,
     *   'payment_count'    => int,
     * ]
     */
    public function get_payment_summary( $args = array() ) {
        $this->init_db();

        $defaults = array(
            'start_date' => '',
            'end_date'   => '',
        );
        $args = wp_parse_args( $args, $defaults );

        $where_clauses = array( '1=1' );
        $where_values  = array();

        if ( $args['start_date'] ) {
            $where_clauses[] = 'date_created >= %s';
            $where_values[]  = $args['start_date'];
        }
        if ( $args['end_date'] ) {
            $where_clauses[] = 'date_created <= %s';
            $where_values[]  = $args['end_date'];
        }

        $where_clause = implode( ' AND ', $where_clauses );
        $base_query   = "SELECT SUM(amount) FROM {$this->table} WHERE {$where_clause}";
        $summary      = array();

        $summary['total_payments']   = (float) $this->db->get_var(
            $this->db->prepare( $base_query . " AND status = 'completed'", ...$where_values )
        ) ?: 0.0;
        $summary['pending_payments'] = (float) $this->db->get_var(
            $this->db->prepare( $base_query . " AND status = 'pending'", ...$where_values )
        ) ?: 0.0;
        $summary['failed_payments']  = (float) $this->db->get_var(
            $this->db->prepare( $base_query . " AND status = 'failed'", ...$where_values )
        ) ?: 0.0;
        $summary['payment_count']    = (int) $this->db->get_var(
            $this->db->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE {$where_clause}", ...$where_values )
        ) ?: 0;

        return $summary;
    }

    /**
     * Récupère un paiement par ID
     */
    public function get_payment( $payment_id ) {
        $this->init_db();
        $query   = $this->db->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $payment_id );
        $payment = $this->db->get_row( $query );
        if ( $payment && ! empty( $payment->meta ) ) {
            $payment->meta = maybe_unserialize( $payment->meta );
        }
        if ( $payment && ! empty( $payment->commission_ids ) ) {
            $payment->commission_ids = explode( ',', $payment->commission_ids );
        }
        return $payment;
    }

    /**
     * Met à jour un paiement
     */
    public function update_payment( $payment_id, $args ) {
        $this->init_db();
        if ( isset( $args['commission_ids'] ) && is_array( $args['commission_ids'] ) ) {
            $args['commission_ids'] = implode( ',', array_map( 'intval', $args['commission_ids'] ) );
        }
        if ( isset( $args['meta'] ) && is_array( $args['meta'] ) ) {
            $args['meta'] = serialize( $args['meta'] );
        }
        $result = $this->db->update( $this->table, $args, array( 'id' => $payment_id ), null, array( '%d' ) );
        if ( false !== $result ) {
            do_action( 'wpap_payment_updated', $payment_id, $args );
        }
        return $result;
    }

    /**
     * Supprime un paiement
     */
    public function delete_payment( $payment_id ) {
        $this->init_db();
        $payment = $this->get_payment( $payment_id );
        if ( ! $payment ) {
            return false;
        }
        if ( 'completed' === $payment->status ) {
            return new WP_Error( 'cannot_delete_completed', __( 'Cannot delete completed payment.', 'wp-affiliate-pro' ) );
        }
        $this->unmark_commissions_as_paid( $payment->commission_ids );
        $result = $this->db->delete( $this->table, array( 'id' => $payment_id ), array( '%d' ) );
        if ( $result ) {
            do_action( 'wpap_payment_deleted', $payment_id, $payment );
        }
        return $result;
    }

    /**
     * Liste des paiements (pagination, filtres)
     */
    public function get_payments( $args = array() ) {
        $this->init_db();

        $defaults = array(
            'affiliate_id'         => 0,
            'status'               => '',
            'method'               => '',
            'orderby'              => 'date_created',
            'order'                => 'DESC',
            'limit'                => 20,
            'offset'               => 0,
            'start_date'           => '',
            'end_date'             => '',
            'payment_date_before'  => '',
            'payment_date_after'   => '',
        );
        $args = wp_parse_args( $args, $defaults );

        $where_clauses = array( '1=1' );
        $where_values  = array();

        if ( $args['affiliate_id'] ) {
            $where_clauses[] = 'affiliate_id = %d';
            $where_values[]  = $args['affiliate_id'];
        }
        if ( $args['status'] ) {
            $where_clauses[] = 'status = %s';
            $where_values[]  = $args['status'];
        }
        if ( $args['method'] ) {
            $where_clauses[] = 'method = %s';
            $where_values[]  = $args['method'];
        }
        if ( $args['start_date'] ) {
            $where_clauses[] = 'date_created >= %s';
            $where_values[]  = $args['start_date'];
        }
        if ( $args['end_date'] ) {
            $where_clauses[] = 'date_created <= %s';
            $where_values[]  = $args['end_date'];
        }
        if ( $args['payment_date_before'] ) {
            $where_clauses[] = 'payment_date <= %s';
            $where_values[]  = $args['payment_date_before'];
        }
        if ( $args['payment_date_after'] ) {
            $where_clauses[] = 'payment_date >= %s';
            $where_values[]  = $args['payment_date_after'];
        }

        $where_clause = implode( ' AND ', $where_clauses );
        $order_clause = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
        $query        = "SELECT * FROM {$this->table} WHERE {$where_clause}";
        if ( $order_clause ) {
            $query .= " ORDER BY {$order_clause}";
        }
        if ( $args['limit'] > 0 ) {
            $query .= $this->db->prepare( " LIMIT %d OFFSET %d", $args['limit'], $args['offset'] );
        }
        if ( $where_values ) {
            $query = $this->db->prepare( $query, ...$where_values );
        }

        $payments = $this->db->get_results( $query );
        foreach ( $payments as $payment ) {
            if ( ! empty( $payment->meta ) ) {
                $payment->meta = maybe_unserialize( $payment->meta );
            }
            if ( ! empty( $payment->commission_ids ) ) {
                $payment->commission_ids = explode( ',', $payment->commission_ids );
            }
        }
        return $payments;
    }

    /**
     * Génération des données de facture
     */
    public function generate_invoice( $payment_id ) {
        $this->init_db();
        $payment = $this->get_payment( $payment_id );
        if ( ! $payment ) {
            return false;
        }
        $affiliate    = wpap()->affiliates->get( $payment->affiliate_id );
        $commissions = array();
        if ( $payment->commission_ids ) {
            foreach ( $payment->commission_ids as $commission_id ) {
                $c = wpap()->commissions->get( $commission_id );
                if ( $c ) {
                    $commissions[] = $c;
                }
            }
        }
        $invoice_data = array(
            'payment'        => $payment,
            'affiliate'      => $affiliate,
            'commissions'    => $commissions,
            'generated_date' => current_time( 'mysql' ),
        );
        return apply_filters( 'wpap_invoice_data', $invoice_data, $payment_id );
    }

    /**
     * AJAX : traitement du paiement
     */
    public function ajax_process_payment() {
        check_ajax_referer( 'wpap_process_payment', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to process payments.', 'wp-affiliate-pro' ) );
        }
        $payment_id = intval( $_POST['payment_id'] );
        $gateway    = sanitize_text_field( $_POST['gateway'] ?? '' );
        $result     = $this->process_payment( $payment_id, $gateway );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        wp_send_json_success( array(
            'message' => __( 'Payment processed successfully.', 'wp-affiliate-pro' ),
            'data'    => $result,
        ) );
    }

    /**
     * Charge les passerelles de paiement
     */
    private function load_payment_gateways() {
        $gateway_files = array(
            'paypal'       => WPAP_PLUGIN_DIR . 'includes/gateways/class-wpap-gateway-paypal.php',
            'stripe'       => WPAP_PLUGIN_DIR . 'includes/gateways/class-wpap-gateway-stripe.php',
            'bank_transfer'=> WPAP_PLUGIN_DIR . 'includes/gateways/class-wpap-gateway-bank-transfer.php',
        );
        foreach ( $gateway_files as $key => $file ) {
            if ( file_exists( $file ) ) {
                include_once $file;
                $class = 'WPAP_Gateway_' . ucfirst( str_replace( '_', '', $key ) );
                if ( class_exists( $class ) ) {
                    $this->gateways[ $key ] = new $class();
                }
            }
        }
    }

    /**
     * Traite un paiement via la passerelle
     */
    public function process_payment( $payment_id, $gateway = null ) {
        $this->init_db();
        $payment = $this->get_payment( $payment_id );
        if ( ! $payment ) {
            return new WP_Error( 'invalid_payment', __( 'Invalid payment ID.', 'wp-affiliate-pro' ) );
        }
        if ( 'pending' !== $payment->status ) {
            return new WP_Error( 'payment_not_pending', __( 'Payment is not in pending status.', 'wp-affiliate-pro' ) );
        }
        $gateway = $gateway ?: $payment->method;
        if ( ! isset( $this->gateways[ $gateway ] ) ) {
            return new WP_Error( 'invalid_gateway', __( 'Invalid payment gateway.', 'wp-affiliate-pro' ) );
        }
        $affiliate = wpap()->affiliates->get( $payment->affiliate_id );
        if ( ! $affiliate ) {
            return new WP_Error( 'invalid_affiliate', __( 'Invalid affiliate.', 'wp-affiliate-pro' ) );
        }
        $result = $this->gateways[ $gateway ]->process_payment( $payment, $affiliate );
        if ( is_wp_error( $result ) ) {
            $this->update_payment( $payment_id, array( 'status' => 'failed', 'notes' => $result->get_error_message() ) );
            do_action( 'wpap_payment_failed', $payment_id, $result );
            return $result;
        }
        $this->update_payment( $payment_id, array(
            'status'         => 'completed',
            'payment_date'   => current_time( 'mysql' ),
            'transaction_id' => $result['transaction_id'] ?? '',
            'notes'          => $result['notes'] ?? '',
        ) );
        do_action( 'wpap_payment_completed', $payment_id, $result );
        return $result;
    }

    /**
     * Traitement en masse
     */
    public function bulk_process_payments( $payment_ids, $gateway = null ) {
        $this->init_db();
        $results    = array();
        $successful = 0;
        $failed     = 0;
        foreach ( $payment_ids as $pid ) {
            $res = $this->process_payment( $pid, $gateway );
            if ( is_wp_error( $res ) ) {
                $failed++;
                $results[ $pid ] = array( 'success' => false, 'error' => $res->get_error_message() );
            } else {
                $successful++;
                $results[ $pid ] = array( 'success' => true,  'data'  => $res );
            }
        }
        do_action( 'wpap_bulk_payments_processed', $results, $successful, $failed );
        return array( 'successful' => $successful, 'failed' => $failed, 'results' => $results );
    }

    /**
     * Planifie un paiement
     */
    public function schedule_payment( $affiliate_id, $amount, $args = array() ) {
        $this->init_db();
        $args['affiliate_id'] = $affiliate_id;
        $args['amount']       = $amount;
        $args['status']       = 'scheduled';
        if ( ! isset( $args['payment_date'] ) ) {
            $args['payment_date'] = $this->get_next_payment_date();
        }
        return $this->create_payment( $args );
    }

    /**
     * Cron : paiements planifiés
     */
    public function process_scheduled_payments() {
        $this->init_db();
        $scheduled = $this->get_payments( array(
            'status'               => 'scheduled',
            'payment_date_before'  => current_time( 'mysql' ),
            'limit'                => 50,
        ) );
        foreach ( $scheduled as $p ) {
            $this->process_payment( $p->id );
        }
    }

    /**
     * Paiements groupés (mass payouts)
     */
    public function create_mass_payment( $affiliate_ids, $args = array() ) {
        $this->init_db();
        $defaults = array(
            'method'           => 'paypal',
            'minimum_amount'   => $this->get_minimum_payout(),
            'include_pending'  => true,
            'include_approved' => true,
        );
        $args = wp_parse_args( $args, $defaults );
        $created = array();
        foreach ( $affiliate_ids as $aid ) {
            $unpaid = $this->get_unpaid_commissions( $aid, $args );
            if ( empty( $unpaid ) ) {
                continue;
            }
            $total = array_sum( wp_list_pluck( $unpaid, 'commission_amount' ) );
            if ( $total < $args['minimum_amount'] ) {
                continue;
            }
            $arg          = $args;
            $arg['commission_ids'] = wp_list_pluck( $unpaid, 'id' );
            $pid          = $this->create_payment( $arg );
            if ( ! is_wp_error( $pid ) ) {
                $created[] = $pid;
            }
        }
        return $created;
    }

}
