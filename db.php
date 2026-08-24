<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * DB — mssg-agenda
 *
 * Regole dbDelta (WordPress):
 *  1. Niente COMMENT sulle colonne — rompono il parser
 *  2. PRIMARY KEY  (id) — DUE spazi tra KEY e (
 *  3. KEY keyname (col) — un solo spazio, niente allineamenti
 *  4. Un'unica riga per colonna, niente spazi extra di allineamento
 */

function mssgag_table( $name ) {
    global $wpdb;
    return $wpdb->prefix . 'mssg_agenda_' . $name;
}

/* ── Orari lavorativi dell'admin ── */
function mssgag_db_create_orari() {
    global $wpdb;
    $t  = mssgag_table( 'orari' );
    $ch = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    /* giorno: 1=Lun 2=Mar 3=Mer 4=Gio 5=Ven 6=Sab 7=Dom */
    dbDelta( "CREATE TABLE `{$t}` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  giorno TINYINT UNSIGNED NOT NULL DEFAULT 1,
  ora_inizio TIME NOT NULL DEFAULT '09:00:00',
  ora_fine TIME NOT NULL DEFAULT '18:00:00',
  slot_min SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  attivo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (id),
  UNIQUE KEY uniq_admin_giorno (admin_id,giorno)
) {$ch};" );
}

/* ── Blocchi / appuntamenti agenda ── */
function mssgag_db_create_blocchi() {
    global $wpdb;
    $t  = mssgag_table( 'blocchi' );
    $ch = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    /*
     * tipo:
     *   interno    = blocco privato del titolare (cliente non vede dettagli)
     *   richiesta  = richiesta appuntamento inviata dal cliente, in attesa
     *   confermato = appuntamento confermato dall'admin
     *   rifiutato  = appuntamento rifiutato dall'admin
     *
     * titolo_interno: solo admin lo vede
     * nota_cliente:   testo inviato dal cliente alla richiesta
     * risposta_admin: eventuale nota di rifiuto inviata al cliente
     */
    dbDelta( "CREATE TABLE `{$t}` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  cliente_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  cantiere_id INT UNSIGNED NOT NULL DEFAULT 0,
  tipo ENUM('interno','richiesta','confermato','rifiutato') NOT NULL DEFAULT 'interno',
  data_ora_inizio DATETIME NOT NULL,
  data_ora_fine DATETIME NOT NULL,
  titolo_interno VARCHAR(255) NOT NULL DEFAULT '',
  luogo VARCHAR(255) NOT NULL DEFAULT '',
  nota_cliente TEXT,
  risposta_admin VARCHAR(255) NOT NULL DEFAULT '',
  ricorrente TINYINT(1) NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY idx_admin (admin_id),
  KEY idx_cliente (cliente_id),
  KEY idx_tipo (tipo),
  KEY idx_data_inizio (data_ora_inizio),
  KEY idx_data_fine (data_ora_fine)
) {$ch};" );
}

function mssgag_ensure_tables() {
    mssgag_db_create_orari();
    mssgag_db_create_blocchi();
    /* Aggiunge colonna luogo se mancante (installazioni precedenti) */
    global $wpdb;
    $tb = mssgag_table('blocchi');
    if ( $wpdb->get_var("SHOW TABLES LIKE '{$tb}'") === $tb ) {
        $cols = $wpdb->get_col("DESCRIBE `{$tb}`");
        if ( ! in_array('luogo', $cols, true) ) {
            $wpdb->query("ALTER TABLE `{$tb}` ADD COLUMN `luogo` VARCHAR(255) NOT NULL DEFAULT '' AFTER `titolo_interno`");
        }
        /* CORREZIONE: la funzionalità "Promemoria" (agenda.js, section-miei-lavori.php)
           inserisce/legge righe con tipo='promemoria' e le colonne notifica_email/
           notifica_minuti, ma né l'ENUM della colonna tipo né queste due colonne
           esistevano — quindi ogni salvataggio falliva (o veniva troncato a stringa
           vuota) e le query che selezionano notifica_email/notifica_minuti per nome
           avrebbero dato errore SQL "colonna sconosciuta". Aggiunte qui. */
        $col_tipo = $wpdb->get_row("SHOW COLUMNS FROM `{$tb}` LIKE 'tipo'");
        if ( $col_tipo && strpos( $col_tipo->Type, "'promemoria'" ) === false ) {
            $wpdb->query("ALTER TABLE `{$tb}` MODIFY COLUMN `tipo` ENUM('interno','richiesta','confermato','rifiutato','promemoria') NOT NULL DEFAULT 'interno'");
        }
        if ( ! in_array('notifica_email', $cols, true) ) {
            $wpdb->query("ALTER TABLE `{$tb}` ADD COLUMN `notifica_email` TINYINT(1) NOT NULL DEFAULT 0 AFTER `ricorrente`");
        }
        if ( ! in_array('notifica_minuti', $cols, true) ) {
            $wpdb->query("ALTER TABLE `{$tb}` ADD COLUMN `notifica_minuti` SMALLINT UNSIGNED NOT NULL DEFAULT 60 AFTER `notifica_email`");
        }
        if ( ! in_array('notifica_inviata', $cols, true) ) {
            $wpdb->query("ALTER TABLE `{$tb}` ADD COLUMN `notifica_inviata` TINYINT(1) NOT NULL DEFAULT 0 AFTER `notifica_minuti`");
        }
    }
}
add_action( 'plugins_loaded', 'mssgag_ensure_tables', 10 );

/* ══════════════════════════════════════════════════════════════
   HELPER: trova l'admin_id principale da mostrare ai clienti
══════════════════════════════════════════════════════════════ */
function mssgag_get_primary_admin_id() {
    /* Prima: opzione configurata manualmente */
    $configured = (int) get_option( 'mssgag_primary_admin_id', 0 );
    if ( $configured ) return $configured;

    /* Seconda: primo utente mssg_admin */
    $admins = get_users( array( 'role' => 'mssg_admin', 'number' => 1, 'fields' => array( 'ID' ) ) );
    if ( ! empty( $admins ) ) return (int) $admins[0]->ID;

    /* Fallback: primo administrator */
    $admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => array( 'ID' ) ) );
    if ( ! empty( $admins ) ) return (int) $admins[0]->ID;

    return 1;
}

/* ══════════════════════════════════════════════════════════════
   HELPER: orari lavorativi per admin
══════════════════════════════════════════════════════════════ */
function mssgag_get_orari( $admin_id ) {
    global $wpdb;
    $t = mssgag_table( 'orari' );
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t}'" ) !== $t ) return array();
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM `{$t}` WHERE admin_id=%d ORDER BY giorno ASC", $admin_id
    ));
    $map = array();
    foreach ( $rows as $r ) $map[ (int) $r->giorno ] = $r;
    return $map;
}

/* ══════════════════════════════════════════════════════════════
   HELPER: genera slot per una data
   Restituisce array di slot con: ts, time, datetime, ts_fine,
   free, tipo_blocco, blocco_id, titolo, passato
   Per il cliente: tipo_blocco è sempre null (nascosto)
══════════════════════════════════════════════════════════════ */
function mssgag_get_slots( $admin_id, $date_str, $for_admin = false, $blocchi_admin_id = null ) {
    /* $admin_id = chi ha configurato gli orari (può essere l'admin principale come fallback)
       $blocchi_admin_id = di chi caricare gli appuntamenti (l'utente corrente) */
    if ( $blocchi_admin_id === null ) $blocchi_admin_id = $admin_id;
    global $wpdb;
    $t_b = mssgag_table( 'blocchi' );
    $t_o = mssgag_table( 'orari' );

    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_o}'" ) !== $t_o ) return array();

    $ts_day = strtotime( $date_str );
    if ( ! $ts_day ) return array();

    /* Giorno della settimana ISO: 1=Lun...7=Dom */
    $giorno = (int) date( 'N', $ts_day );

    $orario = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM `{$t_o}` WHERE admin_id=%d AND giorno=%d AND attivo=1",
        $admin_id, $giorno
    ));
    if ( ! $orario ) return array();

    $slot_min  = max( 30, (int) $orario->slot_min );
    $ts_inizio = strtotime( $date_str . ' ' . $orario->ora_inizio );
    $ts_fine   = strtotime( $date_str . ' ' . $orario->ora_fine );
    $now       = time();

    /* Genera slot base */
    $slots = array();
    $ts    = $ts_inizio;
    while ( $ts < $ts_fine ) {
        $slots[] = array(
            'ts'          => $ts,
            'time'        => date( 'H:i', $ts ),
            'datetime'    => date( 'Y-m-d H:i:s', $ts ),
            'ts_fine'     => $ts + ( $slot_min * 60 ),
            'free'        => $ts > ( $now + 3600 ),
            'tipo_blocco' => null,
            'blocco_id'   => 0,
            'titolo'      => '',
            'passato'     => $ts <= $now,
        );
        $ts += ( $slot_min * 60 );
    }

    /* Marca slot occupati */
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_b}'" ) === $t_b ) {
        $data_start = $date_str . ' 00:00:00';
        $data_end   = $date_str . ' 23:59:59';
        $blocchi = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM `{$t_b}`
             WHERE ( admin_id = %d OR cliente_id = %d )
               AND data_ora_inizio <= %s AND data_ora_fine >= %s
               AND tipo != 'rifiutato'",
            $blocchi_admin_id, $blocchi_admin_id, $data_end, $data_start
        ));

        foreach ( $blocchi as $b ) {
            $b_start = strtotime( $b->data_ora_inizio );
            $b_fine  = strtotime( $b->data_ora_fine );
            foreach ( $slots as &$slot ) {
                if ( $slot['ts'] < $b_fine && $slot['ts_fine'] > $b_start ) {
                    $slot['free']        = false;
                    /* Distingue appuntamenti creati dall'admin da richieste cliente */
                    $is_admin_created    = (int)$b->created_by === (int)$admin_id;
                    $tipo_display = $b->tipo;
                    if ( $b->tipo === 'confermato' && $is_admin_created ) {
                        $tipo_display = 'admin_fissato'; /* colore diverso nel calendario */
                    }
                    $slot['tipo_blocco'] = $for_admin ? $tipo_display : 'occupato';
                    $slot['blocco_id']   = $for_admin ? (int) $b->id : 0;
                    $slot['titolo']      = $for_admin ? esc_html( $b->titolo_interno ) : '';
                    $slot['luogo']       = $for_admin ? esc_html( isset($b->luogo) ? $b->luogo : '' ) : '';
                    $slot['cliente_id']  = $for_admin ? (int) $b->cliente_id : 0;
                    $slot['created_by']  = $for_admin ? (int) $b->created_by : 0;
                    $slot['data_fine']   = $for_admin ? $b->data_ora_fine : '';
                }
            }
            unset( $slot );
        }
    }

    return $slots;
}

/* ══════════════════════════════════════════════════════════════
   AJAX — nonce check
   Standardizzato sul nonce core 'mssg_nonce' (invece del nonce locale
   'mssgag_nonce' usato in precedenza) per uniformità con gli altri
   moduli. Accetta anche il vecchio nonce per compatibilità con pagine
   già in cache al momento del deploy di questa modifica.
══════════════════════════════════════════════════════════════ */
function mssgag_check_nonce() {
    $nonce = isset( $_POST['nonce'] ) ? $_POST['nonce'] : ( isset( $_GET['nonce'] ) ? $_GET['nonce'] : '' );
    if ( ! wp_verify_nonce( $nonce, 'mssg_nonce' ) && ! wp_verify_nonce( $nonce, 'mssgag_nonce' ) ) {
        wp_send_json_error( array( 'msg' => 'Sessione scaduta. Ricarica la pagina.' ) );
    }
}

/* ── Carica slot settimana ── */
add_action( 'wp_ajax_mssgag_get_week', 'mssgag_ajax_get_week' );
add_action( 'wp_ajax_nopriv_mssgag_get_week', function() {
    wp_send_json_error( array( 'msg' => 'Non autenticato.' ) );
});
function mssgag_ajax_get_week() {
    mssgag_check_nonce();
    if ( ! is_user_logged_in() ) wp_send_json_error( array( 'msg' => 'Non autenticato.' ) );

    $uid        = get_current_user_id();
    $for_admin  = mssg_user_can( $uid, 'manage_agenda' );
    $admin_id   = $for_admin ? $uid : mssgag_get_primary_admin_id();
    $week_start = sanitize_text_field( $_POST['week_start'] ?? date( 'Y-m-d', strtotime( 'monday this week' ) ) );

    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $week_start ) ) {
        wp_send_json_error( array( 'msg' => 'Data non valida.' ) );
    }

    $orari     = mssgag_get_orari( $admin_id );

    /* Se questo admin non ha orari configurati, usa quelli dell'admin principale
       per mostrare la struttura del calendario (ma mantieni admin_id per gli appuntamenti propri) */
    $orari_admin_id = $admin_id;
    if ( empty( $orari ) && $for_admin ) {
        $primary = mssgag_get_primary_admin_id();
        if ( $primary && $primary !== $admin_id ) {
            $orari = mssgag_get_orari( $primary );
            $orari_admin_id = $primary;
        }
    }
    $no_orari_propri = empty( mssgag_get_orari( $admin_id ) );
    $nomi_short = array( 1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Gio', 5 => 'Ven', 6 => 'Sab', 7 => 'Dom' );
    $giorni    = array();

    for ( $i = 0; $i < 7; $i++ ) {
        $date_str = date( 'Y-m-d', strtotime( $week_start . " +{$i} days" ) );
        $giorno_n = (int) date( 'N', strtotime( $date_str ) );
        $slots    = mssgag_get_slots( $orari_admin_id, $date_str, $for_admin, $admin_id );

        $giorni[] = array(
            'date'       => $date_str,
            'label'      => ( $nomi_short[ $giorno_n ] ?? '' ) . ' ' . date_i18n( 'd/m', strtotime( $date_str ) ),
            'oggi'       => $date_str === date( 'Y-m-d' ),
            'passato'    => strtotime( $date_str ) < strtotime( date( 'Y-m-d' ) ),
            'lavorativo' => isset( $orari[ $giorno_n ] ) && (int) $orari[ $giorno_n ]->attivo === 1,
            'slots'      => $slots,
        );
    }

    wp_send_json_success( array(
        'giorni'         => $giorni,
        'for_admin'      => $for_admin,
        'no_orari_propri'=> $no_orari_propri && $for_admin,
    ) );
}

/* ── Salva orari (admin) ──
   Standardizzato su mssg_ajax_check() (nonce+auth+capability in un colpo)
   e mssg_db_insert/update invece di $wpdb diretto, come da convenzione core. */
add_action( 'wp_ajax_mssgag_save_orari', function() {
    mssg_ajax_check( 'manage_agenda', 'mssgag_save_orari' );

    $admin_id = get_current_user_id();
    $orari    = $_POST['orari'] ?? array();
    global $wpdb;
    $t = mssgag_table( 'orari' );

    foreach ( $orari as $giorno => $data ) {
        $giorno = (int) $giorno;
        if ( $giorno < 1 || $giorno > 7 ) continue;

        $attivo     = ! empty( $data['attivo'] ) ? 1 : 0;
        $ora_inizio = sanitize_text_field( $data['ora_inizio'] ?? '09:00' );
        $ora_fine   = sanitize_text_field( $data['ora_fine']   ?? '18:00' );
        $slot_min   = max( 30, min( 120, (int) ( $data['slot_min'] ?? 60 ) ) );

        if ( ! preg_match( '/^\d{2}:\d{2}$/', $ora_inizio ) ) $ora_inizio = '09:00';
        if ( ! preg_match( '/^\d{2}:\d{2}$/', $ora_fine   ) ) $ora_fine   = '18:00';

        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM `{$t}` WHERE admin_id=%d AND giorno=%d", $admin_id, $giorno
        ));

        if ( $existing ) {
            mssg_db_update( 'agenda_orari',
                array( 'attivo' => $attivo, 'ora_inizio' => $ora_inizio, 'ora_fine' => $ora_fine, 'slot_min' => $slot_min ),
                (int) $existing
            );
        } else {
            mssg_db_insert( 'agenda_orari', array(
                'admin_id'   => $admin_id,
                'giorno'     => $giorno,
                'ora_inizio' => $ora_inizio,
                'ora_fine'   => $ora_fine,
                'slot_min'   => $slot_min,
                'attivo'     => $attivo,
            ));
        }
    }

    wp_send_json_success( array( 'msg' => 'Orari salvati.' ) );
});

/* ── Aggiungi blocco (admin) ── */
add_action( 'wp_ajax_mssgag_add_blocco', function() {
    mssg_ajax_check( 'manage_agenda', 'mssgag_add_blocco' );

    $admin_id   = get_current_user_id();
    $data_inizio = sanitize_text_field( $_POST['data_inizio'] ?? '' );
    $data_fine   = sanitize_text_field( $_POST['data_fine']   ?? '' );
    $titolo      = sanitize_text_field( $_POST['titolo']      ?? 'Occupato' );
    $tipo        = sanitize_key(        $_POST['tipo']        ?? 'interno' );

    if ( ! $data_inizio || ! $data_fine ) {
        wp_send_json_error( array( 'msg' => 'Date obbligatorie.' ) );
    }
    if ( ! in_array( $tipo, array( 'interno', 'confermato' ), true ) ) $tipo = 'interno';

    $blocco_id = mssg_db_insert( 'agenda_blocchi', array(
        'admin_id'        => $admin_id,
        'cliente_id'      => 0,
        'cantiere_id'     => (int) ( $_POST['cantiere_id'] ?? 0 ),
        'tipo'            => $tipo,
        'data_ora_inizio' => $data_inizio,
        'data_ora_fine'   => $data_fine,
        'titolo_interno'  => $titolo,
        'nota_cliente'    => '',
        'created_by'      => $admin_id,
        'created_at'      => current_time( 'mysql' ),
    ));

    if ( $blocco_id && ! is_wp_error( $blocco_id ) ) {
        wp_send_json_success( array( 'msg' => 'Blocco aggiunto.', 'id' => $blocco_id ) );
    }
    wp_send_json_error( array( 'msg' => 'Errore nel salvataggio.' ) );
});

/* ── Elimina blocco (admin) ── */
add_action( 'wp_ajax_mssgag_delete_blocco', function() {
    mssg_ajax_check( 'manage_agenda', 'mssgag_delete_blocco' );

    $id = (int) ( $_POST['blocco_id'] ?? 0 );
    if ( ! $id ) wp_send_json_error( array( 'msg' => 'ID mancante.' ) );

    global $wpdb;
    $t = mssgag_table( 'blocchi' );
    $b = $wpdb->get_row( $wpdb->prepare( "SELECT admin_id FROM `{$t}` WHERE id=%d", $id ) );
    if ( ! $b || (int) $b->admin_id !== get_current_user_id() ) {
        wp_send_json_error( array( 'msg' => 'Non trovato o permesso negato.' ) );
    }

    $wpdb->delete( $t, array( 'id' => $id ) );
    wp_send_json_success( array( 'msg' => 'Eliminato.' ) );
});

/* ── CORREZIONE: "Promemoria" — questi due handler mancavano del tutto.
   Il form in agenda.js e in mssg-cantieri/section-miei-lavori.php chiamava
   le action 'mssgag_salva_promemoria' e 'mssgag_elimina_promemoria', ma
   nessun add_action le registrava: WordPress rispondeva con errore HTTP 400
   ("azione non valida") e il salvataggio non avveniva mai. ── */
add_action( 'wp_ajax_mssgag_salva_promemoria', function() {
    mssg_ajax_check( 'manage_agenda', 'mssgag_salva_promemoria' );

    $admin_id   = get_current_user_id();
    $id         = (int) ( $_POST['id'] ?? 0 );
    $titolo     = sanitize_text_field( $_POST['titolo'] ?? '' );
    $data_ora   = sanitize_text_field( $_POST['data_ora'] ?? '' );
    $durata_min = max( 0, (int) ( $_POST['durata_min'] ?? 0 ) );
    $notifica_email   = ! empty( $_POST['notifica_email'] ) ? 1 : 0;
    $notifica_minuti  = max( 1, (int) ( $_POST['notifica_minuti'] ?? 60 ) );

    if ( ! $titolo || ! $data_ora || ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $data_ora ) ) {
        wp_send_json_error( array( 'msg' => 'Titolo e data/ora sono obbligatori.' ) );
    }

    $ts_inizio = strtotime( $data_ora );
    $data_fine = $durata_min > 0
        ? date( 'Y-m-d H:i:s', $ts_inizio + $durata_min * 60 )
        : date( 'Y-m-d H:i:s', $ts_inizio + 60 ); /* promemoria puntuale: 1 minuto di durata "tecnica" */

    $fields = array(
        'admin_id'         => $admin_id,
        'cliente_id'       => 0,
        'cantiere_id'      => 0,
        'tipo'             => 'promemoria',
        'data_ora_inizio'  => $data_ora,
        'data_ora_fine'    => $data_fine,
        'titolo_interno'   => $titolo,
        'nota_cliente'     => '',
        'notifica_email'   => $notifica_email,
        'notifica_minuti'  => $notifica_minuti,
        'notifica_inviata' => 0,
        'created_by'       => $admin_id,
    );

    global $wpdb;
    $t = mssgag_table( 'blocchi' );

    if ( $id ) {
        /* Verifica proprietà prima di modificare */
        $owner = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT admin_id FROM `{$t}` WHERE id=%d AND tipo='promemoria'", $id
        ));
        if ( $owner !== $admin_id ) {
            wp_send_json_error( array( 'msg' => 'Promemoria non trovato o permesso negato.' ) );
        }
        $res = mssg_db_update( 'agenda_blocchi', $fields, $id );
        $promemoria_id = $id;
    } else {
        $fields['created_at'] = current_time( 'mysql' );
        $res = mssg_db_insert( 'agenda_blocchi', $fields );
        $promemoria_id = $res;
    }

    if ( is_wp_error( $res ) || ! $res ) {
        wp_send_json_error( array( 'msg' => 'Errore nel salvataggio del promemoria.' ) );
    }

    /* Pianifica (o ripianifica) l'email di notifica via WP-Cron, se richiesta */
    wp_clear_scheduled_hook( 'mssgag_invia_promemoria_email', array( (int) $promemoria_id ) );
    if ( $notifica_email ) {
        $invio_ts = $ts_inizio - ( $notifica_minuti * 60 );
        if ( $invio_ts > time() ) {
            wp_schedule_single_event( $invio_ts, 'mssgag_invia_promemoria_email', array( (int) $promemoria_id ) );
        }
    }

    wp_send_json_success( array( 'msg' => 'Promemoria salvato.', 'id' => (int) $promemoria_id ) );
});

add_action( 'wp_ajax_mssgag_elimina_promemoria', function() {
    mssg_ajax_check( 'manage_agenda', 'mssgag_elimina_promemoria' );

    $id = (int) ( $_POST['id'] ?? 0 );
    if ( ! $id ) wp_send_json_error( array( 'msg' => 'ID mancante.' ) );

    global $wpdb;
    $t = mssgag_table( 'blocchi' );
    $b = $wpdb->get_row( $wpdb->prepare( "SELECT admin_id FROM `{$t}` WHERE id=%d AND tipo='promemoria'", $id ) );
    if ( ! $b || (int) $b->admin_id !== get_current_user_id() ) {
        wp_send_json_error( array( 'msg' => 'Non trovato o permesso negato.' ) );
    }

    $wpdb->delete( $t, array( 'id' => $id ) );
    wp_clear_scheduled_hook( 'mssgag_invia_promemoria_email', array( $id ) );
    wp_send_json_success( array( 'msg' => 'Promemoria eliminato.' ) );
});

/* Invio effettivo dell'email di promemoria (eseguito da WP-Cron) */
add_action( 'mssgag_invia_promemoria_email', function( $promemoria_id ) {
    global $wpdb;
    $t   = mssgag_table( 'blocchi' );
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM `{$t}` WHERE id=%d AND tipo='promemoria' AND notifica_inviata=0", (int) $promemoria_id
    ));
    if ( ! $row ) return;

    $admin = get_userdata( (int) $row->admin_id );
    if ( ! $admin || ! $admin->user_email ) return;

    $az   = function_exists('mssg_get_option') ? mssg_get_option('company_name', get_bloginfo('name')) : get_bloginfo('name');
    $data = date_i18n( 'd/m/Y H:i', strtotime( $row->data_ora_inizio ) );

    wp_mail(
        $admin->user_email,
        "[{$az}] Promemoria: {$row->titolo_interno}",
        "Promemoria per il {$data}:\n\n{$row->titolo_interno}"
    );

    $wpdb->update( $t, array( 'notifica_inviata' => 1 ), array( 'id' => (int) $promemoria_id ) );
});

/* ── Rispondi a richiesta cliente ── */
add_action( 'wp_ajax_mssgag_rispondi_richiesta', function() {
    mssg_ajax_check( 'manage_agenda', 'mssgag_rispondi_richiesta' );

    $blocco_id = (int) ( $_POST['blocco_id'] ?? 0 );
    $azione    = sanitize_key( $_POST['azione'] ?? '' );
    $risposta  = sanitize_text_field( $_POST['risposta'] ?? '' );

    if ( ! $blocco_id || ! in_array( $azione, array( 'conferma', 'rifiuta' ), true ) ) {
        wp_send_json_error( array( 'msg' => 'Parametri non validi.' ) );
    }

    global $wpdb;
    $t = mssgag_table( 'blocchi' );
    $b = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE id=%d", $blocco_id ) );
    if ( ! $b || $b->tipo !== 'richiesta' ) {
        wp_send_json_error( array( 'msg' => 'Richiesta non trovata.' ) );
    }

    $nuovo_tipo = $azione === 'conferma' ? 'confermato' : 'rifiutato';
    mssg_db_update( 'agenda_blocchi', array(
        'tipo'           => $nuovo_tipo,
        'risposta_admin' => $risposta,
    ), $blocco_id );

    if ( $b->cliente_id ) {
        $cliente = get_userdata( (int) $b->cliente_id );
        if ( $cliente ) {
            $az   = function_exists( 'mssg_get_option' ) ? mssg_get_option( 'company_name', get_bloginfo( 'name' ) ) : get_bloginfo( 'name' );
            $data = date_i18n( 'd/m/Y H:i', strtotime( $b->data_ora_inizio ) );
            if ( $azione === 'conferma' ) {
                wp_mail( $cliente->user_email,
                    "[{$az}] Appuntamento confermato — {$data}",
                    "Ciao {$cliente->display_name},\n\nIl tuo appuntamento del {$data} è stato confermato.\n\n— {$az}" );
            } else {
                $body = "Ciao {$cliente->display_name},\n\nCi dispiace, lo slot del {$data} non è più disponibile.";
                if ( $risposta ) $body .= "\nNota: {$risposta}";
                $body .= "\n\nPrenota un altro slot dal gestionale.\n\n— {$az}";
                wp_mail( $cliente->user_email, "[{$az}] Appuntamento non disponibile — {$data}", $body );
            }
        }
    }

    wp_send_json_success( array( 'msg' => $azione === 'conferma' ? 'Confermato.' : 'Rifiutato.' ) );
});

/* ── Cliente invia richiesta appuntamento ── */
add_action( 'wp_ajax_mssgag_richiesta_appuntamento', function() {
    mssgag_check_nonce();
    if ( ! is_user_logged_in() ) wp_send_json_error( array( 'msg' => 'Non autenticato.' ) );

    $uid         = get_current_user_id();
    $data_inizio = sanitize_text_field( $_POST['data_ora']      ?? '' );
    $data_fine   = sanitize_text_field( $_POST['data_ora_fine'] ?? '' );
    $durata_slot = max(1, min(8, (int)($_POST['durata_slot']   ?? 1)));
    $nota        = sanitize_textarea_field( $_POST['nota']      ?? '' );
    $cantiere_id = (int) ( $_POST['cantiere_id']               ?? 0 );
    $admin_id    = mssgag_get_primary_admin_id();

    if ( ! $data_inizio ) wp_send_json_error( array( 'msg' => 'Seleziona uno slot.' ) );

    /* Verifica che tutti gli slot nel range siano liberi */
    $date_str  = date( 'Y-m-d', strtotime( $data_inizio ) );
    $slots     = mssgag_get_slots( $admin_id, $date_str, false );
    $slot_ok   = false;
    $slot_fine = null;
    $slots_to_check = 0;

    /* Trova lo slot di partenza */
    foreach ( $slots as $slot ) {
        if ( $slot['datetime'] === $data_inizio ) {
            if ( ! $slot['free'] || $slot['passato'] ) {
                wp_send_json_error( array( 'msg' => 'Lo slot di partenza non è più disponibile. Ricarica il calendario.' ) );
            }
            /* Conta quanti slot consecutivi liberi esistono */
            $consecutive = 0;
            $ts_fine_finale = $slot['ts'];
            $found_start = false;
            foreach ( $slots as $s2 ) {
                if ( $s2['datetime'] === $data_inizio ) $found_start = true;
                if ( $found_start ) {
                    if ( $s2['free'] && ! $s2['passato'] ) {
                        $consecutive++;
                        $ts_fine_finale = $s2['ts_fine'];
                        if ( $consecutive >= $durata_slot ) break;
                    } else break;
                }
            }
            if ( $consecutive < $durata_slot ) {
                wp_send_json_error( array( 'msg' => 'Non ci sono abbastanza slot consecutivi liberi per la durata selezionata.' ) );
            }
            $slot_ok   = true;
            /* Usa data_fine fornita dal client se valida, altrimenti calcolata */
            if ( $data_fine && strtotime($data_fine) > strtotime($data_inizio) ) {
                $slot_fine = $data_fine;
            } else {
                $slot_fine = date( 'Y-m-d H:i:s', $ts_fine_finale );
            }
            break;
        }
    }

    if ( ! $slot_ok ) {
        wp_send_json_error( array( 'msg' => 'Lo slot non è più disponibile. Ricarica il calendario.' ) );
    }

    /* Verifica cantiere */
    if ( $cantiere_id ) {
        global $wpdb;
        $tc = $wpdb->prefix . 'mssg_cantieri';
        $ok = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM `{$tc}` WHERE id=%d AND (cliente_id=%d OR EXISTS(SELECT 1 FROM {$wpdb->prefix}mssg_cantieri_users cu WHERE cu.cantiere_id=%d AND cu.user_id=%d))",
            $cantiere_id, $uid, $cantiere_id, $uid
        ));
        if ( ! $ok ) $cantiere_id = 0;
    }

    $cliente = get_userdata( $uid );
    $blocco_id = mssg_db_insert( 'agenda_blocchi', array(
        'admin_id'        => $admin_id,
        'cliente_id'      => $uid,
        'cantiere_id'     => $cantiere_id,
        'tipo'            => 'richiesta',
        'data_ora_inizio' => $data_inizio,
        'data_ora_fine'   => $slot_fine,
        'titolo_interno'  => ( $cliente ? $cliente->display_name : 'Cliente' ) . ' — richiesta',
        'nota_cliente'    => $nota,
        'created_by'      => $uid,
        'created_at'      => current_time( 'mysql' ),
    ));

    if ( $blocco_id && ! is_wp_error( $blocco_id ) ) {
        /* Notifica admin */
        $admin = get_userdata( $admin_id );
        if ( $admin && $cliente ) {
            $az       = function_exists( 'mssg_get_option' ) ? mssg_get_option( 'company_name', get_bloginfo( 'name' ) ) : get_bloginfo( 'name' );
            $data_fmt = date_i18n( 'd/m/Y H:i', strtotime( $data_inizio ) );
            wp_mail(
                $admin->user_email,
                "[{$az}] Nuova richiesta da {$cliente->display_name} — {$data_fmt}",
                "Il cliente {$cliente->display_name} ({$cliente->user_email}) ha richiesto un appuntamento per il {$data_fmt}.\n\nNota: {$nota}\n\nAccedi al gestionale per confermare."
            );
        }
        wp_send_json_success( array( 'msg' => 'Richiesta inviata! Riceverai conferma via email.', 'id' => $blocco_id ) );
    }

    wp_send_json_error( array( 'msg' => 'Errore nel salvataggio.' ) );
});

/* ── Cliente annulla la propria richiesta (solo se ancora in attesa) ── */
add_action( 'wp_ajax_mssgag_cliente_annulla', function() {
    if ( ! is_user_logged_in() ) wp_send_json_error( array( 'msg' => 'Non autenticato.' ) );
    /* Usa mssg_nonce (gestionale core) — sempre disponibile per utenti loggati */
    check_ajax_referer( 'mssg_nonce', 'nonce' );
    $uid       = get_current_user_id();
    $blocco_id = (int) ( $_POST['blocco_id'] ?? 0 );
    if ( ! $blocco_id ) wp_send_json_error( array( 'msg' => 'ID mancante.' ) );

    global $wpdb;
    $t = mssgag_table( 'blocchi' );
    $b = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE id=%d", $blocco_id ) );

    if ( ! $b ) wp_send_json_error( array( 'msg' => 'Appuntamento non trovato.' ) );
    if ( (int) $b->cliente_id !== $uid ) wp_send_json_error( array( 'msg' => 'Permesso negato.' ) );
    if ( ! in_array( $b->tipo, array( 'richiesta', 'confermato' ), true ) ) {
        wp_send_json_error( array( 'msg' => 'Questo appuntamento non può essere annullato.' ) );
    }

    $era_confermato = $b->tipo === 'confermato';

    /* Elimina il blocco */
    $wpdb->delete( $t, array( 'id' => $blocco_id ) );

    /* Notifica admin se era già confermato */
    if ( $era_confermato ) {
        $admin_id = mssgag_get_primary_admin_id();
        $admin    = get_userdata( $admin_id );
        $cliente  = get_userdata( $uid );
        if ( $admin && $cliente ) {
            $az   = function_exists( 'mssg_get_option' ) ? mssg_get_option( 'company_name', get_bloginfo( 'name' ) ) : get_bloginfo( 'name' );
            $data = date_i18n( 'd/m/Y H:i', strtotime( $b->data_ora_inizio ) );
            wp_mail(
                $admin->user_email,
                "[{$az}] Appuntamento annullato dal cliente — {$data}",
                "Il cliente {$cliente->display_name} ({$cliente->user_email}) ha annullato l'appuntamento confermato del {$data}."
            );
        }
    }

    wp_send_json_success( array( 'msg' => $era_confermato ? 'Appuntamento annullato. L\'azienda è stata notificata.' : 'Richiesta annullata.' ) );
});

/* ── Cliente richiede modifica data su appuntamento confermato ── */
add_action( 'wp_ajax_mssgag_cliente_modifica', function() {
    if ( ! is_user_logged_in() ) wp_send_json_error( array( 'msg' => 'Non autenticato.' ) );
    check_ajax_referer( 'mssg_nonce', 'nonce' );
    $uid       = get_current_user_id();
    $blocco_id = (int) ( $_POST['blocco_id'] ?? 0 );
    $nota      = sanitize_textarea_field( $_POST['nota'] ?? '' );
    if ( ! $blocco_id ) wp_send_json_error( array( 'msg' => 'ID mancante.' ) );
    if ( ! $nota )      wp_send_json_error( array( 'msg' => 'Indica la data o l\'orario preferiti.' ) );

    global $wpdb;
    $t = mssgag_table( 'blocchi' );
    $b = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE id=%d", $blocco_id ) );

    if ( ! $b || (int) $b->cliente_id !== $uid ) {
        wp_send_json_error( array( 'msg' => 'Appuntamento non trovato.' ) );
    }
    if ( ! in_array( $b->tipo, array( 'richiesta', 'confermato' ), true ) ) {
        wp_send_json_error( array( 'msg' => 'Non modificabile.' ) );
    }

    /* Rimette in stato richiesta con nota di modifica */
    $nuova_nota = '⚠️ Richiesta modifica: ' . $nota;
    $wpdb->update( $t, array(
        'tipo'        => 'richiesta',
        'nota_cliente'=> $nuova_nota,
        'updated_at'  => current_time( 'mysql' ),
    ), array( 'id' => $blocco_id ) );

    /* Notifica admin */
    $admin_id = mssgag_get_primary_admin_id();
    $admin    = get_userdata( $admin_id );
    $cliente  = get_userdata( $uid );
    if ( $admin && $cliente ) {
        $az   = function_exists( 'mssg_get_option' ) ? mssg_get_option( 'company_name', get_bloginfo( 'name' ) ) : get_bloginfo( 'name' );
        $data = date_i18n( 'd/m/Y H:i', strtotime( $b->data_ora_inizio ) );
        wp_mail(
            $admin->user_email,
            "[{$az}] Richiesta modifica appuntamento da {$cliente->display_name}",
            "Il cliente {$cliente->display_name} ({$cliente->user_email}) ha chiesto di modificare l'appuntamento del {$data}.

Note: {$nota}

L'appuntamento è tornato in stato 'In attesa' — accedi al gestionale per rispondere."
        );
    }

    wp_send_json_success( array( 'msg' => 'Richiesta di modifica inviata. L\'azienda ti ricontatterà.' ) );
});

/* ═══════════════════════════════════════════════════════════
   ADMIN: salva appuntamento → mssg_agenda_blocchi (unica sorgente)
   tipo:
     'interno'   → solo promemoria admin, nessun destinatario
     'confermato'→ appuntamento con cliente o collaboratore
═══════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_mssgag_admin_save_appuntamento', function() {
    if ( ! is_user_logged_in() ) wp_send_json_error( array( 'msg' => 'Non autenticato.' ) );
    /* Accetta sia mssgag_nonce (Agenda) che mssg_nonce (core) */
    if ( ! check_ajax_referer( 'mssgag_nonce', 'nonce', false ) &&
         ! check_ajax_referer( 'mssg_nonce',   'nonce', false ) ) {
        wp_send_json_error( array( 'msg' => 'Nonce non valido.' ) );
    }

    $creator     = get_current_user_id();
    $destinatario = (int)( $_POST['user_id']     ?? 0 );
    $cantiere_id = (int)( $_POST['cantiere_id'] ?? 0 );
    $titolo      = sanitize_text_field( $_POST['titolo']   ?? '' );
    $data_ora    = sanitize_text_field( $_POST['data_ora'] ?? '' );
    $durata      = max( 15, (int)( $_POST['durata'] ?? 60 ) );
    $luogo       = sanitize_text_field( $_POST['luogo']    ?? '' );
    $note        = sanitize_textarea_field( $_POST['note'] ?? '' );
    $notifica    = (int)( $_POST['notifica'] ?? 0 );

    if ( ! $titolo || ! $data_ora ) {
        wp_send_json_error( array( 'msg' => 'Oggetto e data obbligatori.' ) );
    }

    $ts_inizio = strtotime( $data_ora );
    if ( ! $ts_inizio ) wp_send_json_error( array( 'msg' => 'Data non valida.' ) );
    $ts_fine   = $ts_inizio + ( $durata * 60 );

    /* tipo: 'interno' se nessun destinatario,
              'richiesta' se richiede conferma del partecipante,
              'confermato' altrimenti */
    $richiedi_conferma = (int)( $_POST['richiedi_conferma'] ?? 0 );
    $tipo = $destinatario
        ? ( $richiedi_conferma ? 'richiesta' : 'confermato' )
        : 'interno';

    $blocco_id = mssg_db_insert( 'agenda_blocchi', array(
        'admin_id'       => $creator,
        'cliente_id'     => $destinatario,
        'cantiere_id'    => $cantiere_id,
        'tipo'           => $tipo,
        'data_ora_inizio'=> date( 'Y-m-d H:i:s', $ts_inizio ),
        'data_ora_fine'  => date( 'Y-m-d H:i:s', $ts_fine ),
        'titolo_interno' => $titolo,
        'luogo'          => $luogo,
        'nota_cliente'   => $note,
        'created_by'     => $creator,
        'created_at'     => current_time( 'mysql' ),
        'updated_at'     => current_time( 'mysql' ),
    ));
    if ( is_wp_error( $blocco_id ) ) $blocco_id = 0;

    /* Email di conferma al destinatario */
    if ( $notifica && $destinatario ) {
        $dest = get_userdata( $destinatario );
        if ( $dest ) {
            $az       = function_exists('mssg_get_option') ? mssg_get_option('company_name', get_bloginfo('name')) : get_bloginfo('name');
            $data_fmt = date_i18n( 'd/m/Y H:i', $ts_inizio );
            wp_mail(
                $dest->user_email,
                "[{$az}] Appuntamento fissato — {$data_fmt}",
                "Ciao {$dest->display_name},

È stato fissato un appuntamento:

📅 {$data_fmt}
📝 {$titolo}
📍 {$luogo}

{$note}

— {$az}"
            );
        }
    }

    wp_send_json_success( array( 'msg' => 'Appuntamento fissato.', 'app_id' => $blocco_id ) );
});

/* ── Admin: elimina appuntamento (da mssg_agenda_blocchi) ── */
add_action( 'wp_ajax_mssgag_admin_delete_appuntamento', function() {
    if ( ! is_user_logged_in() ) wp_send_json_error( array( 'msg' => 'Non autenticato.' ) );
    if ( ! check_ajax_referer( 'mssgag_nonce', 'nonce', false ) &&
         ! check_ajax_referer( 'mssg_nonce',   'nonce', false ) ) {
        wp_send_json_error( array( 'msg' => 'Nonce non valido.' ) );
    }

    $blocco_id = (int)( $_POST['app_id'] ?? 0 );
    $creator   = get_current_user_id();

    global $wpdb;
    $tb = mssgag_table( 'blocchi' );

    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM `{$tb}` WHERE id=%d", $blocco_id
    ));
    if ( ! $row ) wp_send_json_error( array( 'msg' => 'Appuntamento non trovato.' ) );
    if ( (int)$row->created_by !== $creator && ! mssg_user_can( $creator, 'manage_agenda' ) ) {
        wp_send_json_error( array( 'msg' => 'Permesso negato.' ) );
    }

    $wpdb->delete( $tb, array( 'id' => $blocco_id ) );

    /* ── Email di annullamento al partecipante ──
       Solo per appuntamenti futuri: questa stessa azione viene ora richiamata
       anche per eliminare voci dello STORICO (appuntamenti già avvenuti, es.
       da "I miei lavori" → Storico ultimi 30 giorni). Senza questo controllo
       il cliente riceverebbe un'email "appuntamento annullato" per un
       appuntamento che si è già svolto normalmente — messaggio sbagliato e
       fuori contesto. */
    if ( (int)$row->cliente_id > 0 && strtotime( $row->data_ora_inizio ) > time() ) {
        $dest = get_userdata( (int)$row->cliente_id );
        if ( $dest ) {
            $az       = function_exists('mssg_get_option') ? mssg_get_option('company_name', get_bloginfo('name')) : get_bloginfo('name');
            $data_fmt = date_i18n( 'd/m/Y H:i', strtotime( $row->data_ora_inizio ) );
            $titolo   = $row->titolo_interno ?: 'Appuntamento';
            wp_mail(
                $dest->user_email,
                "[{$az}] Appuntamento annullato — {$data_fmt}",
                "Ciao {$dest->display_name},

Ti informiamo che il seguente appuntamento è stato annullato:

📅 {$data_fmt}
📝 {$titolo}
" . ( isset($row->luogo) && $row->luogo ? "📍 {$row->luogo}
" : "" ) . "
Per ulteriori informazioni contattaci.

— {$az}"
            );
        }
    }

    wp_send_json_success( array( 'msg' => 'Appuntamento annullato.' ) );
});

/* ── Sposta appuntamento: cambia data_ora_inizio e data_ora_fine ── */
add_action( 'wp_ajax_mssgag_sposta_appuntamento', function() {
    if ( ! is_user_logged_in() ) wp_send_json_error( array( 'msg' => 'Non autenticato.' ) );
    if ( ! check_ajax_referer( 'mssgag_nonce', 'nonce', false ) &&
         ! check_ajax_referer( 'mssg_nonce',   'nonce', false ) ) {
        wp_send_json_error( array( 'msg' => 'Nonce non valido.' ) );
    }
    $blocco_id  = (int)( $_POST['blocco_id']  ?? 0 );
    $new_start  = sanitize_text_field( $_POST['new_start'] ?? '' );
    $durata_min = (int)( $_POST['durata_min'] ?? 60 );
    if ( ! $blocco_id || ! $new_start ) wp_send_json_error( array( 'msg' => 'Dati mancanti.' ) );

    global $wpdb;
    $tb  = mssgag_table( 'blocchi' );
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$tb}` WHERE id=%d", $blocco_id ) );
    if ( ! $row ) wp_send_json_error( array( 'msg' => 'Appuntamento non trovato.' ) );

    $ts_start   = strtotime( $new_start );
    $ts_end     = $ts_start + ( $durata_min * 60 );

    mssg_db_update( 'agenda_blocchi', array(
        'data_ora_inizio' => date( 'Y-m-d H:i:s', $ts_start ),
        'data_ora_fine'   => date( 'Y-m-d H:i:s', $ts_end ),
    ), $blocco_id );

    /* ── Email di spostamento al partecipante ── */
    if ( (int)$row->cliente_id > 0 ) {
        $dest = get_userdata( (int)$row->cliente_id );
        if ( $dest ) {
            $az          = function_exists('mssg_get_option') ? mssg_get_option('company_name', get_bloginfo('name')) : get_bloginfo('name');
            $vecchia_data = date_i18n( 'd/m/Y H:i', strtotime( $row->data_ora_inizio ) );
            $nuova_data   = date_i18n( 'd/m/Y H:i', $ts_start );
            $titolo       = $row->titolo_interno ?: 'Appuntamento';
            wp_mail(
                $dest->user_email,
                "[{$az}] Appuntamento spostato — {$nuova_data}",
                "Ciao {$dest->display_name},

Il tuo appuntamento è stato spostato:

📝 {$titolo}
🔄 Da: {$vecchia_data}
📅 A: {$nuova_data}
" . ( isset($row->luogo) && $row->luogo ? "📍 {$row->luogo}
" : "" ) . "
— {$az}"
            );
        }
    }

    wp_send_json_success( array( 'msg' => 'Appuntamento spostato.' ) );
});

/* ── Dettaglio slot: fetch on-click per evitare JOIN pesanti nella settimana ── */
add_action( 'wp_ajax_mssgag_get_blocco_detail', function() {
    if ( ! check_ajax_referer( 'mssgag_nonce', 'nonce', false ) &&
         ! check_ajax_referer( 'mssg_nonce',   'nonce', false ) ) {
        wp_send_json_error( array( 'msg' => 'Nonce non valido.' ) );
    }
    $bid = (int)( $_POST['blocco_id'] ?? 0 );
    if ( ! $bid ) wp_send_json_error();

    global $wpdb;
    $tb  = mssgag_table( 'blocchi' );
    $tc  = $wpdb->prefix . 'mssg_cantieri';
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT b.*,
                u.display_name AS partecipante_nome,
                c.nome AS cantiere_nome
         FROM `{$tb}` b
         LEFT JOIN {$wpdb->users} u ON u.ID = b.cliente_id
         LEFT JOIN `{$tc}` c ON c.id = b.cantiere_id
         WHERE b.id = %d",
        $bid
    ));
    if ( ! $row ) wp_send_json_error( array( 'msg' => 'Non trovato.' ) );

    wp_send_json_success( array(
        'id'               => (int) $row->id,
        'titolo'           => esc_html( $row->titolo_interno ),
        'tipo'             => $row->tipo,
        'luogo'            => esc_html( isset($row->luogo) ? $row->luogo : '' ),
        'nota'             => esc_html( $row->nota_cliente ?? '' ),
        'partecipante_nome'=> esc_html( $row->partecipante_nome ?? '' ),
        'cliente_id'       => (int) $row->cliente_id,
        'cantiere_nome'    => esc_html( $row->cantiere_nome ?? '' ),
        'cantiere_id'      => (int) $row->cantiere_id,
        'data_inizio'      => $row->data_ora_inizio,
        'data_fine'        => $row->data_ora_fine,
        'created_by'       => (int) $row->created_by,
    ));
});
