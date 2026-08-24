<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function mssgc_table($n){global $wpdb;return $wpdb->prefix.'mssg_'.$n;}



/* ── Tabella pagamenti/milestone cantiere ── */
function mssgc_db_create_pagamenti() {
    global $wpdb; $ch = $wpdb->get_charset_collate();
    $t = mssgc_table('pagamenti');
    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE `{$t}` (
        id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
        cantiere_id   INT UNSIGNED NOT NULL,
        tipo          ENUM('acconto','avanzamento','saldo') NOT NULL DEFAULT 'avanzamento',
        label         VARCHAR(120) NOT NULL DEFAULT '',
        percentuale   TINYINT UNSIGNED NOT NULL DEFAULT 0,
        ordine        TINYINT UNSIGNED NOT NULL DEFAULT 0,
        pagato        TINYINT(1) NOT NULL DEFAULT 0,
        data_pagamento DATE DEFAULT NULL,
        importo       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        note          VARCHAR(255) NOT NULL DEFAULT '',
        created_at    DATETIME NOT NULL,
        updated_at    DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_cantiere (cantiere_id),
        KEY idx_tipo (tipo)
    ) {$ch};");
}

function mssgc_ensure_tables(){
    global $wpdb;
    $t=mssgc_table('cantieri');
    $exists=$wpdb->get_var("SHOW TABLES LIKE '{$t}'")===$t;
    if(!$exists){
        mssgc_db_create_cantieri();mssgc_db_create_lavorazioni();
        mssgc_db_create_cantieri_users();mssgc_db_create_avanzamenti();
        mssgc_db_create_appuntamenti();mssgc_db_create_media();
        mssgc_db_create_cantieri_chat();mssgc_db_create_pagamenti();
    } else {
        mssgc_db_create_cantieri();
        mssgc_db_create_lavorazioni();
        mssgc_db_create_cantieri_users();
        mssgc_db_create_avanzamenti();
        mssgc_db_create_appuntamenti();
        mssgc_db_create_media();
        mssgc_db_create_cantieri_chat();
        mssgc_db_create_pagamenti();
    }
    /* ALTER TABLE: colonne cloud per mssg_media */
    $tm = mssgc_table('media');
    if ( $wpdb->get_var("SHOW TABLES LIKE '{$tm}'") === $tm ) {
        $cols = $wpdb->get_col("DESCRIBE `{$tm}`");
        if ( !in_array('cloud_url',   $cols) ) $wpdb->query("ALTER TABLE `{$tm}` ADD COLUMN `cloud_url` VARCHAR(1000) NOT NULL DEFAULT '' AFTER `thumb_url`");
        if ( !in_array('storage_loc', $cols) ) $wpdb->query("ALTER TABLE `{$tm}` ADD COLUMN `storage_loc` VARCHAR(10) NOT NULL DEFAULT 'local' AFTER `cloud_url`");
    }

    /* ALTER TABLE: colonne responsabile_id e cliente_id per mssg_cantieri */
    $tc = mssgc_table('cantieri');
    if ( $wpdb->get_var("SHOW TABLES LIKE '{$tc}'") === $tc ) {
        $cols_c = $wpdb->get_col("DESCRIBE `{$tc}`");
        if ( !in_array('responsabile_id', $cols_c) )
            $wpdb->query("ALTER TABLE `{$tc}` ADD COLUMN `responsabile_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `cliente_id`");
        if ( !in_array('cliente_id', $cols_c) )
            $wpdb->query("ALTER TABLE `{$tc}` ADD COLUMN `cliente_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `codice`");
        if ( !in_array('importo_prev', $cols_c) )
            $wpdb->query("ALTER TABLE `{$tc}` ADD COLUMN `importo_prev` DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER `provincia`");
        if ( !in_array('avanzamento_pct', $cols_c) )
            $wpdb->query("ALTER TABLE `{$tc}` ADD COLUMN `avanzamento_pct` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `importo_prev`");
        if ( !in_array('note_interne', $cols_c) )
            $wpdb->query("ALTER TABLE `{$tc}` ADD COLUMN `note_interne` TEXT AFTER `descrizione`");
    }
}

function mssgc_db_create_cantieri(){
    global $wpdb;$ch=$wpdb->get_charset_collate();$t=mssgc_table('cantieri');
    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE `{$t}` (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        codice VARCHAR(40) NOT NULL DEFAULT '',
        nome VARCHAR(255) NOT NULL DEFAULT '',
        cliente_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        responsabile_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        indirizzo VARCHAR(255) NOT NULL DEFAULT '',
        citta VARCHAR(120) NOT NULL DEFAULT '',
        cap VARCHAR(10) NOT NULL DEFAULT '',
        provincia VARCHAR(4) NOT NULL DEFAULT '',
        data_inizio DATE DEFAULT NULL,
        data_fine_prev DATE DEFAULT NULL,
        data_fine_eff DATE DEFAULT NULL,
        stato ENUM('bozza','attivo','sospeso','completato','chiuso','archiviato') NOT NULL DEFAULT 'bozza',
        pinned TINYINT(1) NOT NULL DEFAULT 0,
        descrizione TEXT,
        note_interne TEXT,
        avanzamento_pct TINYINT UNSIGNED NOT NULL DEFAULT 0,
        importo_prev DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        updated_at DATETIME DEFAULT NULL,
        deleted_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_stato (stato), KEY idx_pinned (pinned),
        KEY idx_cliente (cliente_id), KEY idx_deleted (deleted_at)
    ) {$ch};");
}

function mssgc_db_create_cantieri_chat(){
    global $wpdb;$ch=$wpdb->get_charset_collate();$t=mssgc_table('cantieri_chat');
    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE `{$t}` (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        cantiere_id INT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        testo TEXT,
        allegato_url VARCHAR(1000) NOT NULL DEFAULT '',
        allegato_nome VARCHAR(255) NOT NULL DEFAULT '',
        allegato_mime VARCHAR(120) NOT NULL DEFAULT '',
        letto_da TEXT COMMENT 'JSON array user IDs che hanno letto',
        created_at DATETIME NOT NULL,
        deleted_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_cantiere (cantiere_id),
        KEY idx_created (created_at)
    ) {$ch};");
}

function mssgc_db_create_lavorazioni(){
    global $wpdb;$ch=$wpdb->get_charset_collate();$t=mssgc_table('lavorazioni');
    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE `{$t}` (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        cantiere_id INT UNSIGNED NOT NULL,
        parent_id INT UNSIGNED NOT NULL DEFAULT 0,
        titolo VARCHAR(255) NOT NULL DEFAULT '',
        descrizione TEXT,
        stato ENUM('da_fare','in_corso','completata','bloccata','annullata') NOT NULL DEFAULT 'da_fare',
        assegnato_a BIGINT UNSIGNED NOT NULL DEFAULT 0,
        data_inizio DATE DEFAULT NULL,data_fine DATE DEFAULT NULL,
        ore_prev DECIMAL(8,2) NOT NULL DEFAULT 0.00,
        ore_eff DECIMAL(8,2) NOT NULL DEFAULT 0.00,
        ordine SMALLINT NOT NULL DEFAULT 0,
        created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,updated_at DATETIME DEFAULT NULL,deleted_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),KEY idx_cantiere (cantiere_id),KEY idx_stato (stato)
    ) {$ch};");
}

function mssgc_db_create_cantieri_users(){
    global $wpdb;$ch=$wpdb->get_charset_collate();$t=mssgc_table('cantieri_users');
    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE `{$t}` (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        cantiere_id INT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        ruolo ENUM('capo','operaio','subappaltatore','cliente','supervisore') NOT NULL DEFAULT 'operaio',
        note VARCHAR(255) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),UNIQUE KEY uniq_ass (cantiere_id,user_id),
        KEY idx_user (user_id),KEY idx_cantiere (cantiere_id)
    ) {$ch};");
}

function mssgc_db_create_avanzamenti(){
    global $wpdb;$ch=$wpdb->get_charset_collate();$t=mssgc_table('avanzamenti');
    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE `{$t}` (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        cantiere_id INT UNSIGNED NOT NULL,
        titolo VARCHAR(255) NOT NULL DEFAULT '',
        testo TEXT,
        tipo ENUM('aggiornamento','avviso','completamento','problema') NOT NULL DEFAULT 'aggiornamento',
        stato_lavorazione ENUM('in_corso','conclusa','bloccata','in_attesa') NOT NULL DEFAULT 'in_corso',
        visibile_cliente TINYINT(1) NOT NULL DEFAULT 1,
        created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,updated_at DATETIME DEFAULT NULL,deleted_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),KEY idx_cantiere (cantiere_id),KEY idx_visibile (visibile_cliente)
    ) {$ch};");
}

function mssgc_db_create_appuntamenti(){
    global $wpdb;$ch=$wpdb->get_charset_collate();$t=mssgc_table('appuntamenti');
    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE `{$t}` (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        cantiere_id INT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        titolo VARCHAR(255) NOT NULL DEFAULT '',
        data_ora DATETIME NOT NULL,
        durata_min SMALLINT NOT NULL DEFAULT 60,
        luogo VARCHAR(255) NOT NULL DEFAULT '',
        note TEXT,
        stato_cliente ENUM('in_attesa','accettato','proposta_modifica') NOT NULL DEFAULT 'in_attesa',
        proposta_data DATETIME DEFAULT NULL,
        proposta_nota TEXT,
        notifica_inviata TINYINT(1) NOT NULL DEFAULT 0,
        created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),KEY idx_cantiere (cantiere_id),KEY idx_data (data_ora)
    ) {$ch};");
}

function mssgc_db_create_media(){
    global $wpdb;$ch=$wpdb->get_charset_collate();$t=mssgc_table('media');
    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE `{$t}` (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        cantiere_id INT UNSIGNED NOT NULL,
        lavorazione_id INT UNSIGNED NOT NULL DEFAULT 0,
        tipo ENUM('foto','video','documento','altro') NOT NULL DEFAULT 'foto',
        categoria ENUM('cantiere','contratto','planimetria','permesso','sicurezza','altro') NOT NULL DEFAULT 'cantiere',
        nome VARCHAR(255) NOT NULL DEFAULT '',
        nome_file VARCHAR(255) NOT NULL DEFAULT '',
        file_path VARCHAR(1000) NOT NULL DEFAULT '',
        file_url VARCHAR(1000) NOT NULL DEFAULT '',
        mime_type VARCHAR(120) NOT NULL DEFAULT '',
        dimensione INT UNSIGNED NOT NULL DEFAULT 0,
        larghezza SMALLINT NOT NULL DEFAULT 0,
        altezza SMALLINT NOT NULL DEFAULT 0,
        note VARCHAR(255) NOT NULL DEFAULT '',
        thumb_url VARCHAR(1000) NOT NULL DEFAULT '',
        cloud_url VARCHAR(1000) NOT NULL DEFAULT '',
        storage_loc ENUM('local','cloud','both') NOT NULL DEFAULT 'local',
        visibile_cliente TINYINT(1) NOT NULL DEFAULT 0,
        uploaded_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        deleted_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),KEY idx_cantiere (cantiere_id),KEY idx_tipo (tipo),KEY idx_deleted (deleted_at)
    ) {$ch};");
}

/* ── Query cantieri ─────────────────────────────── */
function mssgc_get_cantieri($user_id,$filters=array()){
    global $wpdb;
    $tc=mssgc_table('cantieri');$tcu=mssgc_table('cantieri_users');
    $role=mssg_get_primary_role($user_id);

    if(in_array($role,array('administrator','mssg_admin'))){
        $where='WHERE c.deleted_at IS NULL';$vals=array();
    } elseif($role==='mssg_capo'){
        $where='WHERE c.deleted_at IS NULL AND (c.responsabile_id=%d OR EXISTS(SELECT 1 FROM `'.$tcu.'` p WHERE p.cantiere_id=c.id AND p.user_id=%d))';
        $vals=array($user_id,$user_id);
    } else {
        /* Operaio/cliente: vede cantieri dove è assegnato O dove è il cliente abbinato */
        $where='WHERE c.deleted_at IS NULL AND (c.cliente_id=%d OR EXISTS(SELECT 1 FROM `'.$tcu.'` p WHERE p.cantiere_id=c.id AND p.user_id=%d))';
        $vals=array($user_id,$user_id);
    }

    // Archiviati di default non mostrati (ma inclusi se c'è una ricerca)
    $has_search = !empty($filters['search']);
    if(empty($filters['stato'])){
        if(!$has_search) $where.=" AND c.stato!='archiviato'";
    } elseif($filters['stato']==='archiviato'){
        $where.=" AND c.stato='archiviato'";
    } elseif($filters['stato']!=='tutti'){
        $where.=' AND c.stato=%s';$vals[]=$filters['stato'];
    }

    if($has_search){
        $s='%'.esc_sql($filters['search']).'%';
        $where.=' AND (c.nome LIKE %s OR c.codice LIKE %s OR c.indirizzo LIKE %s OR c.citta LIKE %s)';
        $vals[]=$s;$vals[]=$s;$vals[]=$s;$vals[]=$s;
    }

    $sql="SELECT c.*,u.display_name AS responsabile_nome,uc.display_name AS cliente_nome
          FROM `{$tc}` c
          LEFT JOIN {$wpdb->users} u ON u.ID=c.responsabile_id
          LEFT JOIN {$wpdb->users} uc ON uc.ID=c.cliente_id
          {$where}
          ORDER BY c.pinned DESC, c.created_at DESC";

    return $vals?$wpdb->get_results($wpdb->prepare($sql,...$vals)):$wpdb->get_results($sql);
}

function mssgc_get_cantiere($id,$user_id=null){
    global $wpdb;$tc=mssgc_table('cantieri');
    $c=$wpdb->get_row($wpdb->prepare(
        "SELECT c.*,u.display_name AS responsabile_nome,uc.display_name AS cliente_nome
         FROM `{$tc}` c LEFT JOIN {$wpdb->users} u ON u.ID=c.responsabile_id
         LEFT JOIN {$wpdb->users} uc ON uc.ID=c.cliente_id
         WHERE c.id=%d AND c.deleted_at IS NULL",$id));
    if(!$c)return null;
    if($user_id&&!mssgc_user_can_access($user_id,$id))return null;
    return $c;
}

/* SICUREZZA (Task #23 — isolamento dati cliente): questa funzione decide se un
   utente può accedere al modulo INTERNO "Cantieri" (team, note interne,
   pagamenti con dettagli, chat di cantiere, export PDF completo, ecc.).
   Un cliente (ruolo mssg_cliente) è sempre presente nella tabella
   cantieri_users con ruolo='cliente' per poter comparire nella sua "Area
   cliente" (vedi mssg-clienti), ma NON deve mai poter passare questo
   controllo: prima di questa correzione un cliente poteva raggiungere
   endpoint interni (es. wp_ajax_mssg_cantieri_form) passando il proprio
   cantiere_id e ricevere l'intero form admin — team, note interne, elenco
   di TUTTI i clienti/collaboratori, milestone di pagamento — dati mai
   pensati per la sua vista. L'area cliente filtrata resta l'UNICO canale
   corretto per un cliente. */
function mssgc_user_can_access($user_id,$cantiere_id){
    if ( function_exists('mssg_get_primary_role') && mssg_get_primary_role($user_id) === 'mssg_cliente' ) {
        return false;
    }
    if(mssg_user_can($user_id,'view_all_cantieri'))return true;
    $c=mssg_db_get('cantieri',$cantiere_id);
    if($c&&(int)$c->responsabile_id===(int)$user_id)return true;
    global $wpdb;
    return (bool)$wpdb->get_var($wpdb->prepare(
        "SELECT id FROM `".mssgc_table('cantieri_users')."` WHERE cantiere_id=%d AND user_id=%d AND ruolo!='cliente'",
        $cantiere_id,$user_id));
}

function mssgc_get_collaboratori_cantiere($cantiere_id){
    global $wpdb;$tcu=mssgc_table('cantieri_users');$tu=$wpdb->users;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT cu.*,
         cu.ruolo AS ruolo_cantiere,
         u.display_name,u.user_email,
         COALESCE(p.qualifica,'') AS qualifica,COALESCE(p.telefono,'') AS telefono
         FROM `{$tcu}` cu INNER JOIN `{$tu}` u ON u.ID=cu.user_id
         LEFT JOIN `{$wpdb->prefix}mssg_personale` p ON p.user_id=cu.user_id
         WHERE cu.cantiere_id=%d ORDER BY cu.ruolo ASC,u.display_name ASC",$cantiere_id));
}

function mssgc_get_collaboratori_disponibili(){
    return get_users(array('role__in'=>array('administrator','mssg_admin','mssg_capo','mssg_operaio'),'orderby'=>'display_name'));
}

function mssgc_get_avanzamenti($cantiere_id,$solo_cliente=false){
    global $wpdb;$t=mssgc_table('avanzamenti');
    $w=$solo_cliente?'AND a.visibile_cliente=1':'';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT a.*,u.display_name AS autore FROM `{$t}` a
         LEFT JOIN {$wpdb->users} u ON u.ID=a.created_by
         WHERE a.cantiere_id=%d AND a.deleted_at IS NULL {$w}
         ORDER BY a.created_at DESC",$cantiere_id));
}

function mssgc_get_media($cantiere_id,$tipo=null){
    global $wpdb;$t=mssgc_table('media');
    $w=$tipo?$wpdb->prepare(' AND m.tipo=%s',$tipo):'';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT m.*,u.display_name AS autore FROM `{$t}` m
         LEFT JOIN {$wpdb->users} u ON u.ID=m.uploaded_by
         WHERE m.cantiere_id=%d AND m.deleted_at IS NULL {$w}
         ORDER BY m.created_at DESC",$cantiere_id));
}

function mssgc_get_appuntamenti($cantiere_id){
    global $wpdb;$t=mssgc_table('appuntamenti');
    return $wpdb->get_results($wpdb->prepare(
        "SELECT a.*,u.display_name AS partecipante FROM `{$t}` a
         LEFT JOIN {$wpdb->users} u ON u.ID=a.user_id
         WHERE a.cantiere_id=%d ORDER BY a.data_ora ASC",$cantiere_id));
}

/* ── Upload media (fix callback nominata) ─────── */
function mssgc_upload_dir_filter($dirs,$cantiere_id){
    $dirs['subdir']='/mssg/cantieri/'.$cantiere_id;
    $dirs['path']=$dirs['basedir'].$dirs['subdir'];
    $dirs['url']=$dirs['baseurl'].$dirs['subdir'];
    return $dirs;
}

function mssgc_upload_media($file,$cantiere_id,$args=array()){
    if(!function_exists('wp_handle_upload'))require_once ABSPATH.'wp-admin/includes/file.php';

    $upload_dir=wp_upload_dir();
    $dir=$upload_dir['basedir'].'/mssg/cantieri/'.$cantiere_id.'/';
    if(!file_exists($dir))wp_mkdir_p($dir);

    // Callback nominata — può essere rimossa con precisione
    $filter=function($d) use ($cantiere_id){return mssgc_upload_dir_filter($d,$cantiere_id);};
    add_filter('upload_dir',$filter);
    $uploaded=wp_handle_upload($file,array('test_form'=>false));
    remove_filter('upload_dir',$filter);

    if(isset($uploaded['error']))return new WP_Error('upload_failed',$uploaded['error']);

    $mime=$uploaded['type']??'';
    $tipo='altro';
    if(strpos($mime,'image/')===0)$tipo='foto';
    elseif(strpos($mime,'video/')===0)$tipo='video';
    elseif($mime==='application/pdf'||strpos($mime,'document')!==false||strpos($mime,'spreadsheet')!==false)$tipo='documento';

    $dimensioni=$tipo==='foto'?@getimagesize($uploaded['file']):[0,0];

    /* ── Genera thumbnail per le foto ── */
    $thumb_url='';
    if($tipo==='foto'){
        $thumb_url=mssgc_generate_thumb($uploaded['file'],$uploaded['url']);
    }

    global $wpdb;
    $wpdb->insert(mssgc_table('media'),array(
        'cantiere_id'      =>$cantiere_id,
        'lavorazione_id'   =>(int)($args['lavorazione_id']??0),
        'tipo'             =>$tipo,
        'categoria'        =>sanitize_key($args['categoria']??'cantiere'),
        'nome'             =>sanitize_text_field($args['nome']??basename($uploaded['file'])),
        'nome_file'        =>basename($uploaded['file']),
        'file_path'        =>$uploaded['file'],
        'file_url'         =>$uploaded['url'],
        'mime_type'        =>$mime,
        'dimensione'       =>@filesize($uploaded['file']),
        'larghezza'        =>$dimensioni[0]??0,
        'altezza'          =>$dimensioni[1]??0,
        'note'             =>sanitize_text_field($args['note']??''),
        'thumb_url'        =>$thumb_url,
        'visibile_cliente' =>(int)($args['visibile_cliente']??0),
        'uploaded_by'      =>get_current_user_id(),
        'created_at'       =>current_time('mysql'),
    ));
    $new_id = $wpdb->insert_id;

    /* ── Gestione cloud storage ── */
    $storage_mode = get_option('mssg_storage_mode', 'local');
    $cloud_url    = '';
    $storage_loc  = 'local';

    if ( $storage_mode === 'cloud' || $storage_mode === 'both' ) {
        if ( function_exists('mssgcs_upload_cloud') ) {
            $cu = mssgcs_upload_cloud($uploaded['file'], basename($uploaded['file']), $cantiere_id);
            if ( ! is_wp_error($cu) ) {
                $cloud_url   = $cu;
                $storage_loc = $storage_mode;
                /* Se solo cloud: elimina il file locale */
                if ( $storage_mode === 'cloud' ) {
                    @unlink($uploaded['file']);
                    $wpdb->update(mssgc_table('media'), array(
                        'file_url'    => $cloud_url,
                        'cloud_url'   => $cloud_url,
                        'storage_loc' => 'cloud',
                    ), array('id' => $new_id));
                    return array('id'=>$new_id,'url'=>$cloud_url,'tipo'=>$tipo,'nome'=>basename($uploaded['file']));
                }
                /* Entrambi: aggiorna cloud_url */
                $wpdb->update(mssgc_table('media'), array(
                    'cloud_url'   => $cloud_url,
                    'storage_loc' => 'both',
                ), array('id' => $new_id));
            }
        }
    }

    return array('id'=>$new_id,'url'=>$uploaded['url'],'tipo'=>$tipo,'nome'=>basename($uploaded['file']));
}

/* ── Genera thumbnail 400px per le foto ── */
function mssgc_generate_thumb($file_path,$file_url){
    if(!file_exists($file_path))return '';
    $editor=wp_get_image_editor($file_path);
    if(is_wp_error($editor))return '';
    /* Ridimensiona mantenendo le proporzioni, max 400px sul lato lungo */
    $editor->resize(400,400,false);
    $editor->set_quality(80);
    /* Salva con suffisso -thumb nella stessa cartella */
    $info=pathinfo($file_path);
    $thumb_path=$info['dirname'].'/'.($info['filename']).'-thumb.jpg';
    $saved=$editor->save($thumb_path,'image/jpeg');
    if(is_wp_error($saved)||empty($saved['path']))return '';
    /* Costruisce l'URL del thumb dalla base URL del file */
    $base_url=dirname($file_url);
    return $base_url.'/'.basename($saved['path']);
}
