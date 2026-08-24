<?php
/**
 * Plugin Name: MSS Debug
 * Description: Mostra errori PHP e log direttamente nell'admin WP. Disattiva dopo l'uso.
 * Version: 1.0.0
 * Author: Web.CoopTHC
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── SICUREZZA: questo plugin stampa a schermo errori PHP con percorsi file,
   query e stack di esecuzione — informazioni sensibili in un sito in produzione.
   Prima di questa correzione, "display_errors"/"error_reporting(E_ALL)" venivano
   attivati SEMPRE, anche sul front-end pubblico (area clienti, login, ecc.),
   quindi un qualunque visitatore anonimo che avesse fatto scattare un warning PHP
   avrebbe potuto vedere l'errore stampato direttamente nella pagina.
   Ora la visualizzazione a schermo degli errori è attiva SOLO se:
     1) siamo in area wp-admin (is_admin()) — il front-end del gestionale (dove
        operano clienti e collaboratori) non passa mai da qui — E
     2) l'utente corrente è un amministratore (manage_options).
   La raccolta/registrazione degli errori (per la pagina "MSS Debug" e per
   l'opzione mssg_debug_fatal) resta invece sempre attiva, così l'admin può
   comunque diagnosticare problemi capitati sul front-end, senza però che gli
   errori vengano MAI stampati a video per chi non è amministratore. */
$mssg_debug_can_display = is_admin() && function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );

if ( $mssg_debug_can_display ) {
    @ini_set( 'display_errors', 1 );
    @ini_set( 'display_startup_errors', 1 );
}
@error_reporting( E_ALL );

/* ── Cattura errori in un buffer ───────────────── */
$GLOBALS['mssg_debug_errors'] = array();

set_error_handler( function( $errno, $errstr, $errfile, $errline ) {
    $GLOBALS['mssg_debug_errors'][] = array(
        'type'    => $errno,
        'message' => $errstr,
        'file'    => str_replace( ABSPATH, '', $errfile ),
        'line'    => $errline,
    );
    return false; // lascia proseguire il gestore normale
});

register_shutdown_function( function() {
    $e = error_get_last();
    if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ) ) ) {
        // Fatal error — lo scrive in un'opzione WP così lo vediamo nell'admin
        update_option( 'mssg_debug_fatal', array(
            'message' => $e['message'],
            'file'    => str_replace( ABSPATH, '', $e['file'] ),
            'line'    => $e['line'],
            'time'    => current_time( 'mysql' ),
        ));
    }
});

/* ── Promemoria persistente: questo plugin va disattivato in produzione ── */
add_action( 'admin_notices', function() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    ?>
    <div class="notice notice-warning is-dismissible">
        <p><strong>MSS Debug è attivo.</strong> Questo plugin mostra informazioni diagnostiche (errori PHP, percorsi file, stato database). Disattivalo quando il sito è in produzione e non ti serve più per il debug.</p>
    </div>
    <?php
});

/* ── Admin notice con tutti gli errori ─────────── */
add_action( 'admin_notices', function() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    // Fatal error salvato dallo shutdown
    $fatal = get_option( 'mssg_debug_fatal' );
    if ( $fatal ) : ?>
    <div class="notice notice-error">
        <p><strong>MSS Debug — Fatal Error</strong> (<?php echo esc_html( $fatal['time'] ); ?>)</p>
        <pre style="background:#1a1a1a;color:#ff6b6b;padding:12px;border-radius:6px;overflow:auto;font-size:12px;margin:6px 0"><?php
            echo esc_html( $fatal['message'] ) . "\n";
            echo 'File: ' . esc_html( $fatal['file'] ) . ' (riga ' . (int)$fatal['line'] . ')';
        ?></pre>
        <p>
            <a href="<?php echo esc_url( admin_url('?mssg_clear_fatal=1') ); ?>" class="button">Cancella errore</a>
        </p>
    </div>
    <?php endif;

    // Errori runtime accumulati
    if ( ! empty( $GLOBALS['mssg_debug_errors'] ) ) :
        $labels = array(
            E_WARNING     => 'Warning',
            E_NOTICE      => 'Notice',
            E_USER_ERROR  => 'User Error',
            E_USER_WARNING=> 'User Warning',
            E_DEPRECATED  => 'Deprecated',
        );
    ?>
    <div class="notice notice-warning" style="max-height:300px;overflow:auto">
        <p><strong>MSS Debug — <?php echo count($GLOBALS['mssg_debug_errors']); ?> errori PHP questa pagina</strong></p>
        <?php foreach ( array_slice($GLOBALS['mssg_debug_errors'], 0, 20) as $err ) :
            $label = $labels[$err['type']] ?? 'Error #'.$err['type'];
        ?>
        <div style="border-left:3px solid #f0b429;padding:4px 10px;margin:4px 0;font-size:12px;font-family:monospace">
            <strong><?php echo esc_html($label); ?>:</strong>
            <?php echo esc_html($err['message']); ?>
            <span style="color:#888"> — <?php echo esc_html($err['file']); ?>:<?php echo (int)$err['line']; ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif;
});

// Cancella il fatal salvato
add_action( 'admin_init', function() {
    if ( isset($_GET['mssg_clear_fatal']) && current_user_can('manage_options') ) {
        delete_option('mssg_debug_fatal');
        wp_redirect( admin_url() );
        exit;
    }
});

/* ── Pagina log con info sistema ───────────────── */
add_action( 'admin_menu', function() {
    add_management_page(
        'MSS Debug',
        'MSS Debug',
        'manage_options',
        'mssg-debug',
        'mssg_debug_page'
    );
});

function mssg_debug_page() {
    if ( ! current_user_can('manage_options') ) return;
    global $wpdb;
    ?>
    <div class="wrap">
        <h1>MSS Debug — Informazioni sistema</h1>

        <h2>Versione PHP e WP</h2>
        <table class="widefat striped" style="max-width:600px">
            <tr><td><strong>PHP</strong></td><td><?php echo phpversion(); ?></td></tr>
            <tr><td><strong>WordPress</strong></td><td><?php echo get_bloginfo('version'); ?></td></tr>
            <tr><td><strong>MySQL</strong></td><td><?php echo $wpdb->db_version(); ?></td></tr>
            <tr><td><strong>upload_max_filesize</strong></td><td><?php echo ini_get('upload_max_filesize'); ?></td></tr>
            <tr><td><strong>post_max_size</strong></td><td><?php echo ini_get('post_max_size'); ?></td></tr>
            <tr><td><strong>max_execution_time</strong></td><td><?php echo ini_get('max_execution_time'); ?>s</td></tr>
            <tr><td><strong>WP_DEBUG</strong></td><td><?php echo defined('WP_DEBUG') && WP_DEBUG ? '✅ ON' : '❌ OFF'; ?></td></tr>
            <tr><td><strong>WP upload dir</strong></td><td><?php $u=wp_upload_dir();echo esc_html($u['basedir']); ?></td></tr>
            <tr><td><strong>Upload dir scrivibile</strong></td><td><?php $u=wp_upload_dir();echo is_writable($u['basedir'])?'✅ Sì':'❌ No (problema upload!)'; ?></td></tr>
        </table>

        <h2>Plugin MSS attivi</h2>
        <table class="widefat striped" style="max-width:600px">
            <thead><tr><th>Plugin</th><th>Versione</th><th>Stato</th></tr></thead>
            <tbody>
            <?php
            $mss_plugins = array(
                'mss-gestionale/mss-gestionale.php'     => 'MSS Gestionale Core',
                'mssg-cantieri/mssg-cantieri.php'        => 'Cantieri v2',
                'mssg-cantieri-v3/mssg-cantieri.php'     => 'Cantieri v3',
                'mssg-clienti/mssg-clienti.php'          => 'Clienti',
                'mssg-clienti-completo/mssg-clienti.php' => 'Clienti Completo',
                'mssg-database/mssg-database.php'        => 'Database',
                'mssg-personale/mssg-personale.php'      => 'Personale',
                'mss-login-ui/mss-login-ui.php'          => 'Login UI',
            );
            foreach ( $mss_plugins as $file => $name ) :
                $active = is_plugin_active( $file );
                $data   = $active ? get_plugin_data( WP_PLUGIN_DIR . '/' . $file ) : null;
            ?>
            <tr>
                <td><?php echo esc_html($name); ?></td>
                <td><?php echo $data ? esc_html($data['Version']) : '—'; ?></td>
                <td><?php echo $active ? '<span style="color:#22c55e">✅ Attivo</span>' : '<span style="color:#94a3b8">— Non installato</span>'; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <h2>Tabelle MSS nel database</h2>
        <table class="widefat striped" style="max-width:600px">
            <thead><tr><th>Tabella</th><th>Righe</th><th>Esiste</th></tr></thead>
            <tbody>
            <?php
            $tables = array('cantieri','cantieri_users','lavorazioni','personale','presenze','preventivi','prev_voci','fatture','fatture_voci','materiali','materiali_mov','documenti','chat_messaggi','cantieri_chat','avanzamenti','appuntamenti','media');
            foreach ( $tables as $t ) :
                $full = $wpdb->prefix . 'mssg_' . $t;
                $exists = $wpdb->get_var("SHOW TABLES LIKE '{$full}'") === $full;
                $count  = $exists ? (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$full}`") : 0;
            ?>
            <tr>
                <td><code><?php echo esc_html($full); ?></code></td>
                <td><?php echo $exists ? $count : '—'; ?></td>
                <td><?php echo $exists ? '<span style="color:#22c55e">✅</span>' : '<span style="color:#ef4444">❌ Mancante</span>'; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <h2>Test AJAX</h2>
        <p>Clicca per testare che gli AJAX del gestionale rispondano:</p>
        <button id="mssg-test-ajax" class="button button-primary">Testa AJAX mssg_cantieri_list</button>
        <div id="mssg-ajax-result" style="margin-top:10px;font-family:monospace;font-size:12px"></div>
        <script>
        jQuery('#mssg-test-ajax').on('click', function() {
            var $r = jQuery('#mssg-ajax-result');
            $r.html('Chiamata in corso…');
            jQuery.post(ajaxurl, {
                action: 'mssg_cantieri_list',
                nonce: '<?php echo wp_create_nonce("mssg_nonce"); ?>',
                stato: 'tutti'
            }, function(r) {
                $r.html('<pre style="background:#1a1a1a;color:#22c55e;padding:10px;border-radius:6px;overflow:auto">'+JSON.stringify(r,null,2).substring(0,800)+'</pre>');
            }).fail(function(x) {
                $r.html('<pre style="background:#1a1a1a;color:#ff6b6b;padding:10px;border-radius:6px">Errore HTTP '+x.status+': '+x.responseText.substring(0,400)+'</pre>');
            });
        });
        </script>

        <?php
        $fatal = get_option('mssg_debug_fatal');
        if ( $fatal ) : ?>
        <h2 style="color:#ef4444">⚠️ Ultimo Fatal Error</h2>
        <pre style="background:#1a1a1a;color:#ff6b6b;padding:12px;border-radius:6px;font-size:12px"><?php
            echo esc_html($fatal['message'])."\n";
            echo 'File: '.esc_html($fatal['file']).' (riga '.(int)$fatal['line'].")\n";
            echo 'Quando: '.esc_html($fatal['time']);
        ?></pre>
        <a href="<?php echo esc_url(admin_url('?mssg_clear_fatal=1')); ?>" class="button">Cancella</a>
        <?php endif; ?>

    </div>
    <?php
}
