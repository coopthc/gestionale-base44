<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ══════════════════════════════════════════════════════
   EXPORT CSV e PDF
══════════════════════════════════════════════════════ */

function mssgc_export_cantieri_csv($user_id){
    $cantieri=mssgc_get_cantieri($user_id,array('stato'=>'tutti'));

    $rows=array();
    $rows[]=array('ID','Codice','Nome','Cliente','Responsabile','Indirizzo','Città','Stato','Inizio','Fine prev.','Importo prev. (€)','Creato il');

    foreach($cantieri as $c){
        $rows[]=array(
            $c->id,$c->codice,$c->nome,
            $c->cliente_nome??'',$c->responsabile_nome??'',
            $c->indirizzo,$c->citta,$c->stato,
            $c->data_inizio??'',$c->data_fine_prev??'',
            number_format($c->importo_prev,2,',','.'),
            $c->created_at,
        );
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="cantieri-'.date('Y-m-d').'.csv"');
    header('Pragma: no-cache');
    echo "\xEF\xBB\xBF"; // BOM UTF-8 per Excel
    $out=fopen('php://output','w');
    foreach($rows as $row) fputcsv($out,$row,';');
    fclose($out);
    exit;
}

function mssgc_export_cantiere_pdf_html($cantiere_id,$user_id){
    $c=mssgc_get_cantiere($cantiere_id,$user_id);
    if(!$c)return '';

    $avanzamenti=mssgc_get_avanzamenti($cantiere_id);
    $team=mssgc_get_collaboratori_cantiere($cantiere_id);
    $appuntamenti=mssgc_get_appuntamenti($cantiere_id);
    $media=mssgc_get_media($cantiere_id);
    $azienda=mssg_get_option('company_name',get_bloginfo('name'));

    $stati_map=array('bozza'=>'Bozza','attivo'=>'Attivo','sospeso'=>'Sospeso','completato'=>'Completato','chiuso'=>'Chiuso','archiviato'=>'Archiviato');
    $tipi_icon=array('aggiornamento'=>'🔄','avviso'=>'⚠️','completamento'=>'✅','problema'=>'🔴');

    ob_start();?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title><?php echo esc_html($c->nome);?> — Riepilogo</title>
<style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:Arial,sans-serif;font-size:12px;color:#1a1a2e;line-height:1.5;padding:24px}
    h1{font-size:20px;margin-bottom:4px;color:#1a1a2e}
    h2{font-size:14px;margin:18px 0 8px;color:#1a1a2e;border-bottom:1px solid #e2e8f0;padding-bottom:4px}
    .meta{font-size:11px;color:#666;margin-bottom:16px}
    table{width:100%;border-collapse:collapse;margin-bottom:12px}
    td,th{padding:6px 8px;border:1px solid #e2e8f0;font-size:11px;text-align:left}
    th{background:#f8fafc;font-weight:600}
    .badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600}
    .badge-attivo{background:#dcfce7;color:#166534}
    .badge-bozza{background:#f1f5f9;color:#475569}
    .badge-chiuso{background:#fee2e2;color:#991b1b}
    .badge-completato{background:#dbeafe;color:#1e40af}
    .section{margin-bottom:16px}
    .avanz-row{padding:6px 0;border-bottom:1px solid #f1f5f9}
    .avanz-titolo{font-weight:600;font-size:12px}
    .avanz-meta{font-size:10px;color:#94a3b8;margin-top:2px}
    @media print{body{padding:0}button{display:none}}
</style>
</head>
<body>
<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px">
    <div>
        <h1><?php echo esc_html($c->nome);?></h1>
        <div class="meta">
            <?php echo esc_html($azienda);?> — Generato il <?php echo date_i18n('d/m/Y H:i');?>
        </div>
    </div>
    <div>
        <span class="badge badge-<?php echo esc_attr($c->stato);?>"><?php echo $stati_map[$c->stato]??$c->stato;?></span>
    </div>
</div>

<h2>Dati cantiere</h2>
<table>
    <tr><th>Codice</th><td><?php echo esc_html($c->codice?:'—');?></td><th>Stato</th><td><?php echo $stati_map[$c->stato]??$c->stato;?></td></tr>
    <tr><th>Indirizzo</th><td colspan="3"><?php echo esc_html(trim($c->indirizzo.($c->citta?', '.$c->citta:'').($c->cap?' '.$c->cap:'')));?></td></tr>
    <tr><th>Cliente</th><td><?php echo esc_html($c->cliente_nome?:'—');?></td><th>Responsabile</th><td><?php echo esc_html($c->responsabile_nome?:'—');?></td></tr>
    <tr><th>Inizio</th><td><?php echo $c->data_inizio?date_i18n('d/m/Y',strtotime($c->data_inizio)):'—';?></td><th>Fine prevista</th><td><?php echo $c->data_fine_prev?date_i18n('d/m/Y',strtotime($c->data_fine_prev)):'—';?></td></tr>
    <tr><th>Importo prev.</th><td colspan="3">€ <?php echo number_format($c->importo_prev,2,',','.');?></td></tr>
    <?php if($c->descrizione):?><tr><th>Descrizione</th><td colspan="3"><?php echo nl2br(esc_html($c->descrizione));?></td></tr><?php endif;?>
</table>

<?php if(!empty($team)):?>
<h2>Team (<?php echo count($team);?>)</h2>
<table>
    <tr><th>Nome</th><th>Ruolo</th><th>Email</th></tr>
    <?php $ruoli_label=array('capo'=>'Capo cantiere','operaio'=>'Operaio','subappaltatore'=>'Sub.','supervisore'=>'Supervisore');
    foreach($team as $m):?>
    <tr><td><?php echo esc_html($m->display_name);?></td><td><?php echo $ruoli_label[$m->ruolo_cantiere??'operaio']??$m->ruolo_cantiere;?></td><td><?php echo esc_html($m->user_email);?></td></tr>
    <?php endforeach;?>
</table>
<?php endif;?>

<?php if(!empty($avanzamenti)):?>
<h2>Avanzamento lavori (<?php echo count($avanzamenti);?> aggiornamenti)</h2>
<div class="section">
    <?php foreach($avanzamenti as $a):?>
    <div class="avanz-row">
        <div class="avanz-titolo"><?php echo($tipi_icon[$a->tipo]??'').' '.esc_html($a->titolo);?></div>
        <?php if($a->testo):?><div style="font-size:11px;color:#475569;margin-top:2px"><?php echo nl2br(esc_html($a->testo));?></div><?php endif;?>
        <div class="avanz-meta"><?php echo date_i18n('d/m/Y H:i',strtotime($a->created_at));?> — <?php echo esc_html($a->autore);?></div>
    </div>
    <?php endforeach;?>
</div>
<?php endif;?>

<?php if(!empty($appuntamenti)):?>
<h2>Appuntamenti</h2>
<table>
    <tr><th>Data e ora</th><th>Oggetto</th><th>Partecipante</th><th>Luogo</th></tr>
    <?php foreach($appuntamenti as $a):?>
    <tr>
        <td><?php echo date_i18n('d/m/Y H:i',strtotime($a->data_ora));?></td>
        <td><?php echo esc_html($a->titolo);?></td>
        <td><?php echo esc_html($a->partecipante?:'—');?></td>
        <td><?php echo esc_html($a->luogo?:'—');?></td>
    </tr>
    <?php endforeach;?>
</table>
<?php endif;?>

<?php if(!empty($media)):?>
<h2>Media allegati (<?php echo count($media);?> file)</h2>
<table>
    <tr><th>Nome</th><th>Tipo</th><th>Categoria</th><th>Caricato da</th><th>Dim.</th></tr>
    <?php foreach($media as $m):?>
    <tr>
        <td><?php echo esc_html($m->nome);?></td>
        <td><?php echo ucfirst($m->tipo);?></td>
        <td><?php echo ucfirst($m->categoria);?></td>
        <td><?php echo esc_html($m->autore);?></td>
        <td><?php echo round($m->dimensione/1024,0);?> KB</td>
    </tr>
    <?php endforeach;?>
</table>
<?php endif;?>

<div style="margin-top:24px;padding-top:12px;border-top:1px solid #e2e8f0;font-size:10px;color:#94a3b8;text-align:center">
    <?php echo esc_html($azienda);?> — <?php echo esc_html($c->nome);?> — Riepilogo generato il <?php echo date_i18n('d/m/Y H:i');?>
</div>

<button onclick="window.print()" style="margin-top:16px;padding:8px 16px;background:#e53e3e;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px">🖨 Stampa / Salva PDF</button>
</body>
</html>
    <?php
    return ob_get_clean();
}
