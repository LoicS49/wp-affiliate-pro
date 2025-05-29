<?php
/**
 * Plugin Name:     WP Affiliate Pro
 * Description:     Extension d'affiliation complète pour WordPress.
 * Version:         1.0.0
 * Author:          Votre Nom
 * Text Domain:     wp-affiliate-pro
 * Domain Path:     /languages
 * Requires at least: 5.0
 * Requires PHP:    7.4
 * Network:         false
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// -----------------------------
// 1. Constantes du plugin
// -----------------------------
if ( ! defined( 'WPAP_VERSION' ) ) {
    define( 'WPAP_VERSION', '1.0.0' );
}
if ( ! defined( 'WPAP_PLUGIN_FILE' ) ) {
    define( 'WPAP_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'WPAP_PLUGIN_DIR' ) ) {
    define( 'WPAP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'WPAP_PLUGIN_URL' ) ) {
    define( 'WPAP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'WPAP_PLUGIN_BASENAME' ) ) {
    define( 'WPAP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

// -----------------------------
// 2. Chargement des traductions
// -----------------------------
add_action( 'init', 'wpap_load_textdomain' );
function wpap_load_textdomain() {
    load_plugin_textdomain(
        'wp-affiliate-pro',
        false,
        dirname( WPAP_PLUGIN_BASENAME ) . '/languages'
    );
}

// -----------------------------
// 3. Inclusion des classes cœur
// -----------------------------
$includes = [
    // classes principales
    'includes/class-wp-affiliate-pro.php',
    'includes/class-wpap-install.php',
    'includes/class-wpap-frontend.php',
    'includes/class-wpap-ajax.php',
    'includes/class-wpap-rest-api.php',
    // admin
    'includes/admin/class-wpap-admin.php',
    // emails
    'includes/emails/class-wpap-emails.php',
    // base métier
    'includes/class/class-wpap-database.php',
    'includes/class/class-wpap-affiliates.php',
    'includes/class/class-wpap-commissions.php',
    'includes/class/class-wpap-links.php',
    'includes/class/class-wpap-payments.php',
];

foreach ( $includes as $rel ) {
    $file = WPAP_PLUGIN_DIR . $rel;
    if ( file_exists( $file ) ) {
        require_once $file;
    } else {
        error_log( "WP Affiliate Pro : fichier manquant – {$rel}" );
    }
}

// -----------------------------
// 4. Instance unique du plugin
// -----------------------------
/**
 * Retourne l’instance unique de WP_Affiliate_Pro.
 *
 * @return WP_Affiliate_Pro
 */
function wpap() {
    return WP_Affiliate_Pro::instance();
}
$GLOBALS['wp_affiliate_pro'] = wpap();

// -----------------------------
// 5. Hooks d’activation / désactivation / désinstallation
// -----------------------------
register_activation_hook(   WPAP_PLUGIN_FILE, [ 'WPAP_Install',      'install'   ] );
register_deactivation_hook( WPAP_PLUGIN_FILE, [ 'WP_Affiliate_Pro',  'deactivate' ] );
register_uninstall_hook(    WPAP_PLUGIN_FILE, [ 'WPAP_Install',      'uninstall' ] );

// -----------------------------
// 6. Enregistrement des assets
// -----------------------------
add_action( 'admin_enqueue_scripts', function() {
    wp_enqueue_style(  'wpap-admin-css',    WPAP_PLUGIN_URL . 'assets/css/admin.css',    [], WPAP_VERSION );
    wp_enqueue_script( 'wpap-admin-js',     WPAP_PLUGIN_URL . 'assets/js/admin.js',      [ 'jquery' ], WPAP_VERSION, true );
});

add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style(  'wpap-frontend-css', WPAP_PLUGIN_URL . 'assets/css/frontend.css', [], WPAP_VERSION );
    wp_enqueue_script( 'wpap-frontend-js',  WPAP_PLUGIN_URL . 'assets/js/frontend.js',  [ 'jquery' ], WPAP_VERSION, true );
});
