<?php
/**
 * Plugin Name:       MSS Gestionale — Cantieri
 * Version:           4.0.1
 * Description:       Cantieri completi: I miei lavori per ruolo, pagamenti milestone, media, chat, export. Richiede mss-gestionale ≥ 1.0.0.
 * Author:            Web.CoopTHC
 * License:           Proprietary
 * Text Domain:       mssg-cantieri
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MSSGC_VERSION', '4.0.0' );
define( 'MSSGC_PATH',    plugin_dir_path( __FILE__ ) );
define( 'MSSGC_URL',     plugin_dir_url( __FILE__ ) );

require_once MSSGC_PATH . 'includes/db.php';
require_once MSSGC_PATH . 'includes/capabilities.php';
require_once MSSGC_PATH . 'includes/notifications.php';
require_once MSSGC_PATH . 'includes/section-lista.php';
require_once MSSGC_PATH . 'includes/section-form.php';
require_once MSSGC_PATH . 'includes/section-media.php';
require_once MSSGC_PATH . 'includes/section-avanzamento.php';
require_once MSSGC_PATH . 'includes/section-chat.php';
require_once MSSGC_PATH . 'includes/section-pagamenti.php';
require_once MSSGC_PATH . 'includes/section-miei-lavori.php';
require_once MSSGC_PATH . 'includes/export.php';
require_once MSSGC_PATH . 'includes/ajax.php';
require_once MSSGC_PATH . 'includes/ajax-chat.php';
require_once MSSGC_PATH . 'includes/ajax-export.php';
require_once MSSGC_PATH . 'includes/backup-import.php';
require_once MSSGC_PATH . 'includes/cloud-storage.php';
require_once MSSGC_PATH . 'includes/section-storage.php';
require_once MSSGC_PATH . 'includes/shortcodes.php';
require_once MSSGC_PATH . 'includes/dashboard-widget.php';

add_action( 'mssg_load_modules', 'mssgc_boot' );

function mssgc_boot() {
    if ( ! function_exists( 'mssg_register_module' ) ) return;

    mssg_register_module( 'mssg-cantieri', array(
        'version'     => MSSGC_VERSION,
        'name'        => 'Cantieri',
        'description' => 'Cantieri completi: media, team, avanzamento, chat, pagamenti, export.',
        'path'        => MSSGC_PATH,
        'url'         => MSSGC_URL,
        'icon'        => 'building',
        'priority'    => 10,
    ));

    add_filter( 'mssg_capabilities', 'mssgc_register_capabilities' );

    /* ── Sezione "I miei lavori" — per admin + collaboratori (non cliente) ── */
    mssg_register_section( 'mssg_miei_lavori', array(
        'module_slug'  => 'mssg-cantieri',
        'icon'         => 'briefcase',
        'label'        => 'I miei lavori',
        'group'        => 'gestionale',
        'priority'     => 5,
        'requires_cap' => 'view_miei_lavori',
        'exclude_roles'=> array( 'mssg_cliente' ),
        'render'       => 'mssgc_render_miei_lavori',
    ));

    /* ── Sezione "Cantieri" — lista completa (solo admin e capo) ── */
    mssg_register_section( 'mssg_cantieri', array(
        'module_slug'  => 'mssg-cantieri',
        'icon'         => 'building',
        'label'        => 'Cantieri',
        'group'        => 'gestionale',
        'priority'     => 10,
        'requires_cap' => 'view_all_cantieri',
        'render'       => 'mssgc_render_lista',
    ));

    /* ── Sezione "Esporta dati" — solo admin/capo ── */
    mssg_register_section( 'mssg_export', array(
        'module_slug'  => 'mssg-cantieri',
        'icon'         => 'download',
        'label'        => 'Esporta dati',
        'group'        => 'gestionale',
        'priority'     => 90,
        'requires_cap' => 'manage_cantieri',
        'render'       => 'mssgc_render_export_section',
    ));

    /* ── Sezione "Storage & Cloud" ── */
    mssg_register_section( 'mssg_storage', array(
        'module_slug'  => 'mssg-cantieri',
        'icon'         => 'cloud',
        'label'        => 'Storage & Cloud',
        'group'        => 'gestionale',
        'priority'     => 91,
        'requires_cap' => 'manage_cantieri',
        'render'       => 'mssgcs_render_storage_section',
    ));

    add_action( 'mssg_dashboard_widgets', 'mssgc_dashboard_widget', 10, 1 );
    add_action( 'wp_enqueue_scripts',     'mssgc_enqueue_assets' );
    add_action( 'mssg_admin_submenu',     'mssgc_admin_submenu' );

    mssgc_ensure_tables();
}

function mssgc_enqueue_assets() {
    if ( ! is_user_logged_in() ) return;
    if ( ! mssg_user_can( get_current_user_id(), 'view_cantieri' ) ) return;

    wp_enqueue_style(  'mssg-cantieri', MSSGC_URL . 'assets/css/cantieri.css',  array( 'mss-gestionale' ), MSSGC_VERSION );
    wp_enqueue_script( 'mssg-cantieri', MSSGC_URL . 'assets/js/cantieri.js', array( 'mss-gestionale' ), MSSGC_VERSION, true );
    wp_localize_script( 'mssg-cantieri', 'MSSGC_DATA', array(
        'upload_nonce' => wp_create_nonce( 'mssg_upload_nonce' ),
        'strings'      => array(
            'confirm_delete'   => 'Digita il nome del cantiere per confermare l\'eliminazione:',
            'confirm_archivia' => 'Archiviare questo cantiere? Non apparirà più nella lista principale.',
            'upload_error'     => 'Errore durante il caricamento.',
            'chat_send_error'  => 'Impossibile inviare il messaggio.',
        ),
    ));
}

function mssgc_admin_submenu() {
    add_submenu_page( 'mss-gestionale', 'Cantieri', 'Cantieri', 'manage_options', 'mssg-cantieri-admin', function() {
        echo '<div class="wrap"><h1>Cantieri Admin</h1>';
        $all = mssgc_get_cantieri( get_current_user_id() );
        echo '<p>' . count( $all ) . ' cantieri totali.</p></div>';
    });
}

register_activation_hook( __FILE__, function() {
    mssgc_ensure_tables();
});


/* ══════════════════════════════════════════════════════
   GESTIONALE FULLSCREEN — nasconde header/footer del tema
   quando il gestionale è presente sulla pagina
══════════════════════════════════════════════════════ */
add_action( 'wp_head', function() {
    if ( ! is_user_logged_in() ) return;
    global $post;
    if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'wcthc_account' ) ) return;
    ?>
    <style id="mssg-fullscreen-css">
    /* Nascondi tema */
    .site-header,#masthead,.main-navigation,
    .ast-above-header-wrap,.ast-below-header-wrap,.ast-desktop-header,.ast-mobile-header,
    .ast-header-sticked,.sticky-wrapper,.sticky-nav,[class*="sticky-header"],[class*="nav-bar-sticky"],
    .site-footer,#colophon,.footer-credits,.footer-area,.wp-block-cover,
    .elementor-location-header,.elementor-location-footer{display:none!important}

    /* Sfondo scuro ovunque per utenti loggati */
    html,body,#page,.site,.site-content,
    #content,.content-area,#primary,.site-main,
    #main,article,.inside-article,.entry-content{
        background:#0d0d1a!important
    }
    .msslu-wrap,.msslu-account-wrap,.msslu-account{
        background:#0d0d1a!important;min-height:100vh
    }

    @media(max-width:860px){
        html,body{overflow-x:hidden!important}
        aside.msslu-account-sidebar,.msslu-account-sidebar,.msslu-sidebar,
        .msslu-profile-area,.msslu-nav-wrap{display:none!important}
        .msslu-account-layout{grid-template-columns:1fr!important;padding-top:4px!important;gap:0!important}
        .msslu-account-main,#msslu-section-main,[class*="account-main"]{padding-bottom:100px!important}
        /* Fix cantieri overflow */
        .mssgc-list-area,.mssgc-form-wrap,.mssgc-section,
        #mssgc-panel,.mssgc-table-wrap,.mssgc-lista-wrap{
            overflow-x:auto!important;-webkit-overflow-scrolling:touch;
            max-width:calc(100vw - 16px)!important;box-sizing:border-box!important}
        .mssgc-tabs{overflow-x:auto!important;flex-wrap:nowrap!important;gap:3px!important;padding:4px 6px!important}
        .mssgc-tab-btn{white-space:nowrap;flex-shrink:0!important;
            padding:4px 8px!important;font-size:11px!important;min-width:auto!important}
        .mssgc-grid,.mssgp-cards-grid{grid-template-columns:1fr!important}
        /* Tabella cantieri: larghezza minima per scorrere */
        table.mssgc-table,table[class*="mssgc"]{min-width:480px}

        /* Agenda form: solo testo/search, NON checkbox */
        #mssgag-bk-luogo,#mssgag-bk-note,#mssgag-bk-titolo,
        #mssgag-bk-search,#mssgag-bk-cant,#mssgag-bk-drop{
            width:100%!important;box-sizing:border-box!important;max-width:100%!important
        }
        #mssgag-booking-form{
            overflow-x:hidden!important;max-width:calc(100vw - 16px)!important;
            box-sizing:border-box!important
        }
        /* Tab bar */
        #mssg-tab-bar{
            position:fixed;bottom:0;left:0;right:0;z-index:99999;
            background:#0d0d1a;border-top:0.5px solid rgba(255,255,255,.12);
            display:flex!important;justify-content:space-around;align-items:center;
            height:62px;padding:0 4px;box-shadow:0 -4px 20px rgba(0,0,0,.5)
        }
        .mssg-tab{
            flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;
            gap:2px;cursor:pointer;padding:4px 2px;border:none;background:transparent;
            color:rgba(255,255,255,.38);font-family:inherit;font-size:9px;line-height:1;
            -webkit-tap-highlight-color:transparent;transition:color .15s
        }
        .mssg-tab-ic{font-size:22px;line-height:1;display:block}
        .mssg-tab.active{color:#e91e8c}
        #mssg-drawer{
            position:fixed;bottom:-100%;left:0;right:0;z-index:99998;
            background:#13131f;border-radius:20px 20px 0 0;
            border-top:0.5px solid rgba(255,255,255,.1);
            padding:6px 0 72px;transition:bottom .26s cubic-bezier(.4,0,.2,1);
            box-shadow:0 -8px 40px rgba(0,0,0,.7);max-height:75vh;overflow-y:auto
        }
        #mssg-drawer.open{bottom:0}
        #mssg-drawer-ov{position:fixed;inset:0;z-index:99997;background:rgba(0,0,0,.55);display:none}
        #mssg-drawer-ov.open{display:block}
        .mssg-drawer-handle{width:38px;height:4px;border-radius:2px;background:rgba(255,255,255,.15);margin:8px auto 4px}
        .mssg-drawer-title{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:rgba(255,255,255,.3);padding:10px 20px 8px}
        .mssg-ditem{
            display:flex;align-items:center;gap:14px;padding:14px 20px;
            font-size:15px;color:rgba(255,255,255,.8);cursor:pointer;
            border-bottom:0.5px solid rgba(255,255,255,.05)
        }
        .mssg-ditem-ic{font-size:20px;width:26px;flex-shrink:0;text-align:center}
        .mssg-ditem.danger{color:#ef4444}
        .mssg-ditem:last-child{border-bottom:none}
    }
    @media(min-width:861px){#mssg-tab-bar,#mssg-drawer,#mssg-drawer-ov{display:none!important}}
    </style>

    <script id="mssg-fullscreen-js">
    (function(){
        /* wpadminbar protetto solo per WP admin; per altri viene nascosto dal PHP */
        var OUR=['mssg-tab-bar','mssg-drawer','mssg-drawer-ov'];
        var activeDrawer=null;

        function hideTheme(){
            var g=document.querySelector('.msslu-wrap,.msslu-account-wrap,.msslu-account');
            if(!g) return;
            var node=g;
            while(node&&node!==document.body){
                var par=node.parentElement; if(!par) break;
                Array.from(par.children).forEach(function(s){
                    if(s===node) return;
                    var t=(s.tagName||'').toUpperCase();
                    if('SCRIPT STYLE LINK NOSCRIPT'.indexOf(t)!==-1) return;
                    if(OUR.indexOf(s.id)!==-1) return;
                    s.style.setProperty('display','none','important');
                });
                node=par;
            }
            document.querySelectorAll('body *').forEach(function(el){
                if(OUR.indexOf(el.id)!==-1) return;
                if(el.closest('[class*="msslu"]')||el.closest('#mssg-tab-bar')||el.closest('#mssg-drawer')) return;
                var pos=window.getComputedStyle(el).position;
                if(pos==='fixed'||pos==='sticky') el.style.setProperty('display','none','important');
            });
        }

        /* Navigazione per section-id OPPURE per testo visibile nel nav */
        function goTo(sec,textFallback){
            var link=null;
            if(sec) link=document.querySelector('[data-section="'+sec+'"]');
            if(!link&&textFallback){
                document.querySelectorAll('[data-section]').forEach(function(el){
                    if(!link&&el.textContent.trim().toLowerCase().indexOf(textFallback.toLowerCase())!==-1)
                        link=el;
                });
            }
            if(link) link.click();
            closeDrawer();
        }

        function openDrawer(key,title,items){
            var dr=document.getElementById('mssg-drawer');
            if(!dr) return;
            if(activeDrawer===key){closeDrawer();return;}
            activeDrawer=key;
            var html='<div class="mssg-drawer-handle"></div>'
                    +'<div class="mssg-drawer-title">'+title+'</div>';
            items.forEach(function(item){
                html+='<div class="mssg-ditem'+(item.cls?' '+item.cls:'')+'"'
                    +' data-sec="'+(item.sec||'')+'"'
                    +' data-txt="'+(item.txt||'')+'"'
                    +' data-logout="'+(item.logout?1:0)+'">'
                    +'<span class="mssg-ditem-ic">'+item.ic+'</span>'
                    +'<span>'+item.lbl+'</span></div>';
            });
            dr.innerHTML=html;
            dr.querySelectorAll('.mssg-ditem').forEach(function(d){
                d.addEventListener('click',function(){
                    if(d.dataset.logout==='1'){goLogout();return;}
                    goTo(d.dataset.sec||null, d.dataset.txt||null);
                });
            });
            dr.classList.add('open');
            document.getElementById('mssg-drawer-ov').classList.add('open');
            setActive(key);
        }

        function closeDrawer(){
            activeDrawer=null;
            var dr=document.getElementById('mssg-drawer');
            var ov=document.getElementById('mssg-drawer-ov');
            if(dr) dr.classList.remove('open');
            if(ov) ov.classList.remove('open');
        }

        function goLogout(){
            closeDrawer();
            var l=document.querySelector('a[href*="logout"]');
            if(l){l.click();return;}
            window.location.href='<?php echo esc_js(wp_logout_url(home_url())); ?>';
        }

        function setActive(id){
            document.querySelectorAll('.mssg-tab').forEach(function(b){b.classList.remove('active');});
            var btn=document.querySelector('.mssg-tab[data-tid="'+id+'"]');
            if(btn) btn.classList.add('active');
        }

        var TABS=[
            {id:'home',    ic:'🏠', lbl:'Home',
             fn:function(){
                /* Cerca prima la dashboard di login-ui, poi gestionale, poi primo nav link */
                var slugs=['msslu_dashboard','mssg_dashboard'];
                var found=false;
                for(var i=0;i<slugs.length;i++){
                    var el=document.querySelector('[data-section="'+slugs[i]+'"]');
                    if(el){el.click();found=true;break;}
                }
                if(!found){
                    /* Fallback: primo link di navigazione nella sidebar */
                    var first=document.querySelector('.msslu-nav-link, .msslu-sidebar-link, [class*="nav-link"]');
                    if(first) first.click();
                }
                setActive('home');
                closeDrawer();
             }},
            {id:'cantieri',ic:'🏗', lbl:'Cantieri',
             fn:function(){openDrawer('cantieri','Cantieri',[
                {ic:'💼',sec:'mssg_miei_lavori', txt:'miei lavori', lbl:'I miei lavori'},
                {ic:'👤',sec:'mssg_area_cliente',txt:'area personale',lbl:'Area personale'},
                {ic:'🏗',sec:'mssg_cantieri',    txt:'cantieri',    lbl:'Cantieri'}
             ]);}},
            {id:'agenda',  ic:'📅', lbl:'Agenda',
             fn:function(){goTo('mssg_agenda_admin','agenda');setActive('agenda');}},
            {id:'utility', ic:'⚡', lbl:'Utility',
             fn:function(){openDrawer('utility','Utility',[
                {ic:'👥',sec:'mssg_clienti',  txt:'clienti',  lbl:'Clienti'},
                {ic:'🪪',sec:'mssg_personale',txt:'personale',lbl:'Personale'},
                {ic:'🕐',sec:'mssg_presenze', txt:'presenze', lbl:'Presenze'},
                {ic:'⬇️',sec:'mssg_export',   txt:'esporta',  lbl:'Esporta dati'}
             ]);}},
            {id:'altro',   ic:'⋯',  lbl:'Altro',
             fn:function(){openDrawer('altro','Account',[
                {ic:'👤',sec:'mssg_area_cliente',    txt:'area personale',    lbl:'Area personale'},
                {ic:'👤',sec:'mssg_dati_personali',  txt:'dati personali',    lbl:'Dati personali'},
                {ic:'🔑',sec:'mssg_password',        txt:'password',          lbl:'Password'},
                {ic:'⚙️',sec:'mssg_gestione_account',txt:'gestione account',  lbl:'Gestione account'},
                {ic:'🚪',sec:null,lbl:'Esci',cls:'danger',logout:true}
             ]);}}
        ];

        function buildTabBar(){
            if(document.getElementById('mssg-tab-bar')) return;
            var bar=document.createElement('div'); bar.id='mssg-tab-bar';
            TABS.forEach(function(t){
                var b=document.createElement('button');
                b.className='mssg-tab'; b.dataset.tid=t.id;
                b.innerHTML='<span class="mssg-tab-ic">'+t.ic+'</span><span>'+t.lbl+'</span>';
                b.addEventListener('click',t.fn);
                bar.appendChild(b);
            });
            document.body.appendChild(bar);

            var ov=document.createElement('div'); ov.id='mssg-drawer-ov';
            ov.addEventListener('click',closeDrawer);
            document.body.appendChild(ov);

            var dr=document.createElement('div'); dr.id='mssg-drawer';
            document.body.appendChild(dr);

            setActive('home');

            var secMap={
                'mssg_dashboard':'home',
                'mssg_miei_lavori':'cantieri','mssg_area_cliente':'cantieri','mssg_cantieri':'cantieri',
                'mssg_agenda_admin':'agenda',
                'mssg_clienti':'utility','mssg_personale':'utility','mssg_presenze':'utility','mssg_export':'utility'
            };
            new MutationObserver(function(){
                var a=document.querySelector('.msslu-nav-link.active,[class*="nav-link"][class*="active"]');
                if(!a) return;
                var sec=a.getAttribute('data-section')||'';
                if(secMap[sec]) setActive(secMap[sec]);
            }).observe(document.body,{subtree:true,attributes:true,attributeFilter:['class']});
        }

        function init(){
            hideTheme();
            buildTabBar();
            setTimeout(hideTheme,600);
            setTimeout(hideTheme,1800);
        }

        if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init);
        else init();
    })();
    </script>
    <?php
}, 5 );


/* ══════════════════════════════════════════════════════════
   SEZIONE ESPORTA DATI — integrata in mssg-cantieri
══════════════════════════════════════════════════════════ */
/* ══════════════════════════════════════════════════════
   Nascondi admin bar per utenti gestionale (non WP admin)
   I veri amministratori WP (manage_options) la mantengono
══════════════════════════════════════════════════════ */
add_action( 'after_setup_theme', function() {
    if ( ! is_user_logged_in() ) return;
    /* Nascondi per tutti tranne i veri admin WP */
    if ( ! current_user_can('manage_options') ) {
        add_filter( 'show_admin_bar', '__return_false' );
    }
}, 1 );



function mssgc_render_export_section( $user ) {
    $nonce = wp_create_nonce('mssex_nonce');
    $ajax  = admin_url('admin-ajax.php');
    $items = array(
        array('key'=>'cantieri',    'ic'=>'🏗', 'lbl'=>'Cantieri',    'desc'=>'Nome, stato, date, indirizzo, cliente'),
        array('key'=>'clienti',     'ic'=>'👥', 'lbl'=>'Clienti',     'desc'=>'Anagrafica, email, contatti'),
        array('key'=>'personale',   'ic'=>'🪪', 'lbl'=>'Personale',   'desc'=>'Collaboratori, ruoli'),
        array('key'=>'presenze',    'ic'=>'🕐', 'lbl'=>'Presenze',    'desc'=>'Registro ore per cantiere'),
        array('key'=>'appuntamenti','ic'=>'📅', 'lbl'=>'Appuntamenti','desc'=>'Agenda appuntamenti'),
    );
    ?>
    <div style="padding:16px;max-width:680px">
        <h2 style="font-size:17px;font-weight:700;margin-bottom:4px;color:var(--msslu-text)">⬇️ Esporta dati</h2>
        <p style="font-size:12px;color:var(--msslu-text-muted,rgba(255,255,255,.5));margin-bottom:18px">
            <strong>CSV</strong> = si apre in Excel / Google Sheets &nbsp;·&nbsp;
            <strong>PDF</strong> = pagina stampabile dal browser
        </p>
        <?php foreach($items as $it): ?>
        <div style="background:var(--msslu-box-bg,#1e1e2e);border:1px solid var(--msslu-box-border,rgba(255,255,255,.1));border-radius:10px;padding:12px 14px;margin-bottom:8px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <span style="font-size:24px;flex-shrink:0"><?php echo $it['ic']; ?></span>
            <div style="flex:1;min-width:120px">
                <div style="font-size:14px;font-weight:600;color:var(--msslu-text)"><?php echo $it['lbl']; ?></div>
                <div style="font-size:11px;color:var(--msslu-text-muted,rgba(255,255,255,.4))"><?php echo $it['desc']; ?></div>
            </div>
            <div style="display:flex;gap:6px;flex-shrink:0">
                <a href="<?php echo esc_url($ajax.'?action=mssex_csv_'.$it['key'].'&nonce='.$nonce); ?>"
                   download
                   style="display:inline-flex;align-items:center;gap:5px;padding:7px 14px;
                          background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);
                          border-radius:7px;color:#22c55e;font-size:12px;font-weight:600;text-decoration:none">
                    📊 CSV
                </a>
                <button onclick="mssexPdf('mssex_pdf_<?php echo $it['key']; ?>','<?php echo $nonce; ?>','<?php echo esc_js($it['lbl']); ?>')"
                        style="display:inline-flex;align-items:center;gap:5px;padding:7px 14px;
                               background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);
                               border-radius:7px;color:#ef4444;font-size:12px;font-weight:600;
                               cursor:pointer;font-family:inherit">
                    📄 PDF
                </button>
            </div>
        </div>
        <?php endforeach; ?>
        <!-- Backup totale -->
        <div style="background:rgba(233,30,140,.06);border:1px solid rgba(233,30,140,.2);border-radius:10px;padding:14px;margin-top:14px">
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:10px">
                <div style="flex:1;min-width:160px">
                    <div style="font-size:14px;font-weight:700;color:var(--msslu-text)">🗄 Backup totale</div>
                    <div style="font-size:11px;color:var(--msslu-text-muted,rgba(255,255,255,.4))">DB completo + file media + chat + appuntamenti — reimportabile su altro sito</div>
                </div>
                <button id="mssex-btn-backup"
                   style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:rgba(233,30,140,.15);border:1px solid rgba(233,30,140,.4);border-radius:7px;color:#e91e8c;font-size:13px;font-weight:600;cursor:pointer;flex-shrink:0">
                    📦 Crea e scarica backup
                </button>
            </div>
            <div id="mssex-backup-progress" style="display:none;font-size:12px;color:var(--msslu-text-muted);padding:8px 0">
                <span class="mssg-spinner" style="width:12px;height:12px;border-width:1.5px;vertical-align:middle;margin-right:6px"></span>
                Creazione backup in corso (potrebbe richiedere 30-60 secondi)…
            </div>
            <div id="mssex-backup-result" style="display:none"></div>
        </div>

        <!-- Import backup -->
        <div style="background:rgba(99,102,241,.06);border:1px solid rgba(99,102,241,.25);border-radius:10px;padding:14px;margin-top:10px">
            <div style="font-size:14px;font-weight:700;color:rgba(129,140,248,.9);margin-bottom:8px">📥 Importa backup</div>
            <div style="font-size:11px;color:var(--msslu-text-muted);margin-bottom:10px">
                ⚠️ L'import <strong>sovrascrive</strong> i dati esistenti. Usa solo su un sito nuovo o dopo un backup completo.
                Il sistema aggiorna automaticamente le URL se il dominio è cambiato.
            </div>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <input type="file" id="mssex-import-file" accept=".zip"
                    style="flex:1;font-size:12px;padding:6px 10px;background:var(--msslu-input-bg);border:1px solid rgba(99,102,241,.3);border-radius:6px;color:var(--msslu-text)">
                <button id="mssex-btn-import"
                    style="padding:8px 16px;background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.4);border-radius:7px;color:#818cf8;font-size:13px;font-weight:600;cursor:pointer;flex-shrink:0">
                    📥 Avvia import
                </button>
            </div>
            <div id="mssex-import-progress" style="display:none;font-size:12px;color:var(--msslu-text-muted);margin-top:8px;padding:8px 0">
                <span class="mssg-spinner" style="width:12px;height:12px;border-width:1.5px;vertical-align:middle;margin-right:6px"></span>
                Import in corso (potrebbe richiedere diversi minuti)…
            </div>
            <div id="mssex-import-result" style="display:none;margin-top:8px"></div>
        </div>
    </div>
    <script>
    jQuery(function($){
        /* Backup */
        $('#mssex-btn-backup').on('click', function(){
            var $btn=$(this);
            $btn.prop('disabled',true).text('⏳ Creazione…');
            $('#mssex-backup-progress').show();
            $('#mssex-backup-result').hide();
            $.post('<?php echo esc_js($ajax); ?>',{action:'mssex_backup_totale',nonce:'<?php echo esc_js($nonce); ?>'},function(r){
                $('#mssex-backup-progress').hide();
                $btn.prop('disabled',false).text('📦 Crea e scarica backup');
                if(r.success){
                    var d=r.data;
                    $('#mssex-backup-result').show().html(
                        '<div style="background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);border-radius:7px;padding:10px 14px">'+
                        '<div style="font-size:13px;font-weight:600;color:#22c55e;margin-bottom:6px">✅ '+d.msg+'</div>'+
                        '<a href="'+d.url+'" download style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.3);border-radius:6px;color:#22c55e;font-size:12px;font-weight:600;text-decoration:none">'+
                        '⬇️ Scarica '+d.nome+' ('+d.size_mb+' MB)</a></div>'
                    );
                } else {
                    $('#mssex-backup-result').show().html('<div style="color:#ef4444;font-size:12px;padding:8px 0">❌ '+(r.data&&r.data.msg?r.data.msg:'Errore.')+'</div>');
                }
            }).fail(function(){ $('#mssex-backup-progress').hide();$btn.prop('disabled',false).text('📦 Crea e scarica backup');alert('Errore di connessione.'); });
        });

        /* Import */
        $('#mssex-btn-import').on('click', function(){
            var file=$('#mssex-import-file')[0].files[0];
            if(!file){alert('Seleziona un file ZIP di backup.');return;}
            if(!confirm('⚠️ Stai per sovrascrivere tutti i dati esistenti.\nContinuare?'))return;
            var $btn=$(this);
            $btn.prop('disabled',true).text('⏳ Import…');
            $('#mssex-import-progress').show();
            $('#mssex-import-result').hide();
            var fd=new FormData();
            fd.append('action','mssex_import_backup');
            fd.append('nonce','<?php echo esc_js($nonce); ?>');
            fd.append('backup_file',file);
            $.ajax({url:'<?php echo esc_js($ajax); ?>',type:'POST',data:fd,processData:false,contentType:false})
            .done(function(r){
                $('#mssex-import-progress').hide();
                $btn.prop('disabled',false).text('📥 Avvia import');
                var ok=r.success;
                var msg=r.data&&r.data.msg?r.data.msg:(ok?'Import completato.':'Errore.');
                $('#mssex-import-result').show().html(
                    '<div style="background:rgba('+(ok?'34,197,94':'239,68,68')+',.1);border:1px solid rgba('+(ok?'34,197,94':'239,68,68')+',.3);border-radius:7px;padding:10px 14px;font-size:12px;color:'+(ok?'#22c55e':'#ef4444')+'">'+
                    (ok?'✅ ':'❌ ')+msg.replace(/\n/g,'<br>')+'</div>'
                );
            }).fail(function(){ $('#mssex-import-progress').hide();$btn.prop('disabled',false).text('📥 Avvia import');alert('Errore di connessione.'); });
        });
    });
    </script>
    <script>
    window.mssexPdf = function(action, nonce, label) {
        var url = '<?php echo esc_js($ajax); ?>?action=' + action + '&nonce=' + nonce;
        var w = window.open('', '_blank');
        if (!w) { alert('Abilita i popup per il PDF'); return; }
        var loading = '<html><head><meta charset="UTF-8"><title>'+label+'</title>'
            + '<style>body{font-family:Arial,sans-serif;padding:24px;color:#111}'
            + 'h1{font-size:18px;margin:0 0 4px}p.sub{color:#888;font-size:12px;margin:0 0 16px}'
            + 'table{border-collapse:collapse;width:100%;font-size:12px}'
            + 'th,td{border:1px solid #ccc;padding:7px 9px;text-align:left}'
            + 'th{background:#f5f5f5;font-weight:600}tr:nth-child(even){background:#fafafa}'
            + '.btn{display:inline-block;margin-bottom:16px;padding:8px 18px;'
            + 'background:#e91e8c;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px}'
            + '@media print{.btn{display:none}}</style></head><body>'
            + '<button class="btn" onclick="window.print()">🖨 Stampa / Salva PDF</button>'
            + '<h1>'+label+'</h1>'
            + '<p class="sub">Esportato il <?php echo date("d/m/Y H:i"); ?></p>'
            + '<p>Caricamento...</p></body></html>';
        w.document.write(loading);
        w.document.close();
        fetch(url).then(function(r){return r.text();})
            .then(function(html){
                var body=w.document.body;
                var p=body.querySelector('p:last-child');
                if(p) p.outerHTML=html;
            }).catch(function(){
                w.document.body.innerHTML+='<p style="color:red">Errore nel caricamento.</p>';
            });
    };
    </script>
    <?php
}

/* ── Helpers ── */
function mssex_chk_nonce(){
    if(!check_ajax_referer('mssex_nonce','nonce',false)||!is_user_logged_in()) wp_die('Non autorizzato');
}
function mssex_csv_send($name){
    header('Content-Type:text/csv;charset=UTF-8');
    header('Content-Disposition:attachment;filename="'.$name.'"');
    header('Pragma:no-cache');
    echo "ï»¿"; // BOM UTF-8
}
function mssex_tbl($cols,$rows){
    $h='<table><thead><tr>';
    foreach($cols as $c) $h.='<th>'.esc_html($c).'</th>';
    $h.='</tr></thead><tbody>';
    foreach($rows as $r){$h.='<tr>';foreach((array)$r as $v)$h.='<td>'.esc_html($v ?? '').'</td>';$h.='</tr>';}
    return $h.'</tbody></table>';
}

/* ── Cantieri ── */
/* ── Helper: dati completi cantiere ── */
function mssex_cantieri_data(){ 
    global $wpdb;
    $tc=$wpdb->prefix.'mssg_cantieri'; $tcu=$wpdb->prefix.'mssg_cantieri_users';
    $tav=$wpdb->prefix.'mssg_avanzamenti'; $tpag=$wpdb->prefix.'mssg_pagamenti';
    $cantieri=$wpdb->get_results("SELECT * FROM `{$tc}` ORDER BY nome");
    $out=array();
    foreach($cantieri as $c){
        /* Cliente principale */
        $cliente=''; $email_cl='';
        if($c->cliente_id){ $u=get_userdata($c->cliente_id); if($u){$cliente=$u->display_name;$email_cl=$u->user_email;} }
        /* Team */
        $team=$wpdb->get_results($wpdb->prepare("SELECT u.display_name,cu.ruolo FROM `{$tcu}` cu LEFT JOIN {$wpdb->users} u ON u.ID=cu.user_id WHERE cu.cantiere_id=%d",$c->id));
        $team_str=implode(', ',array_map(function($t){return $t->display_name.' ('.$t->ruolo.')';},$team?:array()));
        /* Avanzamento */
        $av_count=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$tav}` WHERE cantiere_id=%d AND deleted_at IS NULL",$c->id));
        $av_done=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$tav}` WHERE cantiere_id=%d AND tipo='completamento' AND deleted_at IS NULL",$c->id));
        $av_pct=$av_count>0?round($av_done/$av_count*100):($c->avanzamento_pct??0);
        /* Pagamenti */
        $tot_pag=0;
        if($wpdb->get_var("SHOW TABLES LIKE '{$tpag}'") === $tpag){
            $tot_pag=$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(importo),0) FROM `{$tpag}` WHERE cantiere_id=%d",$c->id));
        }
        $out[]=array(
            'ID'               =>$c->id,
            'Nome'             =>$c->nome,
            'Indirizzo'        =>$c->indirizzo,
            'Città'            =>$c->citta,
            'CAP'              =>$c->cap,
            'Provincia'        =>$c->provincia,
            'Stato'            =>$c->stato,
            'Inizio'           =>$c->data_inizio,
            'Fine Prevista'    =>$c->data_fine_prev,
            'Fine Effettiva'   =>$c->data_fine_eff??'',
            'Prezzo Previsto'  =>$c->importo_prev,
            'Totale Pagato'    =>$tot_pag,
            'Avanzamento %'    =>$av_pct.'%',
            'N. Avanzamenti'   =>$av_count,
            'Cliente'          =>$cliente,
            'Email Cliente'    =>$email_cl,
            'Team'             =>$team_str,
            'Descrizione'      =>$c->descrizione??'',
            'Note Interne'     =>$c->note_interne??'',
            'Creato'           =>$c->created_at,
            'Archiviato'       =>$c->deleted_at?'Sì':'No',
        );
    }
    return $out;
}
add_action('wp_ajax_mssex_csv_cantieri',function(){
    mssex_chk_nonce();
    $rows=mssex_cantieri_data();
    mssex_csv_send('cantieri-'.date('Y-m-d').'.csv');
    $f=fopen('php://output','w');
    if(!empty($rows)) fputcsv($f,array_keys($rows[0]));
    foreach($rows as $r) fputcsv($f,array_values($r)); fclose($f); exit;
});
add_action('wp_ajax_mssex_pdf_cantieri',function(){
    mssex_chk_nonce();
    $rows=mssex_cantieri_data();
    $cols=array('Nome','Indirizzo','Città','Stato','Inizio','Fine Prevista','Prezzo Previsto','Totale Pagato','Avanzamento %','Cliente','Team');
    $data=array_map(function($r)use($cols){return array_map(function($c)use($r){return $r[$c]??'';},array_values($cols));},$rows);
    echo mssex_tbl($cols,$data); exit;
});

/* ── Clienti ── */
/* ── Helper: dati completi clienti ── */
function mssex_clienti_data(){
    global $wpdb; $tc=$wpdb->prefix.'mssg_cantieri'; $tcu=$wpdb->prefix.'mssg_cantieri_users';
    $users=get_users(array('role'=>'mssg_cliente','orderby'=>'display_name'));
    $out=array();
    foreach($users as $u){
        $m=get_user_meta($u->ID);
        $g=function($k)use($m){return isset($m[$k][0])?$m[$k][0]:'';};
        /* Cantieri associati */
        $cant=$wpdb->get_col($wpdb->prepare("SELECT c.nome FROM `{$tc}` c WHERE c.cliente_id=%d AND c.deleted_at IS NULL",$u->ID));
        $cant_assoc=$wpdb->get_col($wpdb->prepare("SELECT c.nome FROM `{$tc}` c INNER JOIN `{$tcu}` cu ON cu.cantiere_id=c.id WHERE cu.user_id=%d AND c.deleted_at IS NULL",$u->ID));
        $tutti_cant=array_unique(array_merge($cant,$cant_assoc?:array()));
        $out[]=array(
            'ID'           =>$u->ID,
            'Nome'         =>$u->display_name,
            'Email'        =>$u->user_email,
            'Telefono'     =>$g('mssgcl_telefono')?:$g('billing_phone'),
            'Città'        =>$g('mssgcl_citta')?:$g('billing_city'),
            'Indirizzo'    =>$g('mssgcl_indirizzo')?:$g('billing_address_1'),
            'CAP'          =>$g('mssgcl_cap')?:$g('billing_postcode'),
            'Provincia'    =>$g('mssgcl_provincia'),
            'CF / P.IVA'   =>$g('mssgcl_cf_piva')?:$g('billing_vat'),
            'Azienda'      =>$g('mssgcl_azienda')?:$g('billing_company'),
            'Note'         =>$g('mssgcl_note'),
            'Cantieri'     =>implode(', ',$tutti_cant),
            'Registrato'   =>$u->user_registered,
            'Attivo'       =>in_array('mssg_cliente',(array)$u->roles)?'Sì':'No',
        );
    }
    return $out;
}
add_action('wp_ajax_mssex_csv_clienti',function(){
    mssex_chk_nonce();
    $rows=mssex_clienti_data();
    mssex_csv_send('clienti-'.date('Y-m-d').'.csv');
    $f=fopen('php://output','w');
    if(!empty($rows)) fputcsv($f,array_keys($rows[0]));
    foreach($rows as $r) fputcsv($f,array_values($r)); fclose($f); exit;
});
add_action('wp_ajax_mssex_pdf_clienti',function(){
    mssex_chk_nonce();
    $rows=mssex_clienti_data();
    $cols=array('ID','Nome','Email','Telefono','Città','CF / P.IVA','Azienda','Cantieri','Registrato');
    $data=array_map(function($r)use($cols){return array_map(function($c)use($r){return $r[$c]??'';},array_values($cols));},$rows);
    echo mssex_tbl($cols,$data); exit;
});

/* ── Personale ── */
/* ── Helper: dati completi personale ── */
function mssex_personale_data(){
    global $wpdb; $tp=$wpdb->prefix.'mssg_personale'; $tc=$wpdb->prefix.'mssg_cantieri'; $tcu=$wpdb->prefix.'mssg_cantieri_users';
    $users=get_users(array('role__in'=>array('mssg_operaio','mssg_capo','mssg_admin','administrator'),'orderby'=>'display_name'));
    $out=array();
    foreach($users as $u){
        $ud=get_userdata($u->ID);
        $roles=implode(', ',array_filter(array_keys($ud->caps??array()),function($k){return strpos($k,'mssg_')===0||$k==='administrator';}));
        $p=array(); if($wpdb->get_var("SHOW TABLES LIKE '{$tp}'") === $tp) $p=$wpdb->get_row($wpdb->prepare("SELECT * FROM `{$tp}` WHERE user_id=%d",$u->ID),ARRAY_A)??array();
        $m=get_user_meta($u->ID);
        $g=function($k)use($m){return isset($m[$k][0])?$m[$k][0]:'';};
        /* Cantieri assegnati */
        $cants=$wpdb->get_col($wpdb->prepare("SELECT c.nome FROM `{$tc}` c INNER JOIN `{$tcu}` cu ON cu.cantiere_id=c.id WHERE cu.user_id=%d AND c.deleted_at IS NULL",$u->ID));
        $out[]=array(
            'ID'         =>$u->ID,
            'Nome'       =>$u->display_name,
            'Email'      =>$u->user_email,
            'Ruolo'      =>$roles,
            'Telefono'   =>$p['telefono']??$g('mssg_telefono'),
            'Qualifica'  =>$p['qualifica']??$g('mssg_qualifica'),
            'CF'         =>$p['codice_fiscale']??$g('mssg_cf'),
            'Note'       =>$p['note']??'',
            'Cantieri'   =>implode(', ',$cants?:array()),
            'Registrato' =>$u->user_registered,
        );
    }
    return $out;
}
add_action('wp_ajax_mssex_csv_personale',function(){
    mssex_chk_nonce();
    $rows=mssex_personale_data();
    mssex_csv_send('personale-'.date('Y-m-d').'.csv');
    $f=fopen('php://output','w');
    if(!empty($rows)) fputcsv($f,array_keys($rows[0]));
    foreach($rows as $r) fputcsv($f,array_values($r)); fclose($f); exit;
});
add_action('wp_ajax_mssex_pdf_personale',function(){
    mssex_chk_nonce();
    $rows=mssex_personale_data();
    $cols=array('ID','Nome','Email','Ruolo','Telefono','Qualifica','CF','Cantieri');
    $data=array_map(function($r)use($cols){return array_map(function($c)use($r){return $r[$c]??'';},array_values($cols));},$rows);
    echo mssex_tbl($cols,$data); exit;
});

/* ── Presenze ── */
add_action('wp_ajax_mssex_csv_presenze',function(){
    mssex_chk_nonce(); global $wpdb;
    $t=$wpdb->prefix.'mssg_presenze'; $tc=$wpdb->prefix.'mssg_cantieri';
    if($wpdb->get_var("SHOW TABLES LIKE '{$t}'")!==$t){echo 'Nessuna tabella presenze.';exit;}
    $r=$wpdb->get_results("SELECT u.display_name,c.nome,p.data,p.ore_totali,p.note FROM `{$t}` p LEFT JOIN {$wpdb->users} u ON u.ID=p.user_id LEFT JOIN `{$tc}` c ON c.id=p.cantiere_id ORDER BY p.data DESC",ARRAY_A);
    mssex_csv_send('presenze-'.date('Y-m-d').'.csv');
    $f=fopen('php://output','w'); fputcsv($f,array('Operaio','Cantiere','Data','Ore','Note'));
    foreach($r as $row) fputcsv($f,array_values($row)); fclose($f); exit;
});
add_action('wp_ajax_mssex_pdf_presenze',function(){
    mssex_chk_nonce(); global $wpdb;
    $t=$wpdb->prefix.'mssg_presenze'; $tc=$wpdb->prefix.'mssg_cantieri';
    if($wpdb->get_var("SHOW TABLES LIKE '{$t}'")!==$t){echo '<p>Nessuna presenza.</p>';exit;}
    $r=$wpdb->get_results("SELECT u.display_name,c.nome,p.data,p.ore_totali,p.note FROM `{$t}` p LEFT JOIN {$wpdb->users} u ON u.ID=p.user_id LEFT JOIN `{$tc}` c ON c.id=p.cantiere_id ORDER BY p.data DESC",ARRAY_A);
    echo mssex_tbl(array('Operaio','Cantiere','Data','Ore','Note'),$r); exit;
});

/* ── Appuntamenti ── */
add_action('wp_ajax_mssex_csv_appuntamenti',function(){
    mssex_chk_nonce(); global $wpdb;
    $t=$wpdb->prefix.'mssg_agenda_blocchi'; $tc=$wpdb->prefix.'mssg_cantieri';
    if($wpdb->get_var("SHOW TABLES LIKE '{$t}'")!==$t){echo 'Nessuna tabella.';exit;}
    $r=$wpdb->get_results("SELECT b.tipo,b.titolo_interno,u.display_name,c.nome,b.data_ora_inizio,b.data_ora_fine,COALESCE(b.luogo,'') FROM `{$t}` b LEFT JOIN {$wpdb->users} u ON u.ID=b.cliente_id LEFT JOIN `{$tc}` c ON c.id=b.cantiere_id WHERE b.tipo!='rifiutato' ORDER BY b.data_ora_inizio DESC",ARRAY_A);
    mssex_csv_send('appuntamenti-'.date('Y-m-d').'.csv');
    $f=fopen('php://output','w'); fputcsv($f,array('Tipo','Titolo','Partecipante','Cantiere','Inizio','Fine','Luogo'));
    foreach($r as $row) fputcsv($f,array_values($row)); fclose($f); exit;
});
add_action('wp_ajax_mssex_pdf_appuntamenti',function(){
    mssex_chk_nonce(); global $wpdb;
    $t=$wpdb->prefix.'mssg_agenda_blocchi'; $tc=$wpdb->prefix.'mssg_cantieri';
    if($wpdb->get_var("SHOW TABLES LIKE '{$t}'")!==$t){echo '<p>Nessun dato.</p>';exit;}
    $r=$wpdb->get_results("SELECT b.tipo,b.titolo_interno,u.display_name,c.nome,b.data_ora_inizio,b.data_ora_fine FROM `{$t}` b LEFT JOIN {$wpdb->users} u ON u.ID=b.cliente_id LEFT JOIN `{$tc}` c ON c.id=b.cantiere_id WHERE b.tipo!='rifiutato' ORDER BY b.data_ora_inizio DESC",ARRAY_A);
    echo mssex_tbl(array('Tipo','Titolo','Partecipante','Cantiere','Inizio','Fine'),$r); exit;
});

/* ── Backup totale: ZIP con tutti i CSV ── */
add_action('wp_ajax_mssex_backup_totale', function(){
    mssex_chk_nonce();
    if(!class_exists('ZipArchive')){ wp_die('ZipArchive non disponibile sul server.'); }
    global $wpdb;
    $tc=$wpdb->prefix.'mssg_cantieri'; $tb=$wpdb->prefix.'mssg_agenda_blocchi';
    $tp=$wpdb->prefix.'mssg_presenze'; $tcu=$wpdb->prefix.'mssg_cantieri_users';

    $zip=new ZipArchive();
    $tmp=tempnam(sys_get_temp_dir(),'mssex_').'.zip';
    $zip->open($tmp, ZipArchive::CREATE|ZipArchive::OVERWRITE);

    /* Helper: genera CSV string */
    $mkcsv=function($headers,$rows){
        $buf=fopen('php://temp','r+');
        fwrite($buf,"ï»¿");
        fputcsv($buf,$headers);
        foreach($rows as $r) fputcsv($buf,array_values($r));
        rewind($buf); $s=stream_get_contents($buf); fclose($buf); return $s;
    };

    /* Cantieri */
    $r=$wpdb->get_results("SELECT nome,indirizzo,citta,cap,stato,data_inizio,data_fine_prevista FROM `{$tc}` WHERE deleted_at IS NULL",ARRAY_A);
    $zip->addFromString('cantieri.csv',$mkcsv(array('Nome','Indirizzo','Città','CAP','Stato','Inizio','Fine Prevista'),$r));

    /* Clienti */
    $users=get_users(array('role'=>'mssg_cliente','orderby'=>'display_name'));
    $cl_rows=array(); foreach($users as $u){ $meta=get_user_meta($u->ID); $cl_rows[]=array($u->display_name,$u->user_email,isset($meta['mssgcl_telefono'][0])?$meta['mssgcl_telefono'][0]:'',isset($meta['mssgcl_citta'][0])?$meta['mssgcl_citta'][0]:'',isset($meta['mssgcl_indirizzo'][0])?$meta['mssgcl_indirizzo'][0]:''); }
    $zip->addFromString('clienti.csv',$mkcsv(array('Nome','Email','Telefono','Città','Indirizzo'),$cl_rows));

    /* Personale */
    $staff=get_users(array('role__in'=>array('administrator','mssg_admin','mssg_capo','mssg_operaio'),'orderby'=>'display_name'));
    $st_rows=array(); foreach($staff as $u){$ud=get_userdata($u->ID);$st_rows[]=array($u->display_name,$u->user_email,implode(',',array_keys($ud->caps??array())));}
    $zip->addFromString('personale.csv',$mkcsv(array('Nome','Email','Ruolo'),$st_rows));

    /* Presenze */
    if($wpdb->get_var("SHOW TABLES LIKE '{$tp}'") === $tp){
        $r=$wpdb->get_results("SELECT u.display_name,c.nome,p.data,p.ore_totali,p.note FROM `{$tp}` p LEFT JOIN {$wpdb->users} u ON u.ID=p.user_id LEFT JOIN `{$tc}` c ON c.id=p.cantiere_id ORDER BY p.data DESC",ARRAY_A);
        $zip->addFromString('presenze.csv',$mkcsv(array('Operaio','Cantiere','Data','Ore','Note'),$r));
    }

    /* Appuntamenti */
    if($wpdb->get_var("SHOW TABLES LIKE '{$tb}'") === $tb){
        $r=$wpdb->get_results("SELECT b.tipo,b.titolo_interno,u.display_name,c.nome,b.data_ora_inizio,b.data_ora_fine FROM `{$tb}` b LEFT JOIN {$wpdb->users} u ON u.ID=b.cliente_id LEFT JOIN `{$tc}` c ON c.id=b.cantiere_id WHERE b.tipo!='rifiutato' ORDER BY b.data_ora_inizio DESC",ARRAY_A);
        $zip->addFromString('appuntamenti.csv',$mkcsv(array('Tipo','Titolo','Partecipante','Cantiere','Inizio','Fine'),$r));
    }

    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="mss-backup-'.date('Y-m-d').'.zip"');
    header('Content-Length: '.filesize($tmp));
    readfile($tmp);
    unlink($tmp);
    exit;
});