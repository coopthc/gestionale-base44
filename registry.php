<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ══════════════════════════════════════════════════════════════
   REGISTRY MODULI GESTIONALE
   Ogni plugin modulo si registra qui tramite mssg_register_module().
   Il core non sa nulla dei moduli: li scopre solo tramite questo registry.
══════════════════════════════════════════════════════════════ */

// Contenitore in-memory per questa request
global $mssg_modules;
$mssg_modules = array();

// Sezioni sidebar registrate dai moduli (ordinate per priority)
global $mssg_sections;
$mssg_sections = array();

/* ── API pubblica per i moduli ─────────────────────────── */

/**
 * Un modulo chiama questa funzione nel proprio boot per registrarsi.
 *
 * @param string $slug    Slug univoco, es. 'mssg-cantieri'
 * @param array  $args {
 *   @type string   version
 *   @type string   name          Nome leggibile
 *   @type string   description
 *   @type string   path          __DIR__ del modulo
 *   @type string   url           plugin_dir_url(__FILE__) del modulo
 *   @type string   icon          Nome icona msslu_icon() — es. 'grid'
 *   @type int      priority      Ordine nella sidebar (default 10)
 *   @type array    requires_caps Capability necessarie per vedere il modulo
 * }
 */
function mssg_register_module( $slug, array $args ) {
    global $mssg_modules;

    $defaults = array(
        'version'       => '1.0.0',
        'name'          => $slug,
        'description'   => '',
        'path'          => '',
        'url'           => '',
        'icon'          => 'grid',
        'priority'      => 10,
        'requires_caps' => array(),
    );

    $mssg_modules[ $slug ] = wp_parse_args( $args, $defaults );
}

/**
 * Un modulo registra una o più sezioni nella sidebar del gestionale.
 *
 * @param string $section_key  Chiave univoca, es. 'mssg_cantieri'
 * @param array  $args {
 *   @type string   module_slug   Slug del modulo proprietario
 *   @type string   icon          Nome icona msslu_icon()
 *   @type string   label         Etichetta sidebar
 *   @type string   group         Gruppo visivo: 'gestionale'|'report'|'impostazioni'
 *   @type int      priority      Ordine (default 10)
 *   @type string   requires_cap  Capability richiesta per vedere la voce
 *   @type callable render        Callable che produce l'HTML della sezione
 * }
 */
function mssg_register_section( $section_key, array $args ) {
    global $mssg_sections;

    $defaults = array(
        'module_slug'  => '',
        'icon'         => 'grid',
        'label'        => $section_key,
        'group'        => 'gestionale',
        'priority'     => 10,
        'requires_cap' => 'view_dashboard',
        'render'       => null,
    );

    $mssg_sections[ $section_key ] = wp_parse_args( $args, $defaults );

    // Agganciamento automatico ai hook di login-ui
    if ( is_callable( $args['render'] ?? null ) ) {
        add_filter( "msslu_section_html_{$section_key}", function( $html, $user ) use ( $args ) {
            ob_start();
            call_user_func( $args['render'], $user );
            return ob_get_clean();
        }, 10, 2 );
    }
}

/* ── Lettura registry ──────────────────────────────────── */

function mssg_get_modules() {
    global $mssg_modules;
    return $mssg_modules;
}

function mssg_get_active_module_slugs() {
    return array_keys( mssg_get_modules() );
}

/**
 * Restituisce le sezioni visibili per un utente, ordinate per priority.
 * Chiamato da mssg_register_shell_sections() per costruire la sidebar.
 */
function mssg_get_registered_sections( $user_id ) {
    global $mssg_sections;

    $visible = array();

    // Ordina per priority
    $sorted = $mssg_sections;
    uasort( $sorted, function( $a, $b ) { return $a['priority'] - $b['priority']; } );

    foreach ( $sorted as $key => $cfg ) {
        if ( ! mssg_user_can( $user_id, $cfg['requires_cap'] ) ) continue;
        $visible[ $key ] = array(
            'icon'  => $cfg['icon'],
            'label' => $cfg['label'],
            'group' => $cfg['group'],
        );
    }

    return $visible;
}

/**
 * Raggruppa le sezioni per group (usato da template sidebar avanzata).
 */
function mssg_get_sections_by_group( $user_id ) {
    $sections = mssg_get_registered_sections( $user_id );
    $groups   = array();

    foreach ( $sections as $key => $cfg ) {
        $groups[ $cfg['group'] ][ $key ] = $cfg;
    }

    return $groups;
}
