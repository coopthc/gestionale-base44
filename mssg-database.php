<?php
/**
 * Plugin Name:       MSS Gestionale — Database
 * Description:       Installa e aggiorna tutte le tabelle del gestionale. Va attivato prima di qualsiasi modulo. Richiede mss-gestionale ≥ 1.0.0.
 * Version:           1.0.0
 * Author:            Web.CoopTHC
 * License:           Proprietary
 * Text Domain:       mssg-database
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MSSGDB_VERSION',    '1.0.0' );
define( 'MSSGDB_DB_VERSION', '1.0.0' );   // incrementa ad ogni migration
define( 'MSSGDB_PATH',       plugin_dir_path( __FILE__ ) );
define( 'MSSGDB_OPTION',     'mssg_db_version' );

require_once MSSGDB_PATH . 'includes/migrator.php';
require_once MSSGDB_PATH . 'includes/seeder.php';
require_once MSSGDB_PATH . 'includes/queries.php';

/* ══ Boot ══════════════════════════════════════════════════ */
add_action( 'plugins_loaded', 'mssgdb_boot', 5 );   // priority 5: prima del core

function mssgdb_boot() {
    // Esegue eventuali migration pendenti ad ogni aggiornamento
    if ( get_option( MSSGDB_OPTION ) !== MSSGDB_DB_VERSION ) {
        mssgdb_run_all_migrations();
        update_option( MSSGDB_OPTION, MSSGDB_DB_VERSION );
    }
}

/* ══ Attivazione ════════════════════════════════════════════ */
register_activation_hook( __FILE__, 'mssgdb_activate' );

function mssgdb_activate() {
    mssgdb_run_all_migrations();
    update_option( MSSGDB_OPTION, MSSGDB_DB_VERSION );
}

/* ══ Admin: pagina stato DB ════════════════════════════════ */
add_action( 'mssg_admin_submenu', function() {
    add_submenu_page(
        'mss-gestionale',
        'Database',
        'Database',
        'manage_options',
        'mssg-database',
        'mssgdb_admin_page'
    );
});

function mssgdb_admin_page() {
    global $wpdb;
    if ( ! current_user_can( 'manage_options' ) ) wp_die();

    $tables = mssgdb_get_all_table_names();
    ?>
    <div class="wrap">
        <h1>MSS Gestionale — Database</h1>
        <p>Versione schema installata: <code><?php echo esc_html( get_option( MSSGDB_OPTION, '—' ) ); ?></code>
           &nbsp;|&nbsp; Versione attuale: <code><?php echo MSSGDB_DB_VERSION; ?></code></p>

        <h2>Tabelle</h2>
        <table class="widefat striped" style="max-width:680px">
            <thead><tr><th>Tabella</th><th>Righe</th><th>Stato</th></tr></thead>
            <tbody>
            <?php foreach ( $tables as $t ) :
                $exists = $wpdb->get_var("SHOW TABLES LIKE '{$t}'") === $t;
                $count  = $exists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$t}`") : 0;
            ?>
                <tr>
                    <td><code><?php echo esc_html( $t ); ?></code></td>
                    <td><?php echo $exists ? $count : '—'; ?></td>
                    <td><?php echo $exists
                        ? '<span style="color:#22c55e">✓ OK</span>'
                        : '<span style="color:#ef4444">✗ Mancante</span>'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-top:16px">
            <a href="<?php echo wp_nonce_url( admin_url('admin-post.php?action=mssgdb_repair'), 'mssgdb_repair' ); ?>"
               class="button button-primary">
                Riesegui migration / Ripara tabelle
            </a>
        </p>
    </div>
    <?php
}

add_action( 'admin_post_mssgdb_repair', function() {
    check_admin_referer( 'mssgdb_repair' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die();
    mssgdb_run_all_migrations();
    update_option( MSSGDB_OPTION, MSSGDB_DB_VERSION );
    wp_redirect( admin_url( 'admin.php?page=mssg-database&repaired=1' ) );
    exit;
});

function mssgdb_get_all_table_names() {
    global $wpdb;
    $p = $wpdb->prefix . 'mssg_';
    return array(
        $p . 'cantieri',
        $p . 'cantieri_users',
        $p . 'lavorazioni',
        $p . 'personale',
        $p . 'presenze',
        $p . 'preventivi',
        $p . 'prev_voci',
        $p . 'fatture',
        $p . 'fatture_voci',
        $p . 'materiali',
        $p . 'materiali_mov',
        $p . 'documenti',
        $p . 'chat_messaggi',
    );
}

// Bottone seed nella pagina admin
add_action( 'admin_notices', function() {
    if ( isset( $_GET['seeded'] ) && $_GET['page'] === 'mssg-database' ) {
        echo '<div class="notice notice-success is-dismissible"><p>Dati demo caricati correttamente.</p></div>';
    }
    if ( isset( $_GET['repaired'] ) && $_GET['page'] === 'mssg-database' ) {
        echo '<div class="notice notice-success is-dismissible"><p>Migration eseguite correttamente.</p></div>';
    }
});
