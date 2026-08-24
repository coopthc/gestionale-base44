<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function mssgc_register_capabilities( $caps ) {
    $caps['view_cantieri']      = array('administrator','mssg_admin','mssg_capo','mssg_operaio','mssg_cliente');
    $caps['view_all_cantieri']  = array('administrator','mssg_admin');
    $caps['edit_cantieri']      = array('administrator','mssg_admin','mssg_capo');
    $caps['create_cantieri']    = array('administrator','mssg_admin');
    $caps['delete_cantieri']    = array('administrator','mssg_admin');
    $caps['assign_cantieri']    = array('administrator','mssg_admin','mssg_capo');
    $caps['upload_media']       = array('administrator','mssg_admin','mssg_capo','mssg_operaio');
    $caps['manage_avanzamenti'] = array('administrator','mssg_admin','mssg_capo');
    $caps['view_avanzamenti']   = array('administrator','mssg_admin','mssg_capo','mssg_operaio','mssg_cliente');
    /* "I miei lavori" — solo personale interno, NON il cliente */
    $caps['view_miei_lavori']   = array('administrator','mssg_admin','mssg_capo','mssg_operaio');
    /* 'manage_cantieri' è usata da ajax.php, cloud-storage.php, backup-import.php
       e dalla sezione "Storage & Cloud"/"Esporta dati" (mssg-cantieri.php) ma non
       era mai stata registrata in questa matrice: mssg_user_can() nega di default
       ogni capability non registrata (tranne per i WP administrator "veri"), quindi
       gli utenti con solo il ruolo mssg_admin — il "titolare" per cui questo ruolo
       esiste — non potevano archiviare/eliminare cantieri, gestire lo storage cloud
       né fare backup/restore. Corretto qui. */
    $caps['manage_cantieri']    = array('administrator','mssg_admin');
    return $caps;
}

add_filter('mssg_can_edit_cantieri', function($result,$user_id,$cantiere_id) {
    if (!$result) return false;
    if (mssg_user_can($user_id,'view_all_cantieri')) return true;
    return mssgc_user_can_access($user_id,$cantiere_id);
},10,3);
