<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPAP_Payments {
    /**
     * Instance unique (singleton)
     */
    private static $instance = null;

    /**
     * Récupère l'instance unique
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
            self::$instance->init_hooks();
        }
        return self::$instance;
    }

    /**
     * Initialise les hooks
     */
    public function init_hooks() {
        add_action( 'wpap_process_payment', array( $this, 'process_payment' ), 10, 2 );
        add_action( 'wpap_register_payment', array( $this, 'record_payment' ), 10, 1 );
        add_filter( 'wpap_payment_statuses', array( $this, 'register_payment_statuses' ) );
    }

    /**
     * Traite le paiement (API externe, passerelle…)
     *
     * @param int   $affiliate_id
     * @param float $amount
     */
    public function process_payment( $affiliate_id, $amount ) {
        // TODO : Intégrer la passerelle de paiement
    }

    /**
     * Enregistre le paiement en base de données
     *
     * @param array $payment_data
     */
    public function record_payment( $payment_data ) {
        // TODO : Insert dans la table wpap_payments via WPAP_Database
    }

    /**
     * Ajoute des statuts personnalisés
     *
     * @param array $statuses
     * @return array
     */
    public function register_payment_statuses( $statuses ) {
        $statuses['pending']   = __( 'En attente',   'wp-affiliate-pro' );
        $statuses['completed'] = __( 'Terminé',     'wp-affiliate-pro' );
        $statuses['failed']    = __( 'Échoué',      'wp-affiliate-pro' );
        return $statuses;
    }

    /**
     * Récupère un résumé des paiements pour le dashboard admin.
     *
     * @return array [
     *   'total_paid'    => float,
     *   'count_paid'    => int,
     *   'total_pending' => float,
     *   'count_pending' => int,
     * ]
     */
    public function get_payment_summary() {
        global $wpdb;
        $table = WPAP_Database::instance()->get_table_name( 'payments' );

        // Paiements complétés
        $paid = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(amount) FROM {$table} WHERE status = %s",
            'completed'
        ) );
        $count_paid = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE status = %s",
            'completed'
        ) );

        // Paiements en attente
        $pending = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(amount) FROM {$table} WHERE status = %s",
            'pending'
        ) );
        $count_pending = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE status = %s",
            'pending'
        ) );

        return [
            'total_paid'    => $paid,
            'count_paid'    => $count_paid,
            'total_pending' => $pending,
            'count_pending' => $count_pending,
        ];
    }
}