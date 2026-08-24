<?php
/**
 * Plugin Name:       MSS Gestionale — Personale
 * Description:       Gestione collaboratori, qualifiche, costi orari e abbinamento ai cantieri/lavorazioni. Richiede mss-gestionale ≥ 1.0.0 e mssg-database ≥ 1.0.0.
 * Version:           1.0.0
 * Author:            Web.CoopTHC
 * License:           Proprietary
 * Text Domain:       mssg-personale
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MSSGP_VERSION', '1.0.0' );
define( 'MSSGP_PATH',    plugin_dir_path( __FILE__ ) );
define( 'MSSGP_URL',     plugin_dir_url( __FILE__ ) );

require_once MSSGP_PATH . 'includes/db.php';
require_once MSSGP_PATH . 'includes/capabilities.php';
require_once MSSGP_PATH . 'includes/section-lista.php';
require_once MSSGP_PATH . 'includes/section-profilo.php';
require_once MSSGP_PATH . 'includes/section-presenze.php';
require_once MSSGP_PATH . 'includes/ajax.php';
require_once MSSGP_PATH . 'includes/dashboard-widget.php';

/* ══ Boot ══════════════════════════════════════════════════ */
add_action( 'mssg_load_modules', 'mssgp_boot' );

function mssgp_boot() {
    if ( ! function_exists( 'mssg_register_module' ) ) return;

    mssg_register_module( 'mssg-personale', array(
        'version'     => MSSGP_VERSION,
        'name'        => 'Personale',
        'description' => 'Anagrafica collaboratori, qualifiche, costi e presenze su cantieri.',
        'path'        => MSSGP_PATH,
        'url'         => MSSGP_URL,
        'icon'        => 'users',
        'priority'    => 20,
    ));

    add_filter( 'mssg_capabilities', 'mssgp_register_capabilities' );

    // Sezione lista collaboratori (admin/capo)
    // Rinominata da "Personale" a "Collaboratori": più chiaro per lo staff
    // esterno (operai/capi cantiere spesso non dipendenti diretti).
    mssg_register_section( 'mssg_personale', array(
        'module_slug'  => 'mssg-personale',
        'icon'         => 'users',
        'label'        => 'Collaboratori',
        'group'        => 'gestionale',
        'priority'     => 20,
        'requires_cap' => 'view_personale',
        'render'       => 'mssgp_render_lista',
    ));

    // Sezione presenze (visibile anche all'operaio per le proprie)
    mssg_register_section( 'mssg_presenze', array(
        'module_slug'  => 'mssg-personale',
        'icon'         => 'calendar',
        'label'        => 'Presenze',
        'group'        => 'gestionale',
        'priority'     => 25,
        'requires_cap' => 'view_presenze',
        'render'       => 'mssgp_render_presenze',
    ));

    add_action( 'mssg_dashboard_widgets', 'mssgp_dashboard_widget', 20, 1 );

    add_action( 'wp_enqueue_scripts', 'mssgp_enqueue_assets' );
    add_action( 'mssg_admin_submenu', 'mssgp_admin_submenu' );

    // Quando si crea un utente WP con ruolo gestionale,
    // crea automaticamente il profilo personale
    add_action( 'user_register',   'mssgp_auto_create_profilo', 10, 1 );
    add_action( 'set_user_role',   'mssgp_on_role_change', 10, 3 );
}

/* ══ Assets ════════════════════════════════════════════════ */
function mssgp_enqueue_assets() {
    if ( ! is_user_logged_in() ) return;
    if ( ! mssg_user_can( get_current_user_id(), 'view_presenze' ) ) return;

    wp_enqueue_style(
        'mssg-personale',
        MSSGP_URL . 'assets/css/personale.css',
        array( 'mss-gestionale' ),
        MSSGP_VERSION
    );
    wp_enqueue_script(
        'mssg-personale',
        MSSGP_URL . 'assets/js/personale.js',
        array( 'mss-gestionale' ),
        MSSGP_VERSION,
        true
    );
}

/* ══ Auto-crea profilo personale per nuovi utenti ════════ */
function mssgp_auto_create_profilo( $user_id ) {
    $user = get_userdata( $user_id );
    if ( ! $user ) return;
    // Solo ruoli gestionale (tranne cliente)
    $ruoli_personale = array( 'mssg_admin', 'mssg_capo', 'mssg_operaio' );
    foreach ( $ruoli_personale as $r ) {
        if ( in_array( $r, (array) $user->roles ) ) {
            mssgp_ensure_profilo( $user_id );
            break;
        }
    }
}

function mssgp_on_role_change( $user_id, $role, $old_roles ) {
    $ruoli_personale = array( 'mssg_admin', 'mssg_capo', 'mssg_operaio' );
    if ( in_array( $role, $ruoli_personale ) ) {
        mssgp_ensure_profilo( $user_id );
    }
}

/* ══ Admin ══════════════════════════════════════════════════ */
function mssgp_admin_submenu() {
    add_submenu_page(
        'mss-gestionale',
        'Personale',
        'Personale',
        'manage_options',
        'mssg-personale-admin',
        'mssgp_admin_page'
    );
}

function mssgp_admin_page() {
    echo '<div class="wrap"><h1>Personale — Vista Admin</h1>';
    $tutti = mssgp_get_tutti_collaboratori();
    echo '<p>' . count( $tutti ) . ' collaboratori registrati.</p>';
    echo '</div>';
}

/* ══ Attivazione ════════════════════════════════════════════ */
register_activation_hook( __FILE__, function() {
    // Le tabelle sono già create da mssg-database
    // Crea profili per utenti esistenti con ruolo gestionale
    $users = get_users( array( 'role__in' => array( 'mssg_admin', 'mssg_capo', 'mssg_operaio' ) ) );
    foreach ( $users as $u ) {
        mssgp_ensure_profilo( $u->ID );
    }
});
