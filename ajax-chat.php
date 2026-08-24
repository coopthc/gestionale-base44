<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Invia messaggio chat ───────────────────────── */
add_action('wp_ajax_mssg_chat_invia',function(){
    mssg_ajax_check('view_cantieri');
    $cid=(int)($_POST['cantiere_id']??0);
    $testo=sanitize_textarea_field($_POST['testo']??'');
    $uid=get_current_user_id();
    if(!$cid||(!$testo&&empty($_FILES['allegato'])))wp_send_json_error(array('msg'=>'Messaggio vuoto.'));
    if(!mssgc_user_can_access($uid,$cid))wp_send_json_error(array('msg'=>'Non autorizzato.'));

    $allegato_url=$allegato_nome=$allegato_mime='';
    if(!empty($_FILES['allegato'])&&$_FILES['allegato']['error']===0){
        $r=mssgc_upload_media($_FILES['allegato'],$cid,array('categoria'=>'cantiere','visibile_cliente'=>0));
        if(!is_wp_error($r)){$allegato_url=$r['url'];$allegato_nome=$_FILES['allegato']['name'];$allegato_mime=$_FILES['allegato']['type'];}
    }

    global $wpdb;
    $wpdb->insert(mssgc_table('cantieri_chat'),array(
        'cantiere_id'=>$cid,'user_id'=>$uid,'testo'=>$testo,
        'allegato_url'=>$allegato_url,'allegato_nome'=>$allegato_nome,'allegato_mime'=>$allegato_mime,
        'letto_da'=>json_encode(array($uid)),'created_at'=>current_time('mysql'),
    ));
    $msg_id=$wpdb->insert_id;

    // Render HTML del messaggio per aggiornamento live
    $u=get_userdata($uid);
    $avatar=get_avatar_url($uid,array('size'=>28));
    $ora=date_i18n('d/m H:i',strtotime(current_time('mysql')));
    $html='<div class="mssgc-msg mssgc-msg--out" data-id="'.$msg_id.'">
        <div><div class="mssgc-msg-bubble">'.($testo?nl2br(esc_html($testo)):'');
    if($allegato_url)$html.='<a href="'.esc_url($allegato_url).'" target="_blank" class="mssgc-msg-allegato">'.(strpos($allegato_mime,'image/')===0?'🖼':'📎').' '.esc_html($allegato_nome).'</a>';
    $html.='</div><div class="mssgc-msg-meta">'.$ora.'</div></div>
        <img src="'.esc_url($avatar).'" class="mssgc-msg-avatar" alt=""></div>';

    wp_send_json_success(array('msg'=>'Inviato.','html'=>$html,'msg_id'=>$msg_id));
});

/* ── Poll nuovi messaggi ────────────────────────── */
add_action('wp_ajax_mssg_chat_poll',function(){
    mssg_ajax_check('view_cantieri');
    $cid=(int)($_POST['cantiere_id']??0);
    $last_id=(int)($_POST['last_id']??0);
    $uid=get_current_user_id();
    if(!mssgc_user_can_access($uid,$cid))wp_send_json_error(array('msg'=>'Non autorizzato.'));

    global $wpdb;$t=mssgc_table('cantieri_chat');
    $nuovi=$wpdb->get_results($wpdb->prepare(
        "SELECT m.*,u.display_name AS autore FROM `{$t}` m
         LEFT JOIN {$wpdb->users} u ON u.ID=m.user_id
         WHERE m.cantiere_id=%d AND m.id>%d AND m.deleted_at IS NULL
         ORDER BY m.created_at ASC",$cid,$last_id));

    $items=array();
    foreach($nuovi as $msg){
        $is_out=(int)$msg->user_id===$uid;
        $avatar=get_avatar_url($msg->user_id,array('size'=>28));
        $ora=date_i18n('d/m H:i',strtotime($msg->created_at));
        $html='<div class="mssgc-msg '.($is_out?'mssgc-msg--out':'mssgc-msg--in').'" data-id="'.$msg->id.'">';
        if(!$is_out)$html.='<img src="'.esc_url($avatar).'" class="mssgc-msg-avatar" alt="">';
        $html.='<div><div class="mssgc-msg-bubble">';
        if($msg->testo)$html.=nl2br(esc_html($msg->testo));
        if($msg->allegato_url)$html.='<a href="'.esc_url($msg->allegato_url).'" target="_blank" class="mssgc-msg-allegato">'.(strpos($msg->allegato_mime??'','image/')===0?'🖼':'📎').' '.esc_html($msg->allegato_nome).'</a>';
        $html.='</div><div class="mssgc-msg-meta">'.(!$is_out?esc_html($msg->autore).' · ':'').$ora.'</div></div>';
        if($is_out)$html.='<img src="'.esc_url($avatar).'" class="mssgc-msg-avatar" alt="">';
        $html.='</div>';
        $items[]=array('id'=>(int)$msg->id,'html'=>$html);

        // Segna come letto
        $letti=json_decode($msg->letto_da,true)??array();
        if(!in_array($uid,$letti)){$letti[]=$uid;$wpdb->update($t,array('letto_da'=>json_encode($letti)),array('id'=>$msg->id));}
    }
    wp_send_json_success(array('messaggi'=>$items));
});
