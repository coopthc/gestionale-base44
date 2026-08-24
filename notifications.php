<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ══════════════════════════════════════════════════════════
   NOTIFICHE EMAIL
══════════════════════════════════════════════════════════ */

function mssgc_notify_collaboratore_assegnato( $user_id, $cantiere_id, $ruolo ) {
    $user     = get_userdata($user_id);
    $cantiere = mssgc_get_cantiere($cantiere_id);
    if (!$user || !$cantiere) return;

    $ruoli_label = array(
        'capo'=>'Capo cantiere','operaio'=>'Operaio',
        'subappaltatore'=>'Subappaltatore','supervisore'=>'Supervisore',
    );
    $ruolo_label = $ruoli_label[$ruolo] ?? $ruolo;
    $azienda     = mssg_get_option('company_name', get_bloginfo('name'));
    $url         = mssg_get_private_area_url();
    $indirizzo   = trim($cantiere->indirizzo . ($cantiere->citta ? ', '.$cantiere->citta : ''));

    $subject = "[{$azienda}] Sei stato assegnato al cantiere: {$cantiere->nome}";
    $message = "Ciao {$user->display_name},\n\n"
             . "Sei stato aggiunto al cantiere \"{$cantiere->nome}\" come {$ruolo_label}.\n\n"
             . "📍 Indirizzo: " . ($indirizzo ?: 'Non specificato') . "\n"
             . "📅 Inizio previsto: " . ($cantiere->data_inizio ? date_i18n('d/m/Y',strtotime($cantiere->data_inizio)) : 'Da definire') . "\n"
             . "📊 Stato: " . ucfirst($cantiere->stato) . "\n\n"
             . "Accedi all'area gestionale per tutti i dettagli:\n{$url}\n\n"
             . "— {$azienda}";

    wp_mail($user->user_email, $subject, $message);
}

function mssgc_notify_cliente_cantiere( $user_id, $cantiere_id ) {
    $user     = get_userdata($user_id);
    $cantiere = mssgc_get_cantiere($cantiere_id);
    if (!$user || !$cantiere) return;

    $azienda = mssg_get_option('company_name', get_bloginfo('name'));
    $url     = mssg_get_private_area_url();

    $subject = "[{$azienda}] Il tuo cantiere è stato aperto: {$cantiere->nome}";
    $message = "Gentile {$user->display_name},\n\n"
             . "Abbiamo aperto la pratica per il cantiere \"{$cantiere->nome}\".\n\n"
             . "Da adesso puoi accedere alla tua area personale per seguire lo stato di avanzamento dei lavori e consultare i documenti che ti riguardano.\n\n"
             . "Accedi qui:\n{$url}\n\n"
             . "Per qualsiasi informazione siamo a disposizione.\n\n"
             . "— {$azienda}";

    wp_mail($user->user_email, $subject, $message);
}

function mssgc_notify_avanzamento_cliente( $cantiere_id, $titolo, $testo ) {
    $cantiere = mssgc_get_cantiere($cantiere_id);
    if (!$cantiere || !$cantiere->cliente_id) return;

    $cliente = get_userdata($cantiere->cliente_id);
    if (!$cliente) return;

    $azienda = mssg_get_option('company_name', get_bloginfo('name'));
    $url     = mssg_get_private_area_url();

    $subject = "[{$azienda}] Aggiornamento cantiere: {$cantiere->nome}";
    $message = "Gentile {$cliente->display_name},\n\n"
             . "Nuovo aggiornamento per il cantiere \"{$cantiere->nome}\":\n\n"
             . "» {$titolo}\n"
             . ($testo ? "\n{$testo}\n" : "")
             . "\nAccedi alla tua area per tutti i dettagli:\n{$url}\n\n"
             . "— {$azienda}";

    wp_mail($cliente->user_email, $subject, $message);
}

function mssgc_notify_appuntamento( $user_id, $cantiere_id, $data_ora, $luogo, $note ) {
    $user     = get_userdata($user_id);
    $cantiere = mssgc_get_cantiere($cantiere_id);
    if (!$user || !$cantiere) return;

    $azienda = mssg_get_option('company_name', get_bloginfo('name'));
    $data_f  = date_i18n('d/m/Y \a\l\l\e H:i', strtotime($data_ora));

    $subject = "[{$azienda}] Appuntamento: {$cantiere->nome} — {$data_f}";
    $message = "Gentile {$user->display_name},\n\n"
             . "Le confermiamo il seguente appuntamento:\n\n"
             . "📋 Cantiere: {$cantiere->nome}\n"
             . "📅 Data e ora: {$data_f}\n"
             . ($luogo ? "📍 Luogo: {$luogo}\n" : "")
             . ($note  ? "\nNote: {$note}\n" : "")
             . "\n— {$azienda}";

    wp_mail($user->user_email, $subject, $message);
}
