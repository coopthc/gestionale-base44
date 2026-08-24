<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Form cantiere ──────────────────────────────── */
add_action('wp_ajax_mssg_cantieri_form',function(){
    mssg_ajax_check('view_cantieri');
    $id=(int)($_POST['cantiere_id']??0);
    if($id&&!mssgc_user_can_access(get_current_user_id(),$id))wp_send_json_error(array('msg'=>'Non autorizzato.'));
    wp_send_json_success(array('html'=>mssgc_render_form($id,get_current_user_id())));
});

/* ── Salva cantiere ─────────────────────────────── */
add_action('wp_ajax_mssg_cantieri_save',function(){
    mssg_ajax_check('edit_cantieri','cantieri_save');
    $id=(int)($_POST['cantiere_id']??0);
    if($id&&!mssg_user_can(get_current_user_id(),'edit_cantieri',$id))wp_send_json_error(array('msg'=>'Non autorizzato.'));

    $f=mssg_ajax_fields(array(
        'nome'=>array('type'=>'text','required'=>true),
        'codice'=>array('type'=>'text'),'indirizzo'=>array('type'=>'text'),
        'citta'=>array('type'=>'text'),'cap'=>array('type'=>'text'),
        'data_inizio'=>array('type'=>'date'),'data_fine_prev'=>array('type'=>'date'),
        'importo_prev'=>array('type'=>'float'),'stato'=>array('type'=>'slug'),
        'descrizione'=>array('type'=>'textarea'),'note_interne'=>array('type'=>'textarea'),
    ));
    $stati_ok=array('bozza','attivo','sospeso','completato','chiuso','archiviato');
    if(!in_array($f['stato'],$stati_ok))$f['stato']='bozza';
    foreach(array('data_inizio','data_fine_prev') as $d)if(empty($f[$d]))$f[$d]=null;

    if($id>0){
        mssg_db_update('cantieri',$f,$id);
        wp_send_json_success(array('msg'=>'Cantiere aggiornato.','cantiere_id'=>$id));
    } else {
        mssgc_ensure_tables();
        $new_id=mssg_db_insert('cantieri',$f);
        if(is_wp_error($new_id))wp_send_json_error(array('msg'=>$new_id->get_error_message()));
        wp_send_json_success(array('msg'=>'Cantiere creato.','cantiere_id'=>$new_id,'is_new'=>true));
    }
});

/* ── Elimina cantiere (con conferma nome) ─────── */
add_action('wp_ajax_mssg_cantieri_delete',function(){
    mssg_ajax_check('delete_cantieri');
    $id=(int)($_POST['cantiere_id']??0);
    $confirm=sanitize_text_field($_POST['confirm_nome']??'');
    $c=mssgc_get_cantiere($id);
    if(!$c)wp_send_json_error(array('msg'=>'Cantiere non trovato.'));
    if(strtolower(trim($confirm))!==strtolower(trim($c->nome)))
        wp_send_json_error(array('msg'=>'Nome non corrisponde. Scrivi esattamente: '.esc_html($c->nome)));
    global $wpdb;
    $wpdb->update(mssgc_table('cantieri'),array('deleted_at'=>current_time('mysql')),array('id'=>$id));
    wp_send_json_success(array('msg'=>'Cantiere eliminato.'));
});

/* ── Archivia cantiere ──────────────────────────── */
add_action('wp_ajax_mssg_cantieri_riabilita',function(){
    mssg_ajax_check('edit_cantieri');
    $cid=(int)($_POST['cantiere_id']??0);
    if(!$cid)wp_send_json_error(array('msg'=>'ID mancante.'));
    global $wpdb;
    $wpdb->update(mssgc_table('cantieri'),
        array('stato'=>'attivo','updated_at'=>current_time('mysql')),
        array('id'=>$cid)
    );
    wp_send_json_success(array('msg'=>'Cantiere riabilitato come Attivo.'));
});

add_action('wp_ajax_mssg_cantieri_archivia',function(){
    mssg_ajax_check('edit_cantieri');
    $id=(int)($_POST['cantiere_id']??0);
    global $wpdb;
    $wpdb->update(mssgc_table('cantieri'),array('stato'=>'archiviato','updated_at'=>current_time('mysql')),array('id'=>$id));
    wp_send_json_success(array('msg'=>'Cantiere archiviato.'));
});

/* ── Pin / Unpin cantiere ───────────────────────── */
add_action('wp_ajax_mssg_cantieri_toggle_pin',function(){
    mssg_ajax_check('edit_cantieri');
    $id=(int)($_POST['cantiere_id']??0);
    $c=mssgc_get_cantiere($id);
    if(!$c)wp_send_json_error(array('msg'=>'Non trovato.'));
    $new=(int)$c->pinned===1?0:1;
    global $wpdb;
    $wpdb->update(mssgc_table('cantieri'),array('pinned'=>$new,'updated_at'=>current_time('mysql')),array('id'=>$id));
    wp_send_json_success(array('msg'=>$new?'Cantiere messo in evidenza.':'Rimosso da in evidenza.','pinned'=>$new));
});

/* ── Lista cantieri ─────────────────────────────── */
add_action('wp_ajax_mssg_cantieri_list',function(){
    mssg_ajax_check('view_cantieri');
    $stato=sanitize_key($_POST['stato']??'');
    $search=sanitize_text_field($_POST['search']??'');
    $f=$stato&&$stato!=='tutti'?array('stato'=>$stato):array();
    if($search)$f['search']=$search;
    ob_start();mssgc_render_lista(wp_get_current_user());
    wp_send_json_success(array('html'=>ob_get_clean()));
});

/* ── Aggiorna cliente / responsabile ────────────── */
add_action('wp_ajax_mssg_cantieri_update_cliente',function(){
    mssg_ajax_check('edit_cantieri');
    $cid=(int)($_POST['cantiere_id']??0);$uid=(int)($_POST['cliente_id']??0);
    global $wpdb;$wpdb->update(mssgc_table('cantieri'),array('cliente_id'=>$uid,'updated_at'=>current_time('mysql')),array('id'=>$cid));
    if($uid>0)mssgc_notify_cliente_cantiere($uid,$cid);
    wp_send_json_success(array('msg'=>'Cliente aggiornato.'));
});

add_action('wp_ajax_mssg_cantieri_update_responsabile',function(){
    mssg_ajax_check('edit_cantieri');
    $cid=(int)($_POST['cantiere_id']??0);$rid=(int)($_POST['responsabile_id']??0);
    global $wpdb;$wpdb->update(mssgc_table('cantieri'),array('responsabile_id'=>$rid,'updated_at'=>current_time('mysql')),array('id'=>$cid));
    wp_send_json_success(array('msg'=>'Responsabile aggiornato.'));
});

/* ── Team ───────────────────────────────────────── */
add_action('wp_ajax_mssg_cantieri_aggiungi_col',function(){
    mssg_ajax_check('assign_cantieri');
    $cid=(int)($_POST['cantiere_id']??0);$uid=(int)($_POST['user_id']??0);$ruolo=sanitize_key($_POST['ruolo']??'operaio');
    if(!$cid||!$uid)wp_send_json_error(array('msg'=>'Dati mancanti.'));
    global $wpdb;$t=mssgc_table('cantieri_users');
    $exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM `{$t}` WHERE cantiere_id=%d AND user_id=%d",$cid,$uid));
    if($exists){$wpdb->update($t,array('ruolo'=>$ruolo),array('id'=>$exists));}
    else{$wpdb->insert($t,array('cantiere_id'=>$cid,'user_id'=>$uid,'ruolo'=>$ruolo,'created_at'=>current_time('mysql')));mssgc_notify_collaboratore_assegnato($uid,$cid,$ruolo);}
    $u=get_userdata($uid);$ql=mssg_get_role_label($uid);
    $html='<div class="mssgc-team-row" data-user-id="'.$uid.'">
        <div class="mssgc-team-info"><img src="'.esc_url(get_avatar_url($uid,array('size'=>32))).'" width="32" height="32" style="border-radius:50%;flex-shrink:0">
        <div><div style="font-size:13px;font-weight:500">'.esc_html($u->display_name).'</div><div style="font-size:11px;color:var(--msslu-text-muted)">'.esc_html($ql).'</div></div></div>
        <select class="mssgc-ruolo-select" data-user-id="'.$uid.'" style="font-size:12px;padding:4px 8px;background:var(--msslu-input-bg);border:1px solid var(--msslu-box-border);border-radius:6px;color:var(--msslu-text)">
            <option value="operaio" '.selected($ruolo,'operaio',false).'>Operaio</option>
            <option value="capo" '.selected($ruolo,'capo',false).'>Capo cantiere</option>
            <option value="subappaltatore" '.selected($ruolo,'subappaltatore',false).'>Subappaltatore</option>
            <option value="supervisore" '.selected($ruolo,'supervisore',false).'>Supervisore</option>
        </select>
        <button class="mssg-btn mssg-btn--danger mssgc-rimuovi-col" data-user-id="'.$uid.'" style="padding:4px 8px;font-size:12px">✕</button></div>';
    wp_send_json_success(array('msg'=>'Collaboratore aggiunto.','html'=>$html,'user_id'=>$uid));
});

add_action('wp_ajax_mssg_cantieri_rimuovi_col',function(){
    mssg_ajax_check('assign_cantieri');
    global $wpdb;$wpdb->delete(mssgc_table('cantieri_users'),array('cantiere_id'=>(int)($_POST['cantiere_id']??0),'user_id'=>(int)($_POST['user_id']??0)));
    wp_send_json_success(array('msg'=>'Collaboratore rimosso.'));
});

add_action('wp_ajax_mssg_cantieri_update_ruolo',function(){
    mssg_ajax_check('assign_cantieri');
    global $wpdb;$wpdb->update(mssgc_table('cantieri_users'),array('ruolo'=>sanitize_key($_POST['ruolo']??'operaio')),array('cantiere_id'=>(int)($_POST['cantiere_id']??0),'user_id'=>(int)($_POST['user_id']??0)));
    wp_send_json_success(array('msg'=>'Ruolo aggiornato.'));
});

/* ── Upload media ───────────────────────────────── */
add_action('wp_ajax_mssg_cantieri_upload_media',function(){
    if(!check_ajax_referer('mssg_upload_nonce','nonce',false)&&!check_ajax_referer('mssg_nonce','nonce',false))
        wp_send_json_error(array('msg'=>'Nonce non valido.'));
    if(!mssg_user_can(get_current_user_id(),'upload_media'))wp_send_json_error(array('msg'=>'Non autorizzato.'));
    $cid=(int)($_POST['cantiere_id']??0);
    if(!$cid)wp_send_json_error(array('msg'=>'Cantiere non specificato.'));
    if(empty($_FILES['file']))wp_send_json_error(array('msg'=>'Nessun file.'));
    $result=mssgc_upload_media($_FILES['file'],$cid,array(
        'categoria'=>sanitize_key($_POST['categoria']??'cantiere'),
        'visibile_cliente'=>(int)($_POST['visibile_cliente']??0),
        'nome'=>sanitize_text_field($_POST['nome']??''),
        'note'=>sanitize_text_field($_POST['didascalia']??''),
    ));
    if(is_wp_error($result))wp_send_json_error(array('msg'=>$result->get_error_message()));
    wp_send_json_success(array('msg'=>'File caricato.','media'=>$result));
});

/* ── Elimina media ──────────────────────────────── */
add_action('wp_ajax_mssg_cantieri_delete_media',function(){
    mssg_ajax_check('upload_media');
    $id=(int)($_POST['media_id']??0);
    global $wpdb;$m=$wpdb->get_row($wpdb->prepare("SELECT * FROM `".mssgc_table('media')."` WHERE id=%d",$id));
    if($m&&$m->file_path&&file_exists($m->file_path))@unlink($m->file_path);
    $wpdb->update(mssgc_table('media'),array('deleted_at'=>current_time('mysql')),array('id'=>$id));
    wp_send_json_success(array('msg'=>'File eliminato.'));
});

/* ── Toggle visibile al cliente ─────────────────── */
add_action('wp_ajax_mssg_cantieri_toggle_visibilita',function(){
    mssg_ajax_check('upload_media');
    $id=(int)($_POST['media_id']??0);
    $stato=(int)($_POST['visibile']??0);
    if(!$id)wp_send_json_error(array('msg'=>'ID mancante.'));
    global $wpdb;
    $wpdb->update(mssgc_table('media'),array('visibile_cliente'=>$stato?1:0),array('id'=>$id));
    wp_send_json_success(array('visibile'=>$stato?1:0,'msg'=>$stato?'Visibile al cliente.':'Nascosto al cliente.'));
});

/* ── Salva percentuale avanzamento lavori ──────────
   CORREZIONE: prima si chiamava $wpdb->update() senza controllare né che il
   cantiere esistesse davvero né l'esito della query, e si rispondeva SEMPRE
   con successo. Se il cantiere_id ricevuto dal JS era stale/errato (es. per
   un pannello riutilizzato da un cantiere precedente), l'update non toccava
   nessuna riga ma l'interfaccia mostrava comunque "✓ Salvato!" — dando
   l'impressione che il salvataggio fosse andato a buon fine mentre in
   realtà il valore in DB restava quello precedente (spesso 0%). Ora si
   verifica che il cantiere esista e si rilegge il valore appena scritto,
   restituendolo al JS come conferma autoritativa. */
add_action('wp_ajax_mssg_cantieri_salva_avanzamento_pct',function(){
    mssg_ajax_check('manage_avanzamenti');
    $cid=(int)($_POST['cantiere_id']??0);
    $pct=min(100,max(0,(int)($_POST['pct']??0)));
    if(!$cid)wp_send_json_error(array('msg'=>'Cantiere mancante.'));
    global $wpdb;
    $tc=mssgc_table('cantieri');
    $esiste=$wpdb->get_var($wpdb->prepare("SELECT id FROM `{$tc}` WHERE id=%d AND deleted_at IS NULL",$cid));
    if(!$esiste)wp_send_json_error(array('msg'=>'Cantiere non trovato (potrebbe essere stato eliminato o la pagina potrebbe essere disallineata — ricarica e riprova).'));
    $wpdb->update($tc,array('avanzamento_pct'=>$pct,'updated_at'=>current_time('mysql')),array('id'=>$cid));
    $confermato=(int)$wpdb->get_var($wpdb->prepare("SELECT avanzamento_pct FROM `{$tc}` WHERE id=%d",$cid));
    if($confermato!==$pct)wp_send_json_error(array('msg'=>'Il salvataggio non risulta confermato dal database. Riprova.'));
    wp_send_json_success(array('pct'=>$confermato,'msg'=>'Avanzamento aggiornato.'));
});

/* ── Rigenera thumbnail foto esistenti (una alla volta, chiamato da JS) ── */
add_action('wp_ajax_mssg_cantieri_rigenera_thumb',function(){
    mssg_ajax_check('manage_cantieri');
    $cid=(int)($_POST['cantiere_id']??0);
    if(!$cid)wp_send_json_error(array('msg'=>'Cantiere mancante.'));
    global $wpdb;$t=mssgc_table('media');
    /* Prende UN record foto senza thumb */
    $m=$wpdb->get_row($wpdb->prepare(
        "SELECT id,file_path,file_url FROM `{$t}` WHERE cantiere_id=%d AND tipo='foto' AND (thumb_url='' OR thumb_url IS NULL) AND deleted_at IS NULL LIMIT 1",
        $cid));
    if(!$m){wp_send_json_success(array('done'=>true,'msg'=>'Tutti i thumbnail generati.'));return;}
    $thumb=mssgc_generate_thumb($m->file_path,$m->file_url);
    if($thumb){$wpdb->update($t,array('thumb_url'=>$thumb),array('id'=>$m->id));}
    else{$wpdb->update($t,array('thumb_url'=>$m->file_url),array('id'=>$m->id));} /* fallback: usa originale */
    wp_send_json_success(array('done'=>false,'id'=>(int)$m->id,'thumb'=>$thumb?:$m->file_url));
});

/* ── Svuota chat cantiere ───────────────────────── */
add_action('wp_ajax_mssg_chat_clear',function(){
    mssg_ajax_check('manage_cantieri');
    $cid=(int)($_POST['cantiere_id']??0);
    if(!$cid)wp_send_json_error(array('msg'=>'Cantiere non specificato.'));
    global $wpdb;$t=mssgc_table('cantieri_chat');
    $wpdb->query($wpdb->prepare("UPDATE `{$t}` SET deleted_at=%s WHERE cantiere_id=%d AND deleted_at IS NULL",current_time('mysql'),$cid));
    wp_send_json_success(array('msg'=>'Chat svuotata.'));
});

/* ── Elimina singolo messaggio chat ─────────────── */
add_action('wp_ajax_mssg_chat_delete_msg',function(){
    mssg_ajax_check('manage_cantieri');
    $id=(int)($_POST['msg_id']??0);
    if(!$id)wp_send_json_error(array('msg'=>'ID mancante.'));
    global $wpdb;
    $wpdb->update(mssgc_table('cantieri_chat'),array('deleted_at'=>current_time('mysql')),array('id'=>$id));
    wp_send_json_success(array('msg'=>'Messaggio eliminato.'));
});

/* ── Avanzamento ────────────────────────────────── */
add_action('wp_ajax_mssg_cantieri_pubblica_avanzamento',function(){
    mssg_ajax_check('manage_avanzamenti');
    $cid=(int)($_POST['cantiere_id']??0);
    $titolo=sanitize_text_field($_POST['titolo']??'');
    if(!$titolo)wp_send_json_error(array('msg'=>'Titolo obbligatorio.'));
    $tipo=sanitize_key($_POST['tipo']??'aggiornamento');
    if(!in_array($tipo,array('aggiornamento','avviso','completamento','problema')))$tipo='aggiornamento';
    $visibile=(int)($_POST['visibile_cliente']??1);
    global $wpdb;
    $wpdb->insert(mssgc_table('avanzamenti'),array('cantiere_id'=>$cid,'titolo'=>$titolo,'testo'=>sanitize_textarea_field($_POST['testo']??''),'tipo'=>$tipo,'visibile_cliente'=>$visibile,'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql')));
    if($visibile)mssgc_notify_avanzamento_cliente($cid,$titolo,sanitize_textarea_field($_POST['testo']??''));
    wp_send_json_success(array('msg'=>'Aggiornamento pubblicato'.($visibile?' e notifica inviata.':'.')));
});

add_action('wp_ajax_mssg_cantieri_delete_avanzamento',function(){
    mssg_ajax_check('manage_avanzamenti');
    global $wpdb;$wpdb->update(mssgc_table('avanzamenti'),array('deleted_at'=>current_time('mysql')),array('id'=>(int)($_POST['avanz_id']??0)));
    wp_send_json_success(array('msg'=>'Aggiornamento rimosso.'));
});

/* ── Modifica aggiornamento avanzamento già pubblicato ──
   In precedenza era possibile solo eliminare un aggiornamento (per correggere
   un errore di battitura bisognava cancellarlo e ripubblicarlo, perdendo la
   data originale). Permette ora di modificarlo sul posto. */
add_action('wp_ajax_mssg_cantieri_modifica_avanzamento',function(){
    mssg_ajax_check('manage_avanzamenti');
    $id=(int)($_POST['avanz_id']??0);
    if(!$id)wp_send_json_error(array('msg'=>'ID mancante.'));
    $titolo=sanitize_text_field($_POST['titolo']??'');
    if(!$titolo)wp_send_json_error(array('msg'=>'Titolo obbligatorio.'));
    $tipo=sanitize_key($_POST['tipo']??'aggiornamento');
    if(!in_array($tipo,array('aggiornamento','avviso','completamento','problema')))$tipo='aggiornamento';
    $visibile=(int)($_POST['visibile_cliente']??0);
    global $wpdb;
    $t=mssgc_table('avanzamenti');
    $esiste=$wpdb->get_var($wpdb->prepare("SELECT id FROM `{$t}` WHERE id=%d AND deleted_at IS NULL",$id));
    if(!$esiste)wp_send_json_error(array('msg'=>'Aggiornamento non trovato.'));
    $wpdb->update($t,array(
        'titolo'=>$titolo,
        'testo'=>sanitize_textarea_field($_POST['testo']??''),
        'tipo'=>$tipo,
        'visibile_cliente'=>$visibile,
        'updated_at'=>current_time('mysql'),
    ),array('id'=>$id));
    wp_send_json_success(array('msg'=>'Aggiornamento modificato.'));
});

/* ── Appuntamento ───────────────────────────────── */
add_action('wp_ajax_mssg_cantieri_salva_appuntamento',function(){
    mssg_ajax_check('edit_cantieri');
    $cid=(int)($_POST['cantiere_id']??0);
    $titolo=sanitize_text_field($_POST['titolo']??'');
    $data_ora=sanitize_text_field($_POST['data_ora']??'');
    if(!$titolo||!$data_ora)wp_send_json_error(array('msg'=>'Titolo e data obbligatori.'));
    $uid=(int)($_POST['user_id']??0);$notifica=(int)($_POST['notifica']??1);
    global $wpdb;
    $wpdb->insert(mssgc_table('appuntamenti'),array('cantiere_id'=>$cid,'user_id'=>$uid,'titolo'=>$titolo,'data_ora'=>$data_ora,'durata_min'=>(int)($_POST['durata']??60),'luogo'=>sanitize_text_field($_POST['luogo']??''),'note'=>sanitize_textarea_field($_POST['note']??''),'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql')));
    if($notifica&&$uid)mssgc_notify_appuntamento($uid,$cid,$data_ora,sanitize_text_field($_POST['luogo']??''),sanitize_textarea_field($_POST['note']??''));
    global $last_app_id;$last_app_id=$wpdb->insert_id;
wp_send_json_success(array('msg'=>'Appuntamento salvato'.($notifica&&$uid?' e notifica inviata.':'.'),'app_id'=>$wpdb->insert_id));
});

add_action('wp_ajax_mssg_cantieri_delete_appuntamento',function(){
    mssg_ajax_check('edit_cantieri');
    global $wpdb;$wpdb->delete(mssgc_table('appuntamenti'),array('id'=>(int)($_POST['app_id']??0)));
    wp_send_json_success(array('msg'=>'Appuntamento eliminato.'));
});

/* ── Ricarica tab avanzamento (parziale, no full form) ── */
add_action('wp_ajax_mssg_cantieri_avanzamento_tab',function(){
    mssg_ajax_check('view_cantieri');
    $cid=(int)($_POST['cantiere_id']??0);
    if(!$cid)wp_send_json_error(array('msg'=>'ID cantiere mancante.'));
    /* SICUREZZA (Task #23): questo handler leggeva prima $cid dal POST e
       renderizzava il tab senza verificare che il chiamante fosse davvero
       assegnato a quel cantiere — chiunque avesse la cap 'view_cantieri'
       (inclusi i clienti) poteva passare l'ID di UN CANTIERE QUALSIASI e
       vedere gli aggiornamenti interni. Aggiunto controllo di appartenenza. */
    if(!mssgc_user_can_access(get_current_user_id(),$cid))wp_send_json_error(array('msg'=>'Non autorizzato.'));
    /* mssgc_render_avanzamento_tab usa internamente ob_start/return */
    $html = mssgc_render_avanzamento_tab($cid,get_current_user_id());
    wp_send_json_success($html);
});

/* ── Ricarica tab note/appuntamenti (parziale) ── */
add_action('wp_ajax_mssg_cantieri_note_tab',function(){
    mssg_ajax_check('view_cantieri');
    $cid=(int)($_POST['cantiere_id']??0);
    if(!$cid)wp_send_json_error(array('msg'=>'ID cantiere mancante.'));
    /* SICUREZZA (Task #23): stesso problema di IDOR del tab avanzamento, v. sopra. */
    if(!mssgc_user_can_access(get_current_user_id(),$cid))wp_send_json_error(array('msg'=>'Non autorizzato.'));
    ob_start();
    mssgc_render_note_tab($cid,get_current_user_id());
    wp_send_json_success(ob_get_clean());
});

/* ══════════════════════════════════════════════════════
   PAGAMENTI / MILESTONE CANTIERE
══════════════════════════════════════════════════════ */

/* ── Carica milestone del cantiere ── */
add_action('wp_ajax_mssg_cantieri_pagamenti_load', function() {
    mssg_ajax_check('edit_cantieri');
    $cid = (int)($_POST['cantiere_id'] ?? 0);
    if (!$cid) wp_send_json_error(array('msg'=>'ID mancante.'));
    global $wpdb;
    $pagamenti = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `".mssgc_table('pagamenti')."` WHERE cantiere_id=%d ORDER BY ordine ASC, id ASC",
        $cid));
    $cantiere = mssgc_get_cantiere($cid);
    $importo_prev = (float)($cantiere->importo_prev ?? 0);
    wp_send_json_success(array('pagamenti'=>$pagamenti,'importo_prev'=>$importo_prev));
});

/* ── Salva milestone (crea o aggiorna) ── */
add_action('wp_ajax_mssg_cantieri_pagamento_save', function() {
    mssg_ajax_check('edit_cantieri');
    $cid   = (int)($_POST['cantiere_id'] ?? 0);
    $pid   = (int)($_POST['pagamento_id'] ?? 0);
    $tipo  = sanitize_key($_POST['tipo'] ?? 'avanzamento');
    $label = sanitize_text_field($_POST['label'] ?? '');
    $perc  = min(100, max(0, (int)($_POST['percentuale'] ?? 0)));
    $pagato = (int)($_POST['pagato'] ?? 0);
    $data  = sanitize_text_field($_POST['data_pagamento'] ?? '');
    $importo = (float)($_POST['importo'] ?? 0);
    $note  = sanitize_text_field($_POST['note'] ?? '');
    $ordine = (int)($_POST['ordine'] ?? 0);

    if (!$cid) wp_send_json_error(array('msg'=>'ID cantiere mancante.'));
    if (!in_array($tipo, array('acconto','avanzamento','saldo'))) $tipo='avanzamento';

    global $wpdb;
    $data_row = array(
        'cantiere_id' => $cid, 'tipo' => $tipo, 'label' => $label,
        'percentuale' => $perc, 'ordine' => $ordine, 'pagato' => $pagato,
        'data_pagamento' => $data ?: null, 'importo' => $importo,
        'note' => $note, 'updated_at' => current_time('mysql'),
    );
    if ($pid) {
        $wpdb->update(mssgc_table('pagamenti'), $data_row, array('id'=>$pid));
    } else {
        $data_row['created_at'] = current_time('mysql');
        $wpdb->insert(mssgc_table('pagamenti'), $data_row);
        $pid = $wpdb->insert_id;
    }

    /* Ricalcola progressione cantiere */
    mssgc_aggiorna_progressione($cid);

    wp_send_json_success(array('msg'=>'Salvato.','pagamento_id'=>$pid));
});

/* ── Elimina milestone ── */
add_action('wp_ajax_mssg_cantieri_pagamento_delete', function() {
    mssg_ajax_check('edit_cantieri');
    $pid = (int)($_POST['pagamento_id'] ?? 0);
    if (!$pid) wp_send_json_error(array('msg'=>'ID mancante.'));
    global $wpdb;
    $cid = (int)$wpdb->get_var($wpdb->prepare("SELECT cantiere_id FROM `".mssgc_table('pagamenti')."` WHERE id=%d",$pid));
    $wpdb->delete(mssgc_table('pagamenti'), array('id'=>$pid));
    if ($cid) mssgc_aggiorna_progressione($cid);
    wp_send_json_success(array('msg'=>'Milestone eliminata.'));
});

/* ── Funzione: aggiorna avanzamento cantiere in base ai pagamenti ── */
function mssgc_aggiorna_progressione($cantiere_id) {
    global $wpdb;
    $pagamenti = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `".mssgc_table('pagamenti')."` WHERE cantiere_id=%d ORDER BY ordine ASC",
        $cantiere_id));
    if (empty($pagamenti)) return;
    /* Calcola % totale basata sui pagamenti pagati */
    $perc_totale = 0;
    foreach ($pagamenti as $p) { if ($p->pagato) $perc_totale += $p->percentuale; }
    $perc_totale = min(100, $perc_totale);
    /* Salva nel meta del cantiere */
    $wpdb->update(mssgc_table('cantieri'), array('updated_at'=>current_time('mysql')), array('id'=>$cantiere_id));
    /* Aggiorna meta personalizzato */
    update_option("mssgc_progressione_{$cantiere_id}", $perc_totale);
}

/* ══════════════════════════════════════════════════════
   APPUNTAMENTI — RISPOSTA CLIENTE
══════════════════════════════════════════════════════ */

/* ── Cliente accetta o propone modifica appuntamento ── */
add_action('wp_ajax_mssg_app_risposta_cliente', function() {
    check_ajax_referer('mssg_nonce','nonce');
    if (!is_user_logged_in()) wp_send_json_error(array('msg'=>'Non autenticato.'));

    $app_id  = (int)($_POST['app_id'] ?? 0);
    $azione  = sanitize_key($_POST['azione'] ?? ''); // 'accetta' | 'proponi'
    $prop_dt = sanitize_text_field($_POST['proposta_data'] ?? '');
    $prop_nota = sanitize_textarea_field($_POST['proposta_nota'] ?? '');

    if (!$app_id || !in_array($azione, array('accetta','proponi'))) {
        wp_send_json_error(array('msg'=>'Parametri non validi.'));
    }

    global $wpdb;
    $app = $wpdb->get_row($wpdb->prepare(
        "SELECT a.*, c.nome AS cantiere_nome FROM `".mssgc_table('appuntamenti')."` a
         LEFT JOIN `".mssgc_table('cantieri')."` c ON c.id=a.cantiere_id
         WHERE a.id=%d", $app_id));

    if (!$app) wp_send_json_error(array('msg'=>'Appuntamento non trovato.'));
    /* Verifica che il cliente sia il partecipante */
    if ((int)$app->user_id !== get_current_user_id()) {
        wp_send_json_error(array('msg'=>'Non autorizzato.'));
    }

    if ($azione === 'accetta') {
        $wpdb->update(mssgc_table('appuntamenti'),
            array('stato_cliente'=>'accettato'),
            array('id'=>$app_id));
        $msg_admin = get_userdata(get_current_user_id())->display_name .
            ' ha ACCETTATO l\'appuntamento "' . $app->titolo . '" del ' .
            date_i18n('d/m/Y H:i', strtotime($app->data_ora)) . '.';
    } else {
        if (!$prop_dt) wp_send_json_error(array('msg'=>'Inserisci data e ora proposta.'));
        $wpdb->update(mssgc_table('appuntamenti'),
            array('stato_cliente'=>'proposta_modifica','proposta_data'=>$prop_dt,'proposta_nota'=>$prop_nota),
            array('id'=>$app_id));
        $msg_admin = get_userdata(get_current_user_id())->display_name .
            ' propone una MODIFICA all\'appuntamento "' . $app->titolo . '".' .
            ' Nuova data proposta: ' . date_i18n('d/m/Y H:i', strtotime($prop_dt)) .
            ($prop_nota ? ' — Note: ' . $prop_nota : '');
    }

    /* Notifica email all'admin */
    $admin_email = get_option('admin_email');
    $azienda = mssg_get_option('company_name', get_bloginfo('name'));
    wp_mail($admin_email,
        "[{$azienda}] Risposta appuntamento da " . get_userdata(get_current_user_id())->display_name,
        $msg_admin . "

Cantiere: " . ($app->cantiere_nome ?? '—') . "
Gestisci dal backend gestionale."
    );

    /* Inserisci nelle comunicazioni dell\'admin (tabella mssg_comunicazioni) */
    $tcom = $wpdb->prefix . 'mssg_comunicazioni';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$tcom}'") === $tcom) {
        $wpdb->insert($tcom, array(
            'cliente_id'  => get_current_user_id(),
            'cantiere_id' => $app->cantiere_id,
            'mittente_id' => get_current_user_id(),
            'tipo'        => 'messaggio',
            'oggetto'     => ($azione==='accetta'?'✅ Appuntamento accettato':'📅 Proposta modifica appuntamento').': '.$app->titolo,
            'testo'       => $msg_admin,
            'file_url'    => '',
            'file_nome'   => '',
            'letta'       => 0,
            'created_at'  => current_time('mysql'),
        ));
    }

    wp_send_json_success(array('msg' => $azione==='accetta' ? 'Appuntamento accettato!' : 'Proposta inviata all\'amministratore.'));
});

/* ── Admin: accetta/aggiorna proposta cliente ── */
add_action('wp_ajax_mssg_app_admin_accetta_proposta', function() {
    mssg_ajax_check('edit_cantieri');
    $app_id = (int)($_POST['app_id'] ?? 0);
    $nuova_data = sanitize_text_field($_POST['nuova_data'] ?? '');
    if (!$app_id) wp_send_json_error(array('msg'=>'ID mancante.'));
    global $wpdb;
    $update = array('stato_cliente'=>'accettato');
    if ($nuova_data) $update['data_ora'] = $nuova_data;
    $wpdb->update(mssgc_table('appuntamenti'), $update, array('id'=>$app_id));
    wp_send_json_success(array('msg'=>'Appuntamento aggiornato.'));
});

/* ══════════════════════════════════════════
   AJAX: PAGAMENTI / MILESTONE
═══════════════════════════════════════════ */

/* Aggiungi milestone */
add_action('wp_ajax_mssg_cantieri_pag_aggiungi', function() {
    mssg_ajax_check('edit_cantieri');
    $cid  = (int)($_POST['cantiere_id'] ?? 0);
    if (!$cid) wp_send_json_error(array('msg'=>'ID mancante.'));

    $tipo  = sanitize_key($_POST['tipo'] ?? 'avanzamento');
    $tipi  = array('acconto','avanzamento','saldo');
    if (!in_array($tipo,$tipi)) $tipo = 'avanzamento';

    global $wpdb;
    $tp = mssgc_table('pagamenti');

    /* Calcola ordine */
    $ordine_map = array('acconto'=>1,'avanzamento'=>5,'saldo'=>99);
    $ordine = $ordine_map[$tipo];
    /* Avanzamenti multipli: incrementa ordine */
    if ($tipo === 'avanzamento') {
        $max = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT MAX(ordine) FROM `{$tp}` WHERE cantiere_id=%d AND tipo='avanzamento'", $cid));
        $ordine = max(5, $max + 1);
    }

    $wpdb->insert($tp, array(
        'cantiere_id'  => $cid,
        'tipo'         => $tipo,
        'label'        => sanitize_text_field($_POST['label'] ?? ''),
        'percentuale'  => min(100, max(0, (int)($_POST['percentuale'] ?? 0))),
        'importo'      => (float)($_POST['importo'] ?? 0),
        'ordine'       => $ordine,
        'note'         => sanitize_text_field($_POST['note'] ?? ''),
        'pagato'       => 0,
        'created_at'   => current_time('mysql'),
    ));

    $html = mssgc_render_pagamenti_tab($cid, get_current_user_id());
    wp_send_json_success(array('msg'=>'Milestone aggiunta.','html'=>$html));
});

/* Segna pagato / non pagato */
add_action('wp_ajax_mssg_cantieri_pag_toggle', function() {
    mssg_ajax_check('edit_cantieri');
    /* Accetta sia 'pag_id' (vecchio) che 'milestone_id' (nuovo) */
    $id    = (int)($_POST['milestone_id'] ?? $_POST['pag_id'] ?? 0);
    $stato = isset($_POST['pagato']) ? (int)$_POST['pagato'] : -1;
    $data  = sanitize_text_field($_POST['data_pagamento'] ?? date('Y-m-d'));

    if (!$id) wp_send_json_error(array('msg'=>'ID mancante.'));

    global $wpdb;
    $tp  = mssgc_table('pagamenti');
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$tp}` WHERE id=%d", $id));
    if (!$row) wp_send_json_error(array('msg'=>'Milestone non trovata.'));

    $update = array('updated_at' => current_time('mysql'));
    if ($stato !== -1) {
        $update['pagato']           = $stato;
        $update['data_pagamento']   = $stato ? $data : null;
    } elseif (!empty($_POST['data_pagamento'])) {
        $update['data_pagamento'] = $data;
    }
    $wpdb->update($tp, $update, array('id' => $id));

    /* Restituisce HTML aggiornato del tab */
    $html = mssgc_render_pagamenti_tab($row->cantiere_id, get_current_user_id());
    wp_send_json_success(array('msg'=>'Aggiornato.','html'=>$html));
});

/* Aggiorna data pagamento */
add_action('wp_ajax_mssg_cantieri_pag_data', function() {
    mssg_ajax_check('edit_cantieri');
    $id   = (int)($_POST['pag_id'] ?? 0);
    $data = sanitize_text_field($_POST['data_pagamento'] ?? '');
    global $wpdb;
    $wpdb->update(mssgc_table('pagamenti'),
        array('data_pagamento'=>$data,'updated_at'=>current_time('mysql')),
        array('id'=>$id));
    wp_send_json_success(array('msg'=>'Data salvata.'));
});

/* Elimina milestone */
add_action('wp_ajax_mssg_cantieri_pag_elimina', function() {
    mssg_ajax_check('edit_cantieri');
    $id = (int)($_POST['pag_id'] ?? 0);
    global $wpdb;
    $wpdb->delete(mssgc_table('pagamenti'), array('id'=>$id));
    wp_send_json_success(array('msg'=>'Milestone eliminata.'));
});

/* ══════════════════════════════════════════
   AJAX: APPUNTAMENTI — RISPOSTA CLIENTE
═══════════════════════════════════════════ */

/* Cliente accetta appuntamento */
/* mssg_app_accetta rimosso — gestito da mssg_app_risposta_cliente */
/* mssg_app_proponi rimosso — gestito da mssg_app_risposta_cliente */
/* mssg_app_accetta_proposta rimosso — vedere mssg_app_admin_accetta_proposta */
/* Alias rimosso — handler unico già presente a riga 329 */

/* ── Cliente: elimina appuntamento (solo futuri) ── */
add_action('wp_ajax_mssg_app_elimina', function() {
    check_ajax_referer('mssg_nonce','nonce');
    $id  = (int)($_POST['app_id'] ?? 0);
    $uid = get_current_user_id();
    global $wpdb;
    $app = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `".mssgc_table('appuntamenti')."` WHERE id=%d", $id));
    if (!$app) wp_send_json_error(array('msg'=>'Non trovato.'));
    /* Solo chi ha creato o è il partecipante può eliminarlo */
    if ((int)$app->created_by !== $uid && (int)$app->user_id !== $uid
        && !mssg_user_can($uid,'edit_cantieri')) {
        wp_send_json_error(array('msg'=>'Non autorizzato.'));
    }
    $wpdb->delete(mssgc_table('appuntamenti'), array('id'=>$id));
    wp_send_json_success(array('msg'=>'Appuntamento eliminato.'));
});

/* ── SVG upload support ── */
add_filter( 'upload_mimes', function( $mimes ) {
    if ( current_user_can( 'upload_files' ) || mssg_user_can( get_current_user_id(), 'upload_media' ) ) {
        $mimes['svg']  = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
    }
    return $mimes;
}, 10 );

/* Fix SVG MIME detection (wp_check_filetype fallback) */
add_filter( 'wp_check_filetype_and_ext', function( $data, $file, $filename, $mimes ) {
    if ( ! $data['ext'] && ! $data['type'] ) {
        $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
        if ( $ext === 'svg' ) {
            $data['ext']  = 'svg';
            $data['type'] = 'image/svg+xml';
        }
    }
    return $data;
}, 10, 4 );

/* ── Ricarica solo il tab media (usato dopo upload per restare sulla tab) ── */
add_action( 'wp_ajax_mssg_cantieri_media_tab', function() {
    mssg_ajax_check( 'view_cantieri' );
    $cid = (int) ( $_POST['cantiere_id'] ?? 0 );
    if ( ! $cid ) wp_send_json_error( array( 'msg' => 'ID mancante.' ) );
    /* SICUREZZA (Task #23): idem tab avanzamento/note — evita che un cliente o
       un collaboratore non assegnato veda i media (anche quelli NON marcati
       visibile_cliente) di un cantiere altrui. */
    if ( ! mssgc_user_can_access( get_current_user_id(), $cid ) ) wp_send_json_error( array( 'msg' => 'Non autorizzato.' ) );
    $html = mssgc_render_media_tab( $cid, get_current_user_id() );
    wp_send_json_success( $html );
});

/* ── Ricarica tab pagamenti ── */
add_action( 'wp_ajax_mssg_cantieri_pagamenti_tab', function() {
    mssg_ajax_check( 'view_cantieri' );
    $cid = (int) ( $_POST['cantiere_id'] ?? 0 );
    if ( ! $cid ) wp_send_json_error();
    /* SICUREZZA (Task #23): idem — i pagamenti includono note interne mai
       pensate per il cliente. */
    if ( ! mssgc_user_can_access( get_current_user_id(), $cid ) ) wp_send_json_error( array( 'msg' => 'Non autorizzato.' ) );
    $html = mssgc_render_pagamenti_tab( $cid, get_current_user_id() );
    wp_send_json_success( array( 'html' => $html ) );
});

/* ── Elimina milestone pagamento ── */
add_action( 'wp_ajax_mssg_cantieri_pag_delete', function() {
    mssg_ajax_check( 'edit_cantieri' );
    $pid = (int) ( $_POST['milestone_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( array( 'msg' => 'ID mancante.' ) );
    global $wpdb;
    $tp  = mssgc_table( 'pagamenti' );
    $cid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT cantiere_id FROM `{$tp}` WHERE id=%d", $pid ) );
    $wpdb->delete( $tp, array( 'id' => $pid ) );
    $html = mssgc_render_pagamenti_tab( $cid, get_current_user_id() );
    wp_send_json_success( array( 'msg' => 'Milestone eliminata.', 'html' => $html ) );
});
