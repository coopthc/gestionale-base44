<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ══════════════════════════════════════════════════════════
   BACKUP TOTALE + IMPORT — MSS Gestionale
   Include: DB tutte le tabelle custom, file media, manifest
══════════════════════════════════════════════════════════ */

/* ── Backup totale ── */
add_action( 'wp_ajax_mssex_backup_totale', function() {
    mssex_chk_nonce();
    if ( ! mssg_user_can( get_current_user_id(), 'manage_cantieri' ) ) {
        wp_send_json_error( array( 'msg' => 'Non autorizzato.' ) );
    }

    if ( ! class_exists('ZipArchive') ) {
        wp_send_json_error( array( 'msg' => 'ZipArchive non disponibile sul server.' ) );
    }

    global $wpdb;
    $upload_dir = wp_upload_dir();
    $tmp_path   = $upload_dir['basedir'] . '/mssg/backup-tmp-' . time();
    wp_mkdir_p( $tmp_path . '/media' );

    /* ── 1. Export DB tabelle custom in JSON ── */
    $tabelle = array(
        'mssg_cantieri', 'mssg_cantieri_users', 'mssg_cantieri_chat',
        'mssg_avanzamenti', 'mssg_pagamenti', 'mssg_presenze',
        'mssg_media', 'mssg_personale', 'mssg_agenda_blocchi',
        'mssg_appuntamenti',
    );

    $db_export = array();
    foreach ( $tabelle as $tbl ) {
        $full = $wpdb->prefix . $tbl;
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$full}'" ) !== $full ) continue;
        $rows = $wpdb->get_results( "SELECT * FROM `{$full}`", ARRAY_A );
        $db_export[ $tbl ] = $rows;
    }

    /* Meta utenti rilevanti (solo campi mssg_*) */
    $meta_users = $wpdb->get_results(
        "SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta}
         WHERE meta_key LIKE 'mssgcl_%' OR meta_key LIKE 'mssg_%'
         ORDER BY user_id", ARRAY_A
    );
    $db_export['_user_meta'] = $meta_users;

    /* Utenti del gestionale */
    $utenti = get_users( array(
        'role__in' => array( 'administrator', 'mssg_admin', 'mssg_capo', 'mssg_operaio', 'mssg_cliente' ),
        'fields'   => array( 'ID', 'user_login', 'user_email', 'display_name', 'user_registered' ),
    ) );
    $utenti_exp = array();
    foreach ( $utenti as $u ) {
        $roles = (array) get_userdata( $u->ID )->roles;
        $utenti_exp[] = array(
            'ID' => $u->ID, 'user_login' => $u->user_login,
            'user_email' => $u->user_email, 'display_name' => $u->display_name,
            'user_registered' => $u->user_registered, 'roles' => $roles,
        );
    }
    $db_export['_users'] = $utenti_exp;

    file_put_contents( $tmp_path . '/database.json',
        json_encode( $db_export, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT )
    );

    /* ── 2. Manifest ── */
    $manifest = array(
        'versione'    => '1.0',
        'data'        => current_time( 'mysql' ),
        'sito_url'    => get_site_url(),
        'sito_upload' => $upload_dir['baseurl'],
        'plugin_ver'  => MSSGC_VERSION ?? '4',
        'tabelle'     => array_keys( $db_export ),
    );
    file_put_contents( $tmp_path . '/manifest.json',
        json_encode( $manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT )
    );

    /* ── 3. Copia file media dei cantieri ── */
    $media_base = $upload_dir['basedir'] . '/mssg';
    $media_copiati = 0;
    if ( is_dir( $media_base ) ) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $media_base, RecursiveDirectoryIterator::SKIP_DOTS )
        );
        foreach ( $it as $file ) {
            if ( ! $file->isFile() ) continue;
            /* Salta i backup temporanei */
            if ( strpos( $file->getPathname(), 'backup-tmp-' ) !== false ) continue;
            $rel   = str_replace( $media_base . DIRECTORY_SEPARATOR, '', $file->getPathname() );
            $dest  = $tmp_path . '/media/' . $rel;
            $ddir  = dirname( $dest );
            if ( ! is_dir( $ddir ) ) wp_mkdir_p( $ddir );
            copy( $file->getPathname(), $dest );
            $media_copiati++;
        }
    }

    /* ── 4. Crea ZIP ── */
    $zip_name = 'mssg-backup-' . date( 'Y-m-d_H-i' ) . '.zip';
    $zip_path = $upload_dir['basedir'] . '/mssg/' . $zip_name;

    $zip = new ZipArchive();
    if ( $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
        wp_send_json_error( array( 'msg' => 'Impossibile creare il file ZIP.' ) );
    }

    /* Aggiungi tutti i file dalla cartella tmp */
    $it2 = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $tmp_path, RecursiveDirectoryIterator::SKIP_DOTS )
    );
    foreach ( $it2 as $file ) {
        if ( ! $file->isFile() ) continue;
        $rel = str_replace( $tmp_path . DIRECTORY_SEPARATOR, '', $file->getPathname() );
        $zip->addFile( $file->getPathname(), $rel );
    }
    $zip->close();

    /* Pulizia cartella temporanea */
    mssex_rmdir_recursive( $tmp_path );

    $zip_url = $upload_dir['baseurl'] . '/mssg/' . $zip_name;
    $zip_size = round( filesize( $zip_path ) / 1024 / 1024, 1 );

    wp_send_json_success( array(
        'url'       => $zip_url,
        'nome'      => $zip_name,
        'size_mb'   => $zip_size,
        'media'     => $media_copiati,
        'tabelle'   => count( $db_export ),
        'msg'       => "Backup completato: {$zip_size} MB, {$media_copiati} file media, ".count($db_export)." tabelle DB.",
    ) );
} );

/* ── Helper: cancella ricorsiva cartella ── */
function mssex_rmdir_recursive( $dir ) {
    if ( ! is_dir( $dir ) ) return;
    foreach ( new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
        RecursiveIteratorIterator::CHILD_FIRST
    ) as $f ) {
        $f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
    }
    rmdir( $dir );
}

/* ── Import backup ── */
add_action( 'wp_ajax_mssex_import_backup', function() {
    mssex_chk_nonce();
    if ( ! mssg_user_can( get_current_user_id(), 'manage_cantieri' ) ) {
        wp_send_json_error( array( 'msg' => 'Non autorizzato.' ) );
    }
    if ( ! isset( $_FILES['backup_file'] ) ) {
        wp_send_json_error( array( 'msg' => 'Nessun file caricato.' ) );
    }

    $file = $_FILES['backup_file'];
    if ( $file['error'] !== UPLOAD_ERR_OK ) {
        wp_send_json_error( array( 'msg' => 'Errore upload: ' . $file['error'] ) );
    }

    /* Verifica estensione */
    if ( strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) !== 'zip' ) {
        wp_send_json_error( array( 'msg' => 'Il file deve essere uno ZIP.' ) );
    }

    if ( ! class_exists( 'ZipArchive' ) ) {
        wp_send_json_error( array( 'msg' => 'ZipArchive non disponibile sul server.' ) );
    }

    $upload_dir = wp_upload_dir();
    $tmp_path   = $upload_dir['basedir'] . '/mssg/import-tmp-' . time();
    wp_mkdir_p( $tmp_path );

    $zip = new ZipArchive();
    if ( $zip->open( $file['tmp_name'] ) !== true ) {
        wp_send_json_error( array( 'msg' => 'ZIP non valido o corrotto.' ) );
    }
    $zip->extractTo( $tmp_path );
    $zip->close();

    /* Leggi manifest */
    $manifest_path = $tmp_path . '/manifest.json';
    if ( ! file_exists( $manifest_path ) ) {
        mssex_rmdir_recursive( $tmp_path );
        wp_send_json_error( array( 'msg' => 'File manifest non trovato. Backup non valido.' ) );
    }
    $manifest = json_decode( file_get_contents( $manifest_path ), true );
    $old_url  = $manifest['sito_url']    ?? '';
    $old_upload = $manifest['sito_upload'] ?? '';
    $new_url  = get_site_url();
    $new_upload = $upload_dir['baseurl'];

    /* Leggi database.json */
    $db_path = $tmp_path . '/database.json';
    if ( ! file_exists( $db_path ) ) {
        mssex_rmdir_recursive( $tmp_path );
        wp_send_json_error( array( 'msg' => 'Database JSON non trovato.' ) );
    }
    $db_data = json_decode( file_get_contents( $db_path ), true );
    if ( ! $db_data ) {
        mssex_rmdir_recursive( $tmp_path );
        wp_send_json_error( array( 'msg' => 'Database JSON non valido.' ) );
    }

    global $wpdb;
    $importati = array();
    $errori    = array();

    /* ── Import tabelle DB ── */
    $skip = array( '_users', '_user_meta' ); /* Utenti gestiti separatamente */
    foreach ( $db_data as $tbl => $rows ) {
        if ( in_array( $tbl, $skip ) || empty( $rows ) ) continue;
        $full = $wpdb->prefix . $tbl;
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$full}'" ) !== $full ) {
            $errori[] = "Tabella {$tbl} non esiste sul sito — saltata.";
            continue;
        }

        /* Sostituisci vecchie URL con quelle nuove */
        $rows_json = str_replace( $old_upload, $new_upload, json_encode( $rows ) );
        if ( $old_url && $old_url !== $new_url ) {
            $rows_json = str_replace( $old_url, $new_url, $rows_json );
        }
        $rows = json_decode( $rows_json, true );

        /* Svuota tabella e reinserisce */
        $wpdb->query( "TRUNCATE TABLE `{$full}`" );
        $n = 0;
        foreach ( $rows as $row ) {
            /* Reset auto-increment */
            if ( $wpdb->insert( $full, $row ) ) $n++;
            else $errori[] = "Errore insert in {$tbl}: " . $wpdb->last_error;
        }
        $importati[ $tbl ] = $n;
    }

    /* ── Import meta utenti ── */
    if ( ! empty( $db_data['_user_meta'] ) ) {
        foreach ( $db_data['_user_meta'] as $meta ) {
            $val = str_replace( $old_url, $new_url, $meta['meta_value'] ?? '' );
            update_user_meta( (int) $meta['user_id'], $meta['meta_key'], $val );
        }
        $importati['_user_meta'] = count( $db_data['_user_meta'] );
    }

    /* ── Copia file media ── */
    $media_base_tmp = $tmp_path . '/media';
    $media_dest     = $upload_dir['basedir'] . '/mssg';
    $media_copiati  = 0;
    if ( is_dir( $media_base_tmp ) ) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $media_base_tmp, RecursiveDirectoryIterator::SKIP_DOTS )
        );
        foreach ( $it as $file ) {
            if ( ! $file->isFile() ) continue;
            $rel  = str_replace( $media_base_tmp . DIRECTORY_SEPARATOR, '', $file->getPathname() );
            $dest = $media_dest . '/' . $rel;
            $ddir = dirname( $dest );
            if ( ! is_dir( $ddir ) ) wp_mkdir_p( $ddir );
            copy( $file->getPathname(), $dest );
            $media_copiati++;
        }
    }

    mssex_rmdir_recursive( $tmp_path );

    $msg = "Import completato.\n";
    $msg .= implode( ', ', array_map( function($k,$v){ return "{$k}: {$v} righe"; }, array_keys($importati), $importati ) );
    $msg .= "\nMedia copiati: {$media_copiati}";
    if ( $errori ) $msg .= "\nAvvisi: " . implode( '; ', array_slice( $errori, 0, 5 ) );

    wp_send_json_success( array( 'msg' => $msg, 'importati' => $importati, 'media' => $media_copiati ) );
} );
