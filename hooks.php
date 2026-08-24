<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ══════════════════════════════════════════════════════════════
   HOOK PUBBLICI — MSS Gestionale Core
   ══════════════════════════════════════════════════════════════

   Questo file documenta e inizializza tutti gli hook che i moduli
   possono usare. Non contiene logica applicativa.

   ══ AZIONI DISPONIBILI ══════════════════════════════════════

   mssg_load_modules
     Sparato dopo boot del core. I moduli inizializzano qui le loro classi.
     Nessun parametro.

   mssg_dashboard_widgets( $user )
     Ogni modulo aggiunge i propri widget KPI alla dashboard.
     Il modulo riceve il WP_User corrente.
     Output: HTML diretto (echo).

   mssg_dashboard_activity( $user )
     Log attività recente aggregato. Ogni modulo aggiunge le proprie righe.
     Output: HTML diretto (echo).

   mssg_module_installed( $slug, $version )
     Sparato dopo che un modulo si è registrato con successo.

   mssg_before_render_section( $section_key, $user )
   mssg_after_render_section( $section_key, $user )
     Prima/dopo il render di qualsiasi sezione.

   mssg_ajax_before( $action, $user_id )
     Sparato all'inizio di ogni AJAX handler del gestionale.

   mssg_ajax_error( $action, $msg, $user_id )
     Sparato quando un AJAX handler risponde con errore.

   ══ FILTRI DISPONIBILI ══════════════════════════════════════

   mssg_capabilities( array $matrix ) → array
     Estende la matrice permessi con capability del modulo.

   mssg_can_{$capability}( bool $result, int $user_id, mixed $context ) → bool
     Affina il check di una capability specifica (es. ownership).

   mssg_unknown_cap( bool $result, int $user_id, string $cap, mixed $ctx ) → bool
     Fallback per capability non registrata.

   mssg_dashboard_greeting( string $greeting, WP_User $user ) → string
     Personalizza il saluto in dashboard.

   mssg_nav_groups( array $groups ) → array
     Riordina o rinomina i gruppi della sidebar.

   mssg_ajax_nonce_action( string $action ) → string
     Permette ai moduli di riusare il nonce core o definirne uno proprio.

   ══ HOOK LOGIN-UI CHE I MODULI USANO DIRETTAMENTE ══════════

   add_filter( 'msslu_account_sections', function($sections) {...} )
     → Non usare: il core gestisce già l'aggancio tramite mssg_register_section()

   add_filter( 'msslu_section_html_{key}', function($html, $user) {...}, 10, 2 )
     → Gestito automaticamente da mssg_register_section() se si passa 'render'

   ══ ESEMPIO MODULO MINIMO ═══════════════════════════════════

   // Nel file principale del plugin modulo:

   add_action( 'mssg_load_modules', function() {

       mssg_register_module( 'mssg-cantieri', array(
           'version'     => '1.0.0',
           'name'        => 'Cantieri',
           'description' => 'Gestione cantieri, lavorazioni e documenti.',
           'path'        => plugin_dir_path(__FILE__),
           'url'         => plugin_dir_url(__FILE__),
           'icon'        => 'building',
           'priority'    => 10,
       ));

       mssg_register_section( 'mssg_cantieri', array(
           'module_slug'  => 'mssg-cantieri',
           'icon'         => 'building',
           'label'        => 'Cantieri',
           'group'        => 'gestionale',
           'priority'     => 10,
           'requires_cap' => 'view_cantieri',
           'render'       => 'mssg_cantieri_render_section',
       ));

       add_filter( 'mssg_capabilities', function($caps) {
           $caps['view_cantieri'] = array('administrator','mssg_admin','mssg_capo','mssg_operaio','mssg_cliente');
           $caps['edit_cantieri'] = array('administrator','mssg_admin','mssg_capo');
           return $caps;
       });

       add_action( 'mssg_dashboard_widgets', 'mssg_cantieri_dashboard_widget', 10, 1 );
   });

══════════════════════════════════════════════════════════════ */

/* ── AJAX dispatcher centrale ──────────────────────────────
   Ogni modulo registra i propri handler con:
   add_action( 'wp_ajax_mssg_{action}', function() { ... } );

   Il core fornisce l'helper mssg_ajax_check() per DRY.
────────────────────────────────────────────────────────── */

/**
 * Verifica nonce, autenticazione e capability prima di ogni AJAX.
 * Se fallisce, risponde con JSON error e fa exit.
 *
 * @param string      $capability  Capability richiesta (default: 'view_dashboard')
 * @param string|null $action      Azione AJAX per il log (opzionale)
 */
function mssg_ajax_check( $capability = 'view_dashboard', $action = null ) {
    $nonce = isset( $_POST['nonce'] ) ? $_POST['nonce'] : ( isset( $_GET['nonce'] ) ? $_GET['nonce'] : '' );

    if ( ! wp_verify_nonce( $nonce, 'mssg_nonce' ) ) {
        do_action( 'mssg_ajax_error', $action, 'Nonce non valido.', get_current_user_id() );
        wp_send_json_error( array( 'msg' => 'Sessione scaduta. Ricarica la pagina.' ) );
    }

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'msg' => 'Non autenticato.' ) );
    }

    $user_id = get_current_user_id();

    do_action( 'mssg_ajax_before', $action, $user_id );

    if ( ! mssg_user_can( $user_id, $capability ) ) {
        do_action( 'mssg_ajax_error', $action, 'Permesso negato.', $user_id );
        wp_send_json_error( array( 'msg' => 'Non sei autorizzato a compiere questa azione.' ) );
    }
}

/**
 * Sanitizza e valida i campi POST in un colpo solo.
 * Restituisce i valori puliti o wp_send_json_error() se manca un required.
 *
 * @param array $fields Array di: [ 'nome_campo' => [ 'type'=>'text|int|email|textarea', 'required'=>true ] ]
 * @return array        Valori sanificati
 */
function mssg_ajax_fields( array $fields ) {
    $out = array();
    foreach ( $fields as $name => $cfg ) {
        $raw      = isset( $_POST[ $name ] ) ? $_POST[ $name ] : '';
        $required = $cfg['required'] ?? false;
        $type     = $cfg['type']     ?? 'text';

        if ( $required && $raw === '' ) {
            wp_send_json_error( array( 'msg' => "Campo obbligatorio: {$name}" ) );
        }

        switch ( $type ) {
            case 'int':      $out[ $name ] = (int) $raw;                              break;
            case 'float':    $out[ $name ] = (float) str_replace( ',', '.', $raw );  break;
            case 'email':    $out[ $name ] = sanitize_email( $raw );                  break;
            case 'textarea': $out[ $name ] = sanitize_textarea_field( $raw );         break;
            case 'html':     $out[ $name ] = wp_kses_post( $raw );                    break;
            case 'slug':     $out[ $name ] = sanitize_key( $raw );                    break;
            case 'date':     $out[ $name ] = sanitize_text_field( $raw );             break;
            default:         $out[ $name ] = sanitize_text_field( $raw );
        }
    }
    return $out;
}
