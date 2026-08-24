<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ══════════════════════════════════════════════════════
   CHAT DI CANTIERE
   Caricata dentro il tab Media: upload → chat → gallery
══════════════════════════════════════════════════════ */

function mssgc_render_chat_tab($cantiere_id,$user_id){
    $messaggi=mssgc_get_chat_messaggi($cantiere_id);
    $me=get_current_user_id();
    ob_start();?>
    <div class="mssgc-chat-wrap" id="mssgc-chat-wrap" data-cantiere-id="<?php echo(int)$cantiere_id;?>">

        <div style="padding:10px 14px;border-bottom:1px solid var(--msslu-box-border);font-size:12px;font-weight:500;color:var(--msslu-text-muted);display:flex;align-items:center;justify-content:space-between">
            <span>💬 Chat del cantiere <span id="mssgc-chat-unread" style="margin-left:6px"></span></span>
            <?php if(mssg_user_can($user_id,'manage_cantieri')):?>
            <button id="mssgc-chat-clear" data-cantiere-id="<?php echo(int)$cantiere_id;?>"
                    style="background:none;border:none;color:rgba(239,68,68,.5);font-size:11px;cursor:pointer;padding:2px 6px;border-radius:4px"
                    title="Svuota tutta la chat">🗑 Svuota</button>
            <?php endif;?>
        </div>

        <div class="mssgc-chat-messages" id="mssgc-chat-messages">
            <?php if(empty($messaggi)):?>
            <div class="mssgc-chat-empty">Nessun messaggio. Inizia la conversazione!</div>
            <?php else:
            foreach($messaggi as $msg):
                $is_out=(int)$msg->user_id===$me;
                $avatar=get_avatar_url($msg->user_id,array('size'=>28));
                $ora=date_i18n('d/m H:i',strtotime($msg->created_at));
            ?>
            <div class="mssgc-msg <?php echo $is_out?'mssgc-msg--out':'mssgc-msg--in';?>" data-id="<?php echo(int)$msg->id;?>">
                <?php if(!$is_out):?>
                <img src="<?php echo esc_url($avatar);?>" class="mssgc-msg-avatar" alt="">
                <?php endif;?>
                <div>
                    <div class="mssgc-msg-bubble">
                        <?php if($msg->testo):?><?php echo nl2br(esc_html($msg->testo));?><?php endif;?>
                        <?php if($msg->allegato_url):?>
                        <a href="<?php echo esc_url($msg->allegato_url);?>" target="_blank" class="mssgc-msg-allegato">
                            <?php echo strpos($msg->allegato_mime,'image/')===0?'🖼':'📎';?>
                            <?php echo esc_html($msg->allegato_nome?:'Allegato');?>
                        </a>
                        <?php endif;?>
                    </div>
                    <div class="mssgc-msg-meta">
                        <?php if(!$is_out):?><?php echo esc_html($msg->autore);?> · <?php endif;?>
                        <?php echo $ora;?>
                    </div>
                </div>
                <?php if($is_out):?>
                <img src="<?php echo esc_url($avatar);?>" class="mssgc-msg-avatar" alt="">
                <?php endif;?>
            </div>
            <?php endforeach;endif;?>
        </div>

        <!-- Input area -->
        <div class="mssgc-chat-input-wrap">
            <label class="mssgc-chat-attach" title="Allega file">
                📎
                <input type="file" id="mssgc-chat-file" accept="image/*,video/*,.pdf,.doc,.docx"
                       style="display:none" data-cantiere-id="<?php echo(int)$cantiere_id;?>">
            </label>
            <textarea class="mssgc-chat-input" id="mssgc-chat-input"
                      placeholder="Scrivi un messaggio…" rows="1"
                      data-cantiere-id="<?php echo(int)$cantiere_id;?>"></textarea>
            <button class="mssgc-chat-send" id="mssgc-chat-send"
                    data-cantiere-id="<?php echo(int)$cantiere_id;?>">Invia</button>
        </div>

    </div>
    <?php
    return ob_get_clean();
}

function mssgc_get_chat_messaggi($cantiere_id,$limit=50){
    global $wpdb;
    $t=mssgc_table('cantieri_chat');
    if($wpdb->get_var("SHOW TABLES LIKE '{$t}'")===$t){
        return $wpdb->get_results($wpdb->prepare(
            "SELECT m.*,u.display_name AS autore FROM `{$t}` m
             LEFT JOIN {$wpdb->users} u ON u.ID=m.user_id
             WHERE m.cantiere_id=%d AND m.deleted_at IS NULL
             ORDER BY m.created_at ASC LIMIT %d",$cantiere_id,$limit));
    }
    return array();
}

function mssgc_count_unread_chat($cantiere_id,$user_id){
    global $wpdb;$t=mssgc_table('cantieri_chat');
    if($wpdb->get_var("SHOW TABLES LIKE '{$t}'")===$t){
        $rows=$wpdb->get_col($wpdb->prepare(
            "SELECT letto_da FROM `{$t}` WHERE cantiere_id=%d AND user_id!=%d AND deleted_at IS NULL",
            $cantiere_id,$user_id));
        $unread=0;
        foreach($rows as $r){
            $letti=json_decode($r,true)??array();
            if(!in_array($user_id,$letti))$unread++;
        }
        return $unread;
    }
    return 0;
}
