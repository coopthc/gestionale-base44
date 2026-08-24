<?php
/**
 * Plugin Name:       MySite Login UI
 * Plugin URI:        https://secretpride.it
 * Description:       Login, registrazione, recupero password, area utente personalizzata. 3 temi, slot pubblicitari con programmazione, export CSV/PDF, gestione account GDPR.
 * Version:           1.5.0
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Author:            Web.CoopTHC
 * Author URI:        https://secretpride.it
 * License:           Proprietary
 * Text Domain:       mss-login-ui
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MSSLU_VERSION', '1.5.0' );
define( 'MSSLU_PATH',    plugin_dir_path( __FILE__ ) );
define( 'MSSLU_URL',     plugin_dir_url( __FILE__ ) );
define( 'MSSLU_SLUG',    'mss-login-ui' );

require_once MSSLU_PATH . 'admin/settings.php';
require_once MSSLU_PATH . 'admin/google-login.php';
require_once MSSLU_PATH . 'admin/banners/ad-slots.php';
require_once MSSLU_PATH . 'admin/shortcodes.php';
require_once MSSLU_PATH . 'admin/account.php';
require_once MSSLU_PATH . 'admin/account-delete.php';
require_once MSSLU_PATH . 'admin/export.php';
require_once MSSLU_PATH . 'admin/brevo.php';
require_once MSSLU_PATH . 'admin/woo-override.php';

/* ── Enqueue frontend assets ──────────────────────────── */
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style(  'mss-login-ui', MSSLU_URL . 'assets/css/login-ui.css', array(), MSSLU_VERSION );
    wp_enqueue_script( 'mss-login-ui', MSSLU_URL . 'assets/js/login-ui.js', array('jquery'), MSSLU_VERSION, true );
    wp_localize_script( 'mss-login-ui', 'MSSLU', array(
        'ajax_url'          => admin_url('admin-ajax.php'),
        'nonce'             => wp_create_nonce('msslu_nonce'),
        'redirect_login'    => msslu_get_option('redirect_login',    home_url('/account')),
        'redirect_register' => msslu_get_option('redirect_register', home_url('/account')),
        'home_url'          => home_url(),
        'strings'           => array(
            'disable_confirm' => "Sei sicuro di voler disabilitare il tuo account?\nPotrai riattivarlo contattando l'assistenza.",
            'delete_warning'  => "ATTENZIONE: azione irreversibile.\nTutti i dati personali verranno eliminati.",
            'upload_error'    => 'Errore nel caricamento. Riprova.',
        ),
    ));
} );

/* ══════════════════════════════════════════════════════════════
   LOGIN/REGISTRAZIONE IMMERSIVI — nasconde header/footer del tema
   sulla pagina di accesso, così come già avviene per il gestionale
   (v. "GESTIONALE FULLSCREEN" in mssg-cantieri.php). Prima di questa
   modifica l'esperienza "app" isolata scattava solo DOPO il login;
   la schermata di accesso restava incorniciata dal tema (header,
   menu, footer del sito), rompendo la sensazione di app nativa
   proprio nel primo momento in cui l'utente la incontra. Riusa la
   stessa tecnica "DOM-walk" — nasconde i fratelli dell'elemento del
   form risalendo fino a <body> — perché funziona con qualunque tema,
   senza dover elencare a mano le classi CSS specifiche del tema in uso. ══════════════════════════════════════════════════════════════ */
add_action( 'wp_head', function() {
    if ( is_user_logged_in() ) return; // dopo il login se ne occupa il gestionale
    global $post;
    if ( ! is_a( $post, 'WP_Post' ) ) return;
    $is_login    = has_shortcode( $post->post_content, 'wcthc_login' );
    $is_register = has_shortcode( $post->post_content, 'wcthc_register' );
    if ( ! $is_login && ! $is_register ) return;
    ?>
    <style id="msslu-login-fullscreen-css">
    html,body{background:#0d0d1a!important;min-height:100vh}
    body{display:flex!important;align-items:center;justify-content:center;padding:20px;box-sizing:border-box}
    .msslu-login-wrap,.msslu-register-wrap{width:100%;max-width:440px;margin:0!important}
    .msslu-box{
        background:#13131f;border:0.5px solid rgba(255,255,255,.08);border-radius:18px;
        box-shadow:0 20px 60px rgba(0,0,0,.5);padding:36px 30px
    }
    </style>
    <script id="msslu-login-fullscreen-js">
    (function(){
        function hideThemeChrome(){
            var anchor=document.querySelector('.msslu-login-wrap,.msslu-register-wrap');
            if(!anchor) return;
            var node=anchor;
            while(node&&node!==document.body){
                var par=node.parentElement; if(!par) break;
                Array.prototype.forEach.call(par.children,function(sibling){
                    if(sibling===node) return;
                    var tag=(sibling.tagName||'').toUpperCase();
                    if('SCRIPT STYLE LINK NOSCRIPT META'.indexOf(tag)!==-1) return;
                    sibling.style.setProperty('display','none','important');
                });
                node=par;
            }
            /* Elementi fissi/sticky (barre annuncio, header sticky) fuori dal contenitore principale */
            document.querySelectorAll('body *').forEach(function(el){
                if(el.closest('.msslu-login-wrap,.msslu-register-wrap')) return;
                var pos=window.getComputedStyle(el).position;
                if(pos==='fixed'||pos==='sticky') el.style.setProperty('display','none','important');
            });
        }
        if (document.readyState==='loading') {
            document.addEventListener('DOMContentLoaded', hideThemeChrome);
        } else {
            hideThemeChrome();
        }
    })();
    </script>
    <?php
});

/* ── Modal in footer ──────────────────────────────────── */
add_action( 'wp_footer', 'msslu_render_footer_modal' );

function msslu_render_footer_modal() {
    if ( ! is_user_logged_in() ) {
        echo '<div id="msslu-modal-overlay" class="msslu-modal-overlay">';
        echo '<div class="msslu-modal">';
        echo '<button class="msslu-modal-close" aria-label="Chiudi">&times;</button>';
        echo '<div data-msslu-panel="login">'  . do_shortcode('[wcthc_login]') . '</div>';
        echo '<div data-msslu-panel="register" style="display:none;">' . do_shortcode('[wcthc_register]') . '</div>';
        echo '</div>';
        echo '</div>';
    } else {
        msslu_render_account_modal();
    }
}

function msslu_render_account_modal() {
    $user       = wp_get_current_user();
    $has_woo    = class_exists('WooCommerce');
    $nickname   = get_user_meta($user->ID, 'msslu_nickname', true);
    if ( ! $nickname ) $nickname = $user->display_name;
    $avatar_url = get_user_meta($user->ID, 'msslu_avatar_url', true);
    $avatar_src = $avatar_url ? $avatar_url : get_avatar_url($user->ID, array('size'=>72));

    $sections = array(
        'dashboard' => array('icon'=>'grid',     'label'=>'Dashboard'),
        'details'   => array('icon'=>'user',      'label'=>'Dati personali'),
        'address'   => array('icon'=>'pin',       'label'=>'Indirizzo'),
        'password'  => array('icon'=>'key',       'label'=>'Password'),
        'manage'    => array('icon'=>'settings',  'label'=>'Gestione account'),
    );
    if ( ! $has_woo ) {
        unset($sections['address']);
    }
    $sections = apply_filters('msslu_account_sections', $sections);

    echo '<div id="msslu-modal-overlay" class="msslu-modal-overlay">';
    echo '<div class="msslu-modal msslu-modal--account">';
    echo '<button class="msslu-modal-close" aria-label="Chiudi">&times;</button>';
    echo '<div data-msslu-panel="account">';
    echo '<div class="msslu-wrap msslu-account-wrap">';
    echo '<div class="msslu-account-layout">';

    // Sidebar
    echo '<aside class="msslu-account-sidebar">';
    echo '<div class="msslu-account-avatar">';
    echo '<div class="msslu-avatar-wrap"><img src="' . esc_url($avatar_src) . '" alt="" class="msslu-avatar-img" id="msslu-modal-avatar"></div>';
    echo '<div class="msslu-account-nickname">' . esc_html($nickname) . '</div>';
    echo '<div class="msslu-account-email">' . esc_html($user->user_email) . '</div>';
    echo '</div>';
    echo '<nav class="msslu-account-nav">';
    foreach ( $sections as $key => $item ) {
        $active = $key === 'dashboard' ? 'active' : '';
        echo '<button type="button" class="msslu-nav-item ' . esc_attr($active) . '" data-section="' . esc_attr($key) . '">';
        echo '<span class="msslu-nav-icon">' . msslu_icon($item['icon']) . '</span>';
        echo '<span class="msslu-nav-label">' . esc_html($item['label']) . (!empty($item['badge']) ? '<span class="msslu-nav-badge">' . (int)$item['badge'] . '</span>' : '') . '</span>';
        echo '</button>';
    }
    echo '<a href="' . esc_url(wp_logout_url(home_url())) . '" class="msslu-nav-item msslu-nav-logout">';
    echo '<span class="msslu-nav-icon">' . msslu_icon('logout') . '</span>';
    echo '<span class="msslu-nav-label">Esci</span>';
    echo '</a>';
    echo '</nav>';
    echo '</aside>';

    // Main
    echo '<main class="msslu-account-main" id="msslu-modal-section-main">';
    msslu_section_dashboard($user, $has_woo);
    echo '</main>';

    echo '</div>'; // account-layout
    echo '</div>'; // account-wrap
    echo '</div>'; // data-msslu-panel
    echo '</div>'; // modal
    echo '</div>'; // overlay
}

/* ── AJAX: load section ───────────────────────────────── */
add_action( 'wp_ajax_msslu_load_section', function() {
    if ( ! wp_verify_nonce( isset($_POST['nonce']) ? $_POST['nonce'] : '', 'msslu_nonce' ) ) {
        wp_send_json_error(array('msg'=>'Nonce non valido.'));
    }
    if ( ! is_user_logged_in() ) {
        wp_send_json_error(array('msg'=>'Non autenticato.'));
    }

    $section = sanitize_key( isset($_POST['section']) ? $_POST['section'] : 'dashboard' );
    $user    = wp_get_current_user();
    $has_woo = class_exists('WooCommerce');

    // Parametri extra passati dal JS (es. inf_chat per pannello admin)
    if ( ! empty($_POST['section_params']) && is_array($_POST['section_params']) ) {
        foreach ( $_POST['section_params'] as $k => $v ) {
            $k = sanitize_key($k);
            $_GET[$k] = sanitize_text_field($v);
        }
    }

    ob_start();
    switch ($section) {
        case 'dashboard': msslu_section_dashboard($user, $has_woo); break;
        case 'details':   msslu_section_details($user);              break;
        case 'address':   msslu_section_address($user);              break;
        case 'password':  msslu_section_password($user);             break;
        case 'manage':    msslu_section_account_management($user);   break;
        default:
            /* Sezioni esterne — es. mssg_area_cliente dal plugin gestionale */
            $extra = apply_filters('msslu_section_html_' . $section, null, $user);
            if ($extra !== null) echo $extra;
            else msslu_section_dashboard($user, $has_woo);
    }
    $html = ob_get_clean();

    $nickname   = get_user_meta($user->ID, 'msslu_nickname', true);
    if ( ! $nickname ) $nickname = $user->display_name;
    $avatar_url = get_user_meta($user->ID, 'msslu_avatar_url', true);
    $avatar_src = $avatar_url ? $avatar_url : get_avatar_url($user->ID, array('size'=>72));

    wp_send_json_success(array(
        'html'     => $html,
        'nickname' => esc_html($nickname),
        'avatar'   => esc_url($avatar_src),
    ));
} );

/* ── AJAX: save account form ──────────────────────────── */
add_action( 'wp_ajax_msslu_account_action', function() {
    if ( ! wp_verify_nonce( isset($_POST['nonce']) ? $_POST['nonce'] : '', 'msslu_nonce' ) ) {
        wp_send_json_error(array('msg'=>'Nonce non valido.'));
    }
    if ( ! is_user_logged_in() ) {
        wp_send_json_error(array('msg'=>'Non autenticato.'));
    }
    if ( ! wp_verify_nonce( isset($_POST['msslu_account_nonce']) ? $_POST['msslu_account_nonce'] : '', 'msslu_account' ) ) {
        wp_send_json_error(array('msg'=>'Sessione scaduta.'));
    }
    $user   = wp_get_current_user();
    $result = msslu_handle_account_forms($user);
    if ( $result && $result['type'] === 'success' ) {
        wp_send_json_success(array('msg'=>$result['msg']));
    } else {
        wp_send_json_error(array('msg'=>$result ? $result['msg'] : 'Errore sconosciuto.'));
    }
} );

/* ── MySite Suite bridge ──────────────────────────────── */
add_action( 'plugins_loaded', function() {
    if ( function_exists('mss_register_module') ) {
        mss_register_module( 'mss-login-ui', array(
            'version' => MSSLU_VERSION,
            'name'    => 'MySite Login UI',
            'path'    => MSSLU_PATH,
            'url'     => MSSLU_URL,
        ));
    }
}, 20 );

/* ── Activation / Deactivation ───────────────────────── */
register_activation_hook( __FILE__, function() {
    msslu_create_pages();
    flush_rewrite_rules();
} );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

function msslu_create_pages() {
    $pages = array(
        array('option'=>'msslu_page_account',       'title'=>'Account',           'slug'=>'account',           'content'=>'[wcthc_account]',      'opt_key'=>'redirect_login'),
        array('option'=>'msslu_page_login',         'title'=>'Accedi',            'slug'=>'accedi',            'content'=>'[wcthc_login]',         'opt_key'=>null),
        array('option'=>'msslu_page_register',      'title'=>'Registrati',        'slug'=>'registrati',        'content'=>'[wcthc_register]',      'opt_key'=>null),
        array('option'=>'msslu_page_lost_password', 'title'=>'Recupera password', 'slug'=>'recupera-password', 'content'=>'[wcthc_lost_password]', 'opt_key'=>'lost_password_page'),
    );
    $opts = get_option('msslu_options', array());
    foreach ( $pages as $page ) {
        if ( get_option($page['option']) ) continue;
        $existing = get_page_by_path($page['slug']);
        if ( $existing ) {
            update_option($page['option'], $existing->ID);
            if ($page['opt_key'] && empty($opts[$page['opt_key']])) {
                $opts[$page['opt_key']] = get_permalink($existing->ID);
            }
            continue;
        }
        $pid = wp_insert_post(array(
            'post_title'   => $page['title'],
            'post_name'    => $page['slug'],
            'post_content' => $page['content'],
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ));
        if ( $pid && ! is_wp_error($pid) ) {
            update_option($page['option'], $pid);
            if ($page['opt_key'] && empty($opts[$page['opt_key']])) {
                $opts[$page['opt_key']] = get_permalink($pid);
            }
        }
    }
    update_option('msslu_options', $opts);
}
