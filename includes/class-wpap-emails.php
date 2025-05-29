<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPAP_Emails {
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
        add_filter( 'wpap_email_templates', array( $this, 'register_email_templates' ) );
        add_action( 'wpap_send_email',      array( $this, 'send_email' ), 10, 3 );
    }

    /**
     * Déclare les templates d’email
     *
     * @param array $templates
     * @return array
     */
    public function register_email_templates( $templates ) {
        $templates['payment_completed'] = array(
            'subject' => __( 'Paiement effectué', 'wp-affiliate-pro' ),
            'body'    => 'Bonjour {affiliate_name}, votre paiement de {amount} a été effectué.',
        );
        // TODO : Ajouter d'autres templates (nouvel affilié, commission, etc.)
        return $templates;
    }

    /**
     * Envoie un email via wp_mail
     *
     * @param string $template_key
     * @param int    $user_id
     * @param array  $data
     */
    public function send_email( $template_key, $user_id, $data = array() ) {
        $templates = apply_filters( 'wpap_email_templates', array() );
        if ( empty( $templates[ $template_key ] ) ) {
            return false;
        }
        $template = $templates[ $template_key ];
        $subject  = $this->prepare_subject( $template['subject'], $data );
        $message  = $this->prepare_body(    $template['body'],    $data );
        $to       = get_userdata( $user_id )->user_email;
        wp_mail( $to, $subject, $message );
    }

    /**
     * Remplace les placeholders dans le sujet
     */
    private function prepare_subject( $subject, $data ) {
        foreach ( $data as $key => $value ) {
            $subject = str_replace( '{' . $key . '}', $value, $subject );
        }
        return $subject;
    }

    /**
     * Remplace les placeholders dans le corps et formate
     */
    private function prepare_body( $body, $data ) {
        foreach ( $data as $key => $value ) {
            $body = str_replace( '{' . $key . '}', $value, $body );
        }
        return wpautop( $body );
    }
}