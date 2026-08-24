<?php
/**
 * Plugin Name:       MSS Gestionale — Core
 * Plugin URI:        https://web.coopthc.org
 * Description:       Framework core per il gestionale MSS. Fornisce registry moduli, ruoli utente, hook e UI shell. Richiede mss-login-ui ≥ 1.5.0.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Web.CoopTHC
 * Author URI:        https://web.coopthc.org
 * License:           Proprietary
 * Text Domain:       mss-gestionale
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ══════════════════════════════════════════════════════════════
   COSTANTI
══════════════════════════════════════════════════════════════ */
define( 'MSSG_VERSION',  '1.0.0' );
define( 'MSSG_PATH',     plugin_dir_path( __FILE__ ) );
define( 'MSSG_URL',      plugin_dir_url( __FILE__ ) );
define( 'MSSG_SLUG',     'mss-gestionale' );
define( 'MSSG_MIN_LOGINUI', '1.5.0' );

/* ══════════════════════════════════════════════════════════════
   CARICA SOTTOMODULI CORE
══════════════════════════════════════════════════════════════ */
require_once MSSG_PATH . 'core/roles.php';       // Ruoli WP custom
require_once MSSG_PATH . 'core/registry.php';    // Registry moduli gestionale
require_once MSSG_PATH . 'core/hooks.php';       // Hook pubblici per i moduli
require_once MSSG_PATH . 'core/db.php';          // Helper DB condivisi
require_once MSSG_PATH . 'core/capabilities.php'; // Matrice permessi
require_once MSSG_PATH . 'admin/settings.php';   // Pagina impostazioni WP admin

/* ══════════════════════════════════════════════════════════════
   BOOT — si aggancia a login-ui dopo il caricamento dei plugin
══════════════════════════════════════════════════════════════ */
add_action( 'plugins_loaded', 'mssg_boot', 30 );

function mssg_boot() {

    // Verifica dipendenza mss-login-ui
    if ( ! function_exists( 'msslu_get_option' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>MSS Gestionale</strong> richiede il plugin <strong>MSS Login UI ≥ ' . MSSG_MIN_LOGINUI . '</strong> attivo.';
            echo '</p></div>';
        });
        return;
    }

    // Registra questo core nella suite MSS (se presente)
    if ( function_exists( 'mss_register_module' ) ) {
        mss_register_module( MSSG_SLUG, array(
            'version' => MSSG_VERSION,
            'name'    => 'MSS Gestionale Core',
            'path'    => MSSG_PATH,
            'url'     => MSSG_URL,
        ));
    }

    // Aggancia la shell dell'area privata a login-ui
    mssg_register_shell_sections();

    // Carica i moduli gestionale attivi (plugin separati già inclusi da WP)
    do_action( 'mssg_load_modules' );
}

/* ══════════════════════════════════════════════════════════════
   SHELL — aggiunge sezioni base alla sidebar di login-ui
   I moduli poi aggiungono le proprie voci tramite mssg_register_module()
══════════════════════════════════════════════════════════════ */
function mssg_register_shell_sections() {

    // Dashboard gestionale (sostituisce/affianca quella di login-ui)
    add_filter( 'msslu_account_sections', function( $sections ) {
        $user = wp_get_current_user();

        // Inserisce le sezioni gestionale PRIMA delle sezioni standard
        $gestionale_sections = array();

        // Dashboard principale — visibile a tutti i ruoli gestionale
        if ( mssg_user_can( $user->ID, 'view_dashboard' ) ) {
            $gestionale_sections['mssg_dashboard'] = array(
                'icon'  => 'grid',
                'label' => 'Dashboard',
                'group' => 'gestionale',
            );
            /* CORREZIONE: $sections (passato dal chiamante) contiene già una voce
               'dashboard' => 'Dashboard' definita da mss-login-ui (il modal/account
               generico riusato anche per utenti non-gestionale). Senza questa unset,
               un utente gestionale vede DUE voci "Dashboard" nella sidebar: quella
               generica di login-ui e quella specifica del gestionale appena aggiunta
               sopra. Per gli utenti gestionale la seconda sostituisce completamente
               la prima, quindi va rimossa qui. */
            unset( $sections['dashboard'] );
        }

        // Sezioni registrate dai moduli (ordinate per priority)
        $module_sections = mssg_get_registered_sections( $user->ID );
        foreach ( $module_sections as $key => $cfg ) {
            $gestionale_sections[ $key ] = $cfg;
        }

        // Se l'utente è solo cliente finale, non mostrare sezioni WP standard
        if ( mssg_user_has_role( $user->ID, 'mssg_cliente' ) && ! mssg_user_has_role( $user->ID, 'administrator' ) ) {
            // Rimuove sezioni non pertinenti per il cliente
            unset( $sections['orders'], $sections['address'] );
        }

        // Ruoli gestionale: sposta le sezioni gestionale in cima
        if ( mssg_is_gestionale_user( $user->ID ) ) {
            return array_merge( $gestionale_sections, $sections );
        }

        return $sections;

    }, 10 );

    // Contenuto sezione dashboard gestionale
    add_filter( 'msslu_section_html_mssg_dashboard', function( $html, $user ) {
        ob_start();
        mssg_render_dashboard( $user );
        return ob_get_clean();
    }, 10, 2 );
}

/* ══════════════════════════════════════════════════════════════
   DASHBOARD CORE — i moduli aggiungono i propri widget tramite hook
══════════════════════════════════════════════════════════════ */
function mssg_render_dashboard( $user ) {
    $nickname = get_user_meta( $user->ID, 'msslu_nickname', true ) ?: $user->display_name;
    $role_label = mssg_get_role_label( $user->ID );
    ?>
    <div class="mssg-dashboard">

        <div class="mssg-dashboard-header">
            <div>
                <h2 class="msslu-section-title">
                    <?php echo esc_html( $nickname ); ?>
                    <span class="mssg-role-badge mssg-role-<?php echo esc_attr( mssg_get_primary_role( $user->ID ) ); ?>">
                        <?php echo esc_html( $role_label ); ?>
                    </span>
                </h2>
                <p class="mssg-dashboard-subtitle"><?php echo esc_html( mssg_get_greeting() ); ?></p>
            </div>
        </div>

        <?php
        // Hook: ogni modulo aggiunge i propri widget KPI
        // Esempio: add_action('mssg_dashboard_widgets', function($user) { ... }, 10, 1);
        do_action( 'mssg_dashboard_widgets', $user );
        ?>

        <?php if ( ! has_action( 'mssg_dashboard_widgets' ) ) : ?>
        <div class="mssg-empty-state">
            <p>Nessun modulo attivo. Installa un modulo gestionale per iniziare.</p>
        </div>
        <?php endif; ?>

        <?php
        // Hook: attività recente aggregata da tutti i moduli
        do_action( 'mssg_dashboard_activity', $user );
        ?>

    </div>
    <?php
}

/* ══════════════════════════════════════════════════════════════
   REDIRECT — utenti gestionale vanno direttamente all'area privata
══════════════════════════════════════════════════════════════ */
add_filter( 'login_redirect', 'mssg_login_redirect', 20, 3 );
add_filter( 'msslu_redirect_after_login', 'mssg_loginui_redirect', 20, 2 );

function mssg_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
    if ( is_wp_error( $user ) ) return $redirect_to;
    if ( mssg_is_gestionale_user( $user->ID ) ) {
        return mssg_get_private_area_url();
    }
    return $redirect_to;
}

function mssg_loginui_redirect( $url, $user_id ) {
    if ( mssg_is_gestionale_user( $user_id ) ) {
        return mssg_get_private_area_url();
    }
    return $url;
}

function mssg_get_private_area_url() {
    $page_id = get_option( 'mssg_page_gestionale' );
    if ( $page_id ) return get_permalink( $page_id );
    // Fallback alla pagina account di login-ui
    return msslu_get_option( 'redirect_login', home_url( '/gestionale' ) );
}

/* ══════════════════════════════════════════════════════════════
   ENQUEUE ASSETS
══════════════════════════════════════════════════════════════ */
add_action( 'wp_enqueue_scripts', 'mssg_enqueue_assets' );

function mssg_enqueue_assets() {
    if ( ! is_user_logged_in() ) return;
    if ( ! mssg_is_gestionale_user( get_current_user_id() ) ) return;

    wp_enqueue_style(
        'mss-gestionale',
        MSSG_URL . 'assets/css/gestionale.css',
        array( 'mss-login-ui' ),
        MSSG_VERSION
    );

    wp_enqueue_script(
        'mss-gestionale',
        MSSG_URL . 'assets/js/gestionale.js',
        array( 'jquery', 'mss-login-ui' ),
        MSSG_VERSION,
        true
    );

    wp_localize_script( 'mss-gestionale', 'MSSG', array(
        'ajax_url'       => admin_url( 'admin-ajax.php' ),
        'nonce'          => wp_create_nonce( 'mssg_nonce' ),
        'user_id'        => get_current_user_id(),
        'role'           => mssg_get_primary_role( get_current_user_id() ),
        'modules'        => mssg_get_active_module_slugs(),
        'strings'        => array(
            'confirm_delete' => 'Sei sicuro di voler eliminare questo elemento?',
            'saving'         => 'Salvataggio…',
            'saved'          => 'Salvato.',
            'error'          => 'Si è verificato un errore. Riprova.',
        ),
    ));
}

/* ══════════════════════════════════════════════════════════════
   HELPERS PUBBLICI (usati dai moduli)
══════════════════════════════════════════════════════════════ */
function mssg_get_greeting() {
    $h = (int) current_time( 'G' );
    if ( $h < 12 ) return 'Buongiorno';
    if ( $h < 18 ) return 'Buon pomeriggio';
    return 'Buonasera';
}

/* ══════════════════════════════════════════════════════════════
   ATTIVAZIONE / DISATTIVAZIONE
══════════════════════════════════════════════════════════════ */
register_activation_hook( __FILE__, 'mssg_activate' );
register_deactivation_hook( __FILE__, 'mssg_deactivate' );

function mssg_activate() {
    require_once MSSG_PATH . 'core/roles.php';
    mssg_register_roles();
    mssg_create_pages();
    flush_rewrite_rules();
}

function mssg_deactivate() {
    flush_rewrite_rules();
}

function mssg_create_pages() {
    $page_id = get_option( 'mssg_page_gestionale' );
    if ( $page_id && get_post( $page_id ) ) return;

    $existing = get_page_by_path( 'gestionale' );
    if ( $existing ) {
        update_option( 'mssg_page_gestionale', $existing->ID );
        return;
    }

    $pid = wp_insert_post( array(
        'post_title'   => 'Gestionale',
        'post_name'    => 'gestionale',
        'post_content' => '[mssg_area_privata]',
        'post_status'  => 'publish',
        'post_type'    => 'page',
    ));

    if ( $pid && ! is_wp_error( $pid ) ) {
        update_option( 'mssg_page_gestionale', $pid );
    }
}
