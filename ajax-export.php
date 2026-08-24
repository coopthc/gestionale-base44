<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Export CSV cantieri ────────────────────────── */
add_action('wp_ajax_mssg_cantieri_export_csv',function(){
    mssg_ajax_check('view_all_cantieri');
    mssgc_export_cantieri_csv(get_current_user_id());
});

/* ── Export PDF singolo cantiere ────────────────── */
add_action('wp_ajax_mssg_cantieri_export_pdf',function(){
    mssg_ajax_check('view_cantieri');
    $id=(int)($_POST['cantiere_id']??0);
    if(!$id||!mssgc_user_can_access(get_current_user_id(),$id))wp_send_json_error(array('msg'=>'Non autorizzato.'));
    $html=mssgc_export_cantiere_pdf_html($id,get_current_user_id());
    wp_send_json_success(array('html'=>$html));
});
