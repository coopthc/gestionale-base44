<?php
/**
 * Plugin Name:       MSS Gestionale — Clienti
 * Version:           2.0.0
 * Description:       Gestione clienti + area personale cliente con thread bidirezionale, appuntamenti, documenti. Richiede mss-gestionale ≥ 1.0.0.
 * Author:            Web.CoopTHC
 * License:           Proprietary
 * Text Domain:       mssg-clienti
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MSSGCL_VERSION', '2.0.1' );
define( 'MSSGCL_PATH',    plugin_dir_path( __FILE__ ) );
define( 'MSSGCL_URL',     plugin_dir_url( __FILE__ ) );

require_once MSSGCL_PATH . 'includes/db.php';       /* Tabelle + tutti gli AJAX */
require_once MSSGCL_PATH . 'includes/section-clienti.php';
require_once MSSGCL_PATH . 'includes/section-scheda-cliente.php';
require_once MSSGCL_PATH . 'includes/section-area-cliente.php';
require_once MSSGCL_PATH . 'includes/extra.php';

add_action( 'mssg_load_modules', 'mssgcl_boot' );

function mssgcl_boot() {
    if ( ! function_exists( 'mssg_register_module' ) ) return;

    mssg_register_module( 'mssg-clienti', array(
        'version'     => MSSGCL_VERSION,
        'name'        => 'Clienti',
        'description' => 'Clienti + area personale con chat bidirezionale, appuntamenti, documenti.',
        'path'        => MSSGCL_PATH,
        'url'         => MSSGCL_URL,
        'icon'        => 'users',
        'priority'    => 20,
    ));

    add_filter( 'mssg_capabilities', 'mssgcl_register_capabilities' );

    /* ── Sezione "Clienti" — solo admin ── */
    mssg_register_section( 'mssg_clienti', array(
        'module_slug'  => 'mssg-clienti',
        'icon'         => 'users',
        'label'        => 'Clienti',
        'group'        => 'gestionale',
        'priority'     => 20,
        'requires_cap' => 'view_clienti',
        'render'       => 'mssgcl_render_lista_clienti',
    ));

    /* ── Area personale cliente ──
       La capability 'view_area_cliente' è nominalmente riservata a mssg_cliente,
       ma un utente WordPress "administrator" bypassa sempre tutti i controlli di
       capability (vedi mssg_user_can()), quindi la vede comunque in sidebar
       insieme a "I miei lavori" (mssg-cantieri). Le due voci mostrano contenuti
       diversi (questa è centrata su comunicazioni/appuntamenti, l'altra è la
       panoramica cantieri con KPI), ma con la stessa etichetta "Area personale"
       risultavano indistinguibili per l'admin. Etichetta diversa solo per chi
       la vede come membro dello staff, invariata per il cliente reale. */
    $mssgcl_area_label = 'Area personale';
    if ( is_user_logged_in() ) {
        $mssgcl_area_role = mssg_get_primary_role( get_current_user_id() );
        if ( in_array( $mssgcl_area_role, array( 'administrator', 'mssg_admin', 'mssg_capo', 'mssg_operaio' ), true ) ) {
            $mssgcl_area_label = 'Messaggi & Appuntamenti';
        }
    }
    mssg_register_section( 'mssg_area_cliente', array(
        'module_slug'  => 'mssg-clienti',
        'icon'         => 'home',
        'label'        => $mssgcl_area_label,
        'group'        => 'personale',
        'priority'     => 5,
        'requires_cap' => 'view_area_cliente',
        'render'       => 'mssgcl_render_area_cliente',
    ));

    add_action( 'wp_enqueue_scripts', 'mssgcl_enqueue_assets' );
}

function mssgcl_enqueue_assets() {
    if ( ! is_user_logged_in() ) return;
    wp_enqueue_style(  'mssg-clienti', MSSGCL_URL . 'assets/css/clienti.css',  array( 'mss-gestionale' ), MSSGCL_VERSION );
    wp_enqueue_script( 'mssg-clienti', MSSGCL_URL . 'assets/js/clienti.js', array( 'mss-gestionale' ), MSSGCL_VERSION, true );
    wp_localize_script( 'mssg-clienti', 'MSSGCL', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'mssg_nonce' ),
        'apriScheda' => 'mssgcl_apriScheda',
    ));
}

function mssgcl_register_capabilities( $caps ) {
    $caps['view_clienti']      = array( 'administrator', 'mssg_admin', 'mssg_capo' );
    $caps['create_clienti']    = array( 'administrator', 'mssg_admin' );
    $caps['edit_clienti']      = array( 'administrator', 'mssg_admin', 'mssg_capo' );
    $caps['delete_clienti']    = array( 'administrator', 'mssg_admin' );
    $caps['view_area_cliente'] = array( 'mssg_cliente' );
    return $caps;
}

register_activation_hook( __FILE__, function() {
    mssgcl_ensure_tables();
});


/* ── Pulizia sidebar per clienti ── */
/* Rimuove SOLO la seconda Dashboard di login-ui — tutto il resto rimane */
add_filter( 'msslu_account_sections', function( $sections ) {
    if ( ! is_user_logged_in() ) return $sections;
    if ( ! mssg_user_has_role( get_current_user_id(), 'mssg_cliente' ) ) return $sections;

    foreach ( $sections as $key => $s ) {
        if ( ! is_array( $s ) ) continue;
        $label = $s['label'] ?? '';
        $group = $s['group'] ?? 'base';
        /* Rimuovi SOLO la Dashboard duplicata di login-ui (il gestionale ha la sua) */
        if ( $label === 'Dashboard' && $group !== 'gestionale' ) {
            unset( $sections[ $key ] );
        }
    }
    return $sections;
}, 25 );




/* ══════════════════════════════════════════════════════════════
   INTEGRAZIONE DATI FATTURAZIONE in "Dati personali" di login-ui
   1. wp_ajax_msslu_account_action@priority1 → salva i campi extra
   2. wp_footer → inietta i campi nel form via JS (MutationObserver)
   Nessuna modifica al plugin login-ui richiesta.
══════════════════════════════════════════════════════════════ */

/* ── Salva campi extra al salvataggio dati personali ── */
add_action( 'wp_ajax_msslu_account_action', function() {
    if ( ! is_user_logged_in() ) return;
    if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'msslu_nonce' ) ) return;
    if ( ( $_POST['msslu_action'] ?? '' ) !== 'update_details' ) return;
    if ( ! mssg_user_has_role( get_current_user_id(), 'mssg_cliente' ) ) return;

    $uid = get_current_user_id();
    /* Mappa: chiave POST → [ meta_keys da aggiornare ] */
    $map = array(
        'mssgcl_azienda'   => array( 'mssgcl_azienda',   'billing_company' ),
        'mssgcl_indirizzo' => array( 'mssgcl_indirizzo',  'billing_address_1' ),
        'mssgcl_citta'     => array( 'mssgcl_citta',      'billing_city' ),
        'mssgcl_cap'       => array( 'mssgcl_cap',        'billing_postcode' ),
        'mssgcl_provincia' => array( 'mssgcl_provincia',  'billing_state' ),
        'billing_country'  => array( 'billing_country' ),
    );
    foreach ( $map as $post_key => $meta_keys ) {
        if ( ! isset( $_POST[ $post_key ] ) ) continue;
        $val = sanitize_text_field( $_POST[ $post_key ] );
        foreach ( $meta_keys as $mk ) update_user_meta( $uid, $mk, $val );
    }
    /* NON fare die() — lasciamo che login-ui continui a priority 10 */
}, 1 );

/* ── Inietta campi nel form "Dati personali" di login-ui via JS ── */
add_action( 'wp_footer', function() {
    if ( ! is_user_logged_in() ) return;
    if ( ! mssg_user_has_role( get_current_user_id(), 'mssg_cliente' ) ) return;

    $uid = get_current_user_id();
    $g   = fn( $k ) => esc_js( get_user_meta( $uid, $k, true ) ?: '' );

    $data = array(
        'azienda'   => $g('mssgcl_azienda')   ?: esc_js( get_user_meta($uid,'billing_company',true) ?: '' ),
        'indirizzo' => $g('mssgcl_indirizzo') ?: esc_js( get_user_meta($uid,'billing_address_1',true) ?: '' ),
        'citta'     => $g('mssgcl_citta')     ?: esc_js( get_user_meta($uid,'billing_city',true) ?: '' ),
        'cap'       => $g('mssgcl_cap')       ?: esc_js( get_user_meta($uid,'billing_postcode',true) ?: '' ),
        'provincia' => $g('mssgcl_provincia') ?: esc_js( get_user_meta($uid,'billing_state',true) ?: '' ),
        'paese'     => esc_js( get_user_meta($uid,'billing_country',true) ?: 'IT' ),
    );
    ?>
    <style>
    /* Previene zoom su iOS quando si clicca su input nel layout login-ui */
    @media(max-width:768px){
        .msslu-account input, .msslu-account select, .msslu-account textarea,
        #mssgcl-billing-addon input, #mssgcl-billing-addon select {
            font-size:16px !important;
        }
        /* Billing addon a colonna singola su mobile */
        #mssgcl-billing-addon .msslu-form-row {
            grid-template-columns: 1fr !important;
        }
    }
    </style>
    <script>
    (function($){
        var mssgclBilling = <?php echo wp_json_encode( $data ); ?>;

        function mssgclInjectBilling() {
            var $form = $('form.msslu-form--wide');
            if (!$form.length || $form.find('#mssgcl-billing-addon').length) return;

            var html = '<div id="mssgcl-billing-addon" style="margin-top:24px;padding-top:20px;border-top:1px solid var(--msslu-input-border,rgba(255,255,255,.12))">';
            html += '<div style="font-size:13px;font-weight:700;margin-bottom:16px;color:var(--msslu-text)">Indirizzo e fatturazione</div>';

            html += '<div class="msslu-form-row">';
            html += '<div class="msslu-form-col msslu-form-col--full">';
            html += '<label>Ragione sociale / Azienda</label>';
            html += '<input type="text" name="mssgcl_azienda" value="'+$('<div>').text(mssgclBilling.azienda).html()+'" placeholder="Nome azienda o professionista">';
            html += '</div></div>';

            html += '<div class="msslu-form-row">';
            html += '<div class="msslu-form-col msslu-form-col--full">';
            html += '<label>Indirizzo</label>';
            html += '<input type="text" name="mssgcl_indirizzo" value="'+$('<div>').text(mssgclBilling.indirizzo).html()+'" placeholder="Via e numero civico">';
            html += '</div></div>';

            html += '<div class="msslu-form-row">';
            html += '<div class="msslu-form-col"><label>'+String.fromCharCode(67,105,116,116,225)+'</label>';
            html += '<input type="text" name="mssgcl_citta" value="'+$('<div>').text(mssgclBilling.citta).html()+'" placeholder="'+String.fromCharCode(67,105,116,116,225)+'"></div>';
            html += '<div class="msslu-form-col"><label>CAP</label>';
            html += '<input type="text" name="mssgcl_cap" value="'+$('<div>').text(mssgclBilling.cap).html()+'" placeholder="00000" maxlength="5" style="max-width:120px"></div>';
            html += '</div>';

            html += '<div class="msslu-form-row">';
            html += '<div class="msslu-form-col"><label>Provincia</label>';
            html += '<input type="text" name="mssgcl_provincia" value="'+$('<div>').text(mssgclBilling.provincia).html()+'" placeholder="RM" maxlength="2" style="max-width:80px;text-transform:uppercase"></div>';
            html += '<div class="msslu-form-col"><label>Nazione</label>';
            html += '<select name="billing_country" style="max-width:180px">';
            var paesi = {IT:'Italia',DE:'Germania',FR:'Francia',ES:'Spagna',GB:'Regno Unito',CH:'Svizzera',AT:'Austria',US:'USA'};
            Object.keys(paesi).forEach(function(k){ html += '<option value="'+k+'"'+(mssgclBilling.paese===k?' selected':'')+'>'+paesi[k]+'</option>'; });
            html += '</select></div>';
            html += '</div>';

            html += '</div>'; /* #mssgcl-billing-addon */

            /* Inserisci prima della riga dei pulsanti (submit + export CSV) */
            var $submitRow = $form.find('button[type=submit]').closest('div').first();
            if ($submitRow.length) {
                $submitRow.before(html);
            } else {
                $form.append(html);
            }
        }

        /* Run on load */
        $(function(){ mssgclInjectBilling(); });

        /* Watch for section changes via MutationObserver */
        if (window.MutationObserver) {
            var obs = new MutationObserver(function(muts){
                muts.forEach(function(m){
                    if (m.addedNodes.length) { mssgclInjectBilling(); }
                });
            });
            var target = document.getElementById('msslu-section-content')
                      || document.querySelector('[data-section-content]')
                      || document.body;
            obs.observe(target, {childList: true, subtree: true});
        }
    })(jQuery);
    </script>
    <?php
}, 100 );

/* ── Dashboard widget notifiche per cliente ── */
add_action( 'mssg_dashboard_widgets', function( $user ) {
    if ( ! mssg_user_has_role( $user->ID, 'mssg_cliente' ) ) return;
    mssgcl_render_cliente_notifiche_widget( $user->ID );
}, 5, 1 );

function mssgcl_render_cliente_notifiche_widget( $user_id ) {
    global $wpdb;

    /* Comunicazioni non lette */
    $t_com = $wpdb->prefix . 'mssg_comunicazioni';
    $unread_com = 0;
    if ( $wpdb->get_var("SHOW TABLES LIKE '{$t_com}'") === $t_com ) {
        $unread_com = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$t_com}` WHERE cliente_id=%d AND direzione='admin_to_cliente' AND letta=0",
            $user_id
        ));
    }

    /* Tutti gli appuntamenti futuri (richieste + confermati) */
    $t_ag = $wpdb->prefix . 'mssg_agenda_blocchi';
    $app_futuri = array();
    if ( $wpdb->get_var("SHOW TABLES LIKE '{$t_ag}'") === $t_ag ) {
        $now_dt = current_time('mysql');
        $app_futuri = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM `{$t_ag}`
             WHERE cliente_id=%d AND tipo IN ('richiesta','confermato') AND data_ora_inizio > %s
             ORDER BY data_ora_inizio ASC LIMIT 10",
            $user_id, $now_dt
        ));
    }

    if ( ! $unread_com && empty($app_futuri) ) return;
    ?>
    <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--msslu-box-border)">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--msslu-text-muted);margin-bottom:10px">Notifiche</div>
        <div style="display:flex;flex-direction:column;gap:8px">

            <?php if ( $unread_com > 0 ): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:rgba(233,30,140,.08);border:1px solid rgba(233,30,140,.2);border-radius:9px">
                <div style="display:flex;align-items:center;gap:8px">
                    <span style="font-size:18px">💬</span>
                    <span style="font-size:13px;font-weight:600;color:var(--msslu-text)">Comunicazioni non lette</span>
                </div>
                <span style="font-size:12px;font-weight:800;color:#fff;background:var(--msslu-accent,#e91e8c);padding:3px 9px;border-radius:999px"><?php echo $unread_com; ?></span>
            </div>
            <?php endif; ?>

            <?php if ( ! empty($app_futuri) ): ?>
            <div style="padding:10px 14px;background:var(--msslu-input-bg);border:1px solid var(--msslu-box-border);border-radius:9px">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                    <span style="font-size:16px">📅</span>
                    <span style="font-size:13px;font-weight:600;color:var(--msslu-text)">
                        Appuntamenti
                        <span style="font-size:11px;font-weight:700;background:var(--msslu-accent,#e91e8c);color:#fff;padding:1px 7px;border-radius:999px;margin-left:5px"><?php echo count($app_futuri); ?></span>
                    </span>
                </div>
                <?php foreach ( $app_futuri as $af ):
                    $tc = $af->tipo === 'confermato' ? '#22c55e' : '#f59e0b';
                    $tl = $af->tipo === 'confermato' ? '✅ Confermato' : '⏳ In attesa';
                    $df = date_i18n('d/m/Y H:i', strtotime($af->data_ora_inizio));
                    $is_oggi_a = date('Y-m-d',strtotime($af->data_ora_inizio)) === date('Y-m-d');
                ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:0.5px solid var(--msslu-box-border)">
                    <div>
                        <span style="font-size:13px;font-weight:<?php echo $is_oggi_a?'700':'500'; ?>;color:<?php echo $is_oggi_a?'var(--msslu-accent,#e91e8c)':'var(--msslu-text)'; ?>">
                            <?php echo $is_oggi_a ? '🔴 OGGI ' : ''; ?><?php echo $df; ?>
                        </span>
                    </div>
                    <span style="font-size:11px;font-weight:700;color:<?php echo $tc; ?>;white-space:nowrap;margin-left:8px"><?php echo $tl; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>
    <?php
}
