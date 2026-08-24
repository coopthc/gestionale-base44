<?php
/**
 * Plugin Name:       MSS Gestionale — Agenda & Booking
 * Version:           1.0.0
 * Description:       Agenda del titolare + prenotazione slot da parte dei clienti. Il cliente vede libero/occupato senza dettagli. Richiede mss-gestionale ≥ 1.0.0.
 * Author:            Web.CoopTHC
 * License:           Proprietary
 * Text Domain:       mssg-agenda
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MSSGAG_VERSION', '1.0.0' );
define( 'MSSGAG_PATH',    plugin_dir_path( __FILE__ ) );
define( 'MSSGAG_URL',     plugin_dir_url( __FILE__ ) );

require_once MSSGAG_PATH . 'includes/db.php';
require_once MSSGAG_PATH . 'includes/section-admin-agenda.php';
require_once MSSGAG_PATH . 'includes/section-cliente-booking.php';

add_action( 'mssg_load_modules', 'mssgag_boot' );

function mssgag_boot() {
    if ( ! function_exists( 'mssg_register_module' ) ) return;

    mssg_register_module( 'mssg-agenda', array(
        'version'     => MSSGAG_VERSION,
        'name'        => 'Agenda',
        'description' => 'Agenda titolare + prenotazione slot clienti.',
        'path'        => MSSGAG_PATH,
        'url'         => MSSGAG_URL,
        'icon'        => 'calendar',
        'priority'    => 15,
    ));

    add_filter( 'mssg_capabilities', 'mssgag_register_capabilities' );

    /* Registra le tabelle sul registry centrale del core (mssg_register_table),
       così risultano visibili a backup/debug centralizzati come gli altri moduli.
       mssgag_ensure_tables() (sotto, su plugins_loaded) resta la fonte reale di
       creazione/aggiornamento schema — questa registrazione è solo di visibilità. */
    if ( function_exists( 'mssg_register_table' ) ) {
        mssg_register_table( 'agenda_orari',   'mssg-agenda', 'mssgag_db_create_orari' );
        mssg_register_table( 'agenda_blocchi', 'mssg-agenda', 'mssgag_db_create_blocchi' );
    }

    /* ── Sezione agenda per admin / capo ── */
    mssg_register_section( 'mssg_agenda_admin', array(
        'module_slug'  => 'mssg-agenda',
        'icon'         => 'calendar',
        'label'        => 'Agenda',
        'group'        => 'gestionale',
        'priority'     => 12,
        'requires_cap' => 'manage_agenda',
        'render'       => 'mssgag_render_admin_agenda',
    ));

    /* ── Sezione booking per cliente ── */
    /* Proponi appuntamento rimosso - funzionalità integrata in Agenda */

    add_action( 'wp_enqueue_scripts', 'mssgag_enqueue_assets' );
    mssgag_ensure_tables();
}

function mssgag_register_capabilities( $caps ) {
    $caps['manage_agenda']  = array( 'administrator', 'mssg_admin', 'mssg_capo' );
    $caps['book_appointment'] = array( 'mssg_cliente' );
    return $caps;
}

function mssgag_enqueue_assets() {
    if ( ! is_user_logged_in() ) return;
    $uid = get_current_user_id();
    if ( ! mssg_user_can( $uid, 'manage_agenda' ) && ! mssg_user_can( $uid, 'book_appointment' ) ) return;
    wp_enqueue_style(  'mssg-agenda', MSSGAG_URL . 'assets/css/agenda.css',  array( 'mss-gestionale' ), MSSGAG_VERSION );
    wp_enqueue_script( 'mssg-agenda', MSSGAG_URL . 'assets/js/agenda.js', array( 'mss-gestionale', 'jquery' ), MSSGAG_VERSION, true );

    /* wp_add_inline_script invece di wp_localize_script: il JSON viene stampato
       nello stesso <script> del file (subito prima), quindi resta legato al file
       anche se un plugin di cache/minify (Autoptimize, WP Rocket "combine JS", ecc.)
       concatena o sposta gli script — a differenza di wp_localize_script, che stampa
       un <script> separato e può finire scollegato dal file durante la combinazione. */
    wp_add_inline_script(
        'mssg-agenda',
        'var MSSGAG = ' . wp_json_encode( array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'mssg_nonce' ),
        ) ) . ';',
        'before'
    );
}

register_activation_hook( __FILE__, function() { mssgag_ensure_tables(); });
